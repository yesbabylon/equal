<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\Mail;
use equal\email\Email;
use inventory\server\AlertTrigger;
use inventory\server\Server;
use inventory\server\Status;

[$params, $providers] = eQual::announce([
    'description'       => "Sends servers alerts if their triggers match the current servers statuses.",
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
 * @param $alert
 * @param $server_statuses
 * @return bool
 */
$mustSendAlert = function($alert, $server_statuses) use($comparison_methods) {
    $must_trigger = count($alert['alert_triggers_ids']) > 0;
    foreach($alert['alert_triggers_ids'] as $trigger) {
        if($trigger['repetition'] > count($server_statuses)) {
            $must_trigger = false;
            break;
        }

        for($i = 0; $i <= $trigger['repetition']; $i++) {
            $server_status = $server_statuses[$i];

            $server_status_value = AlertTrigger::getServerStatusValue($trigger['key'], $server_status);
            if(!isset($server_status_value)) {
                $must_trigger = false;
                break 2;
            }

            $server_status_value = AlertTrigger::adaptValue($trigger['key'], $server_status_value);
            $trigger_value = AlertTrigger::adaptValue($trigger['key'], $trigger['value']);

            if(
                !in_array($trigger['operator'], array_keys($comparison_methods))
                || !$comparison_methods[$trigger['operator']]($server_status_value, $trigger_value)
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
 * @param $server
 * @param $alert
 * @return void
 * @throws Exception
 */
$sendAlert = function($server, $alert) {
    $users_emails = array_column($alert['users_ids'], 'login');
    foreach($alert['groups_ids'] as $group) {
        $users_emails = array_merge($users_emails, array_column($group['users_ids'], 'login'));
    }
    $users_emails = array_unique($users_emails);

    foreach($users_emails as $email) {
        $message = new Email();

        $body = "Alert {$alert['name']} for server {$server['name']}:";
        $body .= "<ul>";
        foreach ($alert['alert_triggers_ids'] as $trigger) {
            $body .= "<li>{$trigger['key']} {$trigger['operator']} {$trigger['value']}</li>";
        }
        $body .= "</ul>";

        $message->setTo($email)
            ->setSubject("{$server['name']} alert: {$alert['name']}")
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
        'alerts_ids' => [
            'name',
            'users_ids'             => ['login'],
            'groups_ids'            => ['users_ids' => ['login']],
            'alert_triggers_ids'    => ['key', 'operator', 'value', 'repetition']
        ]
    ])
    ->get();

foreach($servers as $server) {
    if(count($server['alerts_ids']) === 0) {
        continue;
    }

    $max_repetition = 0;
    foreach($server['alerts_ids'] as $alert) {
        foreach($server['alert_triggers_ids'] as $alert_trigger) {
            $max_repetition = max($max_repetition, $alert_trigger['repetition']);
        }
    }

    $statuses = Status::search(
        ['server_id', '=', $server['id']],
        ['sort' => ['created' => 'desc'], 'limit' => $max_repetition]
    )
        ->read(['up', 'server_status'])
        ->get();

    if(empty($statuses)) {
        continue;
    }

    $server_statuses = [];
    foreach($statuses as $status) {
        if($status['up']) {
            $server_statuses[] = array_merge(
                ['up' => 'true'],
                json_decode($status['server_status'] ?? [], true)
            );
        }
        else {
            $server_statuses[] = ['up' => 'false'];
        }
    }

    foreach($server['alerts_ids'] as $alert) {
        $must_trigger = $mustSendAlert($alert, $server_statuses);
        if(!$must_trigger) {
            continue;
        }

        $sendAlert($server, $alert);
    }
}

$context->httpResponse()
        ->status(204)
        ->send();
