<?php
/*
    This file is part of the eQual framework <http://www.github.com/cedricfrancoys/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2021
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\server;

use equal\orm\Model;

class IpAddress extends Model {

    public static function getColumns() {
        return [
            'ip_v4' => [
                'type'              => 'string',
                'description'       => 'IPV4 address of the server (32 bits).'
            ],

            'ip_v6' => [
                'type'              => 'string',
                'description'       => 'IPV6 address of the server (128 bits).'
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
                'description'       => 'Short presentation of the IP address element.'
            ],

            'visibility' => [
                'type'              => 'string',
                'default'           => 'protected',
                'selection'         => ['public', 'protected']
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
            $result[$id] = (string) (strlen($address['ip_v4']))?$address['ip_v4']:$address['ip_v6'];
        }
        return $result;
    }

}
