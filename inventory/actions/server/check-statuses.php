<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use inventory\server\Alert;
use inventory\server\AlertPolicy;
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


$servers = Server::search([
        ['server_type', 'in', ['b2', 'k2', 's2', 'admin']]
    ])
    ->read([
        'id',
        'name',
        'up',
        'server_type',
        'instances_ids' => ['id', 'name', 'up']
    ])
    ->get();

$serverAlertPolicies = AlertPolicy::search(['alert_type', 'in', ['all', $server['server_type']]])
    ->read([
        'id',
        'name',
        'alert_type',
        'users_ids'             => ['login'],
        'groups_ids'            => ['users_ids' => ['login']],
        'alert_triggers_ids'    => ['name', 'key', 'operator', 'value', 'repetition']
    ])
    ->get();

$instanceAlertPolicies = AlertPolicy::search(['alert_type', '=', 'b2_instance'])
    ->read([
        'id',
        'name',
        'alert_type',
        'users_ids'             => ['login'],
        'groups_ids'            => ['users_ids' => ['login']],
        'alert_triggers_ids'    => ['name', 'key', 'operator', 'value', 'repetition']
    ])
    ->get();

// Send servers alerts if triggers matches
foreach($servers as $server) {

    if(empty($serverAlertPolicies)) {
        continue;
    }

    // find out the max repetition required amongst all applicable triggers
    $max_repetition = 1;
    foreach($serverAlertPolicies as $policy) {
        foreach($policy['alert_triggers_ids'] as $trigger) {
            $max_repetition = max($max_repetition, $alert_trigger['repetition']);
        }
    }

    // Get the quantity of server statuses needed to check all the alerts
    $server_statuses = Status::search(
            ['server_id', '=', $server['id']],
            [
                'sort'  => ['created' => 'desc'],
                'limit' => $max_repetition
            ]
        )
        ->read(['status_data'])
        ->get();

    if(empty($server_statuses)) {
        continue;
    }

    $statuses = array_map(
        function ($status_data) {
            return json_decode($status_data, true);
        },
        array_column($server_statuses, 'status_data')
    );

    $statuses['state']['up'] = $server['up'];

    // Create alerts if triggers matches
    foreach($serverAlertPolicies as $policy) {
        if(AlertPolicy::getTriggersResult($policy, $statuses)) {
            Alert::create([
                    'name'              => $policy['name'],
                    'alert_policy_id'   => $policy['id'],
                    'server_id'         => $server['id']
                ]);
        }
    }

    if(empty($instanceAlertPolicies)) {
        continue;
    }

    foreach($server['instances_ids'] as $instance) {

        // Handle trigger repetition to get right quantity of statuses to check against triggers
        $max_repetition = 1;
        foreach($instanceAlertPolicies as $policy) {
            foreach($policy['alert_triggers_ids'] as $alert_trigger) {
                $max_repetition = max($max_repetition, $alert_trigger['repetition']);
            }
        }

        // Get the quantity of instance statuses needed to check all the alerts
        $instance_statuses = Status::search(
                ['instance_id', '=', $instance['id']],
                [
                    'sort'  => ['created' => 'desc'],
                    'limit' => $max_repetition
                ]
            )
            ->read(['status_data'])
            ->get();

        if(empty($instance_statuses)) {
            continue;
        }

        $statuses = array_map(
            function ($status_data) {
                return json_decode($status_data, true);
            },
            array_column($instance_statuses, 'status_data')
        );

        $statuses['state']['up'] = $instance['up'];

        // Send alerts if triggers matches
        foreach($instanceAlertPolicies as $policy) {
            if(AlertPolicy::getTriggersResult($policy, $statuses)) {
                Alert::create([
                        'name'              => $policy['name'],
                        'alert_policy_id'   => $policy['id'],
                        'instance_id'       => $instance['id']
                    ]);
            }
        }
    }
}


$context->httpResponse()
        ->status(204)
        ->send();
