<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace purchase\supplier;

use identity\Identity;
use identity\Partner;

class Supplier extends Partner {

    public function getTable() {
        return 'purchase_supplier_supplier';
    }

    public static function getName() {
        return 'Supplier';
    }

    public static function getDescription() {
        return "A supplier is a company from which the organisation buys goods and services.";
    }

    public static function getColumns() {

        return [

            'relationship' => [
                'type'              => 'string',
                'default'           => 'supplier',
                'description'       => 'Force relationship to Supplier'
            ],

            'invoices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'purchase\accounting\invoice\Invoice',
                'foreign_field'     => 'customer_id',
                'description'       => 'Purchase invoices from the supplier.'
            ]

        ];
    }

    public static function onafterupdate($self, $values) {
        parent::onafterupdate($self, $values);

        $self->read(['partner_identity_id' => ['id', 'supplier_id']]);
        foreach($self as $id => $customer) {
            if(is_null($customer['partner_identity_id']['supplier_id'])) {
                Identity::id($customer['partner_identity_id']['id'])->update(['supplier_id' => $id]);
            }
        }
    }
}
