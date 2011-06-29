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
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once elispm::lib('data/curriculumcourse.class.php');
require_once elispm::lib('data/curriculumstudent.class.php');
require_once elispm::lib('data/user.class.php');
require_once elispm::lib('data/clusterassignment.class.php');
require_once elispm::lib('associationpage2.class.php');
require_once elispm::lib('contexts.php');
require_once elispm::file('userpage.class.php');
require_once elispm::file('curriculumpage.class.php');
require_once elispm::file('form/curriculumstudentform.class.php');

class studentcurriculumpage extends associationpage2 {
    var $pagename = 'stucur';
    var $section = 'users';
    var $tab_page = 'userpage';
    var $data_class = 'curriculumstudent';
    var $parent_data_class = 'user';
    var $parent_page;
    var $context;

    var $params = array();

    //var $default_tab = 'curriculumstudent';

    public function __construct(array $params = null) {
        $this->section = $this->get_parent_page()->section;
        $this->context = parent::_get_page_context();
        $this->params = $this->_get_page_params();
        parent::__construct($params);
    }

    public function _get_page_context() {
//         $id = $this->optional_param('id', 0, PARAM_INT);
//         if ($id) {
//             return get_context_instance(context_level_base::get_custom_context_level('user', 'elis_program'), $id);
//         } else {
//             return parent::_get_page_context();
//         }
        return parent::_get_page_context();
    }

    public function _get_page_params() {
        return array('id' => optional_param('id', 0, PARAM_INT)) + parent::_get_page_params();
        //return parent::_get_page_params();
    }

    function can_do_default() {
        global $DB;

        $id = $this->required_param('id', PARAM_INT);
        if ($this->is_assigning()) {
            // we have enrol capabilities on some curriculum
            $curriculum_contexts = curriculumpage::get_contexts('block/curr_admin:curriculum:enrol');
            if (!$curriculum_contexts->is_empty()) {
                return true;
            }

            // find curricula linked to clusters where the target user is a
            // member, and we have enrol cluster user capabilities
            $cluster_contexts = usersetpage::get_contexts('block/curr_admin:curriculum:enrol_cluster_user');
            $cluster_object = $cluster_contexts->get_filter('clst.id', 'cluster');
            $cluster_filter_array = $cluster_object->get_sql(false, 'clst');
            $cluster_filter = '';

            if (isset($cluster_filter_array['where'])) {
                $cluster_filter = ' WHERE '.$cluster_filter_array['where'];
            }


            $sql = 'SELECT COUNT(curr.id)
                      FROM {'.userset::TABLE.'} clst
                      JOIN {'.clustercurriculum::TABLE.'} clstcurr
                           ON clst.id = clstcurr.clusterid
                      JOIN {'.curriculum::TABLE.'} curr
                           ON clstcurr.curriculumid = curr.id
                      JOIN {'.clusterassignment::TABLE.'} usrclst ON usrclst.clusterid = clst.id AND usrclst.userid = '.$id.
                    $cluster_filter;

            return $DB->count_records_select($sql) > 0;
        } else {
            return userpage::_has_capability('block/curr_admin:user:view', $id);
        }
    }

    function get_title_default() {
        return get_string('breadcrumb_studentcurriculumpage','elis_program');
    }

    protected function get_context() {
        if (!isset($this->context)) {
            $id = isset($this->params['id']) ? $this->params['id'] : required_param('id', PARAM_INT);
            $this->context = get_context_instance(context_level_base::get_custom_context_level('user', 'elis_program'), $id);
        }
        return $this->context;
    }

   protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            $id = optional_param('id', NULL, PARAM_INT);
            $this->parent_page = new userpage(array('id' => $id, 'action' => 'view'));
        }
        return $this->parent_page;
    }

    function get_tab_page() {
        return $this->get_parent_page();
    }

    function build_navigation_default() {
        $navigation = parent::build_navigation_default();

        $this->navbar->add($navigation);
    }

    protected function get_selection_form() {
        if ($this->is_assigning()) {
            return new assigncurriculumform();
        } else {
            return new unassigncurriculumform();
        }
    }

    protected function process_assignment($data) {
        $userid  = $data->id;
        foreach ($data->_selection as $curid) {
            $stucur = new curriculumstudent(array('userid' => $userid,
                                                  'curriculumid' => $curid));
            $stucur->save();
        }

        $tmppage = $this->get_basepage();
        $tmppage->params['_assign'] = 'assign';
        $sparam = stdClass;
        $sparam->num = count($data->_selection);
        redirect($tmppage->url, get_string('num_curricula_assigned', 'elis_program', $sparam));
    }

    protected function process_unassignment($data) {
        $userid  = $data->id;
        foreach ($data->_selection as $associd) {
            $curstu = new curriculumstudent($associd);
            if ($curstu->userid == $userid) { // sanity check
                $curstu->delete();
            }
        }

        $tmppage = $this->get_basepage();
        $sparam = stdClass;
        $sparam->num = count($data->_selection);
        redirect($tmppage->url, get_string('num_curricula_unassigned', 'elis_program', $sparam));
    }

    protected function get_available_records($filter) {
        global $DB;

        $context = $this->get_context();
        $id = $this->required_param('id', PARAM_INT);

        $pagenum = optional_param('page', 0, PARAM_INT);
        $perpage = 30;

        $sort = optional_param('sort', 'name', PARAM_ACTION);
        $order = optional_param('dir', 'ASC', PARAM_ACTION);
        if ($order != 'DESC') {
            $order = 'ASC';
        }

        static $sortfields = array(
            'name' => 'name',
            'idnumber' => 'idnumber',
            );
        if (!array_key_exists($sort, $sortfields)) {
            $sort = key($sortfields);
        }
        if (is_array($sortfields[$sort])) {
            $sortclause = implode(', ', array_map(create_function('$x', "return \"\$x $order\";"), $sortfields[$sort]));
        } else {
            $sortclause = "{$sortfields[$sort]} $order";
        }

        $this->curriculum_contexts = curriculumpage::get_contexts('block/curr_admin:curriculum:enrol');

        $id = required_param('id', PARAM_INT);

        $where = 'id NOT IN (SELECT curriculumid FROM {'.curriculumstudent::TABLE.'} WHERE userid='.$id.')';

        // only show curricula where user has enrol capabilities
        $curriculum_contexts = curriculumpage::get_contexts('block/curr_admin:curriculum:enrol');
        $curriculum_object = $curriculum_contexts->get_filter('curr.id', 'curriculum');
        $curriculum_filter_array = $curriculum_object->get_sql(false,'curr');
        $curriculum_filter = '0=1';

        if (isset($curriculum_filter_array['where'])) {
            $curriculum_filter = ' WHERE '.$curriculum_filter_array['where'];
        }

        $where .= " AND (".$curriculum_filter;

        // or curricula attached to clusters where user has enrol cluster user
        // capabilities (and target user is a member of that cluster)
        $cluster_contexts = usersetpage::get_contexts('block/curr_admin:curriculum:enrol_cluster_user');
        $cluster_object = $cluster_contexts->get_filter('clst.id', 'cluster');
        $cluster_filter_array = $cluster_object->get_sql(false,'clst');
        $cluster_filter = '';

        if (isset($cluster_filter_array['where'])) {
            $cluster_filter = ' WHERE '.$cluster_filter_array['where'];
        }

        $where .= ' OR id IN (SELECT curr.id
                    FROM {'.userset::TABLE.'} clst
                    JOIN {'.clustercurriculum::TABLE.'} clstcurr
                         ON clst.id = clstcurr.clusterid
                    JOIN {'.curriculum::TABLE.'} curr
                         ON clstcurr.curriculumid = curr.id
                    JOIN {'.clusterassignment::TABLE.'} usrclst ON usrclst.clusterid = clst.id AND usrclst.userid = '.$id.
                  $cluster_filter.'))';

        $count = $DB->count_records_select(curriculum::TABLE, $where);
        $users = $DB->get_records_select(curriculum::TABLE, $where, null, $sortclause, '*', $pagenum*$perpage, $perpage);

        return array($users, $count);
    }

    protected function get_assigned_records($filter) {
        global $DB;

        $context = $this->get_context();
        $id = $this->required_param('id', PARAM_INT);

        $pagenum = $this->optional_param('page', 0, PARAM_INT);
        $perpage = 30;

        $sort = $this->optional_param('sort', 'name', PARAM_ACTION);
        $order = $this->optional_param('dir', 'ASC', PARAM_ACTION);
        if ($order != 'DESC') {
            $order = 'ASC';
        }

        static $sortfields = array(
            'name' => 'name',
            'idnumber' => 'idnumber',
            );
        if (!array_key_exists($sort, $sortfields)) {
            $sort = key($sortfields);
        }
        if (is_array($sortfields[$sort])) {
            $sortclause = implode(', ', array_map(create_function('$x', "return \"\$x $order\";"), $sortfields[$sort]));
        } else {
            $sortclause = "{$sortfields[$sort]} $order";
        }

        $sql = 'SELECT curass.id, curass.curriculumid curid, curass.completed, curass.timecompleted, curass.credits,
                cur.idnumber, cur.name, cur.description, cur.reqcredits, curcrscnt.count as numcourses
                  FROM {'.curriculumstudent::TABLE.'} curass
                  JOIN {'.curriculum::TABLE.'} cur ON cur.id = curass.curriculumid
                  JOIN (SELECT curriculumid, COUNT(courseid) AS count
                          FROM {'.curriculumcourse::TABLE.'}
                      GROUP BY curriculumid) curcrscnt ON cur.id = curcrscnt.curriculumid
                 WHERE curass.userid = '.$id.'
              ORDER BY '.$sortclause;
        $where = 'id IN (SELECT curriculumid FROM {'.curriculumstudent::TABLE.'} WHERE userid='.$id.')';

        $count = $DB->count_records_select(curriculum::TABLE, $where);
        $curricula = $DB->get_records_sql($sql, array($sort=>$order), $pagenum*$perpage, $perpage);

        return array($curricula, $count);
    }

    function get_records_from_selection($record_ids) {
        global $DB;

        $usersstring = implode(',', $record_ids);
        $records = $DB->get_records_select(curriculum::TABLE, "id in ($usersstring)");
        return $records;
    }

    protected function create_selection_table($records, $baseurl) {
        $records = $records ? $records : array();
        $columns = array('_selection' => array('header' => ''),
                         'idnumber' => array('header' => get_string('idnumber','elis_program')),
                         'name' => array('header' => get_string('name','elis_program')),
                         'description' => array('header' => get_string('description','elis_program')),
                         'reqcredits' => array('header' => get_string('required_credits','elis_program')));
        if (!$this->is_assigning()) {
            $columns['numcourses'] = array('header' => get_string('num_courses','elis_program'));
            $columns['timecompleted'] = array('header' => get_string('date_completed','elis_program'));
            $columns['credits'] = array('header' => get_string('credits_rec','elis_program'));
        }
        return new user_curriculum_selection_table($records, $columns,
                                                   new moodle_url($baseurl));
    }

}

class user_curriculum_selection_table extends selection_table {
    function __construct(&$items, $columns, $pageurl) {
        global $DB;

        parent::__construct($items, $columns, $pageurl);

        $this->curriculum_contexts = curriculumpage::get_contexts('block/curr_admin:curriculum:enrol');

        $id = required_param('id', PARAM_INT);
        $cluster_contexts = usersetpage::get_contexts('block/curr_admin:curriculum:enrol_cluster_user');
        $cluster_object = $cluster_contexts->get_filter('clst.id', 'cluster');
        $cluster_filter_array = $cluster_object->get_sql(false, 'clst');
        $cluster_filter = '';

        if (isset($cluster_filter_array['where'])) {
            $cluster_filter = ' WHERE '.$cluster_filter_array['where'];
        }

        $sql = 'SELECT curr.id
                  FROM {'.userset::TABLE.'} clst
                  JOIN {'.clustercurriculum::TABLE.'} clstcurr
                       ON clst.id = clstcurr.clusterid
                  JOIN {'.curriculum::TABLE.'} curr
                       ON clstcurr.curriculumid = curr.id
                  JOIN {'.clusterassignment::TABLE.'} usrclst ON usrclst.clusterid = clst.id AND usrclst.userid = '.$id
                  .$cluster_filter;

        $this->cluster_curricula = $DB->get_records_sql($sql);
    }

    function is_sortable_numcourses() {
        return false;
    }

    function is_sortable_description() {
        return false;
    }

    function is_sortable_reqcredits() {
        return false;
    }

    function get_item_display_timecompleted($column, $item) {
        return get_date_item_display($column, $item);
    }

    function is_sortable_timecompleted() {
        return false;
    }

    function is_sortable_credits() {
        return false;
    }

    function get_item_display__selection($column, $item) {
        $curriculumid = isset($item->curriculumid) ? $item->curriculumid : $item->id;
        if ($this->curriculum_contexts->context_allowed($curriculumid, 'curriculum')
            || isset($this->cluster_curricula[$curriculumid])) {
            return parent::get_item_display__selection($column, $item);
        } else {
            return '';
        }
    }
}

class curriculumstudentpage extends associationpage2 {
    var $pagename = 'curstu';
    var $section = 'curr';
    var $tab_page = 'curriculumpage';
    var $data_class = 'curriculumstudent';
    var $parent_data_class = 'curriculum';
    var $parent_page;
    var $context;

    var $params = array();

    //var $default_tab = 'curriculumstudent';

    public function __construct(array $params = null) {
        $this->section = $this->get_parent_page()->section;
        $this->context = parent::_get_page_context();
        $this->params = $this->_get_page_params();
        parent::__construct($params);
    }

    public function _get_page_context() {
//         $id = $this->optional_param('id', 0, PARAM_INT);
//         if ($id) {
//             return get_context_instance(context_level_base::get_custom_context_level('user', 'elis_program'), $id);
//         } else {
//             return parent::_get_page_context();
//         }
        return parent::_get_page_context();
    }

    public function _get_page_params() {
        return array('id' => optional_param('id', 0, PARAM_INT)) + parent::_get_page_params();
        //return parent::_get_page_params();
    }

    function can_do_default() {
        $id = $this->required_param('id', PARAM_INT);
        if ($this->is_assigning()) {
            return curriculumpage::can_enrol_into_curriculum($id);
        } else {
            return curriculumpage::_has_capability('block/curr_admin:curriculum:view', $id);
        }
    }

    function get_title_default() {
        return get_string('breadcrumb_curriculumstudentpage','elis_program');
    }

    protected function get_context() {
        if (!isset($this->context)) {
            $id = required_param('id', PARAM_INT);
            $this->context = get_context_instance(context_level_base::get_custom_context_level('curriculum', 'elis_program'), $id);
        }
        return $this->context;
    }

    protected function get_parent_page() {
        if (!isset($this->parent_page)) {
            $id = optional_param('id', NULL, PARAM_INT);
            $this->parent_page = new curriculumpage(array('id' => $id, 'action' => 'view'));
        }
        return $this->parent_page;
    }

    function get_tab_page() {
        return $this->get_parent_page();
    }

    function build_navigation_default() {
        $navigation = parent::build_navigation_default();

        $this->navbar->add($navigation);
    }

    protected function get_selection_form() {
        if ($this->is_assigning()) {
            return new assignstudentform();
        } else {
            return new unassignstudentform();
        }
    }

    protected function process_assignment($data) {
        $context = $this->get_context();

        $curid  = $data->id;
        foreach ($data->_selection as $userid) {
            $stucur = new curriculumstudent(array('userid' => $userid,
                                                  'curriculumid' => $curid));
            $stucur->save();
        }

        $tmppage = $this->get_basepage();
        $tmppage->params['_assign'] = 'assign';
        $sparam = new stdClass;
        $sparam->num = count($data->_selection);
        redirect($tmppage->url, get_string('num_users_assigned', 'elis_program', $sparam));
    }

    protected function process_unassignment($data) {
        $curid  = $data->id;
        foreach ($data->_selection as $associd) {
            $curstu = new curriculumstudent($associd);
            if ($curstu->curriculumid == $curid) { // sanity check
                $curstu->delete();
            }
        }

        $tmppage = $this->get_basepage();
        $sparam = new stdClass;
        $sparam->num = count($data->_selection);
        redirect($tmppage->url, get_string('num_users_unassigned', 'elis_program', $sparam));
    }

    protected function get_selection_filter() {
        $post = $_POST;
        $filter = new cm_user_filtering(null, 'index.php', array('s' => $this->pagename) + $this->get_base_params());
        $_POST = $post;
        return $filter;
    }

    protected function print_selection_filter($filter) {
        $filter->display_add();
        $filter->display_active();
    }

    protected function get_available_records($filter) {
        global $USER, $DB;

        $context = $this->get_context();
        $id = required_param('id', PARAM_INT);

        $pagenum = optional_param('page', 0, PARAM_INT);
        $perpage = 30;

        $sort = optional_param('sort', 'name', PARAM_ACTION);
        $order = optional_param('dir', 'ASC', PARAM_ACTION);
        if ($order != 'DESC') {
            $order = 'ASC';
        }

        static $sortfields = array(
            'name' => array('lastname', 'firstname'),
            'idnumber' => 'idnumber',
            );
        if (!array_key_exists($sort, $sortfields)) {
            $sort = key($sortfields);
        }
        if (is_array($sortfields[$sort])) {
            $sortclause = implode(', ', array_map(create_function('$x', "return \"\$x $order\";"), $sortfields[$sort]));
        } else {
            $sortclause = "{$sortfields[$sort]} $order";
        }

        $where = 'id NOT IN (SELECT userid FROM {'.curriculumstudent::TABLE.'} WHERE curriculumid='.$id.')';

        /* TO-DO: re-enable this once I know how it's done
        $extrasql = $filter->get_sql_filter();
        if ($extrasql) {
            $where .= ' AND '.$extrasql;
        }
        */

        if(!curriculumpage::_has_capability('block/curr_admin:curriculum:enrol', $id)) {
            //perform SQL filtering for the more "conditional" capability
            $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:curriculum:enrol_cluster_user', $USER->id);

            $allowed_clusters = array();

            //get the clusters assigned to this curriculum
            $clusters = clustercurriculum::get_clusters($id);
            if(!empty($clusters)) {
                foreach($clusters as $cluster) {
                    if($context->context_allowed($cluster->clusterid, 'cluster')) {
                        $allowed_clusters[] = $cluster->id;
                    }
                }
            }

            if(empty($allowed_clusters)) {
                return array(array(), 0);
            } else {
                $cluster_filter = implode(',', $allowed_clusters);
                $cluster_filter = ' id IN (SELECT userid FROM {'.clusterassignment::TABLE.'}
                                            WHERE clusterid IN ('.$cluster_filter.'))';
                $where .= ' AND '.$cluster_filter;
            }
        }

        $count = $DB->count_records_select(user::TABLE, $where);
        $users = $DB->get_records_select(user::TABLE, $where, null, $sortclause, '*', $pagenum*$perpage, $perpage);

        return array($users, $count);
    }

    protected function get_assigned_records($filter) {
        global $DB;

        $context = $this->get_context();
        $id = required_param('id', PARAM_INT);

        $pagenum = optional_param('page', 0, PARAM_INT);
        $perpage = 30;

        $sort = optional_param('sort', 'name', PARAM_ACTION);
        $order = optional_param('dir', 'ASC', PARAM_ACTION);
        if ($order != 'DESC') {
            $order = 'ASC';
        }

        static $sortfields = array(
            'name' => array('usr.lastname', 'usr.firstname'),
            'idnumber' => 'usr.idnumber',
            'country' => 'usr.country',
            'language' => 'usr.language',
            'timecreated' => 'curass.timecreated'
            );
        if (!array_key_exists($sort, $sortfields)) {
            $sort = key($sortfields);
        }
        if (is_array($sortfields[$sort])) {
            $sortclause = implode(', ', array_map(create_function('$x', "return \"\$x $order\";"), $sortfields[$sort]));
        } else {
            $sortclause = "{$sortfields[$sort]} $order";
        }

        $sql = 'SELECT curass.id, usr.id AS userid, usr.firstname, usr.lastname, usr.idnumber, usr.country, usr.language, curass.timecreated
                FROM {'.curriculumstudent::TABLE.'} curass
                JOIN {'.user::TABLE.'} usr on curass.userid = usr.id
                WHERE curass.curriculumid='.$id;
        $where = 'id IN (SELECT userid FROM {'.curriculumstudent::TABLE.'} WHERE curriculumid='.$id.')';

        /* TO-DO: re-enable this once I know how it's done
        $extrasql = $filter->get_sql_filter();
        if ($extrasql) {
            $where .= ' AND '.$extrasql;
            $sql .= ' AND '.$extrasql;
        }
        */

        $sql .= ' ORDER BY '.$sortclause;

        $count = $DB->count_records_select(user::TABLE, $where);
        $users = $DB->get_records_sql($sql, array($sort=>$order), $pagenum*$perpage, $perpage);

        return array($users, $count);
    }

    function get_records_from_selection($record_ids) {
        global $DB;
        $usersstring = implode(',', $record_ids);
        $records = $DB->get_records_select(user::TABLE, "id in ($usersstring)");
        return $records;
    }

    protected function create_selection_table($records, $baseurl) {
        $records = $records ? $records : array();
        $columns = array('_selection' => array('header' => ''),
                         'idnumber' => array('header' => get_string('idnumber','elis_program')),
                         'name' => array('header' => get_string('name','elis_program')),
                         'country' => array('header' => get_string('country','elis_program')),
                         'language' => array('header' => get_string('user_language','elis_program')));
        if (!$this->is_assigning()) {
            $columns['timecreated'] = array('header' => get_string('registered_date','elis_program'));
        }
        return new curriculum_user_selection_table($records, $columns,
                                                   new moodle_url($baseurl));
    }

}

class curriculum_user_selection_table extends selection_table {
    function __construct(&$items, $columns, $pageurl, $decorators=array()) {
        global $USER;

        parent::__construct($items, $columns, $pageurl, $decorators);
        $id = required_param('id', PARAM_INT);
        if (!curriculumpage::_has_capability('block/curr_admin:curriculum:enrol', $id)) {
            $context = cm_context_set::for_user_with_capability('cluster', 'block/curr_admin:curriculum:enrol_cluster_user', $USER->id);

            $allowed_clusters = array();

            //get the clusters assigned to this curriculum
            $clusters = clustercurriculum::get_clusters($id);
            if(!empty($clusters)) {
                foreach($clusters as $cluster) {
                    if($context->context_allowed($cluster->clusterid, 'cluster')) {
                        $allowed_clusters[] = $cluster->id;
                    }
                }
            }
            $this->allowed_clusters = $allowed_clusters;
        }
    }

    function get_item_display_name($column, $item) {
        return fullname($item);
    }

    function get_item_display_timecreated($column, $item) {
        return get_date_item_display($column, $item);
    }

    function get_item_display__selection($column, $item) {
        global $DB;

        $userid = isset($item->userid) ? $item->userid : $item->id;
        if (isset($this->allowed_clusters)) {
            if (empty($this->allowed_clusters) || !$DB->record_exists_select(clusterassignment::TABLE, "userid = {$userid} AND clusterid IN (".implode(',',$this->allowed_clusters).')')) {
                return '';
            }
        }
        return parent::get_item_display__selection($column, $item);
    }
}
