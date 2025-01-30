<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\accounting\invoice;

use symbiose\setting\Setting;
use finance\accounting\AccountChartLine;
use finance\accounting\AccountingEntry;
use finance\accounting\AccountingJournal;
use sale\customer\Customer;
use sale\pay\Funding;
use sale\receivable\Receivable;

class Invoice extends \finance\accounting\Invoice {

    public static function getName() {
        return 'Sale invoice';
    }

    public static function getDescription() {
        return 'A sale invoice is a legal document issued after some goods have been sold to a customer.';
    }

    public static function getColumns() {

        return [

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => 'The organization that emitted the invoice.',
                'default'           => 1
            ],

            'status' => [
                'type'              => 'string',
                'description'       => 'Current status of the invoice.',
                'selection'         => [
                    'proforma',             // draft invoice (no number yet)
                    'invoice',              // final invoice (with unique number and accounting entries)
                    'cancelled'             // the invoice has been cancelled (through reversing entries)
                ],
                'default'           => 'proforma'
            ],

            'reversed_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\accounting\invoice\Invoice',
                'description'       => 'Credit note that was created for cancelling the invoice, if any.',
                'visible'           => ['status', '=', 'cancelled']
            ],

            'is_downpayment' => [
                'type'              => 'boolean',
                'description'       => 'Marks the invoice as a deposit invoice relating to a downpayment (funding).',
                'default'           => false
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Funding',
                'description'       => 'The funding related to the invoice.'
            ],

            'invoice_purpose' => [
                'type'              => 'string',
                'description'       => 'Is the invoice concerning a sale to a customer or a buy from a supplier.',
                'default'           => 'sell',
                'visible'           => false
            ],

            'invoice_number' => [
                'type'              => 'string',
                'description'       => 'Number of the invoice, according to organization logic.',
                'default'           => '[proforma]'
            ],

            'payment_reference' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcPaymentReference',
                'description'       => 'Message for identifying payments related to the invoice.',
                'store'             => true,
                'instant'           => true
            ],

            'emission_date' => [
                'type'              => 'datetime',
                'description'       => 'Reference date for computing the due date.',
                'help'              => 'This value can be changed while the invoice is `proforma`, but cannot be changed afterward (once emitted).',
                'default'           => function() { return time(); },
            ],

            'due_date' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => 'Deadline for the payment is expected, from payment terms.',
                'function'          => 'calcDueDate',
                'store'             => true,
                'instant'           => true
            ],

            'invoice_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\accounting\invoice\InvoiceLine',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Detailed lines of the invoice.',
                'ondetach'          => 'delete',
                'dependencies'      => ['total', 'price']
            ],

            'invoice_line_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\accounting\invoice\InvoiceLineGroup',
                'foreign_field'     => 'invoice_id',
                'description'       => 'Groups of lines of the invoice.',
                'ondetach'          => 'delete',
                'dependencies'      => ['total', 'price']
            ],

            /**
             * Specific Sale Invoice columns
             */

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => 'The counter party organization the invoice relates to.',
                'required'          => true
            ],

            'customer_ref' => [
                'type'              => 'string',
                'description'       => 'Reference that must appear on invoice (requested by customer).'
            ],

            'payment_terms_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\PaymentTerms',
                'description'       => 'The payment terms to apply to the invoice.',
                'default'           => 1
            ],

            'price_billed' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'function'          => 'calcPriceBilled',
                'usage'             => 'amount/money:2',
                'store'             => true,
                'description'       => "Final tax-included amount used for display (inverted for credit notes)."
            ]

        ];
    }

    public static function calcPriceBilled($self) {
        $result = [];
        $self->read(['invoice_type', 'price']);
        foreach($self as $id => $invoice) {
            $result[$id] = $invoice['invoice_type'] == 'invoice' ? $invoice['price'] : -$invoice['price'];
        }

        return $result;
    }


    public static function getPolicies(): array {
        return [
            'can-be-invoiced' => [
                'description' => 'Verifies that the proforma can be invoiced.',
                'function'    => 'policyCanBeInvoiced'
            ]
        ];
    }

    public static function policyCanBeInvoiced($self): array {
        $result = [];
        $self->read(['invoice_lines_ids']);
        foreach($self as $id => $invoice) {
            if(count($invoice['invoice_lines_ids']) === 0) {
                $result[$id] = false;
            }
        }

        return $result;
    }

    public static function getWorkflow() {
        return [
            'proforma' => [
                'description' => 'Draft invoice, still waiting to be completed and for customer approval.',
                'icon' => 'edit',
                'transitions' => [
                    'invoice' => [
                        'description' => 'Update the invoice status based on the `invoice` field.',
                        'help'        => 'The `invoice` field is set by a dedicated controller that manages invoice approval requests.',
                        'policies'    => [
                            'can-be-invoiced',
                        ],
                        'onbefore'  => 'onbeforeInvoice',
                        'onafter'   => 'onafterInvoice',
                        'status'    => 'invoice',
                    ],
                    'cancel-proforma' => [
                        'description' => 'Delete the proforma and set receivables statuses back to pending.',
                        'onafter' => 'onafterCancelProforma',
                        'status'  => 'proforma',
                    ]
                ],
            ],
            'invoice' => [
                'description' => 'Invoice can no longer be modified and can be sent to the customer.',
                'icon' => 'receipt_long',
                'transitions' => [
                    'cancel' => [
                        'description' => 'Set the invoice and receivables statuses as cancelled.',
                        'onafter' => 'onafterCancel',
                        'status' => 'cancelled',
                    ],
                    'cancel-keep-receivables' => [
                        'description' => 'Set the invoice status as cancelled and set receivables statuses back to pending.',
                        'onafter' => 'onafterCancelKeepReceivables',
                        'status' => 'cancelled',
                    ],
                ],
            ],
            'cancelled' => [
                'description' => 'The invoice was cancelled.',
                'icon' => 'cancel',
                'transitions' => [
                ],
            ],
        ];
    }

    public static function onchange($event, $values): array {
        $result = [];
        if(isset($event['customer_id'], $values['status']) && $values['status'] == 'proforma'){
            $customer = Customer::search(['id', '=', $event['customer_id']])
                ->read(['name'])
                ->first();

            $result['invoice_number'] = '[proforma]['.$customer['name'].']['.date('Y-m-d').']';
        }

        return $result;
    }

    public static function calcPaymentReference($self): array {
        $result = [];
        $self->read(['status', 'invoice_number']);
        foreach($self as $id => $invoice) {
            // #memo - prevent generating a payment reference for a proforma
            if($invoice['status'] == 'invoice') {
                // arbitrary value for balance (final) invoice
                $code_ref = 500;

                $result[$id] = self::computePaymentReference($code_ref, preg_replace('/\D/', '', $invoice['number']));
            }
        }

        return $result;
    }

    /**
     * Compute a Structured Reference using belgian SCOR (Structured COmmunication Reference) reference format.
     *
     * Note:
     *  format is aaa-bbbbbbb-XX
     *  where aaa is the prefix, bbbbbbb is the suffix, and XX is the control number, that must verify (aaa * 10000000 + bbbbbbb) % 97
     *  as 10000000 % 97 = 76
     *  we do (aaa * 76 + bbbbbbb) % 97
     */
    protected static function computePaymentReference($prefix, $suffix) {
        $a = intval($prefix);
        $b = intval($suffix);
        $control = ((76*$a) + $b ) % 97;
        $control = ($control == 0) ? 97 : $control;
        return sprintf("%3d%04d%03d%02d", $a, $b / 1000, $b % 1000, $control);
    }

    public static function calcDueDate($self): array {
        $result = [];
        $self->read(['emission_date', 'payment_terms_id' => ['delay_from', 'delay_count']]);
        foreach($self as $id => $invoice) {
            $result[$id] = strtotime('+1 month');

            if(!isset($invoice['emission_date'], $invoice['payment_terms_id']['delay_from'], $invoice['payment_terms_id']['delay_count'])) {
                continue;
            }

            $from = $invoice['payment_terms_id']['delay_from'];
            $delay = $invoice['payment_terms_id']['delay_count'];
            $emission_date = $invoice['emission_date'];

            switch($from) {
                case 'created':
                    $due_date = $emission_date + ($delay * 86400);
                    break;
                case 'next_month':
                default:
                    $due_date = strtotime(date('Y-m-t', $emission_date)) + ($delay * 86400);
                    break;
            }

            $result[$id] = $due_date;
        }

        return $result;
    }

    public static function onbeforeInvoice($self) {
        $self->read(['organisation_id']);
        // generate the accounting entries according to the invoices lines.
        $self->do('generate_accounting_entries');
        foreach($self as $id => $invoice) {
            $format = Setting::get_value('sale', 'invoice', 'sequence_format', '%2d{year}-%05d{sequence}', ['organisation_id' => $invoice['organisation_id']]);
            $year = Setting::get_value('sale', 'invoice', 'fiscal_year', date('Y'), ['organisation_id' => $invoice['organisation_id']]);
            $sequence = Setting::fetch_and_add('sale', 'invoice', 'sequence', 1, ['organisation_id' => $invoice['organisation_id']]);
            if($sequence) {
                $invoice_number = Setting::parse_format($format, [
                        'year'      => $year,
                        'org'       => $invoice['organisation_id'],
                        'sequence'  => $sequence
                    ]);
                self::id($id)->update(['invoice_number' => $invoice_number, 'due_date' => null]);
            }
        }
    }


    /**
     * Generate the fundings for a collection of invoices that just transitioned to "invoiced".
     * Fundings must be created here because due_date is set at invoice emission
    */
    public static function onafterInvoice($self) {
        try {
            // #memo - failing in emitting the fundings cannot interrupt the transition
            $self->do('create_funding');
        }
        catch(\Exception $e) {
            trigger_error("APP::error while creating invoices funding: {$e->getMessage()}", EQ_REPORT_ERROR);
        }
    }

    public static function onafterCancelProforma($self) {
        $self->read(['id']);
        foreach($self as $invoice) {
            $receivables_ids = Receivable::search([
                    ['status', '=', 'invoiced'],
                    ['invoice_id', '=', $invoice['id']],
                ])
                ->ids();

            Receivable::ids($receivables_ids)
                ->update([
                    'status'          => 'pending',
                    'invoice_id'      => null,
                    'invoice_line_id' => null
                ]);

            Invoice::id($invoice['id'])
                ->delete();
        }
    }

    public static function onafterCancel($self) {
        $self->read(['id']);
        foreach($self as $invoice) {
                $receivables_ids = Receivable::search([
                    ['status', '=', 'invoiced'],
                    ['invoice_id', '=', $invoice['id']],
                ])
                ->ids();

            Receivable::ids($receivables_ids)
                ->update(['status' => 'cancelled']);
        }

        $self->do('reverse');
    }

    public static function onafterCancelKeepReceivables($self) {
        $self->read(['id']);
        foreach($self as $invoice) {
            $receivables_ids = Receivable::search([
                    ['status', '=', 'invoiced'],
                    ['invoice_id', '=', $invoice['id']],
                ])
                ->ids();

            Receivable::ids($receivables_ids)
                ->update([
                    'status'          => 'pending',
                    'invoice_id'      => null,
                    'invoice_line_id' => null
                ]);
        }

        $self->do('reverse');
    }

    public static function getActions() {
        return [
            'reverse' => [
                'description'   => 'Creates a new invoice of type credit note to reverse invoice.',
                'help'          => 'Reversing an invoice can only be done when status is "invoice".',
                'policies'      => [],
                'function'      => 'doReverseInvoice'
            ],
            'create_funding' => [
                'description'   => 'Create the funding according to the invoice.',
                'policies'      => [],
                'function'      => 'doCreateFunding'
            ],
            'generate_accounting_entries' => [
                'description'   => 'Creates accounting entries according to  invoice lines.',
                'policies'      => [],
                'function'      => 'doGenerateAccountingEntries'
            ]
        ];
    }

    /**
     * Create new credit notes to reverse the invoices.
     */
    public static function doReverseInvoice($self) {
        $self->read([
                'status',
                'invoice_type',
                'reversed_invoice_id',
                'organisation_id',
                'customer_id',
                'is_downpayment',
                'invoice_line_groups_ids' => [
                    'name',
                    'invoice_lines_ids' => [
                        'product_id',
                        'price_id',
                        'qty',
                        'free_qty',
                        'discount',
                        'downpayment_invoice_id',
                        'vat_rate',
                        'unit_price',
                        'total',
                        'price'
                    ]
                ]
            ]);

        foreach($self as $invoice) {
            if( $invoice['status'] !== 'cancelled'
                || $invoice['invoice_type'] !== 'invoice'
                || isset($invoice['reversed_invoice_id']) ) {
                continue;
            }

            $reversed_invoice = Invoice::create([
                    'invoice_type'        => 'credit_note',
                    'status'              => 'proforma',
                    'emission_date'       => time(),
                    'organisation_id'     => $invoice['organisation_id'],
                    'customer_id'         => $invoice['customer_id'],
                    'is_downpayment'      => $invoice['is_downpayment'],
                    'reversed_invoice_id' => $invoice['id']
                ])
                ->read(['id'])
                ->first(true);
            foreach($invoice['invoice_line_groups_ids'] as $invoice_line_group) {
                $reversed_group = InvoiceLineGroup::create([
                        'name'       => $invoice_line_group['name'],
                        'invoice_id' => $reversed_invoice['id']
                    ])
                    ->first(true);

                foreach($invoice_line_group['invoice_lines_ids'] as $line) {
                    InvoiceLine::create([
                            'description'            => $line['description'],
                            'invoice_id'             => $reversed_invoice['id'],
                            'invoice_line_group_id'  => $reversed_group['id'],
                            'product_id'             => $line['product_id'],
                            'price_id'               => $line['price_id'],
                            'qty'                    => $line['qty'],
                            'free_qty'               => $line['free_qty'],
                            'discount'               => $line['discount'],
                            'downpayment_invoice_id' => $line['downpayment_invoice_id']
                        ])
                        ->update([
                            'vat_rate'   => $line['vat_rate'],
                            'unit_price' => $line['unit_price'],
                            'total'      => $line['total'],
                            'price'      => $line['price']
                        ]);
                }
            }

            if(in_array($invoice['payment_status'], ['pending', 'overdue'])) {
                // no payment was received yet : mark both invoices as balanced (no transaction required)
                Invoice::id($reversed_invoice['id'])->update(['payment_status' => 'balanced']);
                Invoice::id($invoice['id'])->update(['payment_status' => 'balanced']);
            }
            else {
                // #todo: Alert finance_accounting - reimbursement needed
            }

            Invoice::id($invoice['id'])
                ->update(['reversed_invoice_id' => $reversed_invoice['id']]);
        }
    }

    /**
     * Create the fundings according to the invoices.
     */
    public static function doCreateFunding($self) {
        $self->read(['id', 'price', 'payment_reference', 'due_date', 'funding_id']);

        foreach($self as $invoice) {
            $funding = Funding::create([
                    'description'         => 'Sold Invoice',
                    'invoice_id'          => $invoice['id'],
                    'due_amount'          => round($invoice['price'], 2),
                    'is_paid'             => false,
                    'funding_type'        => 'invoice',
                    'payment_reference'   => $invoice['payment_reference'],
                    'due_date'            => $invoice['due_date']
                ])
                ->first();

            Invoice::id($invoice['id'])
                ->update(['funding_id' => $funding['id']]);
        }
    }

    /**
     * Create the accounting entries according tp invoices lines.
     */
    public static function doGenerateAccountingEntries($self) {
        $self->read(['id', 'organisation_id', 'accounting_entries_ids']);
        foreach($self as $id => $invoice) {
            try {
                // remove previously created entries, if any (there should be none)
                AccountingEntry::ids($invoice['accounting_entries_ids'])->delete(true);
                // generate accounting entries
                $accounting_entries = self::computeAccountingEntries($id);

                if(empty($accounting_entries)) {
                    throw new \Exception('invalid_invoice', EQ_ERROR_UNKNOWN);
                }

                $journal = AccountingJournal::search([['organisation_id', '=', $invoice['organisation_id']], ['journal_type', '=', 'SALE']])->read(['id'])->first();

                if(!$journal) {
                    throw new \Exception('missing_mandatory_journal', EQ_ERROR_INVALID_CONFIG);
                }

                // create new entries objects and assign to the sale journal
                foreach($accounting_entries as $entry) {
                    $entry['journal_id'] = $journal['id'];
                    AccountingEntry::create($entry);
                }
            }
            catch(\Exception $e) {
                trigger_error($e->getMessage(), EQ_REPORT_ERROR);
            }
        }
    }

    private static function computeAccountingEntries($invoice_id) {
        $result = [];

        // retrieve specific accounts numbers
        $account_sales = Setting::get_value('sale', 'invoice', 'account_sales', 'not_found');
        $account_sales_taxes = Setting::get_value('sale', 'invoice', 'account_sales-taxes', 'not_found');
        $account_trade_debtors = Setting::get_value('sale', 'invoice', 'account_trade-debtors', 'not_found');
        // $account_downpayments = Setting::get_value('sale', 'invoice', 'account_downpayment', 'not_found');

        $accountSales = AccountChartLine::search(['code', '=', $account_sales])->read(['id', 'description'])->first();
        $accountSalesTaxes = AccountChartLine::search(['code', '=', $account_sales_taxes])->read(['id', 'description'])->first();
        $accountTradeDebtors = AccountChartLine::search(['code', '=', $account_trade_debtors])->read(['id', 'description'])->first();
        // $accountDownpayments = AccountChartLine::search(['code', '=', $account_downpayments])->first();

        try {
            if(!$accountSales) {
                throw new \Exception('APP::missing mandatory account sales', EQ_ERROR_INVALID_CONFIG);
            }

            if(!$accountSalesTaxes) {
                throw new \Exception('APP::missing mandatory account sales taxes', EQ_ERROR_INVALID_CONFIG);
            }

            if(!$accountTradeDebtors) {
                throw new \Exception('APP::missing mandatory account trade debtors', EQ_ERROR_INVALID_CONFIG);
            }

            $invoice = self::id($invoice_id)->read(['id', 'price', 'invoice_type', 'invoice_lines_ids'])->first();

            if(!$invoice) {
                throw new \Exception('ORM::unknown invoice ['.$invoice_id.']', EQ_ERROR_INVALID_PARAM);
            }

            $map_accounting_entries = [];

            // fetch invoice lines
            $lines = InvoiceLine::ids($invoice['invoice_lines_ids'])
                ->read([
                    'total', 'price',
                    'price_id' => [
                        'accounting_rule_id' => [
                            'vat_rule_id' => ['account_id'],
                            'accounting_rule_line_ids' => ['share', 'account_id']
                        ]
                    ]
                ]);

            foreach($lines as $lid => $line) {

                if(!isset($line['price_id'])) {
                    throw new \Exception("APP::invoice line [{$lid}] without price for invoice [{$invoice_id}]", EQ_ERROR_UNKNOWN);
                }

                if(!isset($line['price_id']['accounting_rule_id'])) {
                    throw new \Exception("APP::invoice line [{$lid}] without accounting rule for invoice [{$invoice_id}]", EQ_ERROR_UNKNOWN);
                }

                if(!isset($line['price_id']['accounting_rule_id']['accounting_rule_line_ids'])
                    || !count($line['price_id']['accounting_rule_id']['accounting_rule_line_ids'])) {
                    throw new \Exception("APP::invoice line [{$lid}] without accounting rule lines for invoice [{$invoice_id}]", EQ_ERROR_UNKNOWN);
                }

                if(!isset($line['price_id']['accounting_rule_id']['vat_rule_id'])) {
                    throw new \Exception("APP::invoice line [{$lid}] without VAT rule for invoice [{$invoice_id}]", EQ_ERROR_UNKNOWN);
                }

                // #memo - Only one VAT rate can be applied per line: we should only retrieve the associated account.
                $vat_account_id = $line['price_id']['accounting_rule_id']['vat_rule_id']['account_id'];

                if(!isset($map_accounting_entries[$vat_account_id])) {
                    $map_accounting_entries[$vat_account_id] = 0.0;
                }

                $vat_amount = ($line['price'] < 0 ? -1.0 : 1.0) * (abs($line['price']) - abs($line['total']));
                $map_accounting_entries[$vat_account_id] += $vat_amount;

                $remaining_amount = $line['total'];

                $count_rules = count($line['price_id']['accounting_rule_id']['accounting_rule_line_ids']);
                $i = 1;

                foreach($line['price_id']['accounting_rule_id']['accounting_rule_line_ids'] as $rule_line_id => $ruleLine) {
                    if(!isset($ruleLine['account_id'], $ruleLine['share']) || $ruleLine['account_id'] <= 0 || $ruleLine['share'] <= 0) {
                        throw new \Exception("APP::invalid accounting rule line [{$rule_line_id}] (missing account_id or share) for invoice line [{$lid}] of invoice [{$invoice_id}]", EQ_ERROR_UNKNOWN);
                    }

                    // last line
                    if($i == $count_rules) {
                        $amount = $remaining_amount;
                    }
                    else {
                        $amount = round($line['total'] * $ruleLine['share'], 2);
                        $remaining_amount -= $amount;
                    }

                    if(!isset($map_accounting_entries[$ruleLine['account_id']])) {
                        $map_accounting_entries[$ruleLine['account_id']] = 0.0;
                    }

                    $map_accounting_entries[$ruleLine['account_id']] += $amount;

                    ++$i;
                }
            }

            // create credit lines on sales & taxes accounts
            foreach($map_accounting_entries as $account_id => $amount) {
                $account = AccountChartLine::id($account_id)->read(['description'])->first();
                $result[] = [
                        'name'          => $account['description'],
                        'has_invoice'   => true,
                        'invoice_id'    => $invoice_id,
                        'account_id'    => $account_id,
                        'debit'         => ($invoice['invoice_type'] == 'credit_note')?$amount:0.0,
                        'credit'        => ($invoice['invoice_type'] == 'invoice')?$amount:0.0
                    ];
            }

            // create a debit line on account "trade debtors"
            $result[] = [
                    'name'          => $accountTradeDebtors['description'],
                    'has_invoice'   => true,
                    'invoice_id'    => $invoice_id,
                    'account_id'    => $accountTradeDebtors['id'],
                    'debit'         => ($invoice['invoice_type'] == 'invoice')?$invoice['price']:0.0,
                    'credit'        => ($invoice['invoice_type'] == 'credit_note')?$invoice['price']:0.0
                ];

        }
        catch(\Exception $e) {
            // log error
            trigger_error($e->getMessage(), EQ_REPORT_ERROR);
            // force returning an empty array
            $result = [];
        }

        return $result;
    }

    /**
     * Check whether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager   $om         ObjectManager instance.
     * @param  array                      $ids        List of objects identifiers.
     * @param  array                      $values     Associative array holding the new values to be assigned.
     * @param  string                     $lang       Language in which multilang fields are being updated.
     * @return array                      Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $ids, $values, $lang = 'en') {
        $res = $om->read(self::getType(), $ids, ['status']);

        if($res > 0) {
            foreach($res as $id => $invoice) {
                // only allow editable fields
                if($invoice['status'] != 'proforma') {
                    // editable fields for sale\accounting\invoice\Invoice
                    $editable_fields = ['payment_status', 'customer_ref', 'funding_id', 'reversed_invoice_id'];

                    if( count(array_diff(array_keys($values), $editable_fields)) ) {
                    //    return ['status' => ['non_editable' => "Invoice can only be updated while its status is proforma ({$id})."]];
                    }
                }
            }
        }
        return parent::canupdate($om, $ids, $values, $lang);
    }

    /**
     * Check whether the invoice can be deleted.
     *
     * @param  \equal\orm\ObjectManager    $om         ObjectManager instance.
     * @param  array                       $ids       List of objects identifiers.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $ids) {
        $res = $om->read(get_called_class(), $ids, ['status']);

        if($res > 0) {
            foreach($res as $id => $invoice) {
                if($invoice['status'] != 'proforma') {
                    return ['status' => ['non_removable' => 'Invoice can only be deleted while its status is proforma.']];
                }
            }
        }
        return parent::candelete($om, $ids);
    }
}
