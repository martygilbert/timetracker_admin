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
$sort = optional_param('sort', 'name', PARAM_ALPHA);

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
$index = new moodle_url($CFG->wwwroot.'/blocks/timetracker_admin/vieweligible.php', $urlparams);

$PAGE->set_url($index);

$strtitle = 'View eligible timesheets';
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');


echo $OUTPUT->header();

$maintabs = get_admin_tabs($urlparams, $canmanage, $courseid);
$tabs = array($maintabs);
print_tabs($tabs);

$html = '';

$courses = get_courses($COURSE->category, 'fullname ASC', 'c.id,c.shortname');
if($courses){
    $sql = 'SELECT sheet.*,firstname,lastname,maxtermearnings,'.
        '(reghours + othours) AS SUM_HOURS,(regpay + otpay) AS SUM_PAY '. 
        'from '.$CFG->prefix.
        'block_timetracker_timesheet as sheet,'.
        $CFG->prefix.'block_timetracker_workerinfo as worker '. 
        'where sheet.userid=worker.id AND transactionid=0 AND '.
        'workersignature != 0 AND supervisorsignature != 0 AND '.
        'sheet.courseid in (';
    $list = implode(",", array_keys($courses));   
    $sql .= $list.')';

    if($sort == 'name'){
        $sql .= ' ORDER BY lastname,firstname';
    } else if ($sort == 'hours'){
        $sql .= ' GROUP BY sheet.id ORDER BY SUM_HOURS DESC';
    } else if ($sort == 'pay'){
        $sql .= ' GROUP BY sheet.id ORDER BY SUM_PAY DESC';
    }
    $timesheets = $DB->get_records_sql($sql);
} else {
    print_error('nocourseserror','block_timetracker_admin');
}
            
if($timesheets){
    $sortparams['id'] = $COURSE->id;
    $sorturl = new moodle_url($CFG->wwwroot.
        '/blocks/timetracker_admin/vieweligible.php', $sortparams);

    $html .= '<table align="center" border="1" cellspacing="10px" cellpadding="5px" width="95%">';
    $row = '<tr><th colspan="9">Eligible Timesheets</th></tr>';
    $html .= $row;

    $row = '<tr>';

    $row .= '<td style="font-weight: bold;">Worker ';
    if($sort == 'name'){
        $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
    } else {
        $sorturl->params(array('sort'=>'name'));
        $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort', 'sort'));
    } 
    $row .= '</td>';

    $row .= '<td style="font-weight: bold;">Supervisor</td>';

    $row .= '<td style="font-weight: bold; text-align: right">Hours ';
    if($sort == 'hours'){
        $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
    } else {
        $sorturl->params(array('sort'=>'hours'));
        $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort', 'sort'));
    }
    $row .= '</td>';

    $row .= '<td style="font-weight: bold; text-align: right">Pay ';
    if($sort == 'pay'){
        $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
    } else {
        $sorturl->params(array('sort'=>'pay'));
        $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort', 'sort'));
    }
    $row .= '</td>';

    $row .= '<td style="font-weight: bold;">Range</td>
        <td style="font-weight: bold; text-align: right">Term</td>
        <td style="font-weight: bold; text-align: right">Submitted</td>
        <td style="font-weight: bold; text-align: right">Overall</td>
        <td style="font-weight: bold; text-align: center">Actions</td>
    </tr>';
    $html .= $row;
    foreach ($timesheets as $timesheet){
        $row = ''; 
        $limitsql = 'SELECT MIN(timein) as min, MAX(timeout) as max from '.
            $CFG->prefix.'block_timetracker_workunit WHERE timesheetid='.
            $timesheet->id;
        $limits = $DB->get_record_sql($limitsql);
    
        if($timesheet->otpay > 0){
            $row .= '<tr style="background: yellow">';
        } else {
            $row .= '<tr>';
        }
    
        $row .= '<td>';
        
        $workername = $timesheet->lastname.', '.$timesheet->firstname;
        $row .= truncatestr($workername);
        $row .= '</td>';
    
        $row .= '<td>';
        $super = $DB->get_record('user', array('id'=>$timesheet->supermdlid));
        if(!$super){
            $name = 'Undefined';
        } else {
            $name = $super->lastname.', '.$super->firstname;
            $name = truncatestr($name);
        }
        $row .= $name;
        $row .= '</td>';
    
        $hours = 0;
        $pay = 0;
        $hours += $timesheet->reghours;
        $hours += $timesheet->othours;
        $pay += $timesheet->regpay;
        $pay += $timesheet->otpay;
    
        $row .= '<td style="text-align: right">';
        $row .= number_format(round($hours,3),3);
        $row .= '</td><td style="text-align: right">';
        $row .= '$'.number_format(round($pay,2),2);
        $row .= '</td>';


        $row .= '<td>';
        $row .=
            userdate($limits->min, 
            get_string('simpledate', 'block_timetracker')).'-'.
            userdate($limits->max, 
            get_string('simpledate', 'block_timetracker'));
        $row .='</td>';


        //Submitted/total lifetime earnings 
        $total      = get_total_earnings($timesheet->userid, $timesheet->courseid);
        $official   = get_total_earnings($timesheet->userid, $timesheet->courseid, true); 
        $term       = get_earnings_this_term($timesheet->userid,
            $timesheet->courseid, $limits->max, true);

        if($timesheet->maxtermearnings > 0 &&
            ($term + $pay) > $timesheet->maxtermearnings){
            $earnings_style = 'text-align: right; background: #ff9f17;';
        } else {
            $earnings_style = 'text-align: right;';
        }

        $row .= '<td style="'.$earnings_style.'">';
        $row .= '$'.number_format($term, 2);
        $row .= '</td>';

        $row .= '<td style="text-align: right">';
        $row .= '$'.number_format($official, 2);
        $row .= '</td>';

        $row .= '<td style="text-align: right">';
        $row .= '$'.number_format($total, 2);
        $row .= '</td>';
        
        $row .= '<td style="text-align: center">';

        $viewparams['id'] = $timesheet->courseid;
        $viewparams['userid'] = $timesheet->userid;
        $viewparams['timesheetid'] = $timesheet->id;
        $viewurl = 
            new moodle_url($CFG->wwwroot.
                '/blocks/timetracker/timesheet_fromid.php', $viewparams);
        $viewaction = 
            $OUTPUT->action_icon($viewurl, new pix_icon('date','View Timesheet',
            'block_timetracker'));
        
        $rejectparams['id']             = $timesheet->courseid;
        $rejectparams['timesheetid']    = $timesheet->id;
        $rejecturl = 
            new moodle_url($CFG->wwwroot.
            '/blocks/timetracker_admin/timesheetreject.php', $rejectparams);
    
        $rejecticon = new pix_icon('delete', 
            get_string('reject'),'block_timetracker');
        $rejectaction = $OUTPUT->action_icon($rejecturl, $rejecticon,
            new confirm_action(get_string('rejectts','block_timetracker_admin')));
    
        $row .= $viewaction;
        $row .= ' ';
        $row .= $rejectaction;
        $row .= '</tr>';
        $html .= $row;
    }
    
    $html .= '</table>';
    
} else {
    echo '<br />No eligible timesheets found for this category<br />'; 
}
    
echo $html;

echo $OUTPUT->footer();
