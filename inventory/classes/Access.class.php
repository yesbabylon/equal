<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory;

use equal\orm\Model;
use equal\orm\UsageFactory;

class Access extends Model {

    public static function getDescription() {
        return 'The access details encompass the access type (e.g., HTTP, SSH), along with associated URL components like the IP address or hostname, and connection port for the server, services, instance, and software.';
    }

    const MAP_PORTS = [
        'http'      => '80',
        'https'     => '443',
        'ssh'       => '22',
        'ftp'       => '21',
        'sftp'      => '22',
        'pop'       => '110',
        'smtp'      => '25',
        'git'       => '9418',
        'docker'    => '2345',
    ];

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Name of the access.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Short description of the access.'
            ],

            'access_type' => [
                'type'              => 'string',
                'selection'         => ['http', 'https', 'ssh', 'ftp', 'sftp', 'pop', 'smtp', 'git', 'docker'],
                'description'       => 'Type of the access.',
                'required'          => true,
                'onupdate'          => 'onupdateType'

            ],

            'url' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/url',
                'description'       => 'The URL to access.',
                'function'          => 'calcUrl',
                'store'             => true,
                'instant'           => true
            ],

            'host' => [
                'type'              => 'string',
                'description'       => 'IP address or hostname of the server.',
                'required'          => true,
                'dependents'        => ['name', 'url']
            ],

            'port' => [
                'type'              => 'string',
                'description'       => 'Port to connect to (default based on protocol).',
                'dependents'        => ['url']
            ],

            'username' => [
                'type'              => 'string',
                'description'       => 'Username of the account related to this access.',
                'required'          => true,
                'dependents'        => ['name', 'url']
            ],

            'password' => [
                'type'              => 'string',
                'required'          => true,
                'description'       => 'Password of the account related to this access.',
                'help'              => 'The password is arbitrary and depends on the application logic of the host (so there are no constraints on it).'
            ],

            'server_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\server\Server',
                'description'       => 'Server to which the access belongs.',
                'readonly'          => true,
                'visible'           => ['server_id', '<>', null]
            ],

            'software_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\Software',
                'description'       => 'Software to which the access belongs.',
                'readonly'          => true,
                'visible'           => ['software_id', '<>', null]
            ],

            'instance_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\server\Instance',
                'description'       => '    .',
                'readonly'          => true,
                'visible'           => ['instance_id', '<>', null]
            ],

            'service_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\service\Service',
                'description'       => 'Service to which the access belongs.',
                'readonly'          => true,
                'visible'           => ['service_id', '<>', null]
            ]

        ];
    }

    public static function onupdateType($self, $values) {
        if(isset($values['port'])) {
            return;
        }

        $self->read(['access_type', 'port']);
        foreach($self as $id => $access) {
            if(isset(self::MAP_PORTS[$access['access_type']])) {
                $port = self::MAP_PORTS[$access['access_type']];
                if($access['port'] != $port) {
                    self::id($id)->update(['port' => $port]);
                }
            }
        }
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['username', 'host']);
        foreach($self as $id => $access) {
            $result[$id] = $access['username'].'@'.$access['host'];
        }
        return $result;
    }

    public static function calcUrl($self) {
        $result = [];
        $self->read([
                'port',
                'host',
                'access_type',
                /*
                // #memo - syntax including credentials is a security risk
                'username',
                'password'
                */
            ]);
        foreach($self as $id => $access) {
            $result[$id] = self::computeUrl($access);
        }
        return $result;
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['access_type'], self::MAP_PORTS[$event['access_type']])) {
            $result['port'] = self::MAP_PORTS[$event['access_type']];
        }

        if( isset($event['access_type'])
            || isset($event['username'])
            || isset($event['password'])
            || isset($event['host'])
            || isset($event['port']) ) {

            // #memo - result could still be invalid
            $result['url'] = self::computeUrl([
                    'access_type'   => $event['access_type'] ?? $values['access_type'],
                    /*
                    // #memo - there is no practical use with this syntax
                    'username'      => $event['username'] ?? $values['username'],
                    'password'      => $event['password'] ?? $values['password'],
                    */
                    'host'          => $event['host'] ?? $values['host'],
                    'port'          => $result['port'] ?? $event['port'] ?? $values['port']
                ]);
        }

        return $result;
    }

    private static function computeUrl($access) {
        $format = '%s://';
        $values = [ $access['access_type'] ];
        if(isset($access['username'])) {
            $format .= '%s';
            $values[] = $access['username'];
        }
        if(isset($access['password'])) {
            $format .= ':%s';
            $values[] = $access['password'];
        }
        if(isset($access['username']) ) {
            $format .= '@';
        }
        $format .= '%s';
        $values[] = self::computeAuthority($access);
        return sprintf(
                $format,
                ...$values
            );
    }

    private static function computeAuthority($access) {
        $authority = $access['host'];
        if(!empty($access['port'])) {
            $authority .= ':'.$access['port'];
        }

        return $authority;
    }
}
