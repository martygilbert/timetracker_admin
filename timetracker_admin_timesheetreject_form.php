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
//require_once ('lib.php');

class timetracker_admin_timesheetreject_form  extends moodleform {

    function timetracker_admin_timesheetreject_form($timesheetid, $id, $transid){
        
        $this->timesheetid = $timesheetid;
        $this->id = $id;
        $this->transid = $transid;
        parent::__construct();
    }

    function definition() {
        global $CFG, $USER, $DB, $COURSE;

        $mform =& $this->_form; // Don't forget the underscore! 
        
        $timesheet = $DB->get_record('block_timetracker_timesheet', 
            array('id'=>$this->timesheetid));

        $courseid = $timesheet->courseid;
        $userid = $timesheet->userid;

        $userinfo = $DB->get_record('block_timetracker_workerinfo',
            array('id'=>$userid));
        
        if(!$userinfo){
            print_error('Worker info does not exist for workerinfo id of '.$this->userid);
        }

        $mform->addElement('hidden', 'timesheetid', $this->timesheetid);
        $mform->setType('timesheetid', PARAM_INT);

        $mform->addElement('hidden', 'transid', $this->transid);
        $mform->setType('transid', PARAM_INT);

        $mform->addElement('hidden', 'id', $this->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('html',
            get_string('headername', 'block_timetracker', 
            $userinfo->firstname.' '.$userinfo->lastname));

        $mform->addElement('html', get_string('headertimestamp', 'block_timetracker', 
            userdate($timesheet->workersignature, 
            get_string('datetimeformat', 'block_timetracker'))));

        $mform->addElement('html', '<br /><br />');

        $mform->addElement('editor', 'message', get_string('rejectreason','block_timetracker')); 
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', null, 'required', null, 'client', 'false');

        $mform->addElement('html', '</b>'); 
        $this->add_action_buttons(true, get_string('sendbutton', 'block_timetracker'));

    }   
}
