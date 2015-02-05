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

class timetracker_admin_reportgenerator_form extends moodleform {

    function timetracker_admin_reportgenerator_form($reportstart=0, $reportend=0){
        
        //$this->context = $context;
        $this->reportstart = $reportstart;
        $this->reportend = $reportend;
        parent::__construct();
    }

    function definition() {
        global $CFG, $DB, $COURSE, $OUTPUT;

        $mform =& $this->_form; // Don't forget the underscore! 

        $categoryinfo = $DB->get_record('course_categories', 
            array('id'=>$COURSE->category));

        if(!$categoryinfo)
            $mform->addElement('header', 'general','Report Generator');
        else 
            $mform->addElement('header', 'general','Report Generator for: '.
                $categoryinfo->name);
        
        $now = time();
        if($this->reportstart == 0 || $this->reportend == 0){
            $starttime = usergetdate($now);
            $starttime_mid = make_timestamp($starttime['year'], 
                $starttime['mon'] - 1, $starttime['mday']);
            $this->reportstart = $starttime_mid;

            $endtime = usergetdate($now);
            $endtime_mid = make_timestamp($endtime['year'], 
                $endtime['mon'], $endtime['mday']);
            $this->reportend = $endtime_mid;
        } 

        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'submit', 'Submit');

        $mform->addElement('html',
            '<br />Please provide a date and time range for the report(s) you
            wish to generate:');
        //Start date
        $mform->addElement('date_selector','reportstart','Start Date: ',
            array('optional'=>false, 'step'=>1));    
        $mform->setDefault('reportstart',$this->reportstart);

        //End date
        $mform->addElement('date_selector','reportend','End Date: ',
            array('optional'=>false, 'step'=>1));    
        $mform->setDefault('reportend',$this->reportend);

        $mform->addElement('hidden','id', $COURSE->id);
        $mform->setType('id', PARAM_INT);

        $reportslist = array();
        $reportslist['allts'] = 'Timesheet report (all)';
        $reportslist['notsub'] = 'Timesheet report (not submitted)';
        $reportslist['sub'] = 'Timesheet report (submitted)';
        $mform->addElement('select', 'type', 'Report type:', $reportslist);


        $outputs = array();
        $outputs['screen']          = 'View on screen'; 
        $outputs['downloadascsv']   = 'CSV for Excel import';
        $mform->addElement('select', 'output', 
            $OUTPUT->pix_icon('i/new', 'New').' Output:', $outputs);

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

    }   

    function validation ($data, $files){
        $errors = array();
        if($data['reportstart'] > $data['reportend']){
            $errors['reportstart'] = 'The begin date cannot be after the end date.';
        } else if ($data['reportend'] < $data['reportstart']){
            $errors['reportend'] = 'The end date cannot be before the begin date.';
        }
        return $errors;
    }

}
