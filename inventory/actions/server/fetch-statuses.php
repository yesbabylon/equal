<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use inventory\server\Instance;
use inventory\server\Server;
use inventory\server\Status;

[$params, $providers] = eQual::announce([
    'description'       => "Fetches and saves statuses of servers (b2, k2, s2) and b2 instances.",
    'help'              => "Calls hosts API to fetch 'instant' statuses and updates servers and instances 'up' fields accordingly.",
    'params'            => [],
    'access'            => [
        'visibility'        => 'private'
    ],
    'response'          => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'         => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$servers = Server::search(['server_type', 'in', ['b2', 'k2', 's2']])
    ->read([
        'id',
        'server_type',
        'accesses_ids'  => ['access_type', 'port', 'url'],
        'instances_ids' => ['id', 'name']
    ])
    ->get();

foreach($servers as $server) {

    try {
        $status = equal::run('get', 'inventory_server_status', ['id' => $server['id']]);

        Status::create([
            'server_id'     => $server['id'],
            'status_data'   => json_encode($status)
        ]);

        // server is up
        Server::id($server['id'])->update(['up' => true, 'synced' => time()]);
    }
    catch(Exception $e) {
        // server is down (will cascade to instances)
        Server::id($server['id'])->update(['up' => false, 'synced' => time()]);

        continue;
    }

    if($server['server_type'] === 'b2' && !empty($server['instances_ids'])) {
        foreach($server['instances_ids'] as $instance) {
            try {
                $status = equal::run('get', 'inventory_instance_status', ['id' => $server['id'], 'instance' => $instance['name']]);

                Status::create([
                    'instance_id'   => $instance['id'],
                    'status_data'   => json_encode($status)
                ]);
                // instance is up
                Instance::id($instance['id'])->update(['up' => true, 'synced' => time()]);
            }
            catch(Exception $e) {
                // server is down (will cascade to instances)
                Instance::id($instance['id'])->update(['up' => false, 'synced' => time()]);
            }
        }
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
