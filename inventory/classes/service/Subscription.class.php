<?php
/*
    This file is part of the eQual framework <http://www.github.com/cedricfrancoys/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2021
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\service;

use equal\orm\Model;
use inventory\sale\price\Price;
use inventory\sale\price\PriceList;
use inventory\sale\receivable\Receivable;

class Subscription extends \sale\SaleEntry  {

    public function getTable() {
        // force table name to use distinct tables and ID columns
        return 'inventory_service_subscription';
    }

    const MAP_DURATION = [
        'Monthly'      => "+1 month",
        'Quarterly'    => "+3 month",
        'Half-annual'  => "+6 month",
        'Annual'       => "+1 year"
    ];

    public static function getColumns()
    {
        return [
            'name' => [
                'type'              => 'string',
                'unique'            => true,
                'required'          => true,
                'description'       => 'Name of the product.',
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Information about a subscription.'
            ],

            'date_from' => [
                'type'              => 'date',
                'required'          => true,
                'description'       => 'Start date of subscription.',
                'default'           => time(),
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => 'End date of subscription.',
                'required'          => true,
                'default'           => strtotime("+1 year"),
                'dependencies'      => ['is_expired','has_upcoming_expiry']
            ],

            'duration' => [
                'type'              => 'string',
                'selection'         => ['Monthly', 'Quarterly', 'Half-annual', 'Annual'],
                'description'       => 'Type of the duration.',
                'default'           => 'Annual'

            ],

            'is_expired' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'The subscription is expired.',
                'function'          => 'calcIsExpired',
                'store'             => true,
                'instant'           => true
            ],

            'has_upcoming_expiry' => [
                'type'              => 'computed',
                'description'       => 'The subscription is  up coming expiry.',
                'result_type'       => 'boolean',
                'function'          => 'calcUpcomingExpiry',
                'store'             => true,
                'instant'           => true
            ],

            'ref_order' => [
                'type'              => 'string',
                'description'       => 'Information about the reference number.',
            ],

            'license_key' => [
                'type'              => 'string',
                'description'       => 'Information about the key of license.',
            ],

            'service_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\service\Service',
                'description'       => 'Detail attached to a service.',
                'required'          => true,
                'onupdate'          => 'onupdateHasSubscription',
                'dependencies'      => ['has_external_provider','is_billable','is_internal']
            ],

            'is_internal' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'The subscription is internal.',
                'visible'           => ['service_id','<>', null],
                'store'             => true,
                'function'          => 'calcIsInternal'
            ],

            'is_billable' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'The lines that are assigned to the statement.',
                'visible'           => ['service_id','<>', null],
                'store'             =>  true,
                'function'          => 'calcIsBillable'
            ],

            'has_external_provider' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'The subscription has external provider.',
                'store'             => true,
                'visible'           => ['service_id','<>', null],
                'dependencies'      => ['product_id'],
                'function'          => 'calcHasExternalProvider'
            ],

            'product_id'=> [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\sale\catalog\Product',
                'description'       => 'Product of the catalog sale.',
                'dependencies'      => ['price_id']
            ],

            'price_id'=> [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'inventory\sale\price\Price',
                'description'       => 'Price of the sale.',
                'dependencies'      => ['price'],
                'store'             => true,
                'function'          => 'calcPriceId',

            ],

            'price' => [
                'type'              => 'float',
                'description'       => 'Amount to be received.',
                'usage'             => 'amount/money:2',
                'default'           =>  0.0
            ],

            'has_receivable' => [
                'type'              => 'boolean',
                'description'       => 'The entry is linked to a Receivable entry.',
                'visible'           => ['service_id','<>', null],
                'default'           => false
            ],

            'receivable_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\receivable\Receivable',
                'description'       => 'The entry is linked to a Receivable entry.',
                'visible'           => ['service_id','<>', null]
            ]
        ];
    }


    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['service_id']) && strlen($event['service_id']) > 0 ){
            $service = Service::search(['id', 'in', $event['service_id']])
            ->read(['has_external_provider','is_billable','is_internal'])
            ->first();
            $result['has_external_provider'] = $service['has_external_provider'];
            $result['is_billable'] = $service['is_billable'];
            $result['is_internal'] = $service['is_internal'];
        }

        if(isset($event['date_from']) || isset($event['duration'])){
            $date_from =  (isset($event['date_from'])) ? $event['date_from'] : $values['date_from'];
            $duration = (string) (isset($event['duration'])) ? $event['duration'] : $values['duration'];
            $duration = self::MAP_DURATION[$duration];
            $date_to = strtotime($duration, $date_from);
            $result['date_to'] = $date_to;
            $result['is_expired'] = (time() > $date_to);
            $diff = ($date_to - time())/60/60/24;
            $result['has_upcoming_expiry'] = ($diff<30);
        }

        if(isset($event['product_id']) && isset($values['date_from']) && isset($values['date_to'])) {

            $price_lists_ids = PriceList::search([
                    [
                        ['date_from', '<', $values['date_from']],
                        ['date_to', '>=', $values['date_from']],
                        ['date_to', '<=', $values['date_to']],
                        ['status', '=', 'published'],
                    ],
                    [
                        ['date_from', '>=', $values['date_from']],
                        ['date_to', '>=', $values['date_from']],
                        ['date_to', '<=', $values['date_to']],
                        ['status', '=', 'published'],
                    ],
                    [
                        ['date_from', '>=', $values['date_from']],
                        ['date_to', '>', $values['date_to']],
                        ['status', '=', 'published'],
                    ],
                    [
                        ['date_from', '<', $values['date_from']],
                        ['date_to', '>', $values['date_to']],
                        ['status', '=', 'published'],
                    ],
                ] )
                ->ids();

            $price = Price::search([
                ['product_id', '=', $event['product_id']],
                ['price_list_id', 'in', $price_lists_ids]
                ])->read(['id','name','price'])->first();

                $result['price_id'] = $price;
                $result['price'] = $price['price'];

        }
        return $result;
    }

    public static function calcPriceId($self) {

        $result = [];
        $self->read(['date_from', 'date_to', 'product_id']);
        foreach($self as $id => $subscription) {
            $price_lists_ids = PriceList::search([
                [
                    ['date_from', '<', $subscription['date_from']],
                    ['date_to', '>=', $subscription['date_from']],
                    ['date_to', '<=', $subscription['date_to']],
                    ['status', '=', 'published'],
                ],
                [
                    ['date_from', '>=', $subscription['date_from']],
                    ['date_to', '>=', $subscription['date_from']],
                    ['date_to', '<=', $subscription['date_to']],
                    ['status', '=', 'published'],
                ],
                [
                    ['date_from', '>=', $subscription['date_from']],
                    ['date_to', '>', $subscription['date_to']],
                    ['status', '=', 'published'],
                ],
                [
                    ['date_from', '<', $subscription['date_from']],
                    ['date_to', '>', $subscription['date_to']],
                    ['status', '=', 'published'],
                ],
            ] )
            ->ids();

            $price = Price::search([
                ['product_id', '=', $subscription['product_id']],
                ['price_list_id', 'in', $price_lists_ids]
                ])->read(['id'])->first();

            $result[$id] = $price['id'];
        }



        return $result;

    }


    public static function calcIsExpired($self) {
        $result = [];
        $self->read(['date_to']);
        foreach($self as $id => $subscription) {
            $result[$id] = (time() > $subscription['date_to']);
        }
        return $result;

    }

    public static function calcUpcomingExpiry($self) {
        $self->read(['date_to']);
        $result=[];
        foreach($self as $id => $subscription) {
            $diff = ($subscription['date_to'] - time())/60/60/24; //30 days
            $result[$id] = ($diff<30);
        }
        return $result;
    }

    public static function onupdateHasSubscription($self) {
        $self->read(['service_id']);
        foreach($self as $id) {
            Service::ids($id['service_id'])->update(['has_subscription' =>true]);
        }

    }

    public static function calcHasExternalProvider($self) {
        $result = [];
        $self->read(['service_id' => ['has_external_provider']]);
        foreach($self as $id => $subscription) {
            $result[$id] = $subscription['service_id']['has_external_provider'];
        }
        return $result;
    }
    public static function calcIsInternal($self) {
        $result = [];
        $self->read(['service_id' => ['is_internal']]);
        foreach($self as $id => $subscription) {
            $result[$id] = $subscription['service_id']['is_internal'];
        }
        return $result;
    }

    public static function calcIsBillable($self) {
        $result = [];
        $self->read(['service_id' => ['is_billable']]);
        foreach($self as $id => $subscription) {
            $result[$id] = $subscription['service_id']['is_billable'];
        }
        return $result;
    }


}
