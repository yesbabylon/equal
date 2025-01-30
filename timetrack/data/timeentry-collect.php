<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;

list($params, $providers) = eQual::announce([
    'description' => 'Advanced search for Time Entries: returns a collection of Reports according to extra parameters.',
    'extends'     => 'core_model_collect',
    'params'      => [
        'entity' => [
            'description' => 'Full name (including namespace) of the class to return.',
            'type'        => 'string',
            'default'     => 'timetrack\TimeEntry'
        ],
        'description' => [
            'description'    => 'Display only entries matching the given description',
            'type'           => 'string'
        ],
        'user_id' => [
            'description'    => 'Display only entries of selected user',
            'type'           => 'many2one',
            'foreign_object' => 'core\User'
        ],
        'date_from' => [
            'description'    => 'Display only entries that occurred after selected date',
            'type'           => 'date',
        ],
        'date_to' => [
            'description'    => 'Display only entries that occurred before selected date',
            'type'           => 'date',
        ],
        'customer_id' => [
            'description'    => 'Display only entries of selected customer',
            'type'           => 'many2one',
            'foreign_object' => 'sale\customer\Customer',
            'domain'         => ['is_active', '=', true]
        ],
        'project_id' => [
            'description'    => 'Display only entries of selected project',
            'type'           => 'many2one',
            'foreign_object' => 'timetrack\Project'
        ],
        'origin' => [
            'description'    => 'Display only entries of selected origin',
            'type'           => 'string',
            'selection'      => [
                'all',
                'project',
                'backlog',
                'email',
                'support'
            ],
            'default'        => 'all'
        ],
        'has_receivable' => [
            'description'    => 'Filter entries on has receivable',
            'type'           => 'boolean',
        ],
        'is_billable' => [
            'description'    => 'Filter entries on is billable',
            'type'           => 'boolean',
        ],
        'status' => [
            'description'    => 'Filter entries on status',
            'type'           => 'string',
            'selection'      => [
                    'all',
                    'pending',
                    'ready',
                    'validated',
                    'billed'
                ],
            'default'        => 'all'
        ]
    ],
    'response'    => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'   => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
$context = $providers['context'];

$domain = [ ['object_class', '=', 'timetrack\TimeEntry'] ];

if(isset($params['description']) && strlen($params['description']) > 0) {
    $domain[] = ['description', 'ilike', '%'.$params['description'].'%'];
}

if(isset($params['user_id']) && $params['user_id'] > 0) {
    $domain[] = ['user_id', '=', $params['user_id']];
}

if(isset($params['customer_id']) && $params['customer_id'] > 0) {
    $domain[] = ['customer_id', '=', $params['customer_id']];
}

if(isset($params['date_from']) && $params['date_from'] > 0) {
    if(isset($params['date_to']) && $params['date_to'] > 0) {
        $domain[] = ['date', '>=', $params['date_from']];
        $domain[] = ['date', '<=', $params['date_to']];
    }
    else {
        $domain[] = ['date', '=', $params['date_from']];
    }
}

if(isset($params['project_id']) && $params['project_id'] > 0) {
    $domain[] = ['project_id', '=', $params['project_id']];
}

if(isset($params['origin']) && $params['origin'] != 'all') {
    $domain[] = ['origin', '=', $params['origin']];
}

if(isset($params['has_receivable'])) {
    $domain[] = ['has_receivable', '=', (bool) $params['has_receivable']];
}

if(isset($params['is_billable'])) {
    $domain[] = ['is_billable', '=', (bool) $params['is_billable']];
}

if(isset($params['status']) && $params['status'] != 'all') {
    $domain[] = ['status', '=', $params['status']];
}

if(isset($params['domain']) && count($params['domain'])) {
    $domain = (new Domain($params['domain']))
        ->merge(new Domain($domain))
        ->toArray();
}


$result = eQual::run('get', 'model_collect', [
        'entity'    => 'timetrack\TimeEntry',
        'domain'    => $domain,
        'fields'    => $params['fields'],
        'lang'      => $params['lang'],
        'order'     => $params['order'],
        'sort'      => $params['sort'],
        'start'     => $params['start'],
        'limit'     => $params['limit']
    ]);

$context->httpResponse()
        ->body($result)
        ->send();
