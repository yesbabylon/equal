<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\catalog;

use equal\orm\Model;

class Product extends Model {

    public static function getName() {
        return "Product";
    }

    public static function getDescription() {
        return "A Product is a variant of a Product Model. There is always at least one Product for a given Product Model."
            ." Within the organisation, a product is always referenced by a SKU code (assigned to each variant of a Product Model)."
            ." A SKU code identifies a single product with all its specific characteristics.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => 'The full name of the product (label + sku).'
            ],

            'label' => [
                'type'              => 'string',
                'description'       => 'Human readable memo for identifying the product. Allows duplicates.',
                'required'          => true,
                'dependents'        => ['name']
            ],

            'sku' => [
                'type'              => 'string',
                'description'       => "Stock Keeping Unit code for internal reference. Must be unique.",
                'required'          => true,
                'unique'            => true,
                'dependents'        => ['name']
            ],

            'ean' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.ean',
                'description'       => "IAN/EAN code for barcode generation."
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Description of the variant (specifics)."
            ],

            'product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => ProductModel::getType(),
                'description'       => "Product Model of this variant.",
                'required'          => true,
                'onupdate'          => 'onupdateProductModelId'
            ],

            'family_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'function'          => 'calcFamilyId',
                'foreign_object'    => 'sale\catalog\Family',
                'description'       => "Product Family which current product belongs to.",
                'store'             => true,
                'readonly'          => true
            ],

            'is_pack' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'function'          => 'calcIsPack',
                'description'       => 'Is the product a pack? (from model).',
                'store'             => true,
                'readonly'          => true
            ],

            'has_own_price' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'function'          => 'calcHasOwnPrice',
                'description'       => 'Product is a pack with its own price (from model).',
                'visible'           => ['is_pack', '=', true],
                'store'             => true,
                'readonly'          => true
            ],

            'pack_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\PackLine',
                'foreign_field'     => 'parent_product_id',
                'description'       => "Products that are bundled in the pack.",
                'ondetach'          => 'delete',
                'visible'           => ['is_pack', '=', true]
            ],

            'product_attributes_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\catalog\ProductAttribute',
                'foreign_field'     => 'product_id',
                'description'       => "Attributes set for the product.",
                'ondetach'          => 'delete'
            ],

            'prices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\price\Price',
                'foreign_field'     => 'product_id',
                'description'       => "Prices that are related to this product.",
                'help'              => "If the organisation uses price-lists, the price to use depends on the applicable price list at the moment of the sale.",
                'ondetach'          => 'delete'
            ],

            'stat_section_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\stats\StatSection',
                'description'       => 'Statistics section (overloads the model one, if any).'
            ],

            'can_buy' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'function'          => 'calcCanBuy',
                'description'       => "Can this product be purchased? (from model)",
                'help'              => "Field can_buy is adapted when related value is changed in parent ProductModel.",
                'store'             => true,
                'readonly'          => true
            ],

            'can_sell' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'function'          => 'calcCanSell',
                'description'       => "Can this product be sold? (from model)",
                'help'              => "Field can_sell is adapted when related value is changed in parent ProductModel.",
                'store'             => true,
                'readonly'          => true
            ],

            'groups_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\catalog\Group',
                'foreign_field'     => 'products_ids',
                'description'       => 'Linked groups.',
                'rel_table'         => 'sale_catalog_product_rel_product_group',
                'rel_foreign_key'   => 'group_id',
                'rel_local_key'     => 'product_id'
            ],

            'subscriptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\subscription\Subscription',
                'foreign_field'     => 'product_id',
                'description'       => 'The subscriptions needed for the product.'
            ],

        ];
    }

    /**
     * Computes the display name of the product as a concatenation of Label and SKU.
     *
     */
    public static function calcName($self) {
        $result = [];
        $self->read(['label', 'sku']);
        foreach($self as $id => $product) {
            $result[$id] = '';
            if( isset($product['label']) && strlen($product['label']) ){
                $result[$id] .= $product['label'];
            }
            if( isset($product['sku']) && strlen($product['sku']) ) {
                if(strlen($result[$id])) {
                    $result[$id] .= ' ';
                }
                $result[$id] .= "[{$product['sku']}]";
            }
        }
        return $result;
    }

    public static function calcIsPack($self): array {
        return self::calcFromProductModel($self, 'is_pack');
    }

    public static function calcHasOwnPrice($self): array {
        return self::calcFromProductModel($self, 'has_own_price');
    }

    public static function calcCanBuy($self): array {
        return self::calcFromProductModel($self, 'can_buy');
    }

    public static function calcCanSell($self): array {
        return self::calcFromProductModel($self, 'can_sell');
    }

    public static function calcFamilyId($self): array {
        return self::calcFromProductModel($self, 'family_id');
    }

    private static function calcFromProductModel($self, $column): array {
        $result = [];
        $self->read(['product_model_id' => [$column]]);
        foreach($self as $id => $product) {
            if(isset($product['product_model_id'][$column])) {
                $result[$id] = $product['product_model_id'][$column];
            }
        }

        return $result;
    }

    public static function onupdateProductModelId($self) {
        $self->read(['groups_ids', 'product_model_id' => ['is_pack', 'has_own_price','can_sell','can_buy' , 'groups_ids', 'family_id']]);
        foreach($self as $id => $product) {
            if(isset($product['groups_ids']) && count($product['groups_ids'])) {
                self::id($id)
                    // remove current groups
                    ->update(['groups_ids' => array_map(function ($a) { return -$a; }, (array) $product['groups_ids'])]);
            }
            self::id($id)
                // set values according to assigned model
                ->update([
                    'is_pack'       => null,
                    'has_own_price' => null,
                    'can_sell'      => null,
                    'can_buy'       => null,
                    'groups_ids'    => $product['product_model_id']['groups_ids'],
                    'family_id'     => null
                ]);
        }
    }

    public function getUnique() {
        return [
            ['sku'],
            ['label', 'product_model_id']
        ];
    }
}
