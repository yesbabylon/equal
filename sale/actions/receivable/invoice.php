<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\accounting\invoice\Invoice;
use sale\accounting\invoice\InvoiceLine;
use sale\accounting\invoice\InvoiceLineGroup;
use sale\receivable\Receivable;

list($params, $providers) = eQual::announce([
    'description'   => 'Invoice one or more receivables. Fill in for specific invoice or leave empty to create a new one.',
    'help'          => 'A default invoice can be selected, all receivables from that invoice\'s customer will be added to it.',
    'params'        => [
        'id' =>  [
            'description'       => 'identifier of the targeted receivable.',
            'type'              => 'integer',
            'default'           => 0
        ],

        'ids' =>  [
            'description'       => 'Identifiers of the targeted receivables.',
            'type'              => 'one2many',
            'foreign_object'    => 'sale\receivable\Receivable',
            'default'           => []
        ],

        'invoice_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\accounting\invoice\Invoice',
            'description'       => 'Proforma will be created (leave empty to create a new one).',
            'domain'            => ['status', '=', 'proforma'],
        ],

        'invoice_line_group_name' =>  [
            'description'       => 'Label for grouping on the invoice (leave empty for preset).',
            'type'              => 'string'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

if(empty($params['ids'])) {
    if(!isset($params['id']) || $params['id'] <= 0) {
        throw new Exception('receivable_invalid_id', EQ_ERROR_INVALID_PARAM);
    }

    $params['ids'][] = $params['id'];
}

$receivables = Receivable::search([
        ['id', 'in', $params['ids']],
        ['status', '=', 'pending']
    ])
    ->read([
        'id',
        'name',
        'description',
        'invoice_group',
        'customer_id',
        'product_id' => ['id', 'name'],
        'price_id',
        'unit_price',
        'vat_rate',
        'qty',
        'free_qty',
        'discount'
    ])
    ->get();

if(count($params['ids']) !== count($receivables)) {
    throw new Exception('unknown_receivable', QN_ERROR_UNKNOWN_OBJECT);
}

$default_invoice = null;
if(isset($params['invoice_id'])) {
    $default_invoice = Invoice::id($params['invoice_id'])
        ->read(['id', 'status', 'customer_id'])
        ->first();

    if(!isset($default_invoice)) {
        throw new Exception('unknown_invoice', QN_ERROR_UNKNOWN_OBJECT);
    }
    elseif($default_invoice['status'] !== 'proforma') {
        throw new Exception('invoice_must_be_proforma', QN_ERROR_INVALID_PARAM);
    }
}

foreach($receivables as $receivable) {
    $invoice = null;
    if(isset($default_invoice) && $receivable['customer_id'] === $default_invoice['customer_id']) {
        $invoice = $default_invoice;
    }
    else {
        $invoice = Invoice::search([
                ['customer_id', '=', $receivable['customer_id']],
                ['status', '=', 'proforma']
            ])
            ->read(['status'])
            ->first();

        if(!isset($invoice)) {
            $invoice = Invoice::create([
                    'customer_id' => $receivable['customer_id']
                ])
                ->first();
        }
    }

    $invoice_line_group_name = 'Additional Services ('.date('Y-m-d').')';

    if(isset($receivable['invoice_group'])) {
        $invoice_line_group_name = $receivable['invoice_group'];
    }

    if($params['invoice_line_group_name']) {
        $invoice_line_group_name = $params['invoice_line_group_name'];
    }

    $invoice_line_group = InvoiceLineGroup::search([
            ['invoice_id', '=', $invoice['id']],
            ['name', '=', $invoice_line_group_name]
        ])
        ->read(['id'])
        ->first();

    if(!isset($invoice_line_group)) {
        $invoice_line_group = InvoiceLineGroup::create([
                'invoice_id' => $invoice['id'],
                'name'       => $invoice_line_group_name
            ])
            ->first();
    }

    $invoice_line = InvoiceLine::create([
            //#memo - force name to receivable name instead of computed value
            'name'                  => $receivable['name'],
            'description'           => implode(' - ', array_filter([$receivable['product_id']['name'], $receivable['product_id']['description']])),
            'invoice_line_group_id' => $invoice_line_group['id'],
            'invoice_id'            => $invoice['id'],
            'product_id'            => $receivable['product_id']['id'],
            'price_id'              => $receivable['price_id'],
            'unit_price'            => $receivable['unit_price'],
            'vat_rate'              => $receivable['vat_rate'],
            'qty'                   => $receivable['qty'],
            'free_qty'              => $receivable['free_qty'],
            'discount'              => $receivable['discount'],
            'receivable_id'         => $receivable['id']
        ])
        ->do('reset_invoice_prices')
        ->first();

    Receivable::id($receivable['id'])
        ->update([
            'invoice_id'      => $invoice['id'],
            'invoice_line_id' => $invoice_line['id'],
            'status'          => 'invoiced'
        ]);
}

$context->httpResponse()
        ->status(204)
        ->send();
