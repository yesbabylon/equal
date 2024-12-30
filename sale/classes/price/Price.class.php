<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\price;

use equal\orm\Model;
use finance\accounting\AccountingRule;

class Price extends Model {

    public static function getDescription() {
        return 'A price is an amount of money that corresponds to the sale price of a product or service.'
            .' It is described by an amount, a vat rate, an accounting rule and is part of a price list.';
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'function'          => 'calcName',
                'result_type'       => 'string',
                'store'             => true,
                'description'       => 'The display name of the price.'
            ],

            'price' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Tax excluded price.",
                'required'          => true,
                'dependents'        => ['price_vat'],
                'visible'           => ['price_type', '=', 'direct']
            ],

            'price_vat' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'function'          => 'calcPriceVat',
                'usage'             => 'amount/money:2',
                'description'       => "Tax included price. This field is used to allow encoding prices VAT incl.",
                'store'             => true,
                'visible'           => ['price_type', '=', 'direct']
            ],

            'price_type' => [
                'type'              => 'string',
                'description'       => 'If computed a calculation method is used to compute the price amount.',
                'selection'         => ['direct', 'computed'],
                'default'           => 'direct'
            ],

            'calculation_method_id' => [
                'type'              => 'string',
                'description'       => "Method to use for price computation.",
                'visible'           => ['price_type', '=', 'computed']
            ],

            'price_list_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\PriceList',
                'description'       => "The Price List the price belongs to.",
                'required'          => true,
                'ondelete'          => 'cascade',
                'dependents'        => ['name']
            ],

            'is_active' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'function'          => 'calcIsActive',
                'store'             => true,
                'instant'           => true,
                'description'       => "Is the price currently applicable?"
            ],

            'accounting_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingRule',
                'description'       => "Selling accounting rule. If set, overrides the rule of the product this price is assigned to.",
                'dependents'        => ['vat_rate', 'price_vat']
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => "The Product (sku) the price applies to.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/rate',
                'relation'          => ['accounting_rule_id' => ['vat_rule_id' => 'rate']],
                'description'       => 'VAT rate applied on the price (from accounting rule).',
                'store'             => true,
                'readonly'          => true,
                'visible'           => ['price_type', '=', 'direct']
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['product_id' => ['id', 'sku'], 'price_list_id' => 'name']);
        foreach($self as $id => $product) {
            $result[$id] = "{$product['product_id']['sku']} [{$product['product_id']['id']}] - {$product['price_list_id']['name']}";
        }
        return $result;
    }

    public static function calcPriceVat($self) {
        $result = [];
        $self->read(['price', 'vat_rate']);
        foreach($self as $id => $price) {
            $result[$id] = self::computePriceVatIncluded($price['price'], $price['vat_rate']);
        }
        return $result;
    }

    public static function calcIsActive($self) {
        $result = [];
        $self->read(['price_list_id' => 'is_active']);
        foreach($self as $id => $price) {
            $result[$id] = $price['price_list_id']['is_active'];
        }
        return $result;
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['accounting_rule_id'])) {
            $rule = AccountingRule::id($event['accounting_rule_id'])->read(['vat_rule_id' => 'rate'])->first();
            $result['vat_rate'] = $rule['vat_rule_id']['rate'];
        }

        if(isset($event['price'])) {
            $result['price_vat'] = self::computePriceVatIncluded($event['price'], $values['vat_rate'] ?? 0.0);
        }
        elseif(isset($event['price_vat'])) {
            $result['price'] = self::computePriceVatExcluded($event['price_vat'], $values['vat_rate'] ?? 0.0);
        }

        return $result;
    }

    public function computePriceVatIncluded($price, $vat_rate) {
        return round($price * (1.0 + $vat_rate), 2);
    }

    public function computePriceVatExcluded($price_vat, $vat_rate) {
        return $price_vat / (1.0 + $vat_rate);
    }

    public function getUnique() {
        return [
            ['product_id', 'price_list_id']
        ];
    }

}
