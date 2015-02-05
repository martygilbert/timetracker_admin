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

require_once(dirname(__FILE__) . '/../../config.php');
require_once('lib.php');

require_login();

$courseid = required_param('id', PARAM_INT);
$unitid = required_param('unitid', PARAM_INT);
$ispending = required_param('ispending', PARAM_BOOL);
$fromcourse = required_param('fromcourse', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$minduration = required_param('minduration', PARAM_INT);

$course = $DB->get_record('course', array('id' => $fromcourse), '*', MUST_EXIST);

if(!$course){
    print_error("Course with id $fromcourse does not exist");
}

$PAGE->set_course($course);
$context = $PAGE->context;

global $COURSE;

$urlparams['id']        = $courseid;
$urlparams['minduration']   = $minduration;

$viewlongworkunitsurl = new moodle_url ($CFG->wwwroot.
    '/blocks/timetracker_admin/viewlongworkunits.php', $urlparams);

$catcontext = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);

if (!has_capability('block/timetracker_admin:managetransactions', $catcontext)) { 
    print_error('You don\'t have the permission to generate this alert');
}

/*****************************/
// Get data from 'pending' or 'workunit' table to put into the 'alertunits' table
if($ispending){
    // Pending Work Unit
    $alertunit = $DB->get_record('block_timetracker_pending',array('id'=>$unitid));
} else {
    // Completed Work Unit
    $alertunit = $DB->get_record('block_timetracker_workunit',array('id'=>$unitid));
}

if($alertunit){

    unset($formdata->id);
    $alertunit->alerttime = time();
    $alertunit->payrate = $workerrecord->currpayrate;
    $alertunit->origtimein = $alertunit->timein;

    if(!$ispending)
        $alertunit->origtimeout = $alertunit->timeout;

    $alertunit->timein = $formdata->timeinerror;
    $alertunit->timeout = $formdata->timeouterror;
    $alertunit->message = $formdata->message;

    if($delete == 1)
        $alertunit->todelete = 1;

    $alertid = $DB->insert_record('block_timetracker_alertunits', $alertunit);
    
    if(!$ispending){
        $DB->delete_records('block_timetracker_pending',array('id'=>$unitid));
    } else {
        $DB->delete_records('block_timetracker_workunit',array('id'=>$unitid));
    }


    // Send the email to the selected supervisor(s)

    if($alertid){

        $linkbase = $CFG->wwwroot.'/blocks/timetracker/alertaction.php?alertid='.
            $alertid.'&delete='.$delete;
        $approvelink = $linkbase.'&action=approve';
        $deletelink = $linkbase.'&action=delete';
        $changelink = $linkbase.'&action=change';
        $denylink = $linkbase.'&action=deny';

        // Approve link
        $messagehtml .= '<a href="'.$approvelink.'">';
        $messagehtml .= get_string('emessageapprove','block_timetracker');
        $messagehtml .= '</a> - Approve the work unit as proposed';
        
        $messagehtml .= get_string('br1','block_timetracker');

        // Delete link
        $messagehtml .= '<a href="'.$deletelink.'">';
        $messagehtml .= get_string('emessagedelete','block_timetracker');
        $messagehtml .= '</a> - Delete this alert and remove the work unit';
        
        $messagehtml .= get_string('br1','block_timetracker');
        
        // Change link
        $messagehtml .= '<a href="'.$changelink.'">';
        $messagehtml .= get_string('emessagechange','block_timetracker');
        $messagehtml .= '</a> - Change the proposed work unit before approval';
    
        $messagehtml .= get_string('br1','block_timetracker');
        
        // Deny link
        $messagehtml .= '<a href="'.$denylink.'">';
        $messagehtml .= get_string('emessagedeny','block_timetracker');
        $messagehtml .= '</a> - Deny the propsed work unit and re-insert the original';

        $alertcom = new stdClass();
        $alertcom->alertid = $alertid;
        $alertcom->mdluserid = $USER->id;

        //Insert student record into 'alert_com'
        $res = $DB->insert_record('block_timetracker_alert_com',$alertcom); 

        if (!$res){
            print_error('cannot add student to alert_com');
        }

        foreach($formdata->teacherid as $tid=>$checkvalue){
            //print_object($tid);
        
            if($checkvalue == 1){ //box was checked?
                $user = $DB->get_record('user',array('id'=>$tid));
                $alertcom->mdluserid = $tid;
                //insert alertcom into db
                //print('emailing user: '.$tid);
                if($user){
                    $mailok = email_to_user($user, $from, $subject, 
                        $messagetext, $messagehtml); 
                        
                    $res = $DB->insert_record('block_timetracker_alert_com',$alertcom); 

                    if (!$res){
                        print_error('cannot add teacher to alert_com');
                    }

                    // Delete the unit from the 'pending' or 'workunit' table 
                    //since the data was inserted into the 'alertunits' table 
                    //and any emails have been sent.
                    if($ispending && $mailok)
                        $DB->delete_records('block_timetracker_pending',
                            array('id'=>$unitid));
                    if(!$ispending && $mailok)
                        $DB->delete_records('block_timetracker_workunit',
                            array('id'=>$unitid));
                    if(!$mailok)
                        print_error(
                            "Error sending message to $user->firstname $user->lastname");
                } else 
                    print_error("Failed mailing user $tid");
            }
        }
    } else {
        //print out an error saying we can't handle this alert
    }
}


/****************************/
redirect($viewlongworkunitsurl, $status, 2);
