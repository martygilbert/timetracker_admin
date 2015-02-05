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
 * This form will allow a supervisor or administrator to reject a timesheet 
 *
 * @package    Block
 * @subpackage TimeTracker
 * @copyright  2011 Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once('../../config.php');
require_once('lib.php');
require_once('../timetracker/lib.php');
require('timetracker_admin_timesheetreject_form.php');

global $CFG, $COURSE, $USER;

require_login();

$courseid       = required_param('id', PARAM_INT); 
$timesheetid    = required_param('timesheetid', PARAM_INT);
$transid        = optional_param('transid', -1, PARAM_INT);

$timesheet = $DB->get_record('block_timetracker_timesheet', array('id'=>$timesheetid));

$rejcourseid = $timesheet->courseid;
$rejuserid = $timesheet->userid;

$urlparams['id']            = $courseid;
$urlparams['transid']       = $transid;
$urlparams['timesheetid']   = $timesheetid;

$rejecturl = new moodle_url($CFG->wwwroot.
    '/blocks/timetracker_admin/timesheetreject.php',$urlparams);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = $PAGE->context;

$catcontext = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);


$canmanage = false;
if (has_capability('block/timetracker:manageworkers', $catcontext)) { //supervisor
    $canmanage = true;
}

$canview = false;
if (has_capability('block/timetracker_admin:viewtransactions', $catcontext)) { 
    $canview = true;
}

if(!$canmanage && !$canview){
    print_error('nocatpermission', 'block_timetracker_admin');
}


$PAGE->set_url($rejecturl);
    $PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('rejecttstitle','block_timetracker'));
$PAGE->set_heading(get_string('rejecttstitle','block_timetracker'));

$workerrecord = $DB->get_record('block_timetracker_workerinfo', 
    array('id'=>$rejuserid,'courseid'=>$rejcourseid));

if(!$workerrecord){
    echo "NO WORKER FOUND!";
    die;
}

$strtitle = get_string('rejecttstitle','block_timetracker',
    $workerrecord->firstname.' '.$workerrecord->lastname); 

$PAGE->set_title($strtitle);

$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add($strtitle);

$mform = new timetracker_admin_timesheetreject_form($timesheetid, $courseid, $transid); 

if($transid == -1){
    $reurl = new moodle_url($CFG->wwwroot.
        '/blocks/timetracker_admin/vieweligible.php', $urlparams);
    $reurl->remove_param(array('transid'));
} else {
    $reurl = new moodle_url($CFG->wwwroot.
        '/blocks/timetracker_admin/transactiondetail.php', $urlparams);
}

if ($mform->is_cancelled()){ 
    redirect($reurl);

} else if ($formdata=$mform->get_data()){

    // Data collection to send email to supervisor(s)
    $from = $USER;
    $subject = get_string('tssubject','block_timetracker');

    //***** HTML *****//
    $messagehtml = $workerrecord->firstname.':';
    $messagehtml .= get_string('br2','block_timetracker');
    $messagehtml .= get_string('remessage1','block_timetracker',
        date("n/j/Y g:i:sa", $timesheet->workersignature));
    $messagehtml .= get_string('remessagesup','block_timetracker');
    $messagehtml .= get_string('br1','block_timetracker');
    $messagehtml .= $formdata->message['text']; 
    $messagehtml .= get_string('br2','block_timetracker');
    $messagehtml .= get_string('instruction','block_timetracker');

    //Set all units to be editable by user and supervisor
    $DB->set_field('block_timetracker_workunit','canedit',1,
        array('timesheetid'=>$timesheet->id));

    //Reset all of the units to without a timesheet id
    $DB->set_field('block_timetracker_workunit','timesheetid', 0,
        array('timesheetid'=>$timesheet->id));

    //Remove the timesheet entry from the table
    $DB->delete_records('block_timetracker_timesheet',array('id'=>$timesheet->id));
    
    //Build the email and send to the worker
    $messagetext = format_text_email($messagehtml, FORMAT_HTML);
    $user = $DB->get_record('user',array('id'=>$workerrecord->mdluserid));

    /*
    $mailok = email_to_user($user, $from, $subject, $messagetext, $messagehtml); 
    if(!$mailok){
        print_error("Error sending message to $user->firstname $user->lastname");
    } 
    */

    add_to_log($COURSE->id, 'timetracker_admin', 'delete timesheet', 
        'transactiondetail.php?id='.$COURSE->id.'&transid='.$transid,
        'Rejected timesheet for '.$user->firstname.' '.$user->lastname);

    //send message to any supervisors
    $coursecontext = get_context_instance(CONTEXT_COURSE, $timesheet->courseid);
    $supervisors = get_enrolled_users($coursecontext, 'mod/assignment:grade');
    //$mailedto='';
    /*
    foreach($supervisors as $supervisor){
        //email them the same email
        $mailok = email_to_user($supervisor, $from, $subject, $messagetext, $messagehtml); 
        if(!$mailok){
            print_error("Error sending message to $user->firstname $user->lastname");
        } else {
            $mailedto.=$supervisor->firstname.' '.$supervisor->lastname.' ';
        }
    }
    */

    $mailok = email_to_user_wcc(array_merge(array($user), $supervisors), 
        $from, array($USER), $subject, $messagetext, $messagehtml);
    if(!$mailok){
        print_error("Error sending rejection message to worker and supervisor(s)");
    }

    /*
    $messagetext = 'The below email sent to the worker and the following supervisors:\n'.$mailedto.'\n\n**\n'.$messagetext;
    $messagehtml = '<p>The below email was sent to the following supervisors:<br />'.
        $mailedto.'</p>**<br />'. $messagehtml;
    //send a copy of the email to you. Too ridiculous to use AddCC, I think.
    $mailok = email_to_user($USER, $from, $subject, $messagetext, $messagehtml); 
    if(!$mailok){
            print_error("Error sending a copy of the message to you.");
    } 
    */

    $status = get_string('remessagesent','block_timetracker_admin');

    redirect($reurl, $status, 1);

} else {
    //form is shown for the first time
    
    echo $OUTPUT->header();
    $maintabs = get_admin_tabs($urlparams, $canmanage, $courseid);

    $tabs = array($maintabs);
    
    print_tabs($tabs);

    $mform->display();
    echo $OUTPUT->footer();
}
