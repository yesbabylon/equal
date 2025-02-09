<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;
use equal\orm\Model;

class Partner extends Model {

    public static function getName() {
        return 'Partner';
    }

    public static function getDescription() {
        return "Partner is an entity that describes a relationship (contact, employee, customer, supplier, ...) between two Identities : an Owner identity (often, but not necessarily, the company) and a Partner identity.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'relation'          => ['partner_identity_id' => 'name'],
                'store'             => true,
                'instant'           => true,
                'description'       => 'The display name of the partner (related organisation name).',
                'generation'        => 'generateName'
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => 'Organisation of the current installation the partner belongs to (defaults to current).',
                'default'           => 1
            ],

            'is_internal' => [
                'type'              => 'boolean',
                'description'       => 'The partnership relates to (one of) the organization(s) from the current installation.',
                'default'           => true
            ],

            'owner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'The identity which the targeted identity is partner of.',
                'visible'           => ['is_internal', '=', false],
                'default'           => 1
            ],

            'partner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'dependents'        => ['name'],
                'description'       => 'The targeted identity (the partner).'
            ],

            'relationship' => [
                'type'              => 'string',
                'selection'         => [
                    'user',
                    'contact',
                    'employee',
                    'customer',
                    'supplier'
                ],
                'description'       => 'The kind of partnership that exists between the identities.'
            ],

            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\IdentityType',
                'dependents'        => ['type'],
                'default'           => 1,
                'description'       => 'Type of identity.',
                'help'              => 'Default value is Individual.'
            ],

            'type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'store'             => true,
                'instant'           => true,
                'readonly'          => true,
                'description'       => 'Code of the type of identity.',
                'relation'          => ['type_id' => 'code']
            ],

            'has_vat' => [
                'type'              => 'boolean',
                'description'       => 'Does the organization have a VAT number?',
                'default'           => false,
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],

            'vat_number' => [
                'type'              => 'string',
                'description'       => 'Value Added Tax identification number, if any.',
                'visible'           => [ ['has_vat', '=', true] ]
            ],

            'legal_name' => [
                'type'              => 'string',
                'description'       => 'Full name of the Identity.',
                'visible'           => [ ['type', '<>', 'IN'] ],
                'dependents'        => ['name']
            ],

            'firstname' => [
                'type'              => 'string',
                'description'       => "Full name of the contact (must be a person, not a role).",
                'visible'           => ['type', '=', 'IN'],
                'dependents'        => ['name']
            ],

            'lastname' => [
                'type'              => 'string',
                'description'       => 'Reference contact surname.',
                'visible'           => ['type', '=', 'IN'],
                'dependents'        => ['name']
            ],

            'gender' => [
                'type'              => 'string',
                'selection'         => ['M' => 'Male', 'F' => 'Female', 'X' => 'Non-binary'],
                'description'       => 'Reference contact gender.',
                'visible'           => ['type', '=', 'IN']
            ],

            'title' => [
                'type'              => 'string',
                'selection'         => ['Dr' => 'Doctor', 'Ms' => 'Miss', 'Mrs' => 'Misses', 'Mr' => 'Mister', 'Pr' => 'Professor'],
                'description'       => 'Reference contact title.',
                'visible'           => ['type', '=', 'IN']
            ],

            'date_of_birth' => [
                'type'              => 'date',
                'description'       => 'Date of birth.',
                'visible'           => ['type', '=', 'IN']
            ],

            'lang_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\Lang',
                'description'       => "Preferred language of the identity.",
                'default'           => 1,
                'onupdate'          => 'onupdateLangId'
            ],

            'address_street' => [
                'type'              => 'string',
                'description'       => 'Street and number.'
            ],

            'address_dispatch' => [
                'type'              => 'string',
                'description'       => 'Optional info for mail dispatch (apartment, box, floor, ...).'
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => 'City.'
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => 'Postal code.'
            ],

            'address_state' => [
                'type'              => 'string',
                'description'       => 'State or region.'
            ],

            'address_country' => [
                'type'              => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'Country.',
                'default'           => 'BE'
            ],

            'email' => [
                'type'              => 'string',
                'usage'             => 'email',
                'onupdate'          => 'onupdateEmail',
                'description'       => "Identity main email address."
            ],

            'phone' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'onupdate'          => 'onupdatePhone',
                'description'       => "Identity secondary phone number (mobile or landline)."
            ],

            'mobile' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'onupdate'          => 'onupdateMobile',
                'description'       => "Identity mobile phone number."
            ],

            'fax' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'description'       => "Identity main fax number."
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Is the customer still active?",
                'help'              => "When a customer is not marked as active, it is not displayed amongst the choices when selecting a Customer. However, it is still visible in the list of Customers, and its related informations and documents remain available.",
                'default'           => true
            ]

        ];
    }

    /**
     * Signature for single object change from views.
     *
     * @param  Array    $event     Associative array holding changed fields as keys, and their related new values.
     * @param  Array    $values    Copy of the current (partial) state of the object (fields depend on the view).
     * @return Array    Associative array mapping fields with their resulting values.
     */
    public static function onchange($self, $event, $values) {
        $result = [];
        if(isset($event['type_id'])) {
            $type = IdentityType::id($event['type_id'])->read(['code'])->first();
            if($type) {
                $result['type'] = $type['code'];
            }
            if($event['type_id'] > 1) {
                $result['firstname'] = '';
                $result['lastname'] = '';
            }
        }
        return $result;
    }

    /**
     * Upon creation of the Partner, copy fields values from the identity.
     */
    public static function oncreate($self, $values) {
        if(isset($values['partner_identity_id'])) {
            $fields = [
                    'type_id','has_vat','vat_number','legal_name','firstname','lastname','lang_id',
                    'email','phone','mobile','fax',
                    'address_street','address_dispatch','address_zip',
                    'address_city','address_state','address_country'
                ];
            $identity = Identity::id($values['partner_identity_id'])
                ->read($fields)
                ->first();
            $map_fields = [];
            foreach($fields as $field) {
                if(!isset($values[$field])) {
                    $map_fields[$field] = $identity[$field];
                }
            }
            $self->update($map_fields);
        }
    }

    /**
     * Upon update of the Partner, update related identity or create one if partner_identity_id is not set.
     */
    public static function onafterupdate($self, $values) {
        $common_fields = [
                'type_id','has_vat','vat_number','legal_name','firstname','lastname','lang_id',
                'email','phone','mobile','fax',
                'address_street','address_dispatch','address_zip',
                'address_city','address_state','address_country'
            ];

        $self->read(array_merge($common_fields, ['partner_identity_id', 'state']));

        foreach($self as $id => $partner) {
            if($partner['state'] == 'draft') {
                continue;
            }
            if(is_null($partner['partner_identity_id'])) {
                $map_fields = [];
                foreach($common_fields as $field) {
                    $map_fields[$field] = $partner[$field];
                }

                $identity = Identity::create($map_fields)
                    ->read(['id'])
                    ->first();

                self::id($id)->update(['partner_identity_id' => $identity['id']]);
            }
            else {
                $identity = Identity::id($partner['partner_identity_id'])->read($common_fields)->first();
                foreach($common_fields as $field) {
                    if(isset($values[$field]) && $values[$field] !== $identity[$field]) {
                        Identity::id($partner['partner_identity_id'])->update([$field => $values[$field]]);
                    }
                }
            }
        }
    }

    public static function getConstraints() {
        return Identity::getConstraints();
    }

    public static function generateName() {
        return null;
    }
}
