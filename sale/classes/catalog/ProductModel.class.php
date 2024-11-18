<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\catalog;

use equal\orm\Model;

class ProductModel extends Model {

    public static function getName() {
        return "Product Model";
    }

    public static function getDescription() {
        return "Product Models act as common denominator for products variants (referred to as \"Products\")."
            ." These objects are used for catalogs generation: for instance, if a picture is related to a Product, it is associated on the Product Model level."
            ." A Product Model has at minimum one variant, which means at minimum one SKU.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the product model (used for all variants).",
                'required'          => true
            ],

            'family_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Family',
                'description'       => "Product Family which current product belongs to.",
                'onupdate'          => 'onupdateFamilyId',
                'required'          => true
            ],

            'selling_accounting_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingRule',
                'description'       => 'Accounting rule to use in case of sell.'
            ],

            'buying_accounting_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingRule',
                'description'       => 'Accounting rule to use in case of purchase.'
            ],

            'stat_section_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\stats\StatSection',
                'description'       => 'Statistics section to which relates the product, if any.'
            ],

            'can_buy' => [
                'type'              => 'boolean',
                'description'       => "Can this product be purchased?",
                'default'           => false,
                'onupdate'          => 'onupdateCanBuy'
            ],

            'can_sell' => [
                'type'              => 'boolean',
                'description'       => "Can this product be sold?",
                'default'           => true,
                'onupdate'          => 'onupdateCanSell'
            ],

            'is_pack' => [
                'type'              => 'boolean',
                'description'       => "Is the product a bundle of other products?",
                'default'           => false,
                'onupdate'          => 'onupdateIsPack'
            ],

            'has_own_price' => [
                'type'              => 'boolean',
                'description'       => 'Has the pack its own price, or do we use each sub-product price?',
                'default'           => false,
                'visible'           => ['is_pack', '=', true],
                'onupdate'          => 'onupdateHasOwnPrice'
            ],

            'type' => [
                'type'              => 'string',
                'description'       => 'Is the product a consumable or a service.',
                'selection'         => [
                    'consumable',
                    'service'
                ],
                'required'          => true,
                'default'           => 'service'
            ],

            'consumable_type' => [
                'type'              => 'string',
                'description'       => 'Is the consumable product storable.',
                'selection'         => [
                    'simple',
                    'storable'
                ],
                'visible'           => ['type', '=', 'consumable']
            ],

            'service_type' => [
                'type'              => 'string',
                'description'       => 'Is the service product schedulable.',
                'selection'         => [
                    'simple',
                    'schedulable'
                ],
                'visible'           => ['type', '=', 'service'],
                'default'           => 'simple'
            ],

            'schedule_type' => [
                'type'              => 'string',
                'description'       => 'Is the service schedulable on a specific time or on a time range.',
                'selection'         => [
                    'time',
                    'timerange'
                ],
                'default'           => 'time',
                'visible'           => [ ['type', '=', 'service'], ['service_type', '=', 'schedulable'] ]
            ],

            'schedule_default_value' => [
                'type'              => 'string',
                'description'       => "Default value of the schedule according to type (time: '9:00', timerange: '9:00-10:00').",
                'visible'           => [ ['type', '=', 'service'], ['service_type', '=', 'schedulable'] ]
            ],

            'schedule_offset' => [
                'type'              => 'integer',
                'description'       => 'Default number of days to set-off the service from a sojourn start date.',
                'default'           => 0,
                'visible'           => [ ['type', '=', 'service'], ['service_type', '=', 'schedulable'] ]
            ],

            'tracking_type' => [
                'type'              => 'string',
                'description'       => 'How is the stored consumable product tracked.',
                'selection'         => [
                    'none',
                    'batch',
                    'sku',
                    'upc'
                ],
                'visible'           => [ ['type', '=', 'consumable'], ['consumable_type', '=', 'storable'] ],
                'default'           => 'sku'
            ],

            'description_delivery' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description for delivery notes.",
                'multilang'         => true
            ],

            'description_receipt' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description for reception vouchers.",
                'multilang'         => true
            ],

            'groups_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\Group',
                'foreign_field'     => 'product_models_ids',
                'description'       => 'Linked groups.',
                'rel_table'         => 'sale_catalog_product_rel_productmodel_group',
                'rel_foreign_key'   => 'group_id',
                'rel_local_key'     => 'productmodel_id',
                'onupdate'          => 'onupdateGroupsIds'
            ],

            'categories_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\Category',
                'foreign_field'     => 'product_models_ids',
                'description'       => 'Linked categories',
                'rel_table'         => 'sale_product_rel_productmodel_category',
                'rel_foreign_key'   => 'category_id',
                'rel_local_key'     => 'productmodel_id'
            ],

            'products_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\Product',
                'foreign_field'     => 'product_model_id',
                'description'       => "Product variants that are related to this model.",
            ],

            'options_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\Option',
                'foreign_field'     => 'product_model_id',
                'description'       => "Product options that are related to this model.",
            ]

        ];
    }

    public static function onupdateIsPack($self) {
        $self->read(['products_ids', 'is_pack']);
        foreach($self as $id => $model) {
            Product::ids($model['products_ids'])->update(['is_pack' => $model['is_pack']]);
        }
    }

    public static function onupdateHasOwnPrice($self) {
        $self->read(['products_ids', 'has_own_price']);
        foreach($self as $id => $model) {
            Product::ids($model['products_ids'])->update(['has_own_price' => $model['has_own_price']]);
        }
    }

    public static function onupdateCanSell($self) {
        $self->read(['products_ids', 'can_sell']);
        foreach($self as $id => $model) {
            Product::ids($model['products_ids'])->update(['can_sell' => $model['can_sell']]);
        }
    }

    public static function onupdateCanBuy($self) {
        $self->read(['products_ids', 'can_buy']);
        foreach($self as $id => $model) {
            Product::ids($model['products_ids'])->update(['can_buy' => $model['can_buy']]);
        }
    }

    public static function onupdateFamilyId($self) {
        $self->read(['products_ids', 'family_id']);
        foreach($self as $id => $model) {
            Product::ids($model['products_ids'])->update(['family_id' => $model['family_id']]);
        }
    }

    public static function onupdateGroupsIds($self) {
        $self->read(['products_ids', 'groups_ids']);
        foreach($self as $mid => $model) {
            $products = Product::ids($model['products_ids'])->read('groups_ids')->get();
            foreach($products as $pid => $product) {
                $groups_ids = array_map(function($a) {return "-$a";}, (array) $product['groups_ids']);
                $groups_ids = array_merge($groups_ids, (array) $model['groups_ids']);
                Product::id($pid)->update(['groups_ids' => $groups_ids]);
            }
        }
    }
}
