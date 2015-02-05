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
 * This form will allow administration to add a transaction.
 *
 * @package    Block
 * @subpackage TimeTracker
 * @copyright  2011 Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once("$CFG->libdir/formslib.php");
//require_once('lib.php');

class timetracker_admin_transfer_worker_form extends moodleform {
   function timetracker_admin_transfer_worker_form(){
       parent::__construct();
   }

    function definition() {
        global $COURSE, $DB;

        $mform =& $this->_form;
        $mform->addElement('header','general',
            get_string('transferworker','block_timetracker_admin'));
        
        $mform->addElement('hidden','id', $COURSE->id);
        $mform->addElement('hidden', 'categoryid', $COURSE->category);

        $courses = get_courses($COURSE->category, 'shortname ASC', 'c.id, c.shortname');

        //generate a list of Names/depts in one dropdown
        $workers = $DB->get_records_select('block_timetracker_workerinfo',
            'courseid IN ('.implode(',', array_keys($courses)).') ',
            array('deleted'=>0), 'lastname ASC,firstname ASC');

        //make worker array
        $workerdisp = array();
        foreach($workers as $worker){
            $course = $courses[$worker->courseid];
            $workerdisp[$worker->id] = $worker->lastname.', '.
                $worker->firstname.' - '.$course->shortname;
        }

        $select = &$mform->addElement('select', 'origworker', get_string('workerstotrans', 
            'block_timetracker_admin'), $workerdisp, 'size="20"');
        $select->setMultiple(true);
        $mform->addRule('origworker', 'Required', 'required');

        $coursedisp = array();
        foreach($courses as $course){
            $coursedisp[$course->id] = $course->shortname;
        }

        //generate a list of depts in another dropdown
        $mform->addElement('select', 'todept', get_string('todepart', 
            'block_timetracker_admin'), $coursedisp);
        $mform->addRule('todept', 'Required', 'required');

        $this->add_action_buttons(false, get_string('transferworker',
            'block_timetracker_admin'));

    }

}
?>
