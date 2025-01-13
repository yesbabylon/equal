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
    'description'       => "Fetches status statistics from servers of b2 or aru type, then saves them.",
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

$getServerApiUrl = function(array $accesses) {
    $access_url = null;
    foreach($accesses as $access) {
        if(in_array($access['access_type'], ['http', 'https']) && $access['port'] === '8000') {
            $access_url = $access['url'];
        }
    }

    return $access_url;
};

$getInstancesStatuses = function($instances, $access_url) {
    $instances_statuses = [];
    foreach($instances as $instance) {
        $instance_status = file_get_contents("$access_url/instance/status?instance=$instance");
        if($instance_status) {
            $instance_status = json_decode($instance_status, true);
            $instances_statuses[$instance] = $instance_status['result'];
        }
    }

    return $instances_statuses;
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

$map_up_down_servers_ids = ['up' => [], 'down' => []];
$map_up_down_instances_ids = ['up' => [], 'down' => []];
foreach($servers as $server) {
    $access_url = $getServerApiUrl($server['accesses_ids']);
    if(is_null($access_url)) {
        continue;
    }

    $server_status = file_get_contents("$access_url/status");
    $up = $server_status !== false;

    if($server['server_type'] === 'b2' && !empty($server['instances_ids'])) {
        if($up) {
            $server_status = json_decode($server_status, true);
            $server_status['b2_instances'] = [];

            $instances = file_get_contents("$access_url/instances");
            if($instances) {
                $instances = json_decode($instances, true);
                $instances_statuses = $getInstancesStatuses($instances['result'], $access_url);
                if(!empty($instances_statuses)) {
                    $server_status['b2_instances'] = $instances_statuses;

                    foreach($server['instances_ids'] as $instance) {
                        $instance_up = isset($server_status['b2_instances'][$instance['name']])
                            && $server_status['b2_instances'][$instance['name']]['up'];

                        $map_up_down_instances_ids[$instance_up ? 'up' : 'down'][] = $instance['id'];
                    }
                }
                else {
                    $map_up_down_instances_ids['down'] = array_column($server['instances_ids'], 'id');
                }
            }

            $server_status = json_encode($server_status);
        }
        else {
            $map_up_down_instances_ids['down'] = array_merge(
                $map_up_down_instances_ids['down'],
                array_column($server['instances_ids'], 'id')
            );
        }
    }

    Status::create([
        'server_id'         => $server['id'],
        'up'                => $up,
        'server_status'     => $up ? $server_status : null
    ]);

    $map_up_down_servers_ids[$up ? 'up' : 'down'][] = $server['id'];
}

// Set current status of servers
if(!empty($map_up_down_servers_ids['up'])) {
    Server::search(['id', 'in', $map_up_down_servers_ids['up']])
        ->update(['up' => true]);
}
if(!empty($map_up_down_servers_ids['down'])) {
    Server::search(['id', 'in', $map_up_down_servers_ids['down']])
        ->update(['up' => false]);
}

// Set current status of instances
if(!empty($map_up_down_instances_ids['up'])) {
    Instance::search(['id', 'in', $map_up_down_instances_ids['up']])
        ->update(['up' => true]);
}
if(!empty($map_up_down_instances_ids['down'])) {
    Instance::search(['id', 'in', $map_up_down_instances_ids['down']])
        ->update(['up' => false]);
}

$context->httpResponse()
        ->status(204)
        ->send();
