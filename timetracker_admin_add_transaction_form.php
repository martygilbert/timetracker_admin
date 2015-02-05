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

class timetracker_admin_add_transaction_form extends moodleform {
   function timetracker_admin_add_transaction_form(){
       parent::__construct();
   }

    function definition() {
        global $COURSE, $USER;

        $mform =& $this->_form;
        $mform->addElement('header','general',
            get_string('addtransactionheader','block_timetracker_admin'));
        
        $mform->addElement('hidden','id', $COURSE->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'mdluserid', $USER->id);
        $mform->setType('mdluserid', PARAM_INT);
        $mform->addElement('hidden', 'categoryid', $COURSE->category);
        $mform->setType('categoryid', PARAM_INT);

        $mform->addElement('text', 'description', 'Description'); 
        $mform->setType('description', PARAM_TEXT);
        $mform->addRule('description', null, 'required', null, 'server', 'false');

        $this->add_action_buttons(false, get_string('createtrans','block_timetracker_admin'));

    }

    function validation($data, $files){
    }
}
?>
