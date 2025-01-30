<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\receivable;

use core\setting\Setting;
use equal\orm\Model;

class Receivable extends Model {

    public static function getDescription() {
        return 'A Sale Receivable represent a good or a service that has been sold to a Customer, and whose amount must be received.';
    }

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Default label of the line, based on product.',
                'function'          => 'calcName',
                'store'             => true,
                'readonly'          => true
            ],

            'description' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Description of the receivable.',
                'relation'          => ['sale_entry_id' => ['description']],
                'store'             => true,
                'readonly'          => true
            ],

            'receivables_queue_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\receivable\ReceivablesQueue',
                'description'       => 'The parent Queue the receivable is attached to.',
                'required'          => true,
                'domain'            => ['customer_id', '=', 'object.customer_id']
            ],

            'date' => [
                'type'              => 'datetime',
                'description'       => 'Creation date of the receivable.',
                'help'              => 'Not all sale entries are synchronous, and a receivable might have a distinct date (i.e. subscription).',
                'readonly'          => true
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Status of the receivable (pending, invoiced or cancelled).',
                'selection'         => [
                    'pending',
                    'invoiced',
                    'cancelled'
                ],
                'default'           => 'pending'
            ],

            'sale_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\SaleEntry',
                'description'       => 'The sale entry the receivable originates from.',
                'dependents'        => ['name', 'description', 'product_id', 'price_id', 'unit_price', 'vat_rate', 'qty', 'free_qty', 'discount', 'total', 'price'],
                'required'          => true,
                'readonly'          => true
            ],

            'invoice_group' => [
                'type'              => 'string',
                'description'       => 'Arbitrary name for grouping sales when invoicing (might be left unset).',
            ],

            'customer_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'relation'          => ['sale_entry_id' => ['customer_id']],
                'description'       => 'The Customer to who refers the item.',
                'store'             => true,
                'readonly'          => true
            ],

            'product_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => 'The product (SKU) the receivable relates to.',
                'relation'          => ['sale_entry_id' => ['product_id']],
                'store'             => true,
                'readonly'          => true
            ],

            'price_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => 'The price the receivable relates to.',
                'relation'          => ['sale_entry_id' => ['price_id']],
                'store'             => true,
                'readonly'          => true
            ],

            'unit_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Unit price of the product related to the receivable.',
                'relation'          => ['sale_entry_id' => ['unit_price']],
                'store'             => true,
                'readonly'          => true
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/rate',
                'description'       => 'VAT rate to be applied.',
                'relation'          => ['sale_entry_id' => ['vat_rate']],
                'store'             => true,
                'readonly'          => true
            ],

            'qty' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Quantity of product.',
                'relation'          => ['sale_entry_id' => ['qty']],
                'store'             => true,
                'readonly'          => true
            ],

            'free_qty' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Free quantity of product, if any.',
                'relation'          => ['sale_entry_id' => ['free_qty']],
                'store'             => true,
                'readonly'          => true
            ],

            'discount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/rate',
                'description'       => 'Total amount of discount to apply, if any.',
                'relation'          => ['sale_entry_id' => ['discount']],
                'store'             => true,
                'readonly'          => true
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price of the receivable.',
                'function'          => 'calcTotal',
                'store'             => true,
                'readonly'          => true
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Final tax-included price of the receivable.',
                'function'          => 'calcPrice',
                'store'             => true,
                'readonly'          => true
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\accounting\invoice\Invoice',
                'description'       => 'Invoice the receivable is related to.',
                'ondelete'          => 'null'
            ],

            'invoice_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\InvoiceLine',
                'description'       => 'The invoice line that has been generated based on the item.',
                'ondelete'          => 'null'
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['sale_entry_id' => ['name', 'date', 'object_class'], 'product_id' => ['name']]);
        $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
        foreach($self as $id => $receivable) {
            if(($receivable['sale_entry_id']['object_class'] ?? '') == 'timetrack\Project') {
                $result[$id] = date($date_format, $receivable['sale_entry_id']['date']).' - '.$receivable['sale_entry_id']['name'];
            }
            else {
                $result[$id] = $receivable['product_id']['name'] ?? '';
            }
        }
        return $result;
    }

    public static function calcTotal($self) {
        $result = [];
        $self->read(['qty', 'unit_price', 'free_qty', 'discount']);
        foreach($self as $id => $receivable) {
            $result[$id] = $receivable['unit_price'] * (1.0 - $receivable['discount']) * ($receivable['qty'] - $receivable['free_qty']);
        }

        return $result;
    }

    public static function calcPrice($self) {
        $result = [];
        $self->read(['total', 'vat_rate']);
        $currency_decimal_precision = Setting::get_value('core', 'locale', 'currency.decimal_precision', 2);
        foreach($self as $id => $receivable) {
            $total = (float) $receivable['total'];
            $vat = (float) $receivable['vat_rate'];
            $result[$id] = round($total * (1.0 + $vat), $currency_decimal_precision);
        }

        return $result;
    }

    public static function canupdate($self, $values) {
        $self->read(['status']);
        foreach($self as $receivable) {
            if(array_key_exists('receivables_queue_id', $values)) {
                if($receivable['status'] !== 'pending') {
                    return ['receivables_queue_id' => ['not_allowed' => 'Queue can be modified only when status pending.']];
                }

                if(is_null($values['receivables_queue_id'])) {
                    return ['receivables_queue_id' => ['not_allowed' => 'A receivable must be linked to a queue.']];
                }
            }
        }

        return parent::canupdate($self, $values);
    }
}