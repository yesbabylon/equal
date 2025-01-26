<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\server;

use equal\orm\Model;

class AlertPolicy extends Model {

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the alert.",
                'required'          => true
            ],

            'alert_type' => [
                'type'              => 'string',
                'description'       => "The type of server/instance this alert is meant for.",
                'selection'         => ['all', 'b2', 'b2_instance', 'k2', 's2', 'admin'],
                'default'           => 'all'
            ],

            'alert_triggers_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'inventory\server\AlertTrigger',
                'foreign_field'     => 'alerts_ids',
                'rel_table'         => 'inventory_alert_rel_alert_trigger',
                'rel_foreign_key'   => 'alert_trigger_id',
                'rel_local_key'     => 'alert_id',
                'description'       => "Send the alert when its triggers matches.",
                'domain'            => [
                    [['trigger_type', '=', 'all']],
                    [['trigger_type', '=', 'object.alert_type']]
                ]
            ],

            'users_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'inventory\core\User',
                'foreign_field'     => 'server_alerts_ids',
                'rel_table'         => 'inventory_server_alert_rel_core_user',
                'rel_foreign_key'   => 'user_id',
                'rel_local_key'     => 'alert_id',
                'description'       => "Users to which the alert will be sent if triggers matches."
            ],

            'groups_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'inventory\core\Group',
                'foreign_field'     => 'server_alerts_ids',
                'rel_table'         => 'inventory_server_alert_rel_core_group',
                'rel_foreign_key'   => 'group_id',
                'rel_local_key'     => 'alert_id',
                'description'       => "Group of users to which the alert will be sent if triggers matches."
            ]

        ];
    }

    /**
     * Check a set of statuses against the policy triggers and return true if the policy is triggered.
     *
     * @param array $policy
     * @param array $statuses Server or instance statuses
     * @return bool
     */
    public static function getTriggersResult(array $policy, array $statuses) {

        // Comparison methods to check the server status' value against the trigger's value
        static $comparison_methods = [
            'eq'                => function($value, $trigger_value) { return $value === $trigger_value; },
            'ne'                => function($value, $trigger_value) { return $value !== $trigger_value; },
            'gt'                => function($value, $trigger_value) { return $value > $trigger_value; },
            'gte'               => function($value, $trigger_value) { return $value >= $trigger_value; },
            'lt'                => function($value, $trigger_value) { return $value < $trigger_value; },
            'lte'               => function($value, $trigger_value) { return $value <= $trigger_value; },
            'contains'          => function($value, $trigger_value) { return strpos($value, $trigger_value) !== false; },
            'does_not_contain'  => function($value, $trigger_value) { return strpos($value, $trigger_value) === false; }
        ];

        $result = count($policy['alert_triggers_ids']) > 0;

        foreach($policy['alert_triggers_ids'] as $trigger) {
            if($trigger['repetition'] > count($statuses)) {
                $result = false;
                break;
            }

            for($i = 0; $i < $trigger['repetition']; $i++) {

                $status = $statuses[$i];

                $value = AlertTrigger::getServerStatusValue($trigger['key'], $status);
                if(is_null($value)) {
                    $result = false;
                    break 2;
                }

                $status_value = AlertTrigger::getAdaptedValue($trigger['key'], $value);
                $trigger_value = AlertTrigger::getAdaptedValue($trigger['key'], $trigger['value']);

                if(
                    !in_array($trigger['operator'], array_keys($comparison_methods))
                    || !$comparison_methods[$trigger['operator']]($status_value, $trigger_value)
                ) {
                    $result = false;
                    break 2;
                }
            }
        }

        return $result;
    }
}
