<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use inventory\server\Server;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for Servers: returns a collection according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'description'   => 'name',
            'type'          => 'string',
            'default'       => 'inventory\server\Server'
        ],

        'server_type' => [
            'type'              => 'string',
            'description'       => 'Type of the server.',
            'selection'         => ['all','front', 'node', 'storage']
        ],

        'product_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'inventory\Product',
            'description'       => 'Product to which the server belongs'
        ],

        'access_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'inventory\Access',
            'description'       => 'Access to which the server belongs.'
        ],

        'instance_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'inventory\server\Instance',
            'description'       => 'Instance to which the server belongs.'
        ],

        'software_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'inventory\Software',
            'description'       => 'Software to which the server belongs.'
        ],

        'ip_address_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'inventory\server\IpAddress',
            'description'       => 'Ip address to which the server belongs.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);
/**
 * @var \equal\php\Context $context
 */
$context = $providers['context'];

// Add conditions to the domain to consider advanced parameters
$domain = [];


if(isset($params['server_type']) && strlen($params['server_type']) > 0 && $params['server_type']!= 'all') {
    $domain = ['server_type', '=', $params['server_type']];
}

if(isset($params['product_id']) && $params['product_id'] > 0) {
    $domain = ['product_id', '=', $params['product_id']];
}

if(isset($params['access_id']) && $params['access_id'] > 0) {
    $servers_ids = [];
    $servers_ids = Server::search(['accesses_ids', 'contains', $params['access_id']])->ids();
    $domain = ['id', 'in', $servers_ids];
}

if(isset($params['software_id']) && $params['software_id'] > 0) {
    $servers_ids = [];
    $servers_ids = Server::search(['softwares_ids', 'contains', $params['software_id']])->ids();
    $domain = ['id', 'in', $servers_ids];
}

if(isset($params['instance_id']) && $params['instance_id'] > 0) {
    $servers_ids = [];
    $servers_ids = Server::search(['instances_ids', 'contains', $params['instance_id']])->ids();
    $domain = ['id', 'in', $servers_ids];
}

if(isset($params['ip_address_id']) && $params['ip_address_id'] > 0) {
    $servers_ids = [];
    $servers_ids = Server::search(['ip_address_ids', 'contains', $params['ip_address_id']])->ids();
    $domain = ['id', 'in', $servers_ids];
}

$params['domain'] = (new Domain($params['domain']))
    ->merge(new Domain($domain))
    ->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
