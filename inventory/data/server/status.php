<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\http\HttpRequest;
use inventory\server\Server;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for Servers: returns a collection according to extra parameters.',
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted server',
            'type'              => 'many2one',
            'foreign_object'    => 'inventory\server\Server',
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$server = Server::id($params['id'])->read([
        'server_type',
        'accesses_ids'  => ['access_type', 'port', 'url'],
    ])
    ->first();

// retrieve server API URL from accesses
$access_url = null;
foreach($server['accesses_ids'] as $access) {
    if(in_array($access['access_type'], ['http', 'https']) && $access['port'] === '8000') {
        $access_url = $access['url'];
    }
}

if(!$access_url) {
    throw new Exception('missing_api_url', EQ_ERROR_INVALID_PARAM);
}

$response = (new HttpRequest('GET '.$access_url.'/status?scope=instant'))->send();

$context
    ->httpResponse()
    ->body($response->body())
    ->send();