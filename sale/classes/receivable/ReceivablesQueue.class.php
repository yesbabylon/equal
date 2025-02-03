<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\receivable;
use \equal\orm\Model;

class ReceivablesQueue extends Model {

    public static function getDescription() {
        return "A Receivable Queue is created for each Customer and represent the list of items (receivables) that are waiting to be put on an invoice.";
    }

    public static function getColumns() {
        return [
            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => 'The Customer the queue refers to.',
                'required'          => true
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'The name of the receivables queue.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'receivables_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\receivable\Receivable',
                'foreign_field'     => 'receivables_queue_id',
                'description'       => 'The Receivables attached to the queue.'
            ],

            'pending_receivables_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\receivable\Receivable',
                'foreign_field'     => 'receivables_queue_id',
                'description'       => 'The pending Receivables attached to the queue.',
                'domain'            => ['status', '=', 'pending']
            ],

            'invoiced_receivables_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\receivable\Receivable',
                'foreign_field'     => 'receivables_queue_id',
                'description'       => 'The invoiced Receivables attached to the queue.',
                'domain'            => ['status', '=', 'invoiced']
            ],

            'pending_receivables_count' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Quantity of pending receivables attached to the queue.',
                'function'          => 'calcPendingReceivablesCount'
            ],

            'projects_ids' => [
                'type'            => 'one2many',
                'foreign_object'  => 'timetrack\Project',
                'foreign_field'   => 'receivable_queue_id',
                'description'     => 'Timetrack Projects that use this queue for their Sale Entries.'
            ]
        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['customer_id' => ['name']]);
        foreach($self as $id => $receivables_queue) {
            $result[$id] = $receivables_queue['customer_id']['name'];
        }
        return $result;
    }

    public static function calcPendingReceivablesCount($self): array {
        $result = [];
        $self->read(['pending_receivables_ids']);

        foreach($self as $id => $queue) {
            $result[$id] = count($queue['pending_receivables_ids'] ?? []);
        }

        return $result;
    }

}