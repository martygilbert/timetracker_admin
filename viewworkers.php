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
require_once('../timetracker/lib.php');
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
$index = new moodle_url($CFG->wwwroot.'/blocks/timetracker_admin/viewworkers.php', $urlparams);

$PAGE->set_url($index);

$strtitle = 'View All Workers';
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');


echo $OUTPUT->header();

$maintabs = get_admin_tabs($urlparams, $canmanage, $courseid);
$tabs = array($maintabs);
print_tabs($tabs);

$html = '';

$courselist = get_courses($COURSE->category, 'fullname ASC', 'c.id, c.shortname');

$workers = $DB->get_records_select('block_timetracker_workerinfo', 
    'courseid in ('.implode(',', array_keys($courselist)).')',null, 
    'lastname ASC,firstname ASC');

if($workers){
    $sql  = 'SELECT userid, SUM(regpay + otpay) AS SUM_PAY from '.
        $CFG->prefix.'block_timetracker_timesheet WHERE '.
        'courseid in ('.implode(',', array_keys($courselist)).') '.
        'GROUP BY userid';

    $official = $DB->get_records_sql($sql);

    $html .= '<table border="1" width="95%">
            <tr>
                <td style="font-weight: bold;">Worker Name</td>
                <td style="font-weight: bold;">Supervisor name(s)</td>
                <td style="font-weight: bold;">Department</td>
                <td style="font-weight: bold; text-align: right">Submitted</td>
                <td style="font-weight: bold; text-align: right">Overall</td>
            </tr>';

    $course_link = new moodle_url($CFG->wwwroot.'/course/view.php');
    $user_link = new moodle_url($CFG->wwwroot.'/user/view.php');

    foreach ($workers as $worker){

        $html .= '<tr>';

        $html .= '<td>';
        $user_link->params(array('id'=>$worker->mdluserid,
            'course'=>$worker->courseid));
        $html .= $OUTPUT->action_link($user_link, $worker->lastname.', '.$worker->firstname);
        $html .= '</td>';
    
        $thiscoursecon = get_context_instance(CONTEXT_COURSE, $worker->courseid);
        $teachers = get_enrolled_users($thiscoursecon, 'mod/assignment:grade');
        $html .= '<td>';
        foreach($teachers as $teacher){
            $user_link->params(array('id'=>$teacher->id,
                'course'=>$worker->courseid));
            $html .= $OUTPUT->action_link($user_link,
                $teacher->lastname.', '.$teacher->firstname).'<br />';
        }
        $html = substr($html,0,-6); //trim the last 'br' off
        $html .= '</td>';
    
        $html .= '<td>';
        if(array_key_exists($worker->courseid, $courselist)){
            $course = $courselist[$worker->courseid];
            $course_link->params(array('id'=>$course->id));
            $html .= $OUTPUT->action_link($course_link, $course->shortname);
        } else {
            $html .= 'Unknown';
        }
        $html .= '</td>';

        $officialval=0;
        if(isset($official[$worker->id])){
            $officialval=$official[$worker->id]->sum_pay;
        } 

        $html .= '<td style="text-align: right;">$'.
            number_format($officialval, 2).'</td>';
        
        $html .= '<td style="text-align: right;">$'.
            number_format(get_total_earnings($worker->id, $worker->courseid), 2).'</td>';

        $html .= '</tr>';    
    }
    $html .= '</table>';
} else {
    echo '<br />No workers found for this category<br />'; 
}
    
echo $html;

echo $OUTPUT->footer();
