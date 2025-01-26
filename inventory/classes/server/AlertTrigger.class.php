<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\server;

use equal\orm\Model;

class AlertTrigger extends Model {

    const MAP_STATUS_KEYS_TYPES = [
        /**
         * All
         */
        'state.up'                  => 'boolean',
        'instant.total_proc'        => 'integer',
        'instant.ram_use'           => 'percentage',
        'instant.cpu_use'           => 'percentage',
        'instant.dsk_use'           => 'percentage',

        /**
         * Only b2
         */
        'instant.mysql_mem'         => 'percentage',
        'instant.apache_mem'        => 'percentage',
        'instant.nginx_mem'         => 'percentage',
        'instant.apache_proc'       => 'integer',
        'instant.nginx_proc'        => 'integer',
        'instant.mysql_proc'        => 'integer',

        /**
         * Only b2_instance
         */
        'state.maintenance'               => 'boolean',

        /**
         * Only k2 (backups)
         */
        'instant.backup_tokens_qty' => 'integer',
        'instant.backups_disk'      => 'percentage'
    ];

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name of the trigger.",
                'function'          => 'calcName',
                'store'             => true
            ],

            'trigger_type' => [
                'type'              => 'string',
                'description'       => "The type of server this alert is meant for.",
                'selection'         => ['all', 'b2', 'b2_instance', 'k2', 's2', 'admin'],
                'required'          => true
            ],

            'alerts_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'inventory\server\Alert',
                'foreign_field'     => 'alert_triggers_ids',
                'rel_table'         => 'inventory_alert_rel_alert_trigger',
                'rel_foreign_key'   => 'alert_id',
                'rel_local_key'     => 'alert_trigger_id',
                'description'       => "Alert that uses the trigger to know when to be sent."
            ],

            'key' => [
                'type'              => 'string',
                'description'       => "Name of the server status data used for the check if the alert must be triggered.",
                'help'              => "Some status data keys are only available for certain types of servers.",
                'selection'         => [
                    /**
                     * All
                     */
                    'state.up',
                    'instant.total_proc',
                    'instant.ram_use',
                    'instant.cpu_use',
                    'instant.dsk_use',

                    /**
                     * Only b2
                     */
                    'instant.mysql_mem',
                    'instant.apache_mem',
                    'instant.nginx_mem',
                    'instant.apache_proc',
                    'instant.nginx_proc',
                    'instant.mysql_proc',

                    /**
                     * Only b2_instance
                     */
                    'state.maintenance',

                    /**
                     * Only k2 (backups)
                     */
                    'instant.backup_tokens_qty',
                    'instant.backups_disk'
                ],
                'required'          => true,
                'dependencies'      => ['name']
            ],

            'operator' => [
                'type'              => 'string',
                'description'       => "Operator used for the check if the alert must be triggered.",
                'selection'         => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'contains', 'does_not_contain'],
                'default'           => 'eq',
                'required'          => true,
                'dependencies'      => ['name']
            ],

            'value' => [
                'type'              => 'string',
                'description'       => "Value used for the check if the alert must be triggered.",
                'required'          => true,
                'dependencies'      => ['name']
            ],

            'repetition' => [
                'type'              => 'integer',
                'description'       => "Number of repetitions needed for the alert to be triggered.",
                'help'              => "If repetition is 1, then the trigger must match twice in a row.",
                'min'               => 1,
                'default'           => 1,
                'dependencies'      => ['name']
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['key', 'operator', 'value', 'repetition']);
        foreach($self as $id => $trigger) {
            $result[$id] = $trigger['key'].' '.$trigger['operator'].' '.$trigger['value'];
            if($trigger['repetition'] > 1) {
                $result[$id] .= ' ('.$trigger['repetition'].' times)';
            }
        }

        return $result;
    }

    public static function onchange($event, $values) {
        $result = [];

        $common_keys = [
            'state.up',
            'instant.total_proc',
            'instant.ram_use',
            'instant.cpu_use',
            'instant.disk_use'
        ];

        $b2_keys = [
            'instant.mysql_mem',
            'instant.apache_mem',
            'instant.nginx_mem',
            'instant.apache_proc',
            'instant.nginx_proc',
            'instant.mysql_proc'
        ];

        $b2_instance_keys = [
            'state.maintenance'
        ];

        $k2_keys = [
            'instant.backup_tokens_qty',
            'instant.backups_disk'
        ];

        if(isset($event['trigger_type'])) {
            switch($event['trigger_type']) {
                case 'b2':
                    $result['key'] = [
                        'selection' => array_merge(
                            $common_keys,
                            $b2_keys
                        )
                    ];
                    break;
                case 'b2_instance':
                    $result['key'] = [
                        'selection' => array_merge(
                            $common_keys,
                            $b2_instance_keys
                        )
                    ];
                    break;
                case 'k2':
                    $result['key'] = [
                        'selection' => array_merge(
                            $common_keys,
                            $k2_keys
                        )
                    ];
                    break;
                default:
                    $result['key'] = [
                        'selection' => $common_keys
                    ];
                    break;
            }

            if(!in_array($values['key'], $result['key']['selection'])) {
                $result['key']['value'] = $result['key']['selection'][0];
            }
            else {
                $result['key']['value'] = $values['key'];
            }
        }

        return $result;
    }

    public static function getAdaptedValue(string $key, string $value) {
        if(!isset(self::MAP_STATUS_KEYS_TYPES[$key])) {
            return $value;
        }

        switch(self::MAP_STATUS_KEYS_TYPES[$key]) {
            case 'boolean':
                $value = in_array(strtolower($value), ['1', 'true', 'yes']);
                break;
            case 'integer':
            case 'percentage':
                $value = intval($value);
                break;
        }

        return $value;
    }

    /**
     * Use the given key to return specific data from server status array
     * If key "instant.ram_use" then return ['instant' => ['ram_use' => '11.23%']]
     *
     * @param string $key
     * @param array $status_data
     * @return mixed|null
     */
    public static function getServerStatusValue(string $key, array $status_data) {
        $keys = explode('.', $key);
        if(count($keys) === 1) {
            return $status_data[$key] ?? null;
        }

        $first_key = array_shift($keys);
        if(!isset($status_data[$first_key]) || !is_array($status_data[$first_key])) {
            return null;
        }

        return self::getServerStatusValue(implode('.', $keys), $status_data[$first_key]);
    }

    public static function getConstraints(): array {
        return [
            'key' => [
                'key_not_allowed_for_trigger_type' => [
                    'message'       => 'Not allowed for this trigger type.',
                    'function'      => function ($key, $values) {
                        if(!isset($values['trigger_type'])) {
                            return true;
                        }

                        $allowed_keys = [
                            'state.up',
                            'instant.total_proc',
                            'instant.ram_use',
                            'instant.cpu_use',
                            'instant.dsk_use',
                        ];

                        switch($values['trigger_type']) {
                            case 'b2':
                                $allowed_keys = array_merge($allowed_keys, [
                                    'instant.mysql_mem',
                                    'instant.apache_mem',
                                    'instant.nginx_mem',
                                    'instant.apache_proc',
                                    'instant.nginx_proc',
                                    'instant.mysql_proc'
                                ]);
                                break;
                            case 'b2_instance':
                                $allowed_keys = array_merge($allowed_keys, [
                                    'state.maintenance'
                                ]);
                                break;
                            case 'k2':
                                $allowed_keys = array_merge($allowed_keys, [
                                    'instant.backup_tokens_qty',
                                    'instant.backups_disk'
                                ]);
                                break;
                        }

                        return in_array($key, $allowed_keys);
                    }
                ]
            ]
        ];
    }
}
