<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis
 * @subpackage programmanager
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */
require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('lib.php'));
require_once(elispm::lib('data/student.class.php'));

/**
 * Class for testing the update_enrolment_status method belonging to the
 * pmclass class
 */
class pmclassUpdateEnrolmentStatusTest extends elis_database_test {
    /**
     * Return the list of tables that should be overlayed.
     *
     * @return array The mapping of overlay tables to components
     */
    static protected function get_overlay_tables() {
        return array(course::TABLE => 'elis_program',
                     coursecompletion::TABLE => 'elis_program',
                     pmclass::TABLE => 'elis_program',
                     student::TABLE => 'elis_program',
                     student_grade::TABLE => 'elis_program');
    }

    /**
     * Return the list of tables that should be ignored for writes.
     *
     * @return array The mapping of ignored tables to components
     */
    static protected function get_ignored_tables() {
        return array(coursetemplate::TABLE => 'elis_program',
                     'context' => 'moodle');
    }

    /**
     * Load CSV data for use in this test class
     *
     * @param boolean $create_nonrequired_los set to true to create non-required learning objective records
     * @param $create_required_los set to true to create required learning objective records
     */
    function load_csv_data($create_nonrequired_los = false, $create_required_los = false) {
        //NOTE: for now, can only use one of two parameters

        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        //need PM course to create PM class
        $dataset->addTable(course::TABLE, elis::component_file('program', 'phpunit/pmcoursewithgrade.csv'));
        //need PM classes to create associations
        $dataset->addTable(pmclass::TABLE, elis::component_file('program', 'phpunit/pmclass.csv'));

        if ($create_nonrequired_los) {
            //want a non-required learning objective
            $dataset->addTable(coursecompletion::TABLE, elis::component_file('program', 'phpunit/course_completion_nonrequired.csv'));
        } else if ($create_required_los) {
            //want a required learning objective
            $dataset->addTable(coursecompletion::TABLE, elis::component_file('program', 'phpunit/course_completion_required.csv'));
        }

        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    /**
     * Save a set of enrolments and LO grades to the database
     *
     * @param array $enrolments Enrolment data to save
     * @param array $grades LO grade data to save
     */
    private function save_enrolments($enrolments, $grades = array()) {
        //enrolments
        foreach ($enrolments as $enrolment) {
            $student = new student($enrolment);
            $student->save();
        }

        //LO grades
        foreach ($grades as $grade) {
            $student_grade = new student_grade($grade);
            $student_grade->save();
        }
    }

    /**
     * Validate that a set of enrolments exist in the provided state
     *
     * @param array $expected_enrolments The list of enrolments are are validating
     */
    private function validate_expected_enrolments($expected_enrolments) {
        global $DB;

        //validate count
        $count = $DB->count_records(student::TABLE);
        $this->assertEquals(count($expected_enrolments), $count);

        //validate each enrolment individually
        foreach ($expected_enrolments as $expected_enrolment) {
            $exists = $DB->record_exists(student::TABLE, $expected_enrolment);
            $this->assertTrue($exists);
        }
    }

    /**
     * Data provider that does not include any data for learning objectives
     *
     * @return array An array of runs, containing sub-arrays for parameters
     */
    function noLearningObjectivesProvider() {
        //array for storing our runs
        $runs = array();

        //records that we will be re-using
        $sufficient_grade_record = array('userid' => 1,
                                         'classid' => 100,
                                         'grade' => 100,
                                         'completestatusid' => STUSTATUS_NOTCOMPLETE,
                                         'locked' => 0);
        $sufficient_grade_record_completed = array('userid' => 1,
                                                   'classid' => 100,
                                                   'grade' => 100,
                                                   'completestatusid' => STUSTATUS_PASSED,
                                                   'locked' => 1);
        $insufficient_grade_record = array('userid' => 2,
                                           'classid' => 100,
                                           'grade' => 0,
                                           'completestatusid' => STUSTATUS_NOTCOMPLETE,
                                           'locked' => 0);

        //run with just a sufficient grade
        $enrolments = array();
        $enrolments[] = $sufficient_grade_record;
        $expected_enrolments = array();
        $expected_enrolments[] = $sufficient_grade_record_completed;
        $runs[] = array($enrolments, $expected_enrolments, 100);

        //run with just an insufficient grade
        $enrolments = array();
        $enrolments[] = $insufficient_grade_record;
        $expected_enrolments = array();
        $expected_enrolments[] = $insufficient_grade_record;
        $runs[] = array($enrolments, $expected_enrolments, 100);

        //run with both a sufficient and a sufficient grade
        $enrolments = array();
        $enrolments[] = $sufficient_grade_record;
        $enrolments[] = $insufficient_grade_record;
        $expected_enrolments = array();
        $expected_enrolments[] = $sufficient_grade_record_completed;
        $expected_enrolments[] = $insufficient_grade_record;
        $runs[] = array($enrolments, $expected_enrolments, 100);

        //return all data
        return $runs;
    }

    /**
     * Validate that enrolments are updated appropriate when there are no LOs 
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expected_enrolments Records to validate
     * @param int $classid The id of the class we should run the method for
     * @dataProvider noLearningObjectivesProvider
     */
    public function testEnrolmentUpdatesWithNoLearningObjectives($enrolments, $expected_enrolments, $classid) {
        global $DB;

        $this->load_csv_data();

        $this->save_enrolments($enrolments);

        $class = new pmclass($classid);
        $class->update_enrolment_status();

        $this->validate_expected_enrolments($expected_enrolments);
    }

    /**
     * Validate that enrolments are updated appropriate when there are only
     * non-required LOs
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expected_enrolments Records to validate
     * @param int $classid The id of the class we should run the method for
     * @dataProvider noLearningObjectivesProvider
     */
    public function testEnrolmentUpdatesWithNonrequiredLearningObjectives($enrolments, $expected_enrolments, $classid) {
        global $DB;

        $this->load_csv_data(true);

        $this->save_enrolments($enrolments);

        $class = new pmclass($classid);
        $class->update_enrolment_status();

        $this->validate_expected_enrolments($expected_enrolments);
    }

    /**
     * Data provided that includes information regarding learning objectives
     *
     * @return array An array of runs, containing sub-arrays for parameters
     */
    function learningObjectivesProvider() {
        //array for storing our runs
        $runs = array();

        //records that we will be re-using
        $sufficient_grade_record = array('userid' => 1,
                                         'classid' => 100,
                                         'grade' => 100,
                                         'completestatusid' => STUSTATUS_NOTCOMPLETE,
                                         'locked' => 0);
        $sufficient_grade_record_completed = array('userid' => 1,
                                                   'classid' => 100,
                                                   'grade' => 100,
                                                   'completestatusid' => STUSTATUS_PASSED,
                                                   'locked' => 1);
        $insufficient_grade_record = array('userid' => 2,
                                           'classid' => 100,
                                           'grade' => 0,
                                           'completestatusid' => STUSTATUS_NOTCOMPLETE,
                                           'locked' => 0);

        $sufficient_lo_grade_record = array('completionid' => 1,
                                            'classid' => 100,
                                            'grade' => 100,
                                            'locked' => 0);
        $insufficient_lo_grade_record = array('completionid' => 1,
                                              'classid' => 100,
                                              'grade' => 0,
                                              'locked' => 0);

        //run with sufficient enrolment grade but insufficient required LO grade
        $enrolments = array();
        $enrolments[] = $sufficient_grade_record;
        $lo_grades = array();
        $lo_grades[] = array_merge($insufficient_lo_grade_record, array('userid' => 1));
        $expected_enrolments = array();
        $expected_enrolments[] = $sufficient_grade_record;
        $runs[] = array($enrolments, $lo_grades, $expected_enrolments, 100);

        //run with insufficient enrolment grade but sufficient required LO grade
        $enrolments = array();
        $enrolments[] = $insufficient_grade_record;
        $lo_grades = array();
        $lo_grades[] = array_merge($sufficient_lo_grade_record, array('userid' => 1));
        $expected_enrolments = array();
        $expected_enrolments[] = $insufficient_grade_record;
        $runs[] = array($enrolments, $lo_grades, $expected_enrolments, 100);

        //run with sufficient enrolment grade and sufficient required LO grade
        $enrolments = array();
        $enrolments[] = $sufficient_grade_record;
        $lo_grades = array();
        $lo_grades[] = array_merge($sufficient_lo_grade_record, array('userid' => 1));
        $expected_enrolments = array();
        $expected_enrolments[] = $sufficient_grade_record_completed;
        $runs[] = array($enrolments, $lo_grades, $expected_enrolments, 100);

        //return all data
        return $runs;
    }

    /**
     * Validate that enrolments are updated appropriately when there are required
     * LOs
     *
     * @param array $enrolments Enrolment records to create
     * @param array $lo_grades Learning objective grades to create
     * @param array $expected_enrolments Records to validate
     * @param int $classid The id of the class we should run the method for
     * @dataProvider learningObjectivesProvider
     */
    public function testEnrolmentUpdatesWithRequiredLearningObjectives($enrolments, $lo_grades, $expected_enrolments, $classid) {
        global $DB;

        $this->load_csv_data(false, true);

        $this->save_enrolments($enrolments, $lo_grades);

        $class = new pmclass($classid);
        $class->update_enrolment_status();

        $this->validate_expected_enrolments($expected_enrolments);
    }

    /**
     * Data provider that includes records for testing the "timegraded" field
     *
     * @return array An array of runs, containing sub-arrays for parameters
     */
    function learningObjectiveTimeGradedProvider() {
        //array for storing our runs
        $runs = array();

        //run with one enrolment having a time graded on and LO and one without
        $enrolments = array();
        $enrolments[] = array('userid' => 1,
                              'classid' => 100,
                              'grade' => 100,
                              'completestatusid' => STUSTATUS_NOTCOMPLETE,
                              'locked' => 0);
        $enrolments[] = array('userid' => 2,
                              'classid' => 100,
                              'grade' => 100,
                              'completestatusid' => STUSTATUS_NOTCOMPLETE,
                              'locked' => 0);
        $lo_grades = array();
        $lo_grades[] = array('userid' => 1,
                             'completionid' => 1,
                             'classid' => 100,
                             'grade' => 100,
                             'locked' => 0,
                             'timegraded' => 1000000000);
        $lo_grades[] = array('userid' => 1,
                             'completionid' => 2,
                             'classid' => 100,
                             'grade' => 100,
                             'locked' => 0,
                             'timegraded' => 1);
        $expected_enrolments = array();
        $expected_enrolments[] = array('userid' => 1,
                                      'classid' => 100,
                                      'grade' => 100,
                                      'completestatusid' => STUSTATUS_PASSED,
                                      'completetime' => 1000000000,
                                      'locked' => 1);
        $expected_enrolments[] = array('userid' => 2,
                                      'classid' => 100,
                                      'grade' => 100,
                                      'completestatusid' => STUSTATUS_PASSED,
                                      'locked' => 1);
        $runs[] = array($enrolments, $lo_grades, $expected_enrolments, 100);

        //return all data
        return $runs;
    }

    /**
     * Validate that our method respects the latest time graded on any linked LO
     *
     * @param array $enrolments Enrolment records to create
     * @param array $lo_grades Learning objective grades to create
     * @param array $expected_enrolments Records to validate
     * @param int $classid The id of the class we should run the method for
     * @dataProvider learningObjectiveTimeGradedProvider
     */
    public function testEnrolmentUpdateRespectsLatestLOTimegraded($enrolments, $lo_grades, $expected_enrolments, $classid) {
        global $DB;

        $this->load_csv_data(true);

        $this->save_enrolments($enrolments, $lo_grades);

        //track our time boundaries
        $class = new pmclass($classid);
        $mintime = time();
        $class->update_enrolment_status();
        $maxtime = time();

        $count = $DB->count_records(student::TABLE);

        $this->assertEquals(count($expected_enrolments), $count);

        foreach ($expected_enrolments as $expected_enrolment) {
            $exists = $DB->record_exists(student::TABLE, $expected_enrolment);

            $this->assertTrue($exists);

            if (!isset($expected_enrolment['completetime'])) {
                //validate a time range
                $record = $DB->get_record(student::TABLE, $expected_enrolment);
                $this->assertGreaterThanOrEqual($mintime, $record->completetime);
                $this->assertLessThanOrEqual($maxtime, $record->completetime);
            }
        }
    }

    /**
     * Data provider that includes enrolments for several classes
     *
     * @return array An array of runs, containing sub-arrays for parameters
     */
    function differentClassesProvider() {
        //array for storing our runs
        $runs = array();

        //run with one enrolment that could be completed for one class, and
        //on that could be completed for another
        $enrolments = array();
        $enrolments[] = array('userid' => 1,
                              'classid' => 100,
                              'grade' => 100,
                              'completestatusid' => STUSTATUS_NOTCOMPLETE,
                              'locked' => 0);
        $enrolments[] = array('userid' => 1,
                              'classid' => 101,
                              'grade' => 100,
                              'completestatusid' => STUSTATUS_NOTCOMPLETE,
                              'locked' => 0);
        $expected_enrolments[] = array('userid' => 1,
                                       'classid' => 100,
                                       'grade' => 100,
                                       'completestatusid' => STUSTATUS_PASSED,
                                       'locked' => 1);
        $expected_enrolments[] = $enrolments[1];
        $runs[] = array($enrolments, $expected_enrolments, 100);
        //return all data
        return $runs;
    }

    /**
     * Validate that the method respects the class instance it is called on
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expected_enrolments Records to validate
     * @param int $classid The id of the class we should run the method for
     * @dataProvider differentClassesProvider
     */
    public function testEnrolmentUpdateRespectsClassid($enrolments, $expected_enrolments, $classid) {
        global $DB;

        $this->load_csv_data();

        $this->save_enrolments($enrolments);

        $class = new pmclass($classid);
        $class->update_enrolment_status();

        $this->validate_expected_enrolments($expected_enrolments);
    }

    /**
     * Data provider that helps validate credit values in enrolments
     *
     * @return array An array of runs, containing sub-arrays for parameters
     */
    function creditsProvider() {
        //array for storing our runs
        $runs = array();

        //create one run with one passable enrolment relating to a PM course with
        //a learning objective, and on passable enrolment relating to a lo-less course
        $enrolments = array();
        $enrolments[] = array('userid' => 1,
                              'classid' => 100,
                              'grade' => 100,
                              'completestatusid' => STUSTATUS_NOTCOMPLETE,
                              'locked' => 0,
                              'credits' => 0);
        $enrolments[] = array('userid' => 1,
                              'classid' => 102,
                              'grade' => 100,
                              'completestatusid' => STUSTATUS_NOTCOMPLETE,
                              'locked' => 0,
                              'credits' => 0);
        $expected_enrolments = array();
        $expected_enrolments[] = array('userid' => 1,
                                       'classid' => 100,
                                       'grade' => 100,
                                       'completestatusid' => STUSTATUS_PASSED,
                                       'locked' => 1,
                                       'credits' => 1);
        $expected_enrolments[] = array('userid' => 1,
                                       'classid' => 102,
                                       'grade' => 100,
                                       'completestatusid' => STUSTATUS_PASSED,
                                       'locked' => 1,
                                       'credits' => 1);
        $runs[] = array($enrolments, $expected_enrolments, array(100, 102));

        //return all data
        return $runs;
    }

    /**
     * Validate that credits are correctly transferred from course to enrolment
     *
     * @param array $enrolments Enrolment records to create
     * @param array $expected_enrolments Records to validate
     * @param array $classids The ids of the classes we should run the method for
     * @dataProvider creditsProvider
     */
    public function testEnrolmentUpdateSetsCredits($enrolments, $expected_enrolments, $classids) {
        global $DB;

        $this->load_csv_data(true);

        //set up a second course and class
        $course = new course(array('name' => 'secondcourse',
                                   'idnumber' => 'secondcourse',
                                   'syllabus' => '',
                                   'credits' => 1));
        $course->save();

        $pmclass = new pmclass(array('courseid' => $course->id,
                                     'idnumber' => 'secondclass'));
        $pmclass->save();

        $this->save_enrolments($enrolments);

        foreach ($classids as $classid) {
            $pmclass = new pmclass($classid);
            $pmclass->update_enrolment_status();
        }

        $this->validate_expected_enrolments($expected_enrolments);
    }
}