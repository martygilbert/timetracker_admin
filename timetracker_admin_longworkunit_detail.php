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
 * This form will allow administration to batch sign timesheets electronically and export to payroll.
 *
 * @package    Block
 * @subpackage TimeTracker
 * @copyright  2011 Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once("$CFG->libdir/formslib.php");
require_once('../timetracker/lib.php');

class timetracker_admin_longworkunit_detail extends moodleform {
    function timetracker_admin_longworkunit_detail ($minduration, $canmanage=false){

       $this->canmanage     = $canmanage;
       $this->minduration   = $minduration;
       parent::__construct();
    }

    function definition() {
        global $CFG, $DB, $COURSE;

        $mform =& $this->_form;
        
        $mform->addElement('hidden', 'minduration', $this->minduration);
        $mform->setType('minduration', PARAM_INT);

        $mform->addElement('hidden', 'id', $COURSE->id);
        $mform->setType('id', PARAM_INT);

        $longunits = find_long_workunits($this->minduration);
        $longpending = find_long_workunits($this->minduration, true);
        if(sizeof($longpending) == 0 && sizeof($longunits) == 0){
            $mform->addElement('html', 
                'No workunits longer than '.$this->minduration.' hours');
            return;
        }

        $mform->addElement('header', 'general', 'Long workunits');
        $this->add_checkbox_controller(1);
        $mform->addElement('html', get_info_table_header($this->canmanage));
        add_info_table($mform, $longunits, $this->minduration, $this->canmanage, 1);
        $mform->addElement('html', '</table>');
        
        if($this->canmanage){
            $buttonarray=array();
            $buttonarray[] =
                &$mform->createElement('submit', 'alertunitsbutton', 'Alert Units');
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        }

        $mform->addElement('header', 'general', 'Long pending units');
        $this->add_checkbox_controller(2);
        $mform->addElement('html', get_info_table_header($this->canmanage, true));
        add_info_table($mform, $longpending, $this->minduration, $this->canmanage, 2);
        $mform->addElement('html', '</table>');
        
        if($this->canmanage){
            $buttonarray=array();
            $buttonarray[] =
                &$mform->createElement('submit', 'alertpendingunitsbutton', 
                    'Alert Pending Units');
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        }
    }

}

function find_long_workunits($minduration, $pending = false){
    global $COURSE, $CFG, $DB;
    $courselist = get_courses($COURSE->category, 'fullname ASC', 'c.id, c.shortname');
    $limit = $minduration * 3600;
    $jitter = 60;
    if(!$pending){
        $sql = 'SELECT unit.*, firstname, lastname, dept, supervisor, mdluserid from '.
            $CFG->prefix.'block_timetracker_workunit as unit, '.
            $CFG->prefix.'block_timetracker_workerinfo as info WHERE '.
            'unit.courseid in ('.implode(',', array_keys($courselist)).') AND '.
            'unit.userid = info.id AND unit.timeout - unit.timein > '.($limit-$jitter).
            ' ORDER BY lastname,firstname';
    } else {
        $sql = 'SELECT unit.*, firstname, lastname, dept, supervisor, mdluserid from '.
            $CFG->prefix.'block_timetracker_pending as unit, '.
            $CFG->prefix.'block_timetracker_workerinfo as info WHERE '.
            'unit.courseid in ('.implode(',', array_keys($courselist)).') AND '.
            'unit.userid = info.id AND '.time().' - unit.timein > '.($limit-$jitter).
            ' ORDER BY lastname,firstname';
    }
    $longunits = $DB->get_records_sql($sql);
    return $longunits;
}

function get_info_table_header($canmanage=false, $pending = false){
    $html = '';
    $html .= '<table border="1" width="95%">
            <tr>
                <td style="font-weight: bold;">Worker</td>
                <td style="font-weight: bold;">Supervisor</td>
                <td style="font-weight: bold;">Department</td>';
    if(!$pending)
        $html .='<td style="font-weight: bold;">In/Out</td>';
    else
        $html .='<td style="font-weight: bold;">Clock-in</td>';

    $html .= '<td style="font-weight: bold;">Duration</td>';
    if($canmanage)
        $html .= '<td style="font-weight: bold; text-align:center">Select</td>';
    $html .= '</tr>';
    return $html;
}

function add_info_table($mform, $longunits, $minduration, $canmanage=false,
    $checkboxcontroller=1){
    global $CFG, $DB, $COURSE, $OUTPUT;
    if($longunits){
    
        $course_link = new moodle_url($CFG->wwwroot.'/blocks/timetracker/index.php');
        $user_link = new moodle_url($CFG->wwwroot.'/user/view.php');

        foreach ($longunits as $longunit){
    
            $mform->addElement('html', '<tr>');
    
            //Worker
            $mform->addElement('html','<td style="vertical-align: top">');
            $user_link->params(array('id'=>$longunit->mdluserid,
                'course'=>$longunit->courseid));
            $mform->addElement('html', 
                $OUTPUT->action_link($user_link, truncatestr($longunit->lastname.', '.
                $longunit->firstname)));
            $mform->addElement('html','</td>');
        
            //Supervisor(s)
            $mform->addElement('html', '<td style="vertical-align: top">'.
                truncatestr($longunit->supervisor));
            $mform->addElement('html', '</td>');
        
            //Dept name 
            $course_link->params(array('id'=>$longunit->courseid));
            $mform->addElement('html', '<td style="vertical-align: top">');
            $mform->addElement('html', $OUTPUT->action_link($course_link, 
                truncatestr($longunit->dept, 20)));
            $mform->addElement('html', '</td>');

            //in/out
            $mform->addElement('html', '<td style="vertical-align: top">');
            $mform->addElement('html', userdate($longunit->timein,
                get_string('datetimeformat','block_timetracker')));

            if(isset($longunit->timeout)){
                $mform->addElement('html',' to <br />'.userdate($longunit->timeout,
                    get_string('datetimeformat','block_timetracker')));
            }
            $mform->addElement('html', '</td>');

            //Duration
            $mform->addElement('html', '<td style="vertical-align: top">');
            if(isset($longunit->timeout)){ //full unit
                $mform->addElement('html', get_hours($longunit->timeout -
                    $longunit->timein, $longunit->courseid));
            } else { //pending
                $mform->addElement('html', get_hours(time() -
                    $longunit->timein, $longunit->courseid));
            }
            $mform->addElement('html', ' hour(s)</td>');


            if($canmanage){
                //action link 
                $mform->addElement('html', 
                    '<td style="text-align:center; vertical-align:top">');
                $mform->addElement('advcheckbox', 'longunitid'.
                    $checkboxcontroller.'['.$longunit->id.']','',
                    null, array('group'=>$checkboxcontroller));
                $mform->addElement('html', '</td>');
            }
            
            $mform->addElement('html', '</td></tr>');
        }
    }
}
?>
