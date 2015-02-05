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
$output = optional_param('output', 'screen', PARAM_ALPHA);

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
$index = new moodle_url($CFG->wwwroot.'/blocks/timetracker_admin/viewterminfo.php', $urlparams);

$PAGE->set_url($index);

$strtitle = 'View term earnings information';
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');


if($output != 'csv'){
    echo $OUTPUT->header();

    $maintabs = get_admin_tabs($urlparams, $canmanage, $courseid);
    $tabs = array($maintabs);
    print_tabs($tabs);
}

$html = '';

$courses = get_courses($COURSE->category, 'fullname ASC', 'c.id,c.shortname');

//find term start/end. How, when each course has a different term definition? Poor design
// :(

//BAD HACK
//If the day is between 01/01 and 05/15, set the term to spring 
//If the day is between 05/16 and 07/31, set the term to summer
//If the day is between 08/01 and 12/31, set the term to fall


$year = userdate(time(), "%Y");
$now = time();

$SPRING_START   = make_timestamp($year, 1, 1);
$SPRING_END     = make_timestamp($year, 5, 15, 23, 59, 59);

$SUMMER_START   = make_timestamp($year, 5, 15);
$SUMMER_END     = make_timestamp($year, 7, 31, 23, 59, 59);

$FALL_START     = make_timestamp($year, 8, 1);
$FALL_END       = make_timestamp($year, 12, 31, 23, 59, 59);

if($now >= $SPRING_START && $now <= $SPRING_END){
    $start  = $SPRING_START;
    $end    = $SPRING_END;
} else if ($now >= $SUMMER_START && $now <= $SUMMER_END){
    $start  = $SUMMER_START;
    $end    = $SUMMER_END;
} else {
  $start  = $FALL_START;
  $end    = $FALL_END;
}


if($courses){
    $sql = 'SELECT sheet.*,firstname,lastname,email,maxtermearnings,currpayrate,dept,'.
        'SUM(reghours + othours) AS SUM_HOURS,SUM(regpay + otpay) AS SUM_PAY '. 
        'from '.$CFG->prefix.
        'block_timetracker_timesheet as sheet,'.
        $CFG->prefix.'block_timetracker_workerinfo as worker '. 
        'WHERE sheet.userid=worker.id AND '.
        'transactionid != 0 AND '.
        'workersignature != 0 AND '.
        'supervisorsignature != 0 AND '.
        '(workersignature BETWEEN '.$start.' AND '.$end.') AND '.
        'sheet.courseid in (';
    $list = implode(",", array_keys($courses));   
    $sql .= $list.') GROUP BY sheet.userid';

    if($sort == 'name'){
        $sql .= ' ORDER BY lastname,firstname';
    } else if ($sort == 'pay'){
        $sql .= ' ORDER BY SUM_PAY DESC';
    } else if ($sort == 'maxterm'){
        $sql .= ' ORDER BY maxtermearnings DESC';
    } else if ($sort == 'dept'){
        $sql .= ' ORDER BY dept';
    }
    //error_log($sql);
    $timesheets = $DB->get_records_sql($sql);
} else {
    print_error('nocourseserror','block_timetracker_admin');
}

$headers = array();
$headers[] = 'Worker last';
$headers[] = 'Worker first';
$headers[] = 'Worker email';
$headers[] = 'Dept';
$headers[] = 'Max term';
$headers[] = 'Submitted';
$headers[] = 'Unsubmitted';
$headers[] = 'Remaining';
$headers[] = 'Pct complete';

$data = array();
            
if($timesheets){
    $sortparams['id'] = $COURSE->id;
    $sorturl = new moodle_url($CFG->wwwroot.
        '/blocks/timetracker_admin/viewterminfo.php', $sortparams);



    $index->params(array('output'=>'csv'));
    $html .= '<table align="center" border="1" cellspacing="10px" cellpadding="5px" width="95%">';
    $row = '<tr><th colspan="9">Eligible Timesheets '.
        $OUTPUT->action_link($index, '(Export to CSV)').'</th></tr>';
    $html .= $row;

    $row = '<tr>';

    //Header - worker name
    $row .= '<td style="font-weight: bold;">Worker ';
    if($sort == 'name'){
        $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
    } else {
        $sorturl->params(array('sort'=>'name'));
        $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort', 'sort'));
    } 
    $row .= '</td>';

    //Header - Department 
    $row .= '<td style="font-weight: bold;">Dept ';
    if($sort == 'dept'){
        $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
    } else {
        $sorturl->params(array('sort'=>'dept'));
        $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort', 'sort'));
    } 
    $row .= '</td>';


    //Header - Max Term Earnings 
    $row .= '
        <td style="font-weight: bold; text-align: right">Max Term ';
    if($sort == 'maxterm'){
        $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
    } else {
        $sorturl->params(array('sort'=>'maxterm'));
        $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort', 'sort'));
    }
    $row .= '</td>';

    //Header - Submitted earnings
    $row .= '<td style="font-weight: bold; text-align: right">Submitted ';
    if($sort == 'pay'){
        $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
    } else {
        $sorturl->params(array('sort'=>'pay'));
        $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort', 'sort'));
    }
    $row .= '</td>';

    //Header - Unsubmitted earnings
    $row .= '<td style="font-weight: bold; text-align: right">Unsubmitted</td>';
    
    //Header - Remining earnings
    $row .= '<td style="font-weight: bold; text-align: right">Remaining</td>';

    //Header - % complete
    $row .= '<td style="font-weight: bold; text-align: right">Pct complete</td>';

    $row .= '</tr>';
    $html .= $row;

    foreach ($timesheets as $timesheet){
        $rowdata = array();

        $rowdata[] = $timesheet->lastname;
        $rowdata[] = $timesheet->firstname;
        $rowdata[] = $timesheet->email;
        $rowdata[] = $timesheet->dept;
        $rowdata[] = $timesheet->maxtermearnings;
        $rowdata[] = $timesheet->sum_pay;


        //error_log(print_r($timesheet, true));
        //error_log(sizeof($timesheet));
        $row = ''; 
        $unsubsql = 'SELECT * from '.
            $CFG->prefix.'block_timetracker_workunit WHERE '.
            'userid='.$timesheet->userid.' AND '.
            'timesheetid=0 AND '.
            'timein > '.$start.' AND timeout < '.$end;

        $unsubunits = $DB->get_records_sql($unsubsql);
        
        //find earnings this term that have not been submitted
        $unsubpay = 0;
        foreach($unsubunits as $unsub){

            $unsubhours = get_hours(($unsub->timeout - $unsub->timein), $unsub->courseid);
            $unsubpay += ($unsubhours  * $unsub->payrate);
        }

        $leftToEarn = $timesheet->maxtermearnings - ($timesheet->sum_pay + $unsubpay);
        
        $rowdata[] = $unsubpay;
        $rowdata[] = $leftToEarn;

        //% complete
        $pctcomplete = (($unsubpay + ($timesheet->sum_pay))/$timesheet->maxtermearnings);
        //error_log($unsubpay.' '.$timesheet->sum_pay.' '.$timesheet->maxtermearnings);
        
        $rowdata[] = number_format($pctcomplete, 2);

        $data[] = $rowdata;

        if($output == 'csv') continue; //don't create the HTML

        if ($timesheet->maxtermearnings > 0 && $leftToEarn < 0){
            //over 
            $row .= '<tr style="background: #ff9f17">';
        } else if ($timesheet->maxtermearnings > 0 && $leftToEarn < 50){
            //danger
            $row .= '<tr style="background: yellow">';
        } else {
            $row .= '<tr>';
        }

        $report_link = new moodle_url($CFG->wwwroot.
                    '/blocks/timetracker/reports.php');

        $report_link->params(array(
            'id'=>$timesheet->courseid,
            'userid'=>$timesheet->userid));
    
        //COLUMN - WORKER'S NAME
        $row .= '<td>';
        $workername = $timesheet->lastname.', '.$timesheet->firstname;
        $row .= $OUTPUT->action_link($report_link, truncatestr($workername));
        //$row .= truncatestr($workername);
        $row .= '</td>';

        $report_link->remove_params('userid');

        //COLUMN - WORKER'S DEPT
        $row .= '<td>';
        $row .= $OUTPUT->action_link($report_link, truncatestr($timesheet->dept, 25));
        $row .= '</td>';
    
        //COLUMN - WORKER'S MAX TERM
        $row .= '<td style="text-align: right">';
        $row .= '$'.number_format($timesheet->maxtermearnings, 2);
        $row .= '</td>';
        
        //COLUMN - WORKER'S SUBMITTED PAY
        $row .='<td style="text-align: right">';
        $row .= '$'.number_format(round($timesheet->sum_pay,2),2);
        $row .= '</td>';

        //COLUMN - WORKER'S UN-SUBMITTED PAY
        $row .='<td style="text-align: right">';
        $row .= '$'.number_format($unsubpay, 2);
        $row .= '</td>';

        //COLUMN - $ REMAINING TO EARN
        $row .='<td style="text-align: right">';
        $row .= '$'.number_format(round($leftToEarn,2),2);
        $row .= '</td>';

        //COLUMN - % COMPLETE 
        $row .='<td style="text-align: right">';
        $row .= number_format($pctcomplete * 100, 2).'%';
        $row .= '</td>';

        $row .= '</tr>';

        $html .= $row;
    }
    
    $html .= '</table>';
    
} else {
    echo '<br />No eligible timesheets found for this category<br />'; 
}

if($output=='csv')
    return print_report_csv($headers, $data, 'Term Earnings Information');
else {
    echo $html;

    echo $OUTPUT->footer();
}
