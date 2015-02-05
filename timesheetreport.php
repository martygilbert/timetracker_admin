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
require_once('lib.php');

require_login();

$courseid       = required_param('id', PARAM_INT);
$type           = required_param('type', PARAM_ALPHA);
$reportstart    = required_param('reportstart', PARAM_INT);
$reportend      = required_param('reportend', PARAM_INT);
$output         = required_param('output', PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$urlparams = array();
$urlparams['id']            = $courseid;
$urlparams['type']          = $type;
$urlparams['reportstart']   = $reportstart;
$urlparams['reportend']     = $reportend;
$urlparams['output']        = $output;

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


$index = new moodle_url($CFG->wwwroot.
    '/blocks/timetracker_admin/timesheetreport.php', $urlparams);


$maintabs = get_admin_tabs($urlparams, $canmanage, $courseid);
$tabs = array($maintabs);
$strtitle = 'Timesheet Report';

if($output != 'downloadascsv'){
    $PAGE->set_url($index);

    $PAGE->set_title($strtitle);
    $PAGE->set_heading($strtitle);
    $PAGE->set_pagelayout('base');
    echo $OUTPUT->header();
    print_tabs($tabs);
}

$courselist = get_courses($COURSE->category, 'fullname ASC', 'c.id, c.shortname');
$repstart_disp = userdate($reportstart, get_string('dateformat', 'block_timetracker'));
$repend_disp = userdate($reportend, get_string('dateformat', 'block_timetracker'));

//$type is one of 'allts', 'notsub', 'sub'
if($type == 'sub'){ //just submitted
    $timesheets = get_submitted_timesheets($courselist, $reportstart, $reportend);
    $title = 'Submitted timesheets';
    $title .= ' - '.$repstart_disp.' to '.$repend_disp;
    if($output == 'downloadascsv'){
        if(sizeof($timesheets) > 0)
            print_csv($timesheets, $reportstart, $reportend, $courselist, $title);
        else
            show_error($index, $strtitle, $tabs, "No units found that match this search");
    } else {
        echo $OUTPUT->box_start();
        echo "<h2>$title</h2>";
        print_info_table($timesheets, $reportstart, $reportend, $courselist);
        echo $OUTPUT->box_end();
    }
} else if ($type == 'notsub'){ //not submitted
    $title= "Unsubmitted hours";
    $title .= ' - '.$repstart_disp.' to '.$repend_disp;
    $timesheets = get_workers_with_unsub($courselist, $reportstart, $reportend);

    if($output == 'downloadascsv'){
        if(sizeof($timesheets) > 0)
            print_csv($timesheets, $reportstart, $reportend, $courselist, $title);
        else 
            show_error($index, $strtitle, $tabs, "No units found that match this search");
    } else {
        echo $OUTPUT->box_start();
        echo "<h2>$title</h2>";
        print_info_table($timesheets, $reportstart, $reportend, $courselist);
        echo $OUTPUT->box_end();
    }
} else { //all

    if($output == 'downloadascsv'){
        $timesheets1 = get_submitted_timesheets($courselist, $reportstart, $reportend);
        $timesheets2 = get_workers_with_unsub($courselist, $reportstart, $reportend);
        $total = array_merge($timesheets1, $timesheets2);
        if(sizeof($total) > 0) {
            print_csv($total, $reportstart, $reportend, $courselist, 'All - '.
                $repstart_disp.' to '.$repend_disp);
        } else {
            show_error($index, $strtitle, $tabs, "No units found that match this search");
        }
    } else {
        $title = 'Submitted timesheets';
        $title .= ' - '.$repstart_disp.' to '.$repend_disp;
        echo $OUTPUT->box_start();
        echo "<h2>$title</h2>";
        $timesheets = get_submitted_timesheets($courselist, $reportstart, $reportend);
        print_info_table($timesheets, $reportstart, $reportend, $courselist);
        echo $OUTPUT->box_end();
    
        $title = 'Unsubmitted hours';
        $title .= ' - '.$repstart_disp.' to '.$repend_disp;
        echo $OUTPUT->box_start();
        echo "<h2>$title</h2>";
        $timesheets = get_workers_with_unsub($courselist, $reportstart, $reportend);
        print_info_table($timesheets, $reportstart, $reportend, $courselist);
        echo $OUTPUT->box_end();
    }

}

if($output != 'downloadascsv')
    echo $OUTPUT->footer();

/*****

    Functions

*****/

function print_csv($timesheets, $reportstart, $reportend, $courselist, $title){
    global $CFG, $DB; 

    $headers = array();
    $headers[] = 'Worker Last';
    $headers[] = 'Worker First';
    $headers[] = 'Email';
    $headers[] = 'Worker Sig';
    $headers[] = 'Supervisor';
    $headers[] = 'Supervisor Sig';
    $headers[] = 'Department';
    $headers[] = 'Status';
    $headers[] = 'Official Pay';

    $data = array();
    foreach ($timesheets as $timesheet){
        $row = array(); 
        
        $row[] = $timesheet->lastname;
        $row[] = $timesheet->firstname;
        $row[] = $timesheet->email;
        if(isset($timesheet->workersignature) && $timesheet->workersignature > 0){
            $row[] = userdate($timesheet->workersignature, 
                get_string('datetimeformat', 'block_timetracker'));
        } else {
            $row[] = 'Not signed';
        }

        $row[] = $timesheet->supervisor;


        if(isset($timesheet->supervisorsignature) && $timesheet->supervisorsignature > 0){
            $row[] = userdate($timesheet->supervisorsignature,
                get_string('datetimeformat', 'block_timetracker'));
        } else {
            $row[] = 'Not signed';
        }

        if(array_key_exists($timesheet->courseid, $courselist)){
            $course = $courselist[$timesheet->courseid];
            $row[] = $course->shortname;
        } else {
            $row[] = 'Unknown';
        }
    
        if(!isset($timesheet->workersignature)){
            //not a timesheet at all
            $row[] = 'Not submitted';
        } else if($timesheet->submitted > 0){
            //processed
            $row[] = 'Processed';
        } else if($timesheet->supervisorsignature > 0){
            //signed
            $row[] = 'Processing';
        } else if($timesheet->supervisorsignature == 0){
            //awaiting supervisor signature
            $row[] = 'Awaiting supervisor signature';
        }

        if(isset($timesheet->regpay) && isset($timesheet->otpay)){
            $row[] = $timesheet->regpay + $timesheet->otpay;
        } else {
            $row[] = 'N/A';
        }
        $data[] = $row;
    }
    print_report_csv($headers, $data, $title);
}


function get_submitted_timesheets($courselist, $reportstart, $reportend){
    global $CFG, $DB;
    $sql = 'SELECT * FROM '.
        $CFG->prefix.'block_timetracker_workerinfo info, '.
        $CFG->prefix.'block_timetracker_timesheet timesheet '.
        'WHERE timesheet.userid=info.id AND '.
        'supervisorsignature > 0 AND workersignature > 0 AND '. 
        '( '.
            '(workersignature BETWEEN '.$reportstart.' AND '.$reportend.')'.
            ' OR '.
            '(supervisorsignature BETWEEN '.$reportstart.' AND '.$reportend.')'.
        ') '.
        'AND info.courseid in ('.  implode(',', array_keys($courselist)).') '.
        'ORDER BY info.lastname, info.firstname';
    $timesheets = $DB->get_records_sql($sql);
    return $timesheets;

}

function print_info_table($timesheets, $reportstart, $reportend, $courselist){
    global $CFG, $DB, $COURSE, $OUTPUT;
    $html = '';
    if($timesheets){
        $html .= '<table border="1" width="95%">
                <tr>
                    <td style="font-weight: bold;">Worker<br />name</td>
                    <td style="font-weight: bold;">Supervisor<br />name(s)</td>
                    <td style="font-weight: bold;"><br />Department</td>
                    <td style="font-weight: bold;"><br />Status</td>
                    <td style="font-weight: bold; text-align: center"><br />Actions</td>
                </tr>';
    
        $course_link = new moodle_url($CFG->wwwroot.'/course/view.php');
        $user_link = new moodle_url($CFG->wwwroot.'/user/view.php');
        $ts_link = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker/timesheet_fromid.php');
        $report_link = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker/reports.php');
    
        foreach ($timesheets as $timesheet){
    
            if($timesheet->deleted == 1)
                $html .= '<tr style="text-decoration: line-through">';
            else
                $html .= '<tr>';
    

            //Worker
            $html .= '<td style="vertical-align: top">';
            $user_link->params(array('id'=>$timesheet->mdluserid,
                'course'=>$timesheet->courseid));
            $html .= $OUTPUT->action_link($user_link, $timesheet->lastname.', '.
                $timesheet->firstname);
            if(isset($timesheet->workersignature) && $timesheet->workersignature > 0){
                $html .= '<br />'.userdate($timesheet->workersignature,
                    get_string('datetimeformat', 'block_timetracker'));
            }
            $html .= '</td>';
        
            //Supervisor(s)
            $thiscoursecon = get_context_instance(CONTEXT_COURSE, $timesheet->courseid);
            $teachers = get_enrolled_users($thiscoursecon, 'mod/assignment:grade');
            $html .= '<td style="vertical-align: top">';
            if(isset($timesheet->supervisorsignature) && $timesheet->supervisorsignature > 0){
                $teacher = $DB->get_record_select('user', 'id='.$timesheet->supermdlid);
                $user_link->params(array('id'=>$teacher->id,
                    'course'=>$timesheet->courseid));
                $html .= $OUTPUT->action_link($user_link,
                    $teacher->lastname.', '.$teacher->firstname);
                $html .= '<br />'.userdate($timesheet->supervisorsignature,
                    get_string('datetimeformat', 'block_timetracker'));
            
            } else {
                foreach($teachers as $teacher){
                    $user_link->params(array('id'=>$teacher->id,
                        'course'=>$timesheet->courseid));
                    $html .= $OUTPUT->action_link($user_link,
                        $teacher->lastname.', '.$teacher->firstname).'<br />';
                }
                $html = substr($html,0,-6); //trim the last 'br' off
            }
            $html .= '</td>';
        
            //Dept name (course shortname)
            $html .= '<td style="vertical-align: top">';
            if(array_key_exists($timesheet->courseid, $courselist)){
                $course = $courselist[$timesheet->courseid];
                $course_link->params(array('id'=>$course->id));
                $html .= $OUTPUT->action_link($course_link, $course->shortname);
            } else {
                $html .= 'Unknown';
            }
            $html .= '</td>';
    
            //Status
            $html .= '<td style="vertical-align: top">';
            if(!isset($timesheet->workersignature)){
                //not a timesheet at all
                $html .= '<span style="color: red">Not submitted</span>';
            } else if($timesheet->submitted > 0){
                //processed
                $html .= 'Processed';
            } else if($timesheet->supervisorsignature > 0){
                //signed
                $html .= 'Processing';
            } else if($timesheet->supervisorsignature == 0){
                //awaiting supervisor signature
                $html .= 'Awaiting <br />supervisor signature';
            }
    
            $html .= '</td>';
    
    
            //action link
            $html .= '<td style="text-align:center; vertical-align:top">';
            if(isset($timesheet->workersignature) && $timesheet->workersignature > 0){
                $ts_link->params(array('id'=>$timesheet->courseid,
                    'userid'=>$timesheet->userid,
                    'timesheetid'=>$timesheet->id));
                $viewaction = 
                    $OUTPUT->action_icon($ts_link, new pix_icon('date','View Timesheet',
                    'block_timetracker'));
                $html .= $viewaction;
            } else {
                $report_link->params(array(
                    'id'            => $timesheet->courseid,
                    'userid'        => $timesheet->id,
                    'repstart'   => $reportstart,
                    'repend'     => $reportend
                    ));
                $html .= 
                    $OUTPUT->action_icon($report_link, new pix_icon('report','View report',
                        'block_timetracker'));
    
            }
            $html .= '</td>';
    
            $html .= '</tr>';    
        }
        $html .= '</table>';
    } else {
        echo '<br />No entries found for this category<br />'; 
    }

    echo $html;
}

function show_error($index, $strtitle, $tabs, $msg){
    global $OUTPUT, $PAGE;
    $PAGE->set_url($index);
    $strtitle = 'Timesheet Report';
    $PAGE->set_title($strtitle);
    $PAGE->set_heading($strtitle);
    $PAGE->set_pagelayout('base');
    echo $OUTPUT->header();
    print_tabs($tabs);
    echo $msg;
    echo $OUTPUT->footer();

}
