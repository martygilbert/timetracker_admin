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
 * This block will display a summary of hours and earnings for the worker.
 *
 * @package    TimeTracker
 * @copyright  2012 Marty Gilbert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

defined('MOODLE_INTERNAL') || die();


/**
* Used in navigation
* @return array of tabobjects 
*/
function get_admin_tabs($urlparams, $canmanage = false, $courseid = -1){

    global $CFG;
    $tabs = array();
    $tabs[] = new tabobject('viewtrans',
        new moodle_url($CFG->wwwroot.'/blocks/timetracker_admin/viewtransactions.php',
        $urlparams),'View Transactions');
    $tabs[] = new tabobject('addtrans',
        new moodle_url($CFG->wwwroot.'/blocks/timetracker_admin/addtransaction.php',
        $urlparams), 'Add transaction');
    
    return $tabs;
}

function count_courses_with_unsigned($categoryid){
    global $CFG, $DB;
    $courses = get_courses($categoryid, 'fullname ASC', 'c.id');

    if(!$courses) return 0;
    $courselist = implode(',', array_keys($courses));

    $numtimesheets = $DB->count_records_select('block_timetracker_timesheet',
       'courseid in ('.$courselist.') AND supervisorsignature=0',
       null,'COUNT(DISTINCT courseid)');

    return $numtimesheets;
}

function get_unsigned_timesheets_by_category($categoryid){
    global $CFG, $DB;
    $courses = get_courses($categoryid, 'fullname ASC', 'c.id');

    if(!$courses) return;
    $courselist = implode(',', array_keys($courses));

    $sql = 'SELECT '.$CFG->prefix.
        'block_timetracker_timesheet.* from '.
        $CFG->prefix.'block_timetracker_timesheet,'.
        $CFG->prefix.'block_timetracker_workerinfo WHERE '.
        'userid='.$CFG->prefix.'block_timetracker_workerinfo.id AND '.
        'supervisorsignature=0 AND '.
        $CFG->prefix.'block_timetracker_timesheet.courseid in ('.
        $courselist.') ORDER BY lastname, firstname';

    $timesheets = $DB->get_records_sql($sql);
    return $timesheets;
}

//for cron usage
function count_unsigned_timesheets($courseid){
    global $DB;
    $unsigned = $DB->count_records('block_timetracker_timesheet',
        array('courseid'=>$courseid, 'supervisorsignature'=>0));

    return $unsigned; 
}

function count_outstanding_alerts($courseid){
    global $DB;
    $alerts = $DB->count_records('block_timetracker_alertunits',
        array('courseid'=>$courseid));

    return $alerts; 
}

/*
* Send an email listing the number of outstanding alerts & unsigned timesheets
* to the supervisors of a course. If there are none, no emails are sent.
* @param $courseid the courseid to check
* @return The number of successfully sent emails;
*/
function send_reminders_to_supervisors($courseid){

    global $DB, $CFG;
    $numsheets  = count_unsigned_timesheets($courseid);
    $numalerts  = count_outstanding_alerts($courseid);

    if($numsheets == 0 && $numalerts == 0) return 0;

    $coursecon  = get_context_instance(CONTEXT_COURSE, $courseid);    
    $teachers   = get_enrolled_users($coursecon, 'mod/assignment:grade');

    $course = $DB->get_record('course', array('id'=>$courseid));

    $subj   = '['.$course->shortname.'] Notice: '.
        ($numsheets + $numalerts).' item(s) need your attention';

    $from   = generate_email_supportuser();

    $msg    = 'Hello!<br /><br />This is a reminder that you have '.
        ($numsheets+$numalerts).' item(s) that require your attention. <br /><br />'.
        '<ul>';
         
    if($numsheets > 0){
        $msg .=
        '<li>'.$numsheets.' timesheet(s). To inspect these timesheets, '.
        'visit <a href="'. $CFG->wwwroot.'/blocks/timetracker/supervisorsig.php?id='.
        $courseid.'">this link</a>.</li>';
    }

    if($numalerts > 0){
        $msg .=
        '<li>'.$numalerts.' alert(s). To inspect these alerts, '.
        'visit <a href="'. $CFG->wwwroot.'/blocks/timetracker/managealerts.php?id='.
        $courseid.'">this link</a>. As a reminder, <span style="font-weight: bold">'.
        'workers cannot sign their timesheets if there are outstanding alerts.</span></li>';
    }    
    $msg    .=
        '</ul><br /><br />'.
        'We, and your workers, '.
        'appreciate your prompt attention to this matter.<br />'.
        '<br />Thanks,<br /><br />'.
        '<br />The TimeTracker Development Team';
    
    $count = 0;
    foreach($teachers as $teacher){
        $ok = email_to_user($teacher, $from, $subj, 
            format_text_email($msg, FORMAT_HTML), $msg, '', '', false);
        if(!$ok){
            error_log('Error sending timesheet reminder email to: '.
                $teacher->firstname.' '.$teacher->lastname);
        } else {
            $count++;
        }
    }
    return $count;
}

function truncatestr($str, $length=16, $trailing=''){
    if(strlen($str) > $length){
        $str = substr($str, 0, $length).$trailing;
    }
    return $str;
}

/**
    @return status string regarding this transfer
*/
function transfer_worker($workerid, $courseid, $supervisorname='', $deptname=''){
    global $DB;

    if ($workerid == $courseid ) {
        return ('Trying to transfer worker to a course in which they are already active');
    }
    $worker = $DB->get_record('block_timetracker_workerinfo',
        array('id'=>$workerid));
    if(!$worker) {
        error_log('Error transferring worker with id: '.$workerid);    
        return ('Worker with id of '.$workerid.' does not exist');    
    }



    //FINISH THIS!!! TODO XXX

    //set the worker to deleted 
    $worker->deleted = 1; 
}

function get_workers_with_unsub_by_category($catid, $reportstart, $reportend){
    $courselist = get_courses($catid, 'fullname ASC', 'c.id, c.shortname');
    return get_workers_with_unsub($courselist, $reportstart, $reportend);
}

function get_workers_with_unsub($courselist, $reportstart, $reportend){
    global $CFG, $DB;
    $sql = 'SELECT DISTINCT userid FROM '.$CFG->prefix.
        'block_timetracker_workunit '.
        'WHERE courseid in ('.
        implode(',', array_keys($courselist)).') AND '.
        '('.
            '(timein BETWEEN '.$reportstart.' AND '.$reportend.')'.
            ' OR '.
            '(timeout BETWEEN '.$reportstart.' AND '.$reportend.')'.
        ') AND timesheetid=0';
    $users = $DB->get_records_sql($sql);
    $timesheets=array();
    if(sizeof($users)>0){
        $timesheets = $DB->get_records_select('block_timetracker_workerinfo',
            'id in ('.implode(',',array_keys($users)).')',null,'lastname,firstname');
    } 

    return $timesheets;
}

/**
* Given headers and data, creates a csv
* @param $header - an array of strings for the first row
* @param $data - a 2D array holding data values
* @param $title - the title of the report
* @param $delim - the delimiter to use. Accepted are comma,tab,semicolon,colon,cfg.
*/
function print_report_csv($header, $data, $title='Report', $delim='comma'){
    global $DB, $CFG;

    require_once($CFG->libdir . '/csvlib.class.php');

    $csvexporter = new csv_export_writer($delim);

    $tt = getdate(time());

    $strftimedatetime = get_string("strftimedatetime");

    $newfilename = preg_replace("/[^A-Za-z0-9_]/","", $title);

    $csvexporter->set_filename('TT_'.$newfilename, '.csv');
    $title = array($title,'Generated: '.userdate(time(), $strftimedatetime));

    $csvexporter->add_data($title);
    $csvexporter->add_data($header);

    if (sizeof($data) == 0) {
        return true;
    }

    foreach ($data as $row) {
        $csvexporter->add_data($row);
    }

    $csvexporter->download_file();

    return true;
}

/**
*
* @param $alertunit is either a pending or regular workunit
* @param $minduration the time (in hours)
* @return array of errors - empty array if none.
*/
function admin_alert_longworkunit($alertunit, $minduration){
    global $DB, $CFG, $USER;
    
    $errors = array();
    if(!$alertunit) {
        $errors[] = 'Alerted unit is NULL';
        return $errors;
    }

    $origid = $alertunit->id;

    if(isset($alertunit->timeout)) 
        $ispending = false;
    else
        $ispending = true;

    if($alertunit){

        $worker = $DB->get_record('block_timetracker_workerinfo', 
            array('id'=>$alertunit->userid));

        if(!$worker){
            error_log('Cannot find worker with tt id: '.$alertunit->userid);

            $errors[] = "Worker id $alertunit->userid cannot be found in TimeTracker"; 
            return $errors;
        }

        $mdlworker = $DB->get_record('user', array('id'=>$worker->mdluserid));

        if(!$mdlworker){
            error_log('Cannot find worker with mdl id: '.$worker->mdluserid);
            $errors[] = "Moodle user id $worker->mdluserid cannot be found"; 
            return $errors;
        }


        unset($alertunit->id);

        $alertunit->alerttime = time();
        $alertunit->payrate = $worker->currpayrate;
        $alertunit->origtimein = $alertunit->timein;
        if(!$ispending)
            $alertunit->origtimeout = $alertunit->timeout;

        $alertunit->timeout = $alertunit->timein + 1;

        if(!isset($alertunit->payrate))
            $alertunit->payrate = $worker->currpayrate;
            
        $alertmessage = '<img src="'.$CFG->wwwroot.
            '/blocks/timetracker/pix/alert.gif" /> <strong>ADMINISTRATIVE ALERT</strong>:'. 
            '<br />This work unit is longer than '.$minduration.
            ' hour(s) <br />and must be inspected by a supervisor.';

        $alertunit->message = $alertmessage;
        $alertunit->todelete = 0;
        $alertunit->lastedited = $alertunit->alerttime;
        $alertunit->lasteditedby = $USER->id;

        $alertid = $DB->insert_record('block_timetracker_alertunits', $alertunit);

        if($alertid && $ispending){
            $ret = $DB->delete_records('block_timetracker_pending', array('id'=>$origid));
            if(!$ret){
                $errors[] = "Error deleting pending unit $origid";
            }
        } else if($alertid && !$ispending){
            $ret = $DB->delete_records('block_timetracker_workunit', array('id'=>$origid));
            if(!$ret){
                $errors[] = "Error deleting work unit $origid";
            }
        }

        $alertcom = new stdClass();
        $alertcom->alertid = $alertid;
        $alertcom->mdluserid = $worker->mdluserid;

        //Insert student record into 'alert_com'
        $res = $DB->insert_record('block_timetracker_alert_com', $alertcom);
    
        if (!$res){
            $errors[] = 'Cannot add student to alert_com';
        }


        //add ALL supervisors on an Admin Alert
        $context = get_context_instance(CONTEXT_COURSE, $alertunit->courseid);
        $supers = get_enrolled_users($context, 'moodle/grade:viewall');
        foreach($supers as $supervisor){
            $alertcom->mdluserid = $supervisor->id;
            $res = $DB->insert_record('block_timetracker_alert_com', $alertcom);
            if(!$res){
                $errors[] = 'Error emailing administrative alert to supervisor '.
                    "$supervisor->firstname $supervisor->lastname for ".
                    $alertunit->courseid;
            }
        }

        if(sizeof($errors)==0){
            $errors[] = 'Administrative alert added successfully for '.
                $worker->firstname.' '.$worker->lastname.' in department w/id: '.
                $alertunit->courseid;
        }

    } else {
        //can't find this unit. Do what?
    } 
    return $errors;
}

function send_long_workunits_admin_alert($minduration, $courseid, $userid, $numalerts){

    if($numalerts < 1) return;
    global $DB, $CFG, $USER;

    $ttworker = $DB->get_record('block_timetracker_workerinfo', array('id'=>$userid));
    $worker = $DB->get_record('user', array('id' => $ttworker->mdluserid));
    $course = $DB->get_record('course', array('id' => $courseid));

    if(!$worker){
        error_log('TimeTracker_admin lib.php send_long_workunits_alert and worker with tt_id '.
            $userid.' does not exist');
        return;    
    }

    if(!$course){
        error_log('TimeTracker_admin lib.php send_long_workunits_alert and course with id '.
            $courseid.' does not exist');
        return;    
    }

    //whom do we notify? Send a stock email to all supervisor(s) and this particular student.
    $subject = 'TimeTracker - '.$numalerts.' Administrative Alert(s) for '.$course->shortname; 
    $workermessage  = 'Hello '.$worker->firstname.',<br /><br />';
    //$supervisormessage = 'Hello,<br /><br />'; //add personalized later

    $supervisormessage = 'During a periodic audit, administrators flagged '.$numalerts.
        ' workunit(s) logged by '.$worker->firstname.' '.$worker->lastname.
        ' for being longer than '.$minduration.' hour(s). ';
    $supervisormessage .= 'You must handle these alerts before the worker will be '.
        'able to sign their timesheet.';
    $supervisormessage .= '<br /><br />You can resolve this problem by 
    visiting <a href="'.$CFG->wwwroot.'/blocks/timetracker/managealerts.php?id='.
    $courseid.'">this link</a>.<br /><br />Thanks,<br />The TimeTracker
    Team</a>';

    $workermessage .= 'During a periodic audit, administrators flagged '.$numalerts.
        ' of your workunit(s) for being longer than '.$minduration.' hour(s). ';
    $workermessage .= 'Your supervisor will need to resolve these alerts '.
        'before you are able to sign your timesheet.';
    $workermessage .= '<br /><br />You can check on the status of this alert by 
    visiting <a href="'.$CFG->wwwroot.'/blocks/timetracker/managealerts.php?id='.
    $courseid.'">this link</a>. '.
        'Please contact your supervisor if the alert is not resolved quickly.'.
        '<br /><br />Thanks,<br />The TimeTracker Team</a>';
    
    //email the student 
    $mailok = email_to_user($worker, $USER, $subject, 
        format_text_email($workermessage, FORMAT_HTML), $workermessage);

    if(!$mailok){
        print_error('Error sending admin alert to worker: '.
            "$worker->firstname $worker->lastname");
    } 
   
    ////// NOW, GET ALL "SUPERVISORS" OF THIS COURSEID AND EMAIL THEM

    //send to ALL supervisors on an Admin Alert
    $context = get_context_instance(CONTEXT_COURSE, $courseid);
    $supers = get_enrolled_users($context, 'moodle/grade:viewall');
    foreach($supers as $supervisor){

        //email the supervisor 
        $mailok = email_to_user($supervisor, $USER, $subject, 
            format_text_email('Hello '.
                $supervisor->firstname.',<br /><br />'.$supervisormessage, 
                FORMAT_HTML), $supervisormessage);
    
        if(!$mailok){
            print_error('Error sending admin alert to supervisor: '.
                "$supervisor->firstname $supervisor->lastname for $courseid");
        } 
    }
}

/**
 * ***** Taken from admin/uploadtool.php ******
 * Validation callback function - verified the column line of csv file.
 * Converts standard column names to lowercase.
 * @param csv_import_reader $cir
 * @param array $stdfields standard user fields
 * @param moodle_url $returnurl return url in case of any error
 * @return array list of fields
 */
function tt_validate_upload_columns(csv_import_reader $cir, $stdfields, moodle_url $returnurl) {
    $columns = $cir->get_columns();

    if (empty($columns)) {
        $cir->close();
        $cir->cleanup();
        print_error('cannotreadtmpfile', 'error', $returnurl);
    }
    if (count($columns) < 2) {
        $cir->close();
        $cir->cleanup();
        print_error('csvfewcolumns', 'error', $returnurl);
    }

    // test columns
    $processed = array();
    foreach ($columns as $key=>$unused) {
        $field = $columns[$key];
        $lcfield = textlib::strtolower($field);
        if (in_array($field, $stdfields) or in_array($lcfield, $stdfields)) {
            // standard fields are only lowercase
            $newfield = $lcfield;
        } else {
            $cir->close();
            $cir->cleanup();
            print_error('invalidfieldname', 'error', $returnurl, $field);
        }
        if (in_array($newfield, $processed)) {
            $cir->close();
            $cir->cleanup();
            print_error('duplicatefieldname', 'error', $returnurl, $newfield);
        }
        $processed[$key] = $newfield;
    }
    return $processed;
}

/**
 * Tracking of processed users.
 *
 * This class prints user information into a html table.
 * Taken from uploaduserstool and modified for activateworkers
 * Thanks to Petr!
 * -MGilbert
 *
 * @package    core
 * @subpackage admin
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tt_progress_tracker {
    private $_row;

    public $columns = array('status', 'line', 'firstname', 'lastname', 'email', 'idnum', 
            //'institution', 'dept', 'supervisor', 'position', 'budget', 'payrate', 'maxterm');
            'dept', 'budget', 'payrate', 'maxterm');

    /**
     * Print table header.
     * @return void
     */
    public function start() {
        $ci = 0;
        echo '<table id="uuresults" class="generaltable boxaligncenter flexible-wrap" summary="'.
            get_string('activateworkersresult', 'block_timetracker_admin').'">';
        echo '<tr class="heading r0">';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('status').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('csvline',
            'block_timetracker_admin').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('firstname').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('lastname').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('email').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('idnum',
            'block_timetracker_admin').'</th>';
        //echo '<th class="header c'.$ci++.'" scope="col">'.get_string('institution',
            //'block_timetracker_admin').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('dept',
            'block_timetracker_admin').'</th>';
        //echo '<th class="header c'.$ci++.'" scope="col">'.get_string('supervisor', 
            //'block_timetracker_admin').'</th>';
        //echo '<th class="header c'.$ci++.'" scope="col">'.get_string('position', 
            //'block_timetracker_admin').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('budget', 
            'block_timetracker_admin').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('payrate',
            'block_timetracker_admin').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('maxterm',
            'block_timetracker_admin').'</th>';
        echo '</tr>';
        $this->_row = null;
    }

    /**
     * Flush previous line and start a new one.
     * @return void
     */
    public function flush() {
        if (empty($this->_row) or empty($this->_row['line']['normal'])) {
            // Nothing to print - each line has to have at least number
            $this->_row = array();
            foreach ($this->columns as $col) {
                $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
            }
            return;
        }
        $ci = 0;
        $ri = 1;
        echo '<tr class="r'.$ri.'">';
        foreach ($this->_row as $key=>$field) {
            foreach ($field as $type=>$content) {
                if ($field[$type] !== '') {
                    $field[$type] = '<span class="uu'.$type.'">'.$field[$type].'</span>';
                } else {
                    unset($field[$type]);
                }
            }
            echo '<td class="cell c'.$ci++.'">';
            if (!empty($field)) {
                echo implode('<br />', $field);
            } else {
                echo '&nbsp;';
            }
            echo '</td>';
        }
        echo '</tr>';
        foreach ($this->columns as $col) {
            $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
        }
    }

    /**
     * Add tracking info
     * @param string $col name of column
     * @param string $msg message
     * @param string $level 'normal', 'warning' or 'error'
     * @param bool $merge true means add as new line, false means override all previous text of the same type
     * @return void
     */
    public function track($col, $msg, $level = 'normal', $merge = true) {
        if (empty($this->_row)) {
            $this->flush(); //init arrays
        }
        if (!in_array($col, $this->columns)) {
            debugging('Incorrect column:'.$col);
            return;
        }
        if ($merge) {
            if ($this->_row[$col][$level] != '') {
                $this->_row[$col][$level] .='<br />';
            }
            $this->_row[$col][$level] .= $msg;
        } else {
            $this->_row[$col][$level] = $msg;
        }
    }

    /**
     * Print the table end
     * @return void
     */
    public function close() {
        $this->flush();
        echo '</table>';
    }
}

