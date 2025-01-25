<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\server;

use equal\orm\Model;

class Status extends Model {

    public static function getColumns(): array {
        return [

            'server_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\server\Server',
                'ondelete'          => 'cascade',
                'description'       => "Server concerned by the status.",
                'help'              => "A status can either concern a server or an instance."
            ],

            'instance_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\server\Instance',
                'ondelete'          => 'cascade',
                'description'       => "Instance concerned by the status.",
                'help'              => "A status can either concern an instance or a server."
            ],

            'up' => [
                'type'              => 'boolean',
                'description'       => "True if the the server/instance is up.",
                'default'           => true
            ],

            'status_data' => [
                'type'              => 'string',
                'usage'             => 'text/json',
                'description'       => "JSON representation of server/instance statuses and statistics."
            ]

        ];
    }
}
