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
require('timetracker_admin_mailusers_form.php');

global $CFG, $COURSE, $USER;

require_login();

$courseid   = required_param('id', PARAM_INT); 
$target     = required_param('target', PARAM_ALPHA);

$repstart   = optional_param('repstart', 0, PARAM_INT);
$repend   = optional_param('repstart', 0, PARAM_INT);

$urlparams['id']        = $courseid;
$urlparams['target']    = $target;

$courseurl  = new moodle_url($CFG->wwwroot.
    '/course/view.php',$urlparams);
$courseurl->remove_params('target');

$thisurl    = new moodle_url($CFG->wwwroot.
    '/blocks/timetracker_admin/mailusers.php', $urlparams);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$PAGE->set_course($course);
$context = $PAGE->context;

$catcontext = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);

$canmanage = false;
if (has_capability('block/timetracker_admin:managetransactions', $catcontext)) { 
    $canmanage = true;
}

if(!$canmanage){
    print_error('nocatpermission', 'block_timetracker_admin');
}

if($target == 'all'){
    $whichgroup = 'Users';
} else if ($target == 'supervisors'){
    $whichgroup = 'Supervisors';
} else if ($target == 'workers'){
    $whichgroup = 'Workers';
} else if ($target == 'supervisorswsheets'){
    $whichgroup = 'Supervisors with Unsigned Timesheets';
}

$category = $DB->get_record('course_categories', array('id'=>$COURSE->category));

$PAGE->set_url($thisurl);
    $PAGE->set_pagelayout('base');
$head = 'E-Mail TimeTracker '.$whichgroup.' in '.$category->name;
$PAGE->set_title($head);
$PAGE->set_heading($head);

$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add($head);

$mform = new timetracker_admin_mailusers_form($courseid, $target); 

if ($mform->is_cancelled()){ 
    redirect($courseurl);
} else if ($formdata=$mform->get_data()){

    // Data collection to send email to supervisor(s)
    $from = $USER;
    $subject = $formdata->subject;
    $messagehtml = $formdata->message['text'];

    //send to the target audience
    $courses    = get_courses($COURSE->category, 'fullname ASC', 'c.id, c.shortname');
    $courseids  = implode(',', array_keys($courses));

    $targets = array();

    //BUG #187 - email copy to sender
    $sender = $DB->get_record('user', array('id'=>$USER->id)); 
    if(!$sender) error_log("User does not exist");
    else array_push($targets, $sender);

    if($target == 'all' || $target == 'workers') {
        $sql = 'SELECT DISTINCT mdluserid FROM '.$CFG->prefix.
            'block_timetracker_workerinfo WHERE courseid IN ('.
            $courseids.') AND active=1';
        $ttusers  = $DB->get_records_sql($sql);

        if($ttusers && sizeof($ttusers) > 0){
            $mdlids = array();
            foreach($ttusers as $worker){
                $mdlids[] = $worker->mdluserid;
            }
            $mdlidlist = implode (',', $mdlids);
            $workers = $DB->get_records_select('user', 'id IN ('.$mdlidlist.')');
            $targets += $workers;
        }
    } 

    if ($target == 'all' || $target == 'supervisors'){
        foreach($courses as $course){
            $context = get_context_instance(CONTEXT_COURSE, $course->id);
            $supers = get_enrolled_users($context, 'moodle/grade:viewall');
            if($supers)
                $targets += $supers;
        }
    }

    if ($target == 'supervisorswsheets'){
        //find each courseID that has an unsigned timesheet
        $needsig = $DB->get_records_select('block_timetracker_timesheet',
           'courseid in ('.implode(',', array_keys($courses)).')',
           array('supervisorsignature'=>0),'','DISTINCT courseid');

        //error_log("Supervisors needing signature: ".sizeof($needsig));
        add_to_log($courseid, 'timetracker', 'mail supervisors w/unsigned','',
            'Sent email to '.sizeof($needsig).' supervisors');
        foreach($needsig as $timesheet){
            $context = get_context_instance(CONTEXT_COURSE, $timesheet->courseid);
            $supers = get_enrolled_users($context, 'moodle/grade:viewall');
            if($supers)
                $targets += $supers;
        }
        //exit();
    }
    
    $errorlist = array();
    $count = 0;
    foreach($targets as $target){
        $subj = $subject;
        if($target == $sender){
            $subj = 'FILE COPY - '.$subject;    
        }
        //error_log("Emailing $target->firstname $target->lastname");
        $mailok = email_to_user($target, $from, 'To: '.$target->firstname.' '.
            $target->lastname.' - '.$subj, 
            format_text_email($messagehtml, FORMAT_HTML),
            $messagehtml); 
        if(!$mailok) {
            error_log("Error sending to $target->firstname $target->lastname");
            $errorlist[] = $target;    
        } else 
            $count++;
    }
    add_to_log($courseid, 'timetracker', 'add email','',
        'Sent email to '.$count.' users');

    if(sizeof($errorlist) > 0){
        $output = '<h2>'.$count.' email(s) sent successfully<br />'.
            sizeof($errorlist). 'error(s) sending email(s) to:</h2><br />';
        add_to_log($courseid, 'timetracker', 'mail error','',
            'Error emailing '.sizeof($errorlist).' users');
        foreach($errorlist as $error){
            $output .= $error->lastname.', '.$error->firstname.'<br />';
            add_to_log($courseid, 'timetracker', 'mail error','',
                'Error emailing '.$error->lastname.', '.$error->firstname);
        }
        echo $output;
        echo $OUTPUT->action_link($courseurl, 'Back to course main page');
    }  else {
        redirect($courseurl, $count.' emails sent successfully', 2);
    }

} else {
    //form is shown for the first time
    
    echo $OUTPUT->header();
    $maintabs = get_admin_tabs($urlparams, $canmanage, $courseid);

    $tabs = array($maintabs);
    
    print_tabs($tabs);

    $mform->display();
    echo $OUTPUT->footer();
}
