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
 * This form will allow the worker to submit an alert and correction to the supervisor of an error in a work unit.
 * The supervisor will be able to approve or deny the correction.
 *
 * @package    TimeTracker
 * @copyright  Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once ($CFG->libdir.'/formslib.php');

class timetracker_admin_mailusers_form  extends moodleform {

    function timetracker_admin_mailusers_form($courseid, $target){
        
        $this->courseid = $courseid;
        $this->target = $target;
        parent::__construct();
    }

    function definition() {
        global $CFG, $USER, $DB, $COURSE;

        $mform =& $this->_form; // Don't forget the underscore! 
        
        $mform->addElement('hidden', 'id', $this->courseid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'target', $this->target);
        $mform->setType('target', PARAM_ALPHA);

        $mform->addElement('header', 'thisone', 'TimeTracker Email');

        $mform->addElement('text','subject', 'Message Subject');
        $mform->addRule('subject', null, 'required', null, 'client', 'false');
        $mform->setType('subject', PARAM_TEXT);

        $mform->addElement('editor', 'message', 'Email body');
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', null, 'required', null, 'client', 'false');

        $this->add_action_buttons(true, get_string('sendbutton', 'block_timetracker'));

    }   
    
}
