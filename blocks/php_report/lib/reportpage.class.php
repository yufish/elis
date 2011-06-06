<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

//parent class
require_once($CFG->dirroot . '/elis/core/lib/page.class.php');
//report base class
require_once($CFG->dirroot . '/blocks/php_report/php_report_base.php');
//report container
require_once($CFG->dirroot . '/blocks/php_report/php_report_block.class.php');

if (!defined('REPORT_PAGE_NUM_RECORDS')) {
    define('REPORT_PAGE_NUM_RECORDS', 20);
}

/**
 * Class representing a page used to display reports
 */
class report_page extends elis_page {

    //shortname of contained report
    var $report = '';
    //actual contained report instance
    var $report_instance = FALSE;
    
    public function __construct($params = null) {
        //URL parameter specifies the report instance
        $this->report = $this->required_param('report');
        
        //convert shortname to report instance
        $this->report_instance = php_report::get_default_instance($this->report);

        parent::__construct($params);
    }
    
    /**
     * Specifies whether the current user may use this page to view
     * the report specified via the "report" URL parameter
     * 
     * @return  boolean  TRUE if allowed, otherwise FALSE
     *  
     */
    function can_do_default() {
        global $CFG;
        
        if ($this->report_instance === FALSE) {
            //report wasn't found
            return FALSE;
        }
            
        if (!$this->report_instance->is_available()) {
            //report is not available because required components are not installed
            return FALSE;
        }
            
        if (!$this->report_instance->can_view_report()) {
            //report is not available due to user-based permissions
            return FALSE;
        }
        
        //report is available
        return TRUE;
    }
    
    /**
     * Performs the default action (display the report specified by URL)
     */
    function display_default() {
        global $CFG, $PAGE;
        
        //name of class for specific report instance
        $classname = $this->report . '_report';
        
        //initialize report block, to be dispalyed at 100% width
        $reportblock = new php_report_block($this->report, TRUE, $this->report_instance->get_display_name(),
                                            0, REPORT_PAGE_NUM_RECORDS, $classname, '100%');
        
        //import necessary CSS
        $stylesheet_web_path = $CFG->wwwroot . '/blocks/php_report/styles.php';
        echo '<style>@import url("' . $stylesheet_web_path . '");</style>';
        
        //output the report contents
        echo $reportblock->display();
        
        //needed for AJAX calls
        $PAGE->requires->yui2_lib(array('yahoo',
                                        'dom',
                                        'event',
                                        'connection'));

        $PAGE->requires->js('/elis/core/js/associate.class.js');
        $PAGE->requires->js('/blocks/php_report/js/throbber.php');
        
        //set up JS work to contain dynamic output in the report div
        $init_code = "my_handler = new associate_link_handler('{$CFG->wwwroot}/blocks/php_report/dynamicreport.php',
                                                              'php_report_body_{$this->report}')";
        $PAGE->requires->js_init_code($init_code);
    }
    
    function build_navbar_default() {
        global $CFG;
        parent::build_navbar_default();

        $this->navbar->add($this->report_instance->get_display_name(), null);
    }

    function get_page_title() {
        return $this->report_instance->get_display_name();
    }

    protected function _get_page_params() {
        return array('report' => $this->report) + parent::_get_page_params();
    }
}

