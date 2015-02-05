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
require('timetracker_admin_reportgenerator_form.php');

global $CFG, $COURSE, $USER;
require_login();

$courseid = required_param('id', PARAM_INT);
//$reportstart = optional_param('reportstart', 0,  PARAM_INT);
//$reportend = optional_param('reportend', 0, PARAM_INT);

//error_log($reportstart.' '.$reportend);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$urlparams = array();
$urlparams['id']    = $courseid;

$url = new moodle_url($CFG->wwwroot.'/blocks/timetracker_admin/reportgenerator.php', 
    $urlparams);

$context = get_context_instance(CONTEXT_COURSE, $courseid); 
$PAGE->set_context($context);
$PAGE->set_course($course);


$PAGE->set_url($url);
$PAGE->set_pagelayout('base');

$strtitle = 'TimeTrackerAdmin : Report Generator';

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
    print_error('Invalid permissions to view the report generator');
}



$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);

$index = new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$COURSE->id));

$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add(get_string('pluginname','block_timetracker_admin'));
$PAGE->navbar->add($strtitle);

//$mform = new timetracker_admin_reportgenerator_form($reportstart, $reportend);
$mform = new timetracker_admin_reportgenerator_form();

if ($mform->is_cancelled()){ //user clicked cancel
    redirect($index);

} else if ($formdata=$mform->get_data()){

    $urlparams['id'] = $courseid;
    $urlparams['reportstart'] = $formdata->reportstart;
    $urlparams['reportend'] = strtotime('+ 1 day ', $formdata->reportend) - 1;
    $urlparams['type'] = $formdata->type;
    $urlparams['output'] = $formdata->output;

    redirect(new moodle_url($CFG->wwwroot.
        '/blocks/timetracker_admin/timesheetreport.php', $urlparams));
} else {
    //form is shown for the first time
    
    echo $OUTPUT->header();

    $maintabs = get_admin_tabs($urlparams, $canmanage, $courseid);
    $tabs = array($maintabs);
    print_tabs($tabs);

    $mform->display();
    echo $OUTPUT->footer();
}


