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
        'up'                        => 'boolean',

        /**
         * All server types
         */
        'stats.net.rx'              => 'data_size',
        'stats.net.tx'              => 'data_size',
        'stats.net.total'           => 'data_size',
        'stats.net.avg_rate'        => 'data_rate',
        'stats.cpu'                 => 'percentage',
        'stats.uptime'              => 'days',
        'instant.total_proc'        => 'integer',
        'instant.ram_use'           => 'data_size',
        'instant.cpu_use'           => 'percentage',
        'instant.dsk_use'           => 'data_size',
        'instant.usr_active'        => 'integer',
        'instant.usr_total'         => 'integer',
        /**
         * Only b2
         */
        'stats.mysql_mem'           => 'percentage',
        'stats.apache_mem'          => 'percentage',
        'stats.nginx_mem'           => 'percentage',
        'stats.apache_proc'         => 'integer',
        'stats.nginx_proc'          => 'integer',
        'stats.mysql_proc'          => 'integer',
        /**
         * Only b2 instance
         */
        'maintenance_enabled'       => 'boolean',
        'docker_stats.CPUPerc'      => 'percentage',
        'docker_stats.MemPerc'      => 'percentage',
        /**
         * Only backup
         */
        'instant.backup_tokens_qty' => 'integer',
        'stats.backups_disk'        => 'percentage',
    ];

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the alert."
            ],

            'server_type' => [
                'type'              => 'string',
                'description'       => "The type of server this alert is meant for.",
                'selection'         => ['all', 'b2', 'tapu_backups', 'sapu_stats', 'seru_admin']
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
                'hep'               => "Some status data keys are only available for certain types of servers.",
                'selection'         => [
                    'up',                       // Only key that is always present in server status, true if the server was reachable when inventory\server\Status created.

                    /**
                     * All server types
                     */
                    'stats.net.rx',
                    'stats.net.tx',
                    'stats.net.total',
                    'stats.net.avg_rate',
                    'stats.cpu',
                    'stats.uptime',
                    'instant.total_proc',
                    'instant.ram_use',
                    'instant.cpu_use',
                    'instant.dsk_use',
                    'instant.usr_active',
                    'instant.usr_total',
                    /**
                     * Only b2
                     */
                    'stats.mysql_mem',
                    'stats.apache_mem',
                    'stats.nginx_mem',
                    'stats.apache_proc',
                    'stats.nginx_proc',
                    'stats.mysql_proc',
                    /**
                     * Only b2 instance
                     */
                    'maintenance_enabled',
                    'docker_stats.CPUPerc',
                    'docker_stats.MemPerc',
                    /**
                     * Only backup
                     */
                    'instant.backup_tokens_qty',
                    'stats.backups_disk',
                ]
            ],

            'operator' => [
                'type'              => 'string',
                'description'       => "Operator used for the check if the alert must be triggered.",
                'selection'         => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'contains', 'does_not_contain'],
                'default'           => 'eq'
            ],

            'value' => [
                'type'              => 'string',
                'description'       => "Value used for the check if the alert must be triggered.",
            ],

            'repetition' => [
                'type'              => 'integer',
                'description'       => "Number of repetitions needed for the alert to be triggered.",
                'help'              => "If repetition is 1, then the triggers as to repeat one time for the alert to be triggered. So the trigger must match twice in a row.",
                'min'               => 0,
                'default'           => 0
            ]

        ];
    }

    public static function onchange($event, $values) {
        $result = [];

        $global_keys = [
            'up',
            'stats.net.rx',
            'stats.net.tx',
            'stats.net.total',
            'stats.net.avg_rate',
            'stats.cpu',
            'stats.uptime',
            'instant.total_proc',
            'instant.ram_use',
            'instant.cpu_use',
            'instant.disk_use',
            'instant.usr_active',
            'instant.usr_total',
        ];

        if(isset($event['server_type'])) {
            switch($event['server_type']) {
                case 'b2':
                    $result['key'] = [
                        'selection' => array_merge(
                            $global_keys,
                            [
                                'stats.mysql_mem',
                                'stats.apache_mem',
                                'stats.nginx_mem',
                                'stats.apache_proc',
                                'stats.nginx_proc',
                                'stats.mysql_proc',
                                'maintenance_enabled',
                                'docker_stats.CPUPerc',
                                'docker_stats.MemPerc'
                            ]
                        )
                    ];
                    break;
                case 'tapu_backups':
                    $result['key'] = [
                        'selection' => array_merge(
                            $global_keys,
                            [
                                'instant.backup_tokens_qty',
                                'stats.backups_disk'
                            ]
                        )
                    ];
                    break;
                default:
                    $result['key'] = [
                        'selection' => $global_keys
                    ];
                    break;
            }

            if(!in_array($values['key'], $result['key']['selection'])) {
                $result['key']['value'] = $result['key']['selection'][0];
            }
        }

        return $result;
    }

    public static function adaptValue(string $key, string $value) {
        if(!isset(self::MAP_STATUS_KEYS_TYPES[$key])) {
            return $value;
        }

        switch(self::MAP_STATUS_KEYS_TYPES[$key]) {
            case 'boolean':
                $value = in_array(strtolower($value), ['1', 'true', 'yes']);
                break;
            case 'integer':
            case 'percentage':
            case 'days':
                $value = intval($value);
                break;
            case 'data_size':
            case 'data_rate':
                $value = floatval($value);
                break;
        }

        return $value;
    }

    /**
     * Use the given key to return specific data from server status array
     * If key "stats.uptime" then return ['stats' => ['uptime' => '14days']]
     *
     * @param string $key
     * @param array $server_status
     * @return mixed|null
     */
    public static function getServerStatusValue(string $key, array $server_status) {
        $keys = explode('.', $key);
        if(count($keys) === 1) {
            return $server_status[$key] ?? null;
        }

        $first_key = array_shift($keys);
        if(!isset($server_status[$first_key]) || !is_array($server_status[$first_key])) {
            return null;
        }

        return self::getServerStatusValue(implode('.', $keys), $server_status[$first_key]);
    }

    public static function getConstraints(): array {
        return [
            'key' => [
                'key_not_allowed_for_server_type' => [
                    'message'       => 'Not allowed for this server type.',
                    'function'      => function ($key, $values) {
                        $allowed_keys = [
                            'up',
                            'stats.net.rx',
                            'stats.net.tx',
                            'stats.net.total',
                            'stats.net.avg_rate',
                            'stats.cpu',
                            'stats.uptime',
                            'instant.total_proc',
                            'instant.ram_use',
                            'instant.cpu_use',
                            'instant.disk_use',
                            'instant.usr_active',
                            'instant.usr_total'
                        ];

                        switch($values['server_type']) {
                            case 'b2':
                                $allowed_keys = array_merge($allowed_keys, [
                                    'stats.mysql_mem',
                                    'stats.apache_mem',
                                    'stats.nginx_mem',
                                    'stats.apache_proc',
                                    'stats.nginx_proc',
                                    'stats.mysql_proc',
                                    'maintenance_enabled',
                                    'docker_stats.CPUPerc',
                                    'docker_stats.MemPerc'
                                ]);
                                break;
                            case 'tapu_backups':
                                $allowed_keys = array_merge($allowed_keys, [
                                    'instant.backup_tokens_qty',
                                    'stats.backups_disk'
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
