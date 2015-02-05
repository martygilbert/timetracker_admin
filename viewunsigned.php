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
// You should have received a copy of the GNU General Public License // along with Moodle.  If not, see <http://www.gnu.org/licenses/>.  
/**
 * This block will display a summary of hours and earnings for the worker.
 *
 * @package    TimeTracker
 * @copyright  Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once(dirname(__FILE__) . '/../../config.php');
//require_once('../timetracker/lib.php');
require_once('lib.php');

require_login();

$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = $PAGE->context;

global $DB, $CFG, $COURSE;


$catcontext = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);

$canmanage = false;
if (has_capability('block/timetracker_admin:managetransactions', $catcontext)) { 
    $canmanage = true;
}

$canview = false;
if (has_capability('block/timetracker_admin:viewtransactions', $catcontext)) { 
    $canview = true;
}

if(!$canmanage && !$canview){
    print_error('nocatpermission', 'block_timetracker_admin');
}


$urlparams = array();
$urlparams['id'] = $courseid;
$index = new moodle_url($CFG->wwwroot.'/blocks/timetracker_admin/viewunsigned.php', $urlparams);

$PAGE->set_url($index);

$strtitle = 'View unsigned timesheets';
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');


echo $OUTPUT->header();

$maintabs = get_admin_tabs($urlparams, $canmanage, $courseid);
$tabs = array($maintabs);
print_tabs($tabs);

$html = '';

$timesheets = get_unsigned_timesheets_by_category($COURSE->category);
if($timesheets){
    $html .= '<table border="1" width="95%">
            <tr>
                <td style="font-weight: bold;">Worker signature</td>
                <td style="font-weight: bold;">Supervisor name(s)</td>
                <td style="font-weight: bold;">Department</td>
                <td style="font-weight: bold;">Work Unit Range</td>
            </tr>';

    $course_link = new moodle_url($CFG->wwwroot.
        '/blocks/timetracker/supervisorsig.php');

    foreach ($timesheets as $ts){
        $worker = $DB->get_record('block_timetracker_workerinfo', array('id'=>$ts->userid));
        if(!$worker) continue;

        $limitsql = 'SELECT MIN(timein) as min,MAX(timeout) as max from '.
            $CFG->prefix.'block_timetracker_workunit WHERE timesheetid='.
            $ts->id;
        $limits = $DB->get_record_sql($limitsql);

        $html .= '<tr>';

        $html .= '<td>';
        $workername = truncatestr($worker->lastname.', '.$worker->firstname);
        $html .= userdate($ts->workersignature, 
            get_string('simpledate', 'block_timetracker'));
        $html .= ' '.$workername;
        $html .= '</td>';
    
        $thiscoursecon = get_context_instance(CONTEXT_COURSE, $ts->courseid);
        $teachers = get_enrolled_users($thiscoursecon, 'mod/assignment:grade');
        $html .= '<td>';
        foreach($teachers as $teacher){
            $html .= truncatestr($teacher->lastname.', '.$teacher->firstname).'<br />';
        }
        $html = substr($html,0,-6); //trim the last 'br' off
        $html .= '</td>';
    
        $tcourse = $DB->get_record('course', array('id'=>$ts->courseid));
        $html .= '<td>';
        if($tcourse){
            $course_link->params(array('id'=>$tcourse->id));
            $html .= $OUTPUT->action_link($course_link, $tcourse->shortname);
        } else {
            $html .= 'Unknown';
        }
        $html .= '</td>';

        $html .= '<td>';
        $html .= userdate($limits->min, '%m/%d/%y').' - '.
            userdate($limits->max, '%m/%d/%y');
        $html .= '</td>';

        $html .= '</tr>';    
    }
    $html .= '</table>';
} else {
    echo '<br />No unsigned timesheets found for this category<br />'; 
}
    
echo $html;

echo $OUTPUT->footer();
