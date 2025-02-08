<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;

use equal\orm\Model;
use hr\employee\Employee;
use sale\customer\Customer;
use sale\customer\Contact as CustomerContact;
use purchase\supplier\Supplier;

/**
 * This class is meant to be used as an interface for other entities (organisation and partner).
 */
class Identity extends Model {

    public static function getName() {
        return "Identity";
    }

    public static function getDescription() {
        return "An Identity is either a legal or natural person: organizations are legal persons and users, contacts and employees are natural persons. An identity might have several partners of various kind (contact, employee, provider, customer, ...).";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'instant'           => true,
                'dependents'        => [
                    'user_id'             => 'name',
                    'contact_id'          => 'name',
                    'customer_contact_id' => 'name',
                    'employee_id'         => 'name',
                    'customer_id'         => 'name',
                    'supplier_id'         => 'name'
                ],
                'description'       => 'The display name of the identity.',
                'help'              => "The display name is a computed field that returns a concatenated string containing either the firstname+lastname, or the legal name of the Identity, based on the kind of Identity.\n
                    For instance, 'name', for a company with \"My Company\" as legal name will return \"My Company\". \n
                    Whereas, for an individual having \"John\" as firstname and \"Smith\" as lastname, it will return \"John Smith\"."
            ],

            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\IdentityType',
                'onupdate'          => 'onupdateTypeId',
                // default is 'IN' individual
                'default'           => 1,
                'dependents  '      => ['type', 'name'],
                'description'       => 'Type of identity.'
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

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'A short reminder to help user identify the targeted person and its specifics.'
            ],

            'bank_account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn:iban',
                'description'       => "Number of the bank account of the Identity, if any.",
                'visible'           => [ ['has_parent', '=', false] ]
            ],

            'bank_account_bic' => [
                'type'              => 'string',
                'description'       => "Identifier of the Bank related to the Identity's bank account, when set.",
                'visible'           => [ ['has_parent', '=', false] ]
            ],

            'signature' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => 'Identity signature to append to communications.',
                'multilang'         => true
            ],

            /*
                Fields specific to organizations
            */
            'legal_name' => [
                'type'              => 'string',
                'description'       => 'Full name of the Identity.',
                'visible'           => [ ['type', '<>', 'IN'] ],
                'dependents'        => ['name'],
                'onupdate'          => 'onupdateLegalName'
            ],

            'short_name' => [
                'type'              => 'string',
                'description'       => 'Usual name to be used as a memo for identifying the organization (acronym or short name).',
                'visible'           => [ ['type', '<>', 'IN'] ],
                'dependents'        => ['name']
            ],

            'has_vat' => [
                'type'              => 'boolean',
                'description'       => 'Does the organization have a VAT number?',
                'visible'           => [ ['type', '<>', 'IN'], ['has_parent', '=', false] ],
                'default'           => false,
                'onupdate'          => 'onupdateHasVat'
            ],

            'vat_number' => [
                'type'              => 'string',
                'description'       => 'Value Added Tax identification number, if any.',
                'visible'           => [ ['has_vat', '=', true], ['type', '<>', 'IN'], ['has_parent', '=', false] ],
                'onupdate'          => 'onupdateVatNumber'
            ],

            'registration_number' => [
                'type'              => 'string',
                'description'       => 'Organization registration number (company number).',
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],

            /*
                Fields specific to citizen: children organizations and parent company, if any
            */
            'citizen_identification' => [
                'type'              => 'string',
                'description'       => 'Citizen registration number, if any.',
                'visible'           => [ ['type', '=', 'IN'] ]
            ],

            'nationality' => [
                'type'              => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'The country the person is citizen of.',
                'default'           => 'BE'
            ],

            /*
                Relational fields specific to organizations: children organizations and parent company, if any
            */
            'children_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Identity',
                'foreign_field'     => 'parent_id',
                'domain'            => [ ['id', '<>', 'object.id'], ['type', '<>', 'IN'] ],
                'description'       => 'Children departments of the organization, if any.',
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],

            'has_parent' => [
                'type'              => 'boolean',
                'description'       => 'Does the identity have a parent organization?',
                'visible'           => [ ['type', '<>', 'IN'] ],
                'default'           => false
            ],

            'parent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'domain'            => [ ['id', '<>', 'object.id'], ['type', '<>', 'IN'] ],
                'description'       => 'Parent company of which the organization is a branch (department), if any.',
                'visible'           => [ ['has_parent', '=', true] ]
            ],

            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Contact',
                'foreign_field'     => 'owner_identity_id',
                'domain'            => ['partner_identity_id', '<>', 'object.id'],
                'description'       => 'List of contacts related to the organization, if any.',
                'help'              => 'A contact is an arbitrary relation between two identities. Any Identity can have several contacts.'
            ],

            'users_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\User',
                'foreign_field'     => 'owner_identity_id',
                'description'       => 'List of users of the identity, if any.' ,
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],

            'employees_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'hr\employee\Employee',
                'foreign_field'     => 'owner_identity_id',
                'description'       => 'List of employees of the organization, if any.' ,
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],

            'customers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\customer\Customer',
                'foreign_field'     => 'owner_identity_id',
                'domain'            => ['relationship', '=', 'customer'],
                'description'       => 'List of customers of the organization, if any.',
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],

            'suppliers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'foreign_field'     => 'owner_identity_id',
                'description'       => 'List of suppliers of the organization, if any.',
                'visible'           => [ ['type', '<>', 'IN'] ]
            ],


            /*
                Contact details.
                For individuals, these are the contact details of the person herself.
            */
            'firstname' => [
                'type'              => 'string',
                'description'       => "Full name of the contact (must be a person, not a role).",
                'visible'           => ['type', '=', 'IN'],
                'dependents'        => ['name'],
                'onupdate'          => 'onupdateFirstname'
            ],

            'lastname' => [
                'type'              => 'string',
                'description'       => 'Reference contact surname.',
                'visible'           => ['type', '=', 'IN'],
                'dependents'        => ['name'],
                'onupdate'          => 'onupdateLastname'
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

            /*
                Description of the Identity address.
                For organizations this is the official (legal) address (typically headquarters, but not necessarily)
            */
            'address_street' => [
                'type'              => 'string',
                'description'       => 'Street and number.',
                'onupdate'          => 'onupdateAddressStreet'
            ],

            'address_dispatch' => [
                'type'              => 'string',
                'description'       => 'Optional info for mail dispatch (apartment, box, floor, ...).',
                'onupdate'          => 'onupdateAddressDispatch'
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => 'City.',
                'onupdate'          => 'onupdateAddressCity'
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => 'Postal code.',
                'onupdate'          => 'onupdateAddressZip'
            ],

            'address_state' => [
                'type'              => 'string',
                'description'       => 'State or region.',
                'onupdate'          => 'onupdateAddressState'
            ],

            'address_country' => [
                'type'              => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'Country.',
                'default'           => 'BE',
                'onupdate'          => 'onupdateAddressCountry'
            ],

            /*
                Additional official contact details.
                For individuals these are personal contact details, whereas for companies these are official (registered) details.
            */
            'email' => [
                'type'              => 'string',
                'usage'             => 'email',
                'onupdate'          => 'onupdateEmail',
                'description'       => "Identity main email address."
            ],

            'email_alt' => [
                'type'              => 'string',
                'usage'             => 'email',
                'description'       => "Identity secondary email address."
            ],

            'phone' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'onupdate'          => 'onupdatePhone',
                'description'       => "Identity secondary phone number (mobile or landline)."
            ],

            'phone_alt' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'description'       => "Identity main phone number (mobile or landline)."
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

            // Companies can also have an official website.
            'website' => [
                'type'              => 'string',
                'usage'             => 'uri/url',
                'description'       => 'Organization main official website URL, if any.',
                'visible'           => ['type', '<>', 'IN']
            ],

            // an identity can have additional addresses
            'addresses_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Address',
                'foreign_field'     => 'identity_id',
                'description'       => 'List of addresses related to the identity.',
            ],

            /*
                For organizations, there might be a reference person: a person who is entitled to legally represent the organization (typically the director, the manager, the CEO, ...).
                These contact details are commonly requested by service providers for validating the identity of an organization.
            */
            'reference_partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'domain'            => ['relationship', '=', 'contact'],
                'description'       => 'Contact (natural person) that can legally represent the organization.',
                'onupdate'          => 'onupdateReferencePartnerId',
                'visible'           => [ ['type', '<>', 'IN'], ['type', '<>', 'SE'] ]
            ],

            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => 'User associated to this identity, if any.',
                'visible'           => [['type', '=', 'IN'], ['is_organisation', '=', false]],
                'onupdate'          => 'onupdateUserId'
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'foreign_field'     => 'partner_identity_id',
                'description'       => 'Customer associated to this identity, if any.',
                'visible'           => [['is_organisation', '=', false]],
                'onupdate'          => 'onupdateCustomerId'
            ],

            'supplier_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'purchase\supplier\Supplier',
                'foreign_field'     => 'partner_identity_id',
                'description'       => 'Supplier associated to this identity, if any.',
                'visible'           => [['is_organisation', '=', false]],
                'onupdate'          => 'onupdateSupplierId'
            ],

            'contact_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Contact',
                'foreign_field'     => 'partner_identity_id',
                'description'       => 'Contact associated to this identity, if any.',
                'visible'           => [['is_organisation', '=', false]],
                'onupdate'          => 'onupdateContactId'
            ],

            'customer_contact_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Contact',
                'foreign_field'     => 'partner_identity_id',
                'description'       => 'Customer contact associated to this identity, if any.',
                'visible'           => [['is_organisation', '=', false]],
                'onupdate'          => 'onupdateCustomerContactId'
            ],

            'employee_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\employee\Employee',
                'foreign_field'     => 'partner_identity_id',
                'description'       => 'Employee associated to this identity, if any.',
                'visible'           => [['is_organisation', '=', false]],
                'onupdate'          => 'onupdateEmployeeId'
            ],

            'image_document_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'documents\Document',
                'description'    => 'Logo or picture of the identity.',
                'help'           => 'Company logo for organizations or profile image for natural person.'
            ],

            'is_organisation' => [
                'type'           => 'boolean',
                'default'        => false,
                'description'    => 'The identity is an organisation.',
                'onupdate'       => 'onupdateIsOrganisation'
            ],

            'organisation_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'identity\Organisation',
                'description'    => 'The organisation the identity refers to.',
                'visible'        => ['is_organisation', '=', true]
            ]

        ];
    }

    /**
     * For organizations the name is the legal name.
     * For individuals, the name is the concatenation of first and last names.
     */
    public static function calcName($self) {
        $result = [];
        $self->read(['type', 'firstname', 'lastname', 'legal_name', 'short_name']);
        foreach($self as $id => $identity) {
            $parts = [];
            if($identity['type'] == 'IN') {
                if(isset($identity['firstname']) && strlen($identity['firstname'])) {
                    $parts[] = ucfirst($identity['firstname']);
                }
                if(isset($identity['lastname']) && strlen($identity['lastname']) ) {
                    $parts[] = mb_strtoupper($identity['lastname']);
                }
            }
            if(empty($parts) ) {
                if(isset($identity['short_name']) && strlen($identity['short_name'])) {
                    $parts[] = $identity['short_name'];
                }
                elseif(isset($identity['legal_name']) && strlen($identity['legal_name'])) {
                    $parts[] = $identity['legal_name'];
                }
            }
            $result[$id] = implode(' ', $parts);
        }
        return $result;
    }

    private static function updateField($self, $field) {
        $self->read(['user_id', 'contact_id', 'customer_contact_id', 'employee_id', 'customer_id', 'supplier_id', $field]);
        foreach($self as $id => $identity) {
            if($identity['user_id']) {
                User::id($identity['user_id'])->update([$field => $identity[$field]]);
            }
            if($identity['contact_id']) {
                Contact::id($identity['contact_id'])->update([$field => $identity[$field]]);
            }
            if($identity['customer_contact_id']) {
                CustomerContact::id($identity['customer_contact_id'])->update([$field => $identity[$field]]);
            }
            if($identity['employee_id']) {
                Employee::id($identity['employee_id'])->update([$field => $identity[$field]]);
            }
            if($identity['customer_id']) {
                Customer::id($identity['customer_id'])->update([$field => $identity[$field]]);
            }
            if($identity['supplier_id']) {
                Supplier::id($identity['supplier_id'])->update([$field => $identity[$field]]);
            }
        }
    }

    public static function onupdateTypeId($self) {
        self::updateField($self, 'type_id');
    }

    public static function onupdateLegalName($self) {
        self::updateField($self, 'legal_name');
    }

    public static function onupdateFirstname($self) {
        self::updateField($self, 'firstname');
    }

    public static function onupdateLastname($self) {
        self::updateField($self, 'lastname');
    }

    public static function onupdateHasVat($self) {
        self::updateField($self, 'has_vat');
    }

    public static function onupdateVatNumber($self) {
        self::updateField($self, 'vat_number');
    }

    public static function onupdateEmail($self) {
        self::updateField($self, 'email');
    }

    public static function onupdatePhone($self) {
        self::updateField($self, 'phone');
    }

    public static function onupdateMobile($self) {
        self::updateField($self, 'mobile');
    }

    public static function onupdateLangId($self) {
        self::updateField($self, 'lang_id');
    }

    public static function onupdateAddressStreet($self) {
        self::updateField($self, 'address_street');
    }

    public static function onupdateAddressDispatch($self) {
        self::updateField($self, 'address_dispatch');
    }

    public static function onupdateAddressCity($self) {
        self::updateField($self, 'address_city');
    }

    public static function onupdateAddressZip($self) {
        self::updateField($self, 'address_zip');
    }

    public static function onupdateAddressState($self) {
        self::updateField($self, 'address_state');
    }

    public static function onupdateAddressCountry($self) {
        self::updateField($self, 'address_country');
    }

    public static function onupdateUserId($self) {
        $self->read(['user_id']);
        foreach($self as $id => $identity) {
            User::id($identity['user_id'])->update(['identity_id' => $id]);
        }
    }

    public static function onupdateContactId($self) {
        $self->read(['contact_id']);
        foreach($self as $id => $identity) {
            Contact::id($identity['contact_id'])->update(['partner_identity_id' => $id]);
        }
    }

    public static function onupdateCustomerContactId($self) {
        $self->read(['customer_contact_id']);
        foreach($self as $id => $identity) {
            CustomerContact::id($identity['customer_contact_id'])->update(['partner_identity_id' => $id]);
        }
    }

    public static function onupdateEmployeeId($self) {
        $self->read(['employee_id']);
        foreach($self as $id => $identity) {
            Employee::id($identity['employee_id'])->update(['partner_identity_id' => $id]);
        }
    }

    public static function onupdateSupplierId($self) {
        $self->read(['supplier_id']);
        foreach($self as $id => $identity) {
            Supplier::id($identity['supplier_id'])->update(['partner_identity_id' => $id]);
        }
    }

    public static function onupdateCustomerId($self) {
        $self->read(['customer_id']);
        foreach($self as $id => $identity) {
            Customer::id($identity['customer_id'])->update(['partner_identity_id' => $id]);
        }
    }

    /**
     * When a reference partner is given, add it to the identity's contacts, if not already present
     */
    public static function onupdateReferencePartnerId($self) {
        $self->read(['reference_partner_id', 'reference_partner_id' => 'partner_identity_id', 'contacts_ids' => 'partner_identity_id']);
        foreach($self as $id => $identity) {
            $contacts_ids = [];
            if($identity['contacts_ids'] && count($identity['contacts_ids'])) {
                $contacts_ids = $identity['contacts_ids']->get(true);
            }
            if(!in_array($identity['reference_partner_id']['partner_identity_id'], array_map( function($a) { return $a['partner_identity_id']; }, $contacts_ids))) {
                // create a contact with the customer as 'booking' contact
                Contact::create([
                        'owner_identity_id'     => $id,
                        'partner_identity_id'   => $identity['reference_partner_id']['partner_identity_id']
                    ]);
            }
        }
    }

    /**
     * Signature for single object change from views.
     *
     * @param  Array    $event     Associative array holding changed fields as keys, and their related new values.
     * @param  Array    $values    Copy of the current (partial) state of the object (fields depend on the view).
     * @return Array    Associative array mapping fields with their resulting values.
     */
    public static function onchange($self, $event, $values, $lang) {
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

        if(isset($event['address_zip']) && isset($values['address_country'])) {
            $list = self::getCitiesByZip($event['address_zip'], $values['address_country'], $lang);
            if($list) {
                $result['address_city'] = [
                    'value' => '',
                    'selection' => $list
                ];
            }
        }

        return $result;
    }

    /**
     * Returns cities' names based on a zip code and a country.
     */
    private static function getCitiesByZip($zip, $country, $lang) {
        $result = null;

        $file = EQ_BASEDIR."/packages/identity/i18n/{$lang}/zipcodes/{$country}.json";
        if(file_exists($file)) {
            $data = file_get_contents($file);
            $map_zip = json_decode($data, true);
            if(isset($map_zip[$zip])) {
                $result = $map_zip[$zip];
            }
        }
        // fallback to english value, if defined
        if(!$result) {
            $file = EQ_BASEDIR."/packages/identity/i18n/en/zipcodes/{$country}.json";
            if(file_exists($file)) {
                $data = file_get_contents($file);
                $map_zip = json_decode($data, true);
                if(isset($map_zip[$zip])) {
                    $result = $map_zip[$zip];
                }
            }
        }

        return $result;
    }

    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $ids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $ids, $values, $lang='en') {
        if(isset($values['type_id'])) {
            $identities = $om->read(get_called_class(), $ids, [ 'firstname', 'lastname', 'legal_name' ], $lang);
            foreach($identities as $id => $identity) {
                if($values['type_id'] == 1) {
                    $firstname = '';
                    $lastname = '';
                    if(isset($values['firstname'])) {
                        $firstname = $values['firstname'];
                    }
                    else {
                        $firstname = $identity['firstname'];
                    }
                    if(isset($values['lastname'])) {
                        $lastname = $values['lastname'];
                    }
                    else {
                        $lastname = $identity['lastname'];
                    }

                    if(!strlen($firstname) ) {
                        return ['firstname' => ['missing' => "Firstname cannot be empty for natural person (identity $id)."]];
                    }
                    if(!strlen($lastname) ) {
                        return ['lastname' => ['missing' => "Lastname cannot be empty for natural person (identity $id)."]];
                    }
                }
                else {
                    $legal_name = '';
                    if(isset($values['legal_name'])) {
                        $legal_name = $values['legal_name'];
                    }
                    else {
                        $legal_name = $identity['legal_name'];
                    }
                    if(!strlen($legal_name)) {
                        return ['legal_name' => ['missing' => 'Legal name cannot be empty for legal person.']];
                    }
                }
            }
        }
        return parent::canupdate($om, $ids, $values, $lang);
    }

    public static function getConstraints() {
        return [
            'legal_name' =>  [
                'too_short' => [
                    'message'       => 'Legal name must be minimum 2 chars long.',
                    'function'      => function ($legal_name, $values) {
                        return !( strlen($legal_name) < 2 && isset($values['type_id']) && $values['type_id'] != 1 );
                    }
                ],
                'too_long' => [
                    'message'       => 'Legal name must be maximum 70 chars long.',
                    'function'      => function ($legal_name, $values) {
                        return !( strlen($legal_name) > 70 && isset($values['type_id']) && $values['type_id'] != 1 );
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Legal name must contain only naming glyphs.',
                    'function'      => function ($legal_name, $values) {
                        if( isset($values['type_id']) && $values['type_id'] == 1 ) {
                            return true;
                        }
                        // authorized : a-z, 0-9, '/', '-', ',', '.', ''', '&'
                        return (bool) (preg_match('/^[\w\'\-,.&][^_!¡?÷?¿\\+=@#$%ˆ*{}|~<>;:[\]]{1,}$/u', $legal_name));
                    }
                ]
            ],
            'firstname' =>  [
                'too_short' => [
                    'message'       => 'Firstname must be 2 chars long at minimum.',
                    'function'      => function ($firstname, $values) {
                        return !( strlen($firstname) < 2 && isset($values['type_id']) && $values['type_id'] == 1 );
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Firstname must contain only naming glyphs.',
                    'function'      => function ($firstname, $values) {
                        if( isset($values['type_id']) && $values['type_id'] != 1 ) {
                            return true;
                        }
                        return (bool) (preg_match('/^[\w\'\-,.][^0-9_!¡?÷?¿\/\\+=@#$%ˆ&*(){}|~<>;:[\]]{1,}$/u', $firstname));
                    }
                ]
            ],
            'lastname' =>  [
                'too_short' => [
                    'message'       => 'Lastname must be 2 chars long at minimum.',
                    'function'      => function ($lastname, $values) {
                        return !( strlen($lastname) < 2 && isset($values['type_id']) && $values['type_id'] == 1 );
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Lastname must contain only naming glyphs.',
                    'function'      => function ($lastname, $values) {
                        if( isset($values['type_id']) && $values['type_id'] != 1 ) {
                            return true;
                        }
                        return (bool) (preg_match('/^[\w\'\-,.][^0-9_!¡?÷?¿\/\\+=@#$%ˆ&*(){}|~<>;:[\]]{1,}$/u', $lastname));
                    }
                ]
            ]
        ];
    }

    public static function onupdateIsOrganisation($self) {
        $self->read(['is_organisation']);
        foreach($self as $id => $organisation) {
            if(!$organisation['is_organisation']) {
                self::id($id)->update(['organisation_id' => null]);
            }
        }
    }

    /**
     * Upon update, if an Identity relates to an Organisation, synchronize common fields with related Organisation
     */
    public static function onafterupdate($self, $values, $orm) {
        $organisation_fields = $orm->getModel(Organisation::getType())->getSchema();
        $self->read(['is_organisation', 'organisation_id']);
        $organisation_values = array_intersect_key($values, $organisation_fields);
        foreach($self as $id => $identity) {
            if($identity['is_organisation']) {
                Organisation::id($identity['organisation_id'])->update($organisation_values);
            }
        }
    }
}
