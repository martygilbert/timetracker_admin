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
 * Allow admins to see the work units over X hours
 *
 * @package    TimeTracker
 * @copyright  Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once ($CFG->libdir.'/formslib.php');

class timetracker_admin_longworkunits_form extends moodleform {

    function timetracker_admin_reportgenerator_form(){
        parent::__construct();
    }

    function definition() {
        global $CFG, $DB, $COURSE;

        $mform =& $this->_form; // Don't forget the underscore! 

        $categoryinfo = $DB->get_record('course_categories', 
            array('id'=>$COURSE->category));

        if(!$categoryinfo)
            $mform->addElement('header', 'general','Find long workunits');
        else 
            $mform->addElement('header', 'general','Find long workunits for: '.
                $categoryinfo->name);
        

        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'submit', 'Submit');

        $hours = array();
        for($i = 8; $i < 169; $i+=2){
            $hours[$i] = $i;
        }
        $mform->addElement('select', 'minduration', 'Workunits over (hours):', $hours);
        $mform->setDefault('minduration', 24);

        $mform->addElement('hidden','id', $COURSE->id);
        $mform->setType('id', PARAM_INT);

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

    }   

}
