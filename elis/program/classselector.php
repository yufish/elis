<?php
/*
*  ELIS(TM): Enterprise Learning Intelligence Suite
*
*  Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
*
*  This program is free software: you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation, either version 3 of the License, or
*  (at your option) any later version.
*
*  This program is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  You should have received a copy of the GNU General Public License
*  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*  @package    elis
*  @subpackage curriculummanagement
*  @author     Remote-Learner.net Inc
*  @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
*  @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
*/

/**
 * This script is used to return a track selection form
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

require_login(SITEID, false);

global $CFG, $PAGE, $OUTPUT, $DB;

require_once($CFG->dirroot . '/elis/program/lib/lib.php');
require_once($CFG->dirroot . '/elis/program/lib/selectionpopup.class.php');
require_once($CFG->dirroot . '/lib/weblib.php');
require_once($CFG->dirroot . '/lib/dml/moodle_database.php');

define('MAX_NUM_ROWS', 50);

$letterselect   = optional_param('alpha', '', PARAM_TEXT);
$search         = optional_param('search', '', PARAM_TEXT);
$element_update = required_param('id', PARAM_TEXT);
$callback       = required_param('callback', PARAM_TEXT);


$baseurl        = new moodle_url('/elis/program/classselector.php',
                                array('alpha'    => $letterselect,
                                      'search'   => $search,
                                      'id'       => $element_update,
                                      'callback' => $callback));

$PAGE->requires->js('/elis/program/js/results_engine/results_selection.js', true);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('popup');


echo $OUTPUT->header();

pmalphabox($baseurl);
pmsearchbox('/elis/program/classselector.php');

echo html_writer::start_tag('center');

$alpha          = explode(',', get_string('alphabet', 'langconfig'));

$table          = new classselectiontable('classselection', $element_update, $callback);
$columns        = 'cls.id,cd.syllabus,cls.idnumber';
$where          = '';
$alphawhere     = '';
$searchwhere    = '';
$params         = array();

if (!empty($letterselect)) {
    $alphawhere = $DB->sql_like('cd.syllabus', ':alphasyllabus', false) .' OR '
                . $DB->sql_like('cls.idnumber', ':alphaidnumber', false);
    $params['alphasyllabus'] = $letterselect.'%';
    $params['alphaidnumber'] = $letterselect.'%';
}

if (!empty($search)) {
    $searchwhere = $DB->sql_like('cls.idnumber', ':searchclsidnumber' , false, false) . ' OR '
                 . $DB->sql_like('cd.syllabus', ':searchsyllabus', false, false);

    $params['searchclsidnumber']    = '%'.$search.'%';
    $params['searchsyllabus']       = '%'.$search.'%';


}

if (empty($alphawhere) and empty($searchwhere)) {
    $where = '1';
} else {
    if (!empty($alphawhere)) {
        $where = $alphawhere;
    }

    if (!empty($searchwhere)) {

        $where = (empty($where)) ? $searchwhere :
                                   $where . ' AND (' .  $searchwhere .')';
    }

}

$colheader1 = get_string('results_course_desc_header', 'elis_program');
$colheader2 = get_string('results_id_number_header', 'elis_program');

$from = "{$CFG->prefix}crlm_course cd RIGHT JOIN {$CFG->prefix}crlm_class cls ON cd.id = cls.courseid ";
$table->set_sql($columns, $from, $where, $params);
$table->define_baseurl($baseurl);
$table->collapsible(false);
$table->define_columns(array('syllabus', 'idnumber'));
$table->define_headers(array($colheader1, $colheader2));
$table->out(MAX_NUM_ROWS, false);

echo html_writer::end_tag('center');

echo $OUTPUT->footer();