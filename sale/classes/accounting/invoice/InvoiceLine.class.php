<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\accounting\invoice;

use sale\catalog\Product;
use sale\price\Price;
use sale\price\PriceList;

class InvoiceLine extends \finance\accounting\InvoiceLine {

    public static function getColumns() {
        return [

            /**
             * Override Finance Invoice columns
             */

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Default label of the line, based on product (computed).',
                'function'          => 'calcName',
                'store'             => true,
                'instant'           => true
            ],

            'invoice_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\accounting\invoice\InvoiceLineGroup',
                'description'       => 'Group the line relates to (in turn, groups relate to their invoice).',
                'ondelete'          => 'cascade',
                'domain'            => ['invoice_id', '=', 'object.invoice_id']
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\accounting\invoice\Invoice',
                'description'       => 'Invoice the line is related to.',
                'required'          => true,
                'onupdate'          => 'onupdateInvoiceId',
                'ondelete'          => 'cascade'
            ],

            'unit_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Unit price of the product related to the line.',
                'function'          => 'calcUnitPrice',
                'store'             => true
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/rate',
                'description'       => 'VAT rate to be applied.',
                'function'          => 'calcVatRate',
                'store'             => true,
                'default'           => 0.0,
                'onupdate'          => 'onupdateVatRate'
            ],

            /**
             * Specific Sale InvoiceLine columns
             */

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => 'The product (SKU) the line relates to.',
                'required'          => true,
                'dependents'      => ['name']
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => 'The price the line relates to (assigned at line creation).',
                'onupdate'          => 'onupdatePriceId'
            ],

            'receivable_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\receivable\Receivable',
                'description'       => 'Receivable at the origin of the invoice line.'
            ],

            'has_receivable' => [
                'type'              => 'boolean',
                'description'       => 'Was the line generated from a receivable.',
                'default'           => false
            ]

        ];
    }

    public static function onchange($event, $values): array {
        $result = [];

        if(isset($event['product_id'])) {
            $product = Product::id($event['product_id'])
                ->read(['name'])
                ->first();

            if(isset($product)) {
                $result['name'] = $product['name'];
            }

            $price_lists_ids = PriceList::search([
                [
                    ['date_from', '<=', time()],
                    ['date_to', '>=', time()],
                    ['status', '=', 'published'],
                ]
            ])
                ->ids();

            $result['price_id'] = Price::search([
                ['product_id', '=', $event['product_id']],
                ['price_list_id', 'in', $price_lists_ids]
            ])
                ->read(['id', 'name', 'price', 'vat_rate'])
                ->first();

            if(isset($result['price_id']['price'])) {
                $result['unit_price'] = $result['price_id']['price'];
            }

            if(isset($result['price_id']['vat_rate'])) {
                $result['vat_rate'] = $result['price_id']['vat_rate'];
            }
        }

        return $result;
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['product_id' => ['name']]);
        foreach($self as $id => $line) {
            $result[$id] = $line['product_id']['name'];
        }

        return $result;
    }

    public static function calcUnitPrice($self): array {
        $result = [];
        $self->read(['price_id' => ['price']]);
        foreach($self as $id => $line) {
            $result[$id] = $line['price_id']['price'];
        }

        return $result;
    }

    public static function calcVatRate($self): array {
        $result = [];
        $self->read(['price_id' => ['vat_rate']]);
        foreach($self as $id => $line) {
            $result[$id] = 0.0;
            if(isset($line['price_id']['vat_rate'])) {
                $result[$id] = floatval($line['price_id']['vat_rate']);
            }
        }

        return $result;
    }

    public static function onupdatePriceId($self) {
        $self->update([
            'vat_rate'   => null,
            'unit_price' => null,
            'total'      => null,
            'price'      => null
        ]);

        $self->do('reset_invoice_prices');
    }

    public static function canupdate($self, $values): array {
        $self->read(['has_receivable']);
        foreach($self as $invoice_line) {
            if($invoice_line['has_receivable']) {
                return ['receivable_id' => ['non_editable' => 'Invoice lines generated by receivable cannot be updated.']];
            }
        }

        return parent::canupdate($self, $values);
    }
}
