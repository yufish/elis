<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * Determines whether the current plugin supports the supplied feature
 *
 * @param string $feature A feature description, either in the form
 *                        [entity] or [entity]_[action]
 *
 * @return mixed An array of actions for a supplied entity, an array of
 *               required fields for a supplied action, or false on error
 */
function rlipimport_multiple_supports($feature) {
    global $CFG;
    require_once(dirname(__FILE__).'/multiple.class.php');

    $data_plugin = new rlip_importplugin_multiple();

    //delegate to class method
    return $data_plugin->plugin_supports($feature);
}