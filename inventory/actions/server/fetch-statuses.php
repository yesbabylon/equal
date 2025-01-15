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
    'description'       => "Fetches and saves statuses from servers of b2, tapu_backups, sapu_stats and seru_admin type. It also handles the statuses of b2 instances.",
    'help'              => "Updates servers and instances 'up' fields.",
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

/**
 * Methods
 */

/**
 * Returns api url from given accesses (http/https on port 8000)
 *
 * @param array $accesses
 * @return string|null
 */
$getServerApiUrl = function(array $accesses) {
    $access_url = null;
    foreach($accesses as $access) {
        if(in_array($access['access_type'], ['http', 'https']) && $access['port'] === '8000') {
            $access_url = $access['url'];
        }
    }

    return $access_url;
};

/**
 * Returns server status for the given server's access url
 *
 * @param string $access_url
 * @return array
 */
$getServerStatus = function(string $access_url) {
    $server_status_res = file_get_contents("$access_url/status");
    if($server_status_res === false) {
        return ['up' => false];
    }

    $server_status_res = json_decode($server_status_res, true);

    return array_merge(
        ['up' => true],
        $server_status_res['result']
    );
};

/**
 * Returns instance status for given b2 server's access url
 *
 * @param string $instance
 * @param string $access_url
 * @return array
 */
$getInstanceStatus = function(string $instance, string $access_url): array {
    $instance_status = file_get_contents("$access_url/instance/status?instance=$instance");
    if($instance_status === false) {
        return ['up' => false];
    }

    $instance_status = json_decode($instance_status, true);

    return $instance_status['result'];
};

/**
 * Action
 */

$servers = Server::search(['server_type', 'in', ['b2', 'tapu_backups', 'sapu_stats', 'seru_admin']])
    ->read([
        'server_type',
        'accesses_ids'  => ['access_type', 'port', 'url'],
        'instances_ids' => ['name']
    ])
    ->get();

$map_up_down_servers_ids = $map_up_down_instances_ids = ['up' => [], 'down' => []];
foreach($servers as $server) {
    $access_url = $getServerApiUrl($server['accesses_ids']);
    if(is_null($access_url)) {
        continue;
    }

    $server_status = $getServerStatus($access_url);

    Status::create([
        'server_id'     => $server['id'],
        'up'            => $server_status['up'],
        'status_data'   => json_encode($server_status)
    ]);

    $map_up_down_servers_ids[$server_status['up'] ? 'up' : 'down'][] = $server['id'];

    if($server['server_type'] === 'b2' && !empty($server['instances_ids'])) {
        if($server_status['up']) {
            foreach($server['instances_ids'] as $instance) {
                $instance_status = $getInstanceStatus($instance['name'], $access_url);

                Status::create([
                    'instance_id'   => $instance['id'],
                    'up'            => $instance_status['up'],
                    'status_data'   => json_encode($instance_status)
                ]);

                $map_up_down_instances_ids[$instance_status['up'] ? 'up' : 'down'][] = $instance['id'];
            }
        }
        else {
            foreach($server['instances_ids'] as $instance) {
                Status::create([
                    'instance_id'   => $instance['id'],
                    'up'            => false,
                    'status_data'   => json_encode(['up' => false])
                ]);
            }

            // All instances set to down because b2 server down
            $map_up_down_instances_ids['down'] = array_merge(
                $map_up_down_instances_ids['down'],
                array_column($server['instances_ids'], 'id')
            );
        }
    }
}

// Sync current status of servers
if(!empty($map_up_down_servers_ids['up'])) {
    Server::search(['id', 'in', $map_up_down_servers_ids['up']])
        ->update(['up' => true, 'synced' => time()]);
}
if(!empty($map_up_down_servers_ids['down'])) {
    Server::search(['id', 'in', $map_up_down_servers_ids['down']])
        ->update(['up' => false, 'synced' => time()]);
}

// Sync current status of instances
if(!empty($map_up_down_instances_ids['up'])) {
    Instance::search(['id', 'in', $map_up_down_instances_ids['up']])
        ->update(['up' => true, 'synced' => time()]);
}
if(!empty($map_up_down_instances_ids['down'])) {
    Instance::search(['id', 'in', $map_up_down_instances_ids['down']])
        ->update(['up' => false, 'synced' => time()]);
}

$context->httpResponse()
        ->status(204)
        ->send();
