<?php
/*
    This file is part of the eQual framework <http://www.github.com/cedricfrancoys/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2021
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory;

use equal\orm\Model;

class Product extends Model {

    public static function getDescription()
    {
        return "Products are softwares or projects that are either owned by the company or by a Customer.";
    }
    public static function getColumns()
    {
        return [
            'name' => [
                'type'              => 'string',
                'unique'            => true,
                'description'       => 'Name of the product.',
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short presentation of the product.'
            ],

            'is_internal' =>[
                'type'              => 'boolean',
                'description'       => 'The product is internal.',
                'help'              => "Internal products are used by the company. Information relating to external products are kept so that the company is work on those.",
                'default'           => false
            ],

            'customer_id'=> [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\sale\customer\Customer',
                'description'       => 'Customer of the product.',
                'visible'           => ['is_internal','=', false]
            ],

            'servers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\server\Server',
                'foreign_field'     => 'product_id',
                'ondetach'          => 'delete',
                'description'       => 'Server used by product.'
            ],

            'services_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\service\Service',
                'foreign_field'     => 'product_id',
                'ondetach'          => 'delete',
                'description'       => 'Services used by product.'
            ],


        ];
    }

    public static function onchange($event) {
        $result = [];

        if(isset($event['is_internal'])){
            $result['customer_id']= '';
        }
        return $result;
    }
}
