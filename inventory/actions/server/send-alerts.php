<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\Mail;
use equal\email\Email;
use inventory\server\Alert;
use inventory\server\AlertTrigger;
use inventory\server\Server;
use inventory\server\Status;

[$params, $providers] = eQual::announce([
    'description'       => "Sends servers/instances alerts if their triggers match the last servers status(es).",
    'params'            => [],
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
 * Comparison methods to check the server status' value against the trigger's value
 */
$comparison_methods = [
    'eq'                => function($value, $trigger_value) { return $value === $trigger_value; },
    'ne'                => function($value, $trigger_value) { return $value !== $trigger_value; },
    'gt'                => function($value, $trigger_value) { return $value > $trigger_value; },
    'gte'               => function($value, $trigger_value) { return $value >= $trigger_value; },
    'lt'                => function($value, $trigger_value) { return $value < $trigger_value; },
    'lte'               => function($value, $trigger_value) { return $value <= $trigger_value; },
    'contains'          => function($value, $trigger_value) { return strpos($value, $trigger_value) !== false; },
    'does_not_contain'  => function($value, $trigger_value) { return strpos($value, $trigger_value) === false; }
];

/**
 * Returns true if the given alert must be sent bases on server statuses and alert triggers
 *
 * @param array $alert
 * @param array $statuses Server or instance statuses
 * @return bool
 */
$mustSendAlert = function(array $alert, array $statuses) use($comparison_methods) {
    $must_trigger = count($alert['alert_triggers_ids']) > 0;
    foreach($alert['alert_triggers_ids'] as $trigger) {
        if($trigger['repetition'] > count($statuses)) {
            $must_trigger = false;
            break;
        }

        for($i = 0; $i < $trigger['repetition']; $i++) {
            $status = $statuses[$i];

            $status_value = AlertTrigger::getServerStatusValue($trigger['key'], $status);
            if(is_null($status_value)) {
                $must_trigger = false;
                break 2;
            }

            $status_value = AlertTrigger::adaptValue($trigger['key'], $status_value);
            $trigger_value = AlertTrigger::adaptValue($trigger['key'], $trigger['value']);

            if(
                !in_array($trigger['operator'], array_keys($comparison_methods))
                || !$comparison_methods[$trigger['operator']]($status_value, $trigger_value)
            ) {
                $must_trigger = false;
                break 2;
            }
        }
    }

    return $must_trigger;
};

/**
 * Sends the given alert to all users link for given server
 *
 * @param array $alert
 * @param string $alert_email_subject
 * @return void
 * @throws Exception
 */
$sendAlert = function(array $alert, string $alert_email_subject) {
    $users_emails = array_column($alert['users_ids'], 'login');
    foreach($alert['groups_ids'] as $group) {
        $users_emails = array_merge($users_emails, array_column($group['users_ids'], 'login'));
    }
    $users_emails = array_unique($users_emails);
    if(empty($users_emails)) {
        trigger_error("APP::no user nor group configured for alert {$alert['name']}}", EQ_REPORT_WARNING);
    }

    $body = "<div>Alert triggers:</div>";
    $body .= "<ul>";
    foreach($alert['alert_triggers_ids'] as $trigger) {
        $body .= "<li>{$trigger['name']}</li>";
    }
    $body .= "</ul>";

    foreach($users_emails as $email) {
        $message = new Email();

        $message->setTo($email)
            ->setSubject($alert_email_subject)
            ->setContentType("text/html")
            ->setBody($body);

        Mail::send($message);
    }
};


/**
 * Action
 */

$servers = Server::search(['server_type', 'in', ['b2', 'tapu_backups', 'sapu_stats', 'seru_admin']])
    ->read([
        'name',
        'server_type',
        'instances_ids' => ['name']
    ])
    ->get();

$alerts = Alert::search()
    ->read([
        'name',
        'alert_type',
        'users_ids'             => ['login'],
        'groups_ids'            => ['users_ids' => ['login']],
        'alert_triggers_ids'    => ['name', 'key', 'operator', 'value', 'repetition']
    ])
    ->get();

// Send servers alerts if triggers matches
foreach($servers as $server) {
    $server_alerts = array_filter(
        $alerts,
        function ($alert) use($server) {
            return in_array($alert['alert_type'], ['all', $server['server_type']]);
        }
    );

    if(count($server_alerts) === 0) {
        // No alert configured
        continue;
    }

    // Handle trigger repetition to get right quantity of statuses to check against triggers
    $max_repetition = 1;
    foreach($server_alerts as $alert) {
        foreach($alert['alert_triggers_ids'] as $alert_trigger) {
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
        ->read(['up', 'status_data'])
        ->get();

    if(empty($server_statuses)) {
        // No statuses yet
        continue;
    }

    $server_statuses_data = array_map(
        function ($status_data) {
            return json_decode($status_data, true);
        },
        array_column($server_statuses, 'status_data')
    );

    // Send alerts if triggers matches
    foreach($server_alerts as $alert) {
        if(!$mustSendAlert($alert, $server_statuses_data)) {
            continue;
        }

        $sendAlert(
            $alert,
            "Alert \"{$alert['name']}\" for server {$server['name']}"
        );
    }
}

// Send instances alerts if triggers matches
foreach($servers as $server) {
    foreach($server['instances_ids'] as $instance) {
        $instance_alerts = array_filter(
            $alerts,
            function ($alert) {
                return in_array($alert['alert_type'], ['all', 'b2_instance']);
            }
        );

        if(count($instance_alerts) === 0) {
            // No alert configured
            continue;
        }

        // Handle trigger repetition to get right quantity of statuses to check against triggers
        $max_repetition = 1;
        foreach($instance_alerts as $alert) {
            foreach($alert['alert_triggers_ids'] as $alert_trigger) {
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
            ->read(['up', 'status_data'])
            ->get();

        if(empty($instance_statuses)) {
            // No statuses yet
            continue;
        }

        $instance_statuses_data = array_map(
            function ($status_data) {
                return json_decode($status_data, true);
            },
            array_column($instance_statuses, 'status_data')
        );

        // Send alerts if triggers matches
        foreach($instance_alerts as $alert) {
            if(!$mustSendAlert($alert, $instance_statuses_data)) {
                continue;
            }

            $sendAlert(
                $alert,
                "Alert \"{$alert['name']}\" for instance {$instance['name']} (server {$server['name']})"
            );
        }
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
