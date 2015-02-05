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
 * This form will allow the worker to submit an alert and correction to the supervisor of an error in a 
 * work unit. The supervisor will be able to approve, change, or deny the correction.
 *
 * @package    Block
 * @subpackage TimeTracker
 * @copyright  2011 Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once('../../config.php');
require_once('lib.php');
require('timetracker_admin_longworkunit_detail.php');

global $CFG, $COURSE, $USER;
require_login();

$courseid = required_param('id', PARAM_INT);
$minduration = required_param('minduration', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$urlparams = array();
$urlparams['id']            = $courseid;
$urlparams['minduration']   = $minduration;

$viewlongunits = new moodle_url($CFG->wwwroot.
    '/blocks/timetracker_admin/longworkunitdetail.php', $urlparams);

$context = get_context_instance(CONTEXT_COURSE, $courseid); 
$PAGE->set_context($context);
$PAGE->set_course($course);


$PAGE->set_url($viewlongunits);
$PAGE->set_pagelayout('base');

$strtitle = 'TimeTrackerAdmin : Long Workunit Detail';

$catcon = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);
$canmanage = false;
if (has_capability('block/timetracker_admin:managetransactions', $catcon)) {
    $canmanage = true;
}

$canview = false;
if (has_capability('block/timetracker_admin:viewtransactions', $catcon)) { 
    $canview = true;
}

if(!$canmanage && !$canview){
    print_error('Invalid permissions to view this page');
}

$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);

$index = new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$COURSE->id));

$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add(get_string('pluginname','block_timetracker_admin'));
$PAGE->navbar->add($strtitle);

$mform = new timetracker_admin_longworkunit_detail($minduration, $canmanage);

echo $OUTPUT->header();

$maintabs = get_admin_tabs($urlparams, $canmanage, $courseid);
$tabs = array($maintabs);
print_tabs($tabs);

if ($mform->is_cancelled()){ //user clicked cancel
    redirect($index);

} else if ($formdata=$mform->get_data()){

    $results = array();

    if(isset($formdata->alertunitsbutton)){
        $pending = false;
        $workunitids = $formdata->longunitid1; 
    } else if (isset($formdata->alertpendingunitsbutton)) {
        $pending = true;
        $workunitids = $formdata->longunitid2; 
    }

    /*
    if(isset($formdata->longunitid1)){
        error_log("Dealing with regular work unit admin alert");
        $pending = false;
    } else if(isset($formdata->longunitid2)){
        error_log("dealing with pending alert");
        $pending = true;
    }
    */

    if(!$workunitids || sizeof($workunitids) == 0){
        echo 'No units selected to alert. Exiting.';
    } else {
        foreach($workunitids as $unitid=>$value){
            if($value == 1){
                echo '<hr /></br>';

                if(!$pending){
                    $alertunit = $DB->get_record('block_timetracker_workunit', 
                        array('id'=>$unitid));
                } else {
                    $alertunit = $DB->get_record('block_timetracker_pending', 
                        array('id'=>$unitid));
                }

                if(!$alertunit){
                    error_log("in longworkunitdetail and no unit for $unitid"); 
                    echo "No unit found for $unitid. Skipping.<br />"; 
                    continue;
                }

                //actually move the unit to 'alerts'
                $output = admin_alert_longworkunit($alertunit, $minduration);

                if($output){
                    foreach($output as $o){
                        echo "$o<br />";
                    }
                } else {
                    echo 'No results. Hmm. <br />';
                }
    
                //tally the results to send summary emails.
                //one email per user per course with alerted totals.
                if(isset($results[$alertunit->userid][$alertunit->courseid])){
                    $results[$alertunit->userid][$alertunit->courseid]++;
                } else {
                    $results[$alertunit->userid][$alertunit->courseid] = 1;
                }
            }
        }

        //after going through _ALL_, we need to send cumulative reports
        $userids = array_keys($results);
        $total = 0;
        foreach ($userids as $uid){
            if(is_array($results[$uid])){
                $courseids = array_keys($results[$uid]);
                foreach($courseids as $cid){
                    send_long_workunits_admin_alert($minduration,
                        $cid, $uid, $results[$uid][$cid]);
                }
            } else { //only 1 course
                $courseid = $results[$uid];

                send_long_workunits_admin_alert($minduration,
                    $courseid, $uid, $results[$uid][$courseid]);
            }
        }
    } 
    echo '<hr /><br />'.$OUTPUT->action_link($index, 'Return to main').' | '.
        $OUTPUT->action_link($viewlongunits, 'View more long workunits');
} else {
    //form is shown for the first time
    $mform->display();
}

echo $OUTPUT->footer();
