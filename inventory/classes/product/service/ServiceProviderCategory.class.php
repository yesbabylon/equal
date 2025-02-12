<?php

namespace inventory\product\service;

use equal\orm\Model;

class ServiceProviderCategory extends Model {

    public static function getColumns()
    {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => '',
                'required'          => true,
                'multilang'         => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short presentation of the Service Provider Category.',
                'multilang'         => true
            ],

            'services_providers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\product\service\ServiceProvider',
                'foreign_field'     => 'service_provider_category_id',
                'description'       => 'Service provider that belongs to the category.'
            ],
        ];
    }
}
