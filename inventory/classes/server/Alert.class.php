<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\server;

use equal\orm\Model;

class Alert extends Model {

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the alert.",
                'required'          => true
            ],

            'servers_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'inventory\server\Server',
                'foreign_field'     => 'alerts_ids',
                'rel_table'         => 'inventory_server_rel_alert',
                'rel_foreign_key'   => 'server_id',
                'rel_local_key'     => 'alert_id',
                'description'       => "Servers that use this alert."
            ],

            'alert_triggers_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'inventory\server\AlertTrigger',
                'foreign_field'     => 'alerts_ids',
                'rel_table'         => 'inventory_alert_rel_alert_trigger',
                'rel_foreign_key'   => 'alert_trigger_id',
                'rel_local_key'     => 'alert_id',
                'description'       => "Send the alert when its triggers matches."
            ],

            'users_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'core\User',
                'foreign_field'     => 'server_alerts_ids',
                'rel_table'         => 'inventory_server_alert_rel_core_user',
                'rel_foreign_key'   => 'user_id',
                'rel_local_key'     => 'alert_id',
                'description'       => "Users to which the alert will be sent if triggers matches."
            ],

            'groups_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'core\Group',
                'foreign_field'     => 'server_alerts_ids',
                'rel_table'         => 'inventory_server_alert_rel_core_group',
                'rel_foreign_key'   => 'group_id',
                'rel_local_key'     => 'alert_id',
                'description'       => "Group of users to which the alert will be sent if triggers matches."
            ]

        ];
    }
}
