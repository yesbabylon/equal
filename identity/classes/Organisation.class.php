<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;

class Organisation extends Identity {

    public static function getName() {
        return "Organisation";
    }

    public function getTable() {
        // force table name to use distinct tables and ID columns
        return 'identity_organisation';
    }

    public static function getDescription() {
        return "Organizations are the legal entities to which the ERP is dedicated. By convention, the main Organization uses ID 1.";
    }

    public static function getColumns() {
        return [
            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\IdentityType',
                'onupdate'          => 'onupdateTypeId',
                'default'           => 3,
                'description'       => 'Type of identity.'
            ],

            'type' => [
                'type'              => 'string',
                'default'           => 'C',
                'readonly'          => true
            ]
        ];
    }
}
