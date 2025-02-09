<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\subscription;

use sale\SaleEntry;

class SubscriptionEntry extends SaleEntry {

    public static function getDescription() {
        return 'A subscription entry represents one period of a subscription. Like a sale entry a receivable can be generated from it.';
    }

    public static function getColumns(): array {
        return [

            /**
             * Override SaleEntry columns
             */

            'qty' => [
                'type'           => 'float',
                'description'    => 'Quantity of product.',
                'default'        => 1,
                'visible'        => false
            ],

            'object_class' => [
                'type'           => 'string',
                'description'    => 'Class of the object.',
                'default'        => 'sale\subscription\SubscriptionEntry'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Short readable identifier of the entry.',
                'store'             => true,
                'function'          => 'calcName'
            ],

            'subscription_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'sale\subscription\Subscription',
                'description'    => 'Identifier of the Subscription the sale entry originates from.',
                'dependents'     => ['product_id', 'customer_id', 'is_billable']
            ],

            'product_id' => [
                'type'           => 'computed',
                'result_type'    => 'many2one',
                'foreign_object' => 'sale\catalog\Product',
                'description'    => 'Product of the catalog sale.',
                'store'          => true,
                'instant'        => true,
                'relation'       => ['subscription_id' => 'product_id']
            ],

            'customer_id' => [
                'type'           => 'computed',
                'result_type'    => 'many2one',
                'foreign_object' => 'sale\customer\Customer',
                'description'    => 'The Customer to who refers the item.',
                'store'          => true,
                'instant'        => true,
                'relation'       => ['subscription_id' => 'customer_id']
            ],

            'is_billable' => [
                'type'           => 'computed',
                'result_type'    => 'boolean',
                'description'    => 'Can be billed to the customer.',
                'store'          => true,
                'instant'        => true,
                'relation'       => ['subscription_id' => 'is_billable']
            ],

            /**
             * Specific SubscriptionEntry columns
             */

            'date_from' => [
                'type'           => 'date',
                'description'    => 'Start date of the subscription period this entry covers.',
                'required'       => true
            ],

            'date_to' => [
                'type'           => 'date',
                'description'    => 'End date of the subscription period this entry covers.',
                'required'       => true
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['subscription_id' => ['name'], 'date_from', 'date_to']);
        foreach($self as $id => $entry) {
            $result[$id] = $entry['subscription_id']['name'] . ' - ' . date('Y-m-d', $entry['date_from']) . '-' . date('Y-m-d', $entry['date_to']);
        }
        return $result;
    }

}
