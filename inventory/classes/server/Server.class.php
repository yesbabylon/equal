<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\server;

use equal\orm\Model;
use inventory\Access;

class Server extends Model {


    public static function getDescription() {
        return 'The Server includes its name, description, type, access, instances, associated products, IP addresses, and installed software.';
    }

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Internal identification ex. trg.be-master.',
                'unique'            => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Short description of the Server.',
            ],

            'server_type' => [
                'type'              => 'string',
                'description'       => 'Type of the server.',
                'selection'         => ['front', 'node', 'storage', 'b2', 'tapu_backups', 'sapu_stats', 'seru_admin'],
                'default'           => 'front',
                'onupdate'          => 'onupdateServerType'
            ],

            'synced' => [
                'type'              => 'datetime',
                'description'       => 'Date of last automatic status update.',
                'help'              => 'The "up" field can be auto updated by the action "inventory_server_fetch-status".'
            ],

            'up' => [
                'type'              => 'boolean',
                'description'       => 'Is the server currently up, is set according to the last inventory\server\Status retrieval.',
                'default'           => false
            ],

            'send_alerts' => [
                'type'              => 'boolean',
                'description'       => "Are monitoring alerts sent for that server.",
                'default'           => true,
                'visible'           => ['server_type', 'in', ['b2', 'tapu_backups', 'sapu_stats', 'seru_admin']],
                'onupdate'          => 'onupdateSendAlerts'
            ],

            'accesses_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\Access',
                'foreign_field'     => 'server_id',
                'description'       => 'Access information to the server.'
            ],

            'instances_ids' => [
                'type'              => 'one2many',
                'foreign_field'     => 'server_id',
                'foreign_object'    => 'inventory\server\Instance',
                'ondetach'          => 'delete',
                'description'       => 'Instances running on the server.'
            ],

            'instances_count' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcInstancesCount'
            ],

            'products_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'inventory\Product',
                'foreign_field'     => 'servers_ids',
                'rel_table'         => 'inventory_rel_product_server',
                'rel_foreign_key'   => 'product_id',
                'rel_local_key'     => 'server_id',
                'ondelete'          => 'cascade',
                'description'       => 'List of products that are using the server.'
            ],

            'products_count' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcProductsCount'
            ],

            'ip_address_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\server\IpAddress',
                'foreign_field'     => 'server_id',
                'ondetach'          => 'delete',
                'description'       => 'IP Addresses of the server.'
            ],

            'softwares_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\Software',
                'foreign_field'     => 'server_id',
                'ondetach'          => 'delete',
                'description'       => 'List of Software installed on the server.'
            ],

            'statuses_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\server\Status',
                'foreign_field'     => 'server_id',
                'description'       => 'Statuses of the server.'
            ]

        ];
    }

    public static function calcProductsCount($self): array {
        $result = [];
        $self->read(['products_ids']);
        foreach($self as $id => $product) {
            $result[$id] = count($product['products_ids']);
        }

        return $result;
    }

    public static function calcInstancesCount($self): array {
        $result = [];
        $self->read(['instances_ids']);
        foreach($self as $id => $server) {
            $result[$id] = count($server['instances_ids']);
        }

        return $result;
    }

    public static function getActions(): array {
        return [
            'create_management_api_access' => [
                'description'   => "Creates the management api access if needed.",
                'policies'      => [],
                'function'      => 'doCreateManagementApiAccess'
            ]
        ];
    }

    public static function onupdateSendAlerts($self) {
        $self->read(['send_alerts', 'server_type', 'instances_ids']);
        foreach($self as $server) {
            if($server['server_type'] === 'b2' && !$server['send_alerts']) {
                Instance::ids($server['instances_ids'])
                    ->update(['send_alerts' => false]);
            }
        }
    }

    public static function onupdateServerType($self) {
        $self->do('create_management_api_access');
    }

    public static function doCreateManagementApiAccess($self) {
        $self->read([
            'server_type',
            'accesses_ids'      => ['access_type', 'protocol', 'port'],
            'ip_address_ids'    => ['ip_v4', 'visibility']
        ]);

        foreach($self as $server) {
            if(!in_array($server['server_type'], ['b2', 'tapu_backups', 'sapu_stats', 'seru_admin'])) {
                continue;
            }

            foreach($server['accesses_ids'] as $access) {
                if(in_array($access['access_type'], ['http', 'https']) && $access['port'] === '8000') {
                    continue 2;
                }
            }

            $private_ip_address = null;
            foreach($server['ip_address_ids'] as $ip_address) {
                if($ip_address['visibility'] === 'private') {
                    $private_ip_address = $ip_address;
                    break;
                }
            }
            if(is_null($private_ip_address) || is_null($private_ip_address['ip_v4'])) {
                continue;
            }

            Access::create([
                'name'          => 'Management API',
                'server_id'     => $server['id'],
                'access_type'   => 'https',
                'port'          => '8000',
                'host'          => explode('/', $private_ip_address['ip_v4'])[0],
                'username'      => '',
                'password'      => ''
            ]);
        }
    }
}
