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
 * This block will provide admin functions for TimeTracker
 *
 * @package    Block
 * @subpackage TimeTracker
 * @copyright  2012 Marty Gilbert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once($CFG->dirroot.'/blocks/timetracker_admin/lib.php');
require_once('lib.php');

class block_timetracker_admin extends block_base {

    function init() {
        global $USER;
        if(is_siteadmin($USER->id)){
            $this->title = get_string('blocktitle', 'block_timetracker_admin');
        }
    }

    function get_content() {
        global $CFG, $DB, $USER, $OUTPUT, $COURSE;

        $catcontext  = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);

        $canmanage = false;
        if (has_capability('block/timetracker_admin:managetransactions', $catcontext)) { 
            $canmanage = true;
        }

        $canview = false;
        if (has_capability('block/timetracker_admin:viewtransactions', $catcontext)) { 
            $canview = true;
        }

        /*
        if(!$canmanage && !$canview){
            return;
        }
        */

        if($this->content !== NULL){
            return $this->content;
        }

        $this->content = new stdClass;

        /*
        if(!is_siteadmin($USER->id)){
            $this->content->text    .= '<span style="font-weight: bold">'.
                'TimeTracker Admin</span>';
            $this->content->text    .= '<br /><br />';
        }
        */

        $baseurl = $CFG->wwwroot.'/blocks/timetracker_admin';
        $urlparams['id']    = $COURSE->id;


        $this->content->text    .= '<h4>Transactions</h4>';
        
        //make icon for add transaction
        $addurl = new moodle_url($baseurl.'/addtransaction.php', $urlparams);
        $addaction = $OUTPUT->action_icon($addurl, new pix_icon('add',
            'Add transaction', 'block_timetracker'));
        $this->content->text .= $addaction.' '.
            $OUTPUT->action_link($addurl, 'Add transaction').'<br />';     

        //make icon for view transactions
        $viewurl = new moodle_url($baseurl.'/viewtransactions.php', $urlparams);
        $viewaction = $OUTPUT->action_icon($viewurl, new pix_icon('list',
            'View transactions', 'block_timetracker'));

        $this->content->text    .= $viewaction.' '.
            $OUTPUT->action_link($viewurl, 'View transactions').'<br />';


        if($canmanage){
            $this->content->text    .= '<br />';
            $this->content->text    .= '<h4>Email actions</h4>'; 
            //make icons for mail options
            $emailurl = new moodle_url($baseurl.'/mailusers.php', array(
                'id'=>$COURSE->id,
                'target'=>'all'));
            $email_all_action = $OUTPUT->action_icon($emailurl, new pix_icon('email',
                'All users', 'block_timetracker_admin'));
            $this->content->text    .= $email_all_action.' '.
                $OUTPUT->action_link($emailurl, 'All users').'<br />';
    
            $emailurl->params(array('target'=>'supervisors'));
            $email_supervisors_action = $OUTPUT->action_icon($emailurl, new pix_icon('email',
                'All supervisors', 'block_timetracker_admin'));
            $this->content->text    .= $email_supervisors_action.' '.
                $OUTPUT->action_link($emailurl, 'All supervisors').'<br />';

            //find out how many timesheets need signing?
            $numcourses = count_courses_with_unsigned($COURSE->category);
            $emailurl->params(array('target'=>'supervisorswsheets'));
            $email_supervisorswsheets_action 
                = $OUTPUT->action_icon($emailurl, new pix_icon('email',
                'Supervisors needing to sign timesheets', 'block_timetracker_admin'));
            $this->content->text    .= $email_supervisorswsheets_action.' '.
                $OUTPUT->action_link($emailurl, 'Supervisors w/unsigned ('.
                $numcourses.')').
                '<br />';
    
            $emailurl->params(array('target'=>'workers'));
            $email_workers_action = $OUTPUT->action_icon($emailurl, new pix_icon('email',
                'Email all workers', 'block_timetracker_admin'));
            $this->content->text    .= $email_workers_action.' '.
                $OUTPUT->action_link($emailurl, 'All workers').'<br />';

            $this->content->text    .= '<br />';
            $this->content->text    .= '<h4>Utilities</h4>'; 

            $activation_url = new moodle_url($baseurl.'/upload_activation.php', $urlparams);

            $upload_activation_action = $OUTPUT->action_icon(
                $activation_url, new pix_icon('upload',
                'Upload activiation file', 'block_timetracker_admin'));
            $this->content->text    .= $upload_activation_action.' '.
                $OUTPUT->action_link($activation_url, 'Mass activation').'<br />';


        }


        $this->content->text    .= '<br /><h4>Timesheet reports</h4>';

        $viewunsignedurl = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker_admin/viewunsigned.php', $urlparams);

        $viewunsignedaction = $OUTPUT->action_icon($viewunsignedurl, new pix_icon('view',
            'View unsigned timesheets', 'block_timetracker_admin'));

        $this->content->text    .= $viewunsignedaction.' '.
            $OUTPUT->action_link($viewunsignedurl, 'Unsigned');

        $this->content->text    .= '<br />';

        $vieweligibleurl = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker_admin/vieweligible.php', $urlparams);

        $vieweligibleaction = $OUTPUT->action_icon($vieweligibleurl, new pix_icon('view',
            'Eligible timesheets', 'block_timetracker_admin'));

        $this->content->text    .= $vieweligibleaction.' '.
            $OUTPUT->action_link($vieweligibleurl, 'Eligible');
            //$OUTPUT->pix_icon('i/new', 'New');

        $this->content->text    .= '<br />';

        //Timesheet Report
        $tsreporturl = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker_admin/reportgenerator.php', $urlparams);
        $tsreportaction = $OUTPUT->action_icon($tsreporturl, new pix_icon('view',
            'Generic reports', 'block_timetracker_admin'));

        $this->content->text    .= $tsreportaction.' '.
            $OUTPUT->action_link($tsreporturl, 'Generic reports');

        $this->content->text    .= '<br /><br /><h4>Misc reports</h4>';


        $longunitsurl = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker_admin/viewlongworkunits.php', $urlparams);

        $longunitsaction = $OUTPUT->action_icon($longunitsurl, new pix_icon('view',
            'Long workunits', 'block_timetracker_admin'));

        $this->content->text    .= $longunitsaction.' '.
            $OUTPUT->action_link($longunitsurl, 'Long workunits ');
            //$OUTPUT->pix_icon('i/new', 'New');

        $this->content->text    .= '<br />';


        $viewworkersurl = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker_admin/viewworkers.php', $urlparams);
        $viewworkersaction = $OUTPUT->action_icon($viewworkersurl, new pix_icon('view',
            'View Workers', 'block_timetracker_admin'));

        $this->content->text    .= $viewworkersaction.' '.
            $OUTPUT->action_link($viewworkersurl, 'All workers');

        $this->content->text    .= '<br />';



        $viewsupersurl = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker_admin/listsupers.php', $urlparams);
        $viewsupersaction = $OUTPUT->action_icon($viewsupersurl, new pix_icon('view',
            'View supervisors', 'block_timetracker_admin'));

        $this->content->text    .= $viewsupersaction.' '.
            $OUTPUT->action_link($viewsupersurl, 'View supervisors');

        $this->content->text    .= '<br />';



        $viewterminfourl = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker_admin/viewterminfo.php', $urlparams);
        $viewterminfoaction = $OUTPUT->action_icon($viewterminfourl, new pix_icon('view',
            'Term info', 'block_timetracker_admin'));

        $this->content->text    .= $viewterminfoaction.' '.
            $OUTPUT->action_link($viewterminfourl, 'Term info').
            $OUTPUT->pix_icon('i/new', 'New');

        $this->content->text    .= '<br />';



        //$this->content->text = 'Hi!';
        return $this->content;
    }
    
    
    function instance_allow_multiple() {
        return false;
    }
    
    function has_config() {
        return false;
    }
    
    function instance_allow_config() {
        return true;
    }

    function cron() {
        global $CFG, $DB; 

        $lastcron = $DB->get_field('block', 'lastcron', 
            array('name'=>'timetracker_admin'));
        if($lastcron == 0){
            $yesterday = strtotime ("Yesterday 6am");
            $DB->set_field('block', 'lastcron', $yesterday,
                array('name'=>'timetracker_admin'));
            return;
        }

        $numemails = 0;

        $sql = 'SELECT DISTINCT courseid from '.$CFG->prefix.
            'block_timetracker_timesheet WHERE supervisorsignature=0 ORDER BY courseid';
        
        $courseids = $DB->get_records_sql($sql);
        
        $sql = 'SELECT DISTINCT courseid from '.$CFG->prefix.
            'block_timetracker_alertunits ORDER BY courseid';

        $alertids = $DB->get_records_sql($sql);

        /*
        foreach($alertids as $alert){
            if(!array_key_exists($alert->courseid, $courseids)){
                array_push($courseids, $alert);
            }
        }
        */

        //use this line instead of the above 'for' loop 
        //union works on the array keys, which happen to be
        //the courseids in question. This will combine,
        //and avoid duplicates.
        $courseids = $courseids + $alertids; //union?

        foreach($courseids as $course){
            $numemails += send_reminders_to_supervisors($course->courseid);    
        }

        mtrace('Number of emails sent: '.$numemails);
        if($numemails > 0)
            add_to_log(0, '', 'Sent Reminders', '0',
                'Sent '.$numemails.' reminder emails');

        //update cron to execute in the morning at 7am - ish
        $DB->set_field('block', 'lastcron', strtotime("Today 6am"),
            array('name'=>'timetracker_admin'));
    }

}
