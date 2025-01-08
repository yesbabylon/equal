<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\core;

class User extends \core\User {

    public static function getColumns() {
        return [

            'server_alerts_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'inventory\server\Alert',
                'foreign_field'     => 'users_ids',
                'rel_table'         => 'inventory_server_alert_rel_core_user',
                'rel_foreign_key'   => 'alert_id',
                'rel_local_key'     => 'user_id',
                'description'       => "Server alerts that will be sent to the user if triggers match."
            ]

        ];
    }
}
