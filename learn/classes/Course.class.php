<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace learn;

use equal\orm\Model;

class Course extends Model {

    public static function getColumns(): array
    {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Unique slug of the program.'
            ],

            'title' => [
                'type'              => 'string',
                'multilang'         => true
            ],

            'subtitle' => [
                'type'              => 'string',
                'multilang'         => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'multilang'         => true
            ],

            'modules' => [
                'type'              => 'alias',
                'alias'             => 'modules_ids'
            ],

            'modules_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'learn\Module',
                'foreign_field'     => 'course_id',
                'order'             => 'order',
                'sort'              => 'asc',
                'ondetach'          => 'delete',
            ],

            'quizzes_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'learn\Quiz',
                'foreign_field'     => 'course_id',
                'ondetach'          => 'delete'
            ],

            'bundles_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'learn\Bundle',
                'foreign_field'     => 'course_id',
                'ondetach'          => 'delete'
            ],

            'langs_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'learn\Lang',
                'foreign_field'     => 'courses_ids',
                'rel_table'         => 'learn_rel_lang_course',
                'rel_foreign_key'   => 'lang_id',
                'rel_local_key'     => 'course_id',
                'description'       => "List of languages in which the program is available"
            ]

        ];
    }

}