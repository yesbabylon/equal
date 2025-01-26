<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use inventory\server\Server;

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
        'instances_ids'
    ])
    ->get();

foreach($servers as $server) {
    equal::run('do', 'inventory_server_fetch-status', ['id' => $server['id']]);
    if(!empty($server['instances_ids'])) {
        foreach($server['instances_ids'] as $instance_id) {
            equal::run('do', 'inventory_instance_fetch-status', ['id' => $instance_id]);
        }
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
