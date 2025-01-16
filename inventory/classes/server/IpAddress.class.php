<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\server;

use equal\orm\Model;

class IpAddress extends Model {

    public static function getDescription() {
        return 'The IP addresses (v4 or v6) associated with server.';
    }

    public static function getColumns() {
        return [

            'ip_v4' => [
                'type'              => 'string',
                'description'       => 'IPV4 address of the server (32 bits).',
                'dependents'        => ['name']
            ],

            'ip_v6' => [
                'type'              => 'string',
                'description'       => 'IPV6 address of the server (128 bits).',
                'dependents'        => ['name']
            ],

            'name' => [
                'type'              => 'computed',
                'description'       => 'Name to ip address.',
                'function'          => 'calcName',
                'result_type'       => 'string',
                'store'             => true,
                'readonly'          => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Short presentation of the IP address element.'
            ],

            'visibility' => [
                'type'              => 'string',
                'default'           => 'public',
                'selection'         => ['public', 'private', 'protected'],
                'description'       => 'Visibility indicates how an IP address is exposed to the cloud.',
                'help'              => 'A public address is associated with a reverse DNS entry. A protected address is a public IP not associated with any DNS record. A private address is not public and is only accessible through a private network.',
                'onupdate'          => 'onupdateVisibility'
            ],

            'server_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\server\Server',
                'ondelete'          => 'cascade',
                'description'       => 'Server attached to the Ip address.',
                'required'          => true,
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['ip_v4', 'ip_v6']);
        foreach($self as $id => $address) {
            if(isset($address['ip_v4']) && strlen($address['ip_v4'] > 0)) {
                $result[$id] = $address['ip_v4'];
            }
            elseif(isset($address['ip_v6']) && strlen($address['ip_v6'] > 0)) {
                $result[$id] = $address['ip_v6'];
            }
        }

        return $result;
    }

    public static function onupdateVisibility($self) {
        $self->read(['server_id']);
        foreach($self as $ip_address) {
            Server::id($ip_address['server_id'])
                ->do('create_management_api_access');
        }
    }
}
