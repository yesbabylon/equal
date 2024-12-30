<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\customer;

use identity\Identity;

class Customer extends \identity\Partner {

    public function getTable() {
        return 'sale_customer_customer';
    }

    public static function getName() {
        return 'Customer';
    }

    public static function getDescription() {
        return "A customer is a partner with whom the company carries out commercial sales operations.";
    }

    public static function getColumns() {

        return [

            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "Rate class that applies to the customer.",
                'help'              => "The fare (rate) class allows for the automatic assignment of a price list or price calculation for the customer.",
                'default'           => 1,
                'readonly'          => true
            ],

            'customer_nature_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\CustomerNature',
                'description'       => 'Nature of the customer (map with rate classes).',
                'onupdate'          => 'onupdateCustomerNatureId'
            ],

            'customer_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\CustomerType',
                'description'       => "Type of customer (map with rate classes). Defaults to 'individual'.",
                'help'              => "If partner is a customer, it can be assigned a customer type",
                'default'           => 1,
                'onupdate'          => 'onupdateCustomerTypeId'
            ],

            'relationship' => [
                'type'              => 'string',
                'default'           => 'customer',
                'description'       => 'Force relationship to Customer'
            ],

            'address' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcAddress',
                'description'       => 'Main address from related Identity.'
            ],

            'ref_account' => [
                'type'              => 'string',
                'description'       => 'Arbitrary reference account number for identifying the customer in external accounting softwares.',
                'readonly'          => true
            ],

            'receivables_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\receivable\Receivable',
                'foreign_field'     => 'customer_id',
                'description'       => 'List receivables of the customer.'
            ],

            'sales_entries_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\SaleEntry',
                'foreign_field'     => 'customer_id',
                'description'       => 'List sales entries of the customer.'
            ],

            'bookings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Booking',
                'foreign_field'     => 'booking_id',
                'description'       => 'List bookings of the customer.'
            ],

            'products_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\Product',
                'foreign_field'     => 'customer_id',
                'description'       => 'List products of the customer.'
            ],

            'softwares_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\Software',
                'foreign_field'     => 'customer_id',
                'description'       => 'List softwares of the customer.'
            ],

            'services_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\service\Service',
                'foreign_field'     => 'customer_id',
                'description'       => 'List services of the customer.'
            ],

            'subscriptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\subscription\Subscription',
                'foreign_field'     => 'customer_id',
                'description'       => 'List subscriptions of the customer.'
            ],

            'invoices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\accounting\invoice\Invoice',
                'foreign_field'     => 'customer_id',
                'description'       => 'List invoices of the customer.'
            ],

            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\customer\Contact',
                'foreign_field'     => 'customer_id',
                'description'       => 'List contacts of the customer.'
            ],

            'customer_external_ref' => [
                'type'              => 'string',
                'description'       => 'External reference for the customer, if any.'
            ],

            'flag_latepayer' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Mark the customer as bad payer.'
            ],

            'flag_damage' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Mark the customer with a damage history.'
            ],

            'flag_nuisance' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Mark the customer with a disturbances history.'
            ]

        ];
    }

    public static function onupdateCustomerNatureId($self) {
        $self->read(['customer_nature_id' => ['rate_class_id', 'customer_type_id']]);
        foreach($self as $id => $customer) {
            if($customer['customer_nature_id']) {
                self::id($id)->update([
                        'rate_class_id'     => $customer['customer_nature_id']['rate_class_id'],
                        'customer_type_id'  => $customer['customer_nature_id']['customer_type_id']
                    ]);
            }
        }
    }

    public static function calcAddress($self) {
        $result = [];
        $self->read(['address_street', 'address_city']);
        foreach($self as $id => $customer) {
            $result[$id] = "{$customer['address_street']} {$customer['address_city']}";
        }
        return $result;
    }

    public static function onupdateCustomerTypeId($self) {
        $self->read(['customer_type_id']);

        foreach($self as $id => $customer) {
            // #memo - there is a strict equivalence between identity type and customer type (the only distinction is in the presentation)
            self::id($id)->update(['type_id' => $customer['customer_type_id']]);
        }
    }

    public static function onafterupdate($self, $values) {
        // this must be done after general sync (which creates an Identity if necessary, and prevents updating the `customer_id` of the target Identity)
        parent::onafterupdate($self, $values);

        $self->read(['partner_identity_id' => ['id', 'customer_id']]);
        foreach($self as $id => $customer) {
            if($customer['partner_identity_id']['customer_id'] != $id) {
                Identity::id($customer['partner_identity_id']['id'])->update(['customer_id' => $id]);
            }
        }
    }

    public static function onchange($self, $event, $values) {
        $result = parent::onchange($self, $event, $values);
        if(isset($event['type_id'])) {
            $result['customer_type_id'] = CustomerType::id($event['type_id'])->read(['id', 'name'])->first(true);
        }
        return $result;
    }
}
