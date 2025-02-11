<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;

class User extends \core\User {

    public static function getName() {
        return 'User';
    }

    public static function getColumns() {
        return [

            'identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'domain'            => ['type', '=', 'IN'],
                'description'       => 'The contact related to the user.',
                'dependencies'      => ['name']
            ],

            'setting_values_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\setting\SettingValue',
                'foreign_field'     => 'user_id',
                'description'       => 'List of settings that relate to the user.'
            ],

            'owner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => "The organization the user relates to (defaults to current).",
                'default'           => 1
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => 'The organisation the user belongs to.',
                'default'           => 1
            ]

        ];
    }

    public static function onafterupdate($self, $values) {
        parent::onafterupdate($self, $values);

        $self->read(['identity_id' => ['id', 'user_id']]);
        foreach($self as $id => $user) {
            if(is_null($user['identity_id']['user_id'])) {
                Identity::id($user['identity_id']['id'])->update(['user_id' => $id]);
            }
        }
    }

}