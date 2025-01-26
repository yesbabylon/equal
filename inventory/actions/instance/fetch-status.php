<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use inventory\server\Instance;
use inventory\server\Status;

[$params, $providers] = eQual::announce([
    'description'       => "Fetches and saves statuses for a given instance.",
    'help'              => "Calls hosts API to fetch 'instant' statuses and updates instance 'up' field accordingly.",
    'params'            => [
        'id' =>  [
            'description'       => 'Identifier of the targeted instance',
            'type'              => 'many2one',
            'foreign_object'    => 'inventory\server\Instance',
            'required'          => true
        ]
    ],
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

$instance = Instance::id($params['id'])
    ->read([
        'id',
        'name',
        'server_id'
    ])
    ->first();

if(!$instance) {
    throw new Exception('unknown_instance', EQ_ERROR_INVALID_PARAM);
}

try {
    $status = equal::run('get', 'inventory_instance_status', ['id' => $instance['server_id'], 'instance' => $instance['name']]);

    Status::create([
        'instance_id'   => $instance['id'],
        'status_data'   => json_encode($status)
    ]);
    // instance is up
    Instance::id($instance['id'])->update(['up' => true, 'synced' => time()]);
}
catch(Exception $e) {
    // instance is down (will cascade to instances)
    Instance::id($instance['id'])->update(['up' => false, 'synced' => time()]);
}


$context->httpResponse()
        ->status(204)
        ->send();
