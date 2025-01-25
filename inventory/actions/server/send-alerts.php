<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\Mail;
use equal\email\Email;
use inventory\server\Alert;
use inventory\server\Server;
use inventory\server\Instance;

[$params, $providers] = eQual::announce([
    'description'       => "Sends servers/instances pending alerts to users (email).",
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


$servers = Server::search([
        ['server_type', 'in', ['b2', 'k2', 's2', 'admin']],
        ['send_alerts', '=', true]
    ])
    ->read([
        'name',
        'server_type',
        'instances_ids' => ['name', 'send_alerts']
    ])
    ->get();

$alerts = Alert::search(['status', '=', 'pending'])
    ->read([
        'id',
        'name',
        'server_id' => ['id', 'name'],
        'instance_id' => ['id', 'name'],
        'alert_policy_id' => [
            'alert_type',
            'users_ids'             => ['login'],
            'groups_ids'            => ['users_ids' => ['login']],
            'alert_triggers_ids'    => ['name', 'key', 'operator', 'value', 'repetition']
        ]
    ])
    ->get();

foreach($alerts as $id => $alert) {
    if($alert['server_id']) {
        $server = Server::id($alert['server_id']['id'])->read(['send_alerts'])->first();
        if($server['send_alerts']) {
            $sendAlert(
                $alert['alert_policy_id'],
                "Alert \"{$alert['name']}\" for server {$alert['server_id']['name']}"
            );
        }

    }
    else if($alert['instance_id']) {
        $instance = Instance::id($alert['instance_id']['id'])->read(['send_alerts'])->first();

        if($instance['send_alerts']) {
            $sendAlert(
                $alert['alert_policy_id'],
                "Alert \"{$alert['name']}\" for instance {$alert['instance_id']['name']}"
            );
        }
    }
    Alert::id($id)->update(['status' => 'sent']);
}


$context->httpResponse()
        ->status(204)
        ->send();
