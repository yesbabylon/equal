<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

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

/**
 * Action
 */

$servers = Server::search(['server_type', 'in', ['b2', 'tapu_backups', 'sapu_stats', 'seru_admin']])
    ->read(['accesses_ids' => ['access_type', 'port', 'url']])
    ->get();

foreach($servers as $server) {
    $access_url = $getServerApiUrl($server['accesses_ids']);
    if(is_null($access_url)) {
        continue;
    }

    $server_status = file_get_contents("$access_url/status");
    $up = $server_status !== false;

    Status::create([
        'server_id'         => $server['id'],
        'up'                => $up,
        'server_status'     => $up ? $server_status : null
    ]);
}

$context->httpResponse()
        ->status(204)
        ->send();
