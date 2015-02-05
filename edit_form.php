<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Form for editing TimeTrackerAdmin block instances.
 *
 * @package    Block
 * @subpackage TimeTrackerAdmin
 * @copyright  2012 Marty Gilbert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_timetracker_admin_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        global $CFG, $DB, $USER, $COURSE;
        
        // Fields for editing block contents.
        $mform->addElement('header', 'configheader', 
            get_string('categoryconfig','block_timetracker_admin'));
        $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
        if(has_capability('moodle/site:config', $context)){
    
            //THIS IS NEVER USED, CORRECT?
            $mform->addElement('text','config_workcategoryid',
                get_string('workcategorydesc','block_timetracker_admin'));
            $mform->setType('config_workcategoryid', PARAM_TEXT);
            $mform->setDefault('config_workcategoryid', 0);
        } else {
            $mform->addElement('html', 'You must be a supervisor to set the course category');    
        }

    }

    function validation ($data, $files){
        $errors = array();

        return $errors;
    }

}
