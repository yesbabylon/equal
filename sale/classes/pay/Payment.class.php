<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\pay;

use equal\orm\Model;
use finance\bank\BankStatementLine;
class Payment extends Model {

    public static function getDescription() {
        return 'A payment is an amount of money that was paid by a customer for a product or service.'
            .' It can origin form the cashdesk or a bank transfer. If it is from a bank transfer it is linked to a bank statement line.';
    }

    public static function getColumns() {

        return [

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => "The customer to whom the payment relates."
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount paid (whatever the origin).',
                'dependents'        => ['funding_id' => ['is_paid', 'paid_amount']]
            ],

            'communication' => [
                'type'              => 'string',
                'description'       => "Message from the payer.",
            ],

            'receipt_date' => [
                'type'              => 'datetime',
                'description'       => "Time of reception of the payment.",
                'default'           => time()
            ],

            'payment_origin' => [
                'type'              => 'string',
                'selection'         => [
                    'cashdesk',             // money was received at the cashdesk
                    'bank'                  // money was received on a bank account
                ],
                'description'       => "Origin of the received money.",
                'default'           => 'bank'
            ],

            'payment_method' => [
                'type'              => 'string',
                'selection'         => [
                    'cash',                 // cash money
                    'bank_card',            // electronic payment with bank (or credit) card
                    'voucher',              // gift or coupon
                    'wire_transfer'         // transfer between bank account
                ],
                'description'       => "The method used for payment at the cashdesk.",
                'visible'           => ['payment_origin', '=', 'cashdesk'],
                'default'           => 'cash'
            ],

            'operation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pos\Operation',
                'description'       => 'The operation the payment relates to.',
                'visible'           => ['payment_origin', '=', 'cashdesk']
            ],

            'statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\bank\BankStatementLine',
                'description'       => 'The bank statement line the payment relates to.',
                'visible'           => ['payment_origin', '=', 'bank']
            ],

            'voucher_ref' => [
                'type'              => 'string',
                'description'       => 'The reference of the voucher the payment relates to.',
                'visible'           => [ ['payment_origin', '=', 'cashdesk'], ['payment_method', '=', 'voucher'] ]
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Funding',
                'description'       => 'The funding the payment relates to, if any.',
                'onupdate'          => 'onupdateFundingId'
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\accounting\invoice\Invoice',
                'description'       => 'The invoice targeted by the payment, if any.',
                'domain'            => ['status', '=', 'invoice']
            ],

            'is_exported' => [
                'type'              => 'boolean',
                'description'       => 'Mark the payment as exported (part of an export to elsewhere).',
                'default'           => false
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'paid'
                ],
                'description'       => 'Current status of the payment.',
                'default'           => 'paid'
            ]

        ];
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['payment_origin'])) {
            switch($event['payment_origin']) {
                case 'cashdesk':
                    $result['statement_line_id'] = null;
                    break;
                case 'bank':
                    $result['payment_method'] = 'cash';
                    $result['operation_id'] = null;
                    $result['voucher_ref'] = null;
                    break;
            }
        }

        if(isset($event['funding_id'])) {
            $funding = Funding::id($event['funding_id'])
                ->read(['type', 'due_amount', 'invoice_id' => ['customer_id' => ['name']]])
                ->first();

            if(!is_null($funding)) {
                if($funding['funding_type'] == 'invoice' && isset($funding['invoice_id']['customer_id']))  {
                    $result['customer_id'] = [
                        'id'   => $funding['invoice_id']['customer_id']['id'],
                        'name' => $funding['invoice_id']['customer_id']['name']
                    ];
                }

                if(isset($values['amount']) && $values['amount'] > $funding['due_amount']) {
                    $result['amount'] = $funding['due_amount'];
                }
            }
        }

        return $result;
    }

    /**
     * Assign customer_id and invoice_id from invoice relating to funding, if any.
     * Force recomputing of target funding computed fields (is_paid and paid_amount).
     */
    public static function onupdateFundingId($self, $values) {
        trigger_error("ORM::calling sale\pay\Payment::onupdateFundingId", QN_REPORT_DEBUG);

        $funding_fields = [
            'funding_type',
            'due_amount',
            'invoice_id' => ['customer_id'],
        ];

        $self->read(['funding_id', 'customer_id', 'funding_id' => $funding_fields, 'amount', 'statement_line_id']);
        foreach($self as $id => $payment) {
            if($payment['funding_id']) {
                // make sure a customer_id is assigned to the payment
                if(is_null($payment['customer_id']) && isset($payment['funding_id']['invoice_id'])) {
                    self::id($id)->update([
                        'customer_id' => $payment['funding_id']['invoice_id']['customer_id'],
                        'invoice_id'  => $payment['funding_id']['invoice_id']['id'],
                    ]);
                }

                self::id($id)->update(['is_paid' => null, 'paid_amount' => null]);
                $fundings_ids[] = $payment['funding_id'];
            }
        }

        Funding::ids($fundings_ids)->read(['is_paid', 'paid_amount']);
    }

    public static function canupdate($self, $values) {
        $self->read(['state', 'is_exported', 'payment_origin', 'amount', 'statement_line_id' => ['amount', 'remaining_amount']]);
        foreach($self as $payment) {
            if($payment['is_exported']) {
                return ['is_exported' => ['non_editable' => 'Once exported a payment can no longer be updated.']];
            }

            $payment_origin = $values['payment_origin'] ?? $payment['payment_origin'];
            if($payment_origin == 'bank' && isset($values['amount'])) {
                $statement_line = $payment['statement_line_id'];
                if(isset($values['statement_line_id'])) {
                    $statement_line = BankStatementLine::id($values['statement_line_id'])
                        ->read(['amount', 'remaining_amount'])
                        ->first();
                }

                $sign_line = intval($statement_line['amount'] > 0) - intval($statement_line['amount'] < 0);
                $sign_payment = intval($values['amount'] > 0) - intval($values['amount'] < 0);

                // #memo - we prevent creating payment that do not decrease the remaining amount
                if($sign_line != $sign_payment) {
                    return ['amount' => ['incompatible_sign' => "Payment amount ({$values['amount']}) and statement line amount ({$statement_line['amount']}) must have the same sign."]];
                }

                // #memo - when state is still draft, it means that reconcile is made manually
                if($payment['state'] == 'draft') {
                    if(round($statement_line['amount'], 2) < 0) {
                        if(round($statement_line['remaining_amount'] - $values['amount'], 2) > 0) {
                            return ['amount' => ['excessive_amount' => "Payment amount ({$values['amount']}) cannot be higher than statement line remaining amount ({$statement_line['remaining_amount']}) (err#1)."]];
                        }
                    }
                    else {
                        if(round($statement_line['remaining_amount'] - $values['amount'], 2) < 0) {
                            return ['amount' => ['excessive_amount' => "Payment amount ({$values['amount']}) cannot be higher than statement line remaining amount ({$statement_line['remaining_amount']}) (err#2)."]];
                        }
                    }
                }
                else  {
                    if(round($statement_line['amount'], 2) < 0) {
                        if(round($statement_line['remaining_amount'] + $payment['amount'] - $values['amount'], 2) > 0) {
                            return ['amount' => ['excessive_amount' => "Payment amount ({$values['amount']}) cannot be higher than statement line remaining amount ({$statement_line['remaining_amount']}) (err#3)."]];
                        }
                    }
                    else {
                        if(round($statement_line['remaining_amount'] + $payment['amount'] - $values['amount'], 2) < 0) {
                            return ['amount' => ['excessive_amount' => "Payment amount ({$values['amount']}) cannot be higher than statement line remaining amount ({$statement_line['remaining_amount']}) (err#4)."]];
                        }
                    }
                }
            }
        }

        return parent::canupdate($self, $values);
    }

    public static function ondelete($self) {
        $self->read(['statement_line_id', 'funding_id']);
        foreach($self as $payment) {
            BankStatementLine::id($payment['statement_line_id'])->update(['status' => 'pending']);
            Funding::id($payment['funding_id'])->update(['is_paid' => false]);
        }

        return parent::ondelete($self);
    }

    public static function candelete($self) {
        $self->read(['status']);
        foreach($self as $payment) {
            if($payment['status'] == 'paid') {
                return ['status' => ['non_removable' => 'Paid payments cannot be removed.']];
            }
        }

        return parent::candelete($self);
    }
}
