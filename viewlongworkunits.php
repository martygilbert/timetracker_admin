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
require('timetracker_admin_longworkunits.php');

global $CFG, $COURSE, $USER;
require_login();

$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$urlparams = array();
$urlparams['id']    = $courseid;

$url = new moodle_url($CFG->wwwroot.'/blocks/timetracker_admin/viewlongworkunits.php', 
    $urlparams);

$context = get_context_instance(CONTEXT_COURSE, $courseid); 
$PAGE->set_context($context);
$PAGE->set_course($course);


$PAGE->set_url($url);
$PAGE->set_pagelayout('base');

$strtitle = 'TimeTrackerAdmin : Long Workunits';

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


$index = new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$COURSE->id));

$mform = new timetracker_admin_longworkunits_form();


if ($mform->is_cancelled()){ //user clicked cancel
    redirect($index);

} else if ($formdata=$mform->get_data()){

    $minduration = $formdata->minduration;
    $detail = new moodle_url($CFG->wwwroot.'/blocks/timetracker_admin/longworkunitdetail.php', 
        array('id'=>$COURSE->id, 'minduration'=>$minduration));
    redirect($detail); 

} else {
    $PAGE->set_title($strtitle);
    $PAGE->set_heading($strtitle);

    $PAGE->navbar->add(get_string('blocks'));
    $PAGE->navbar->add(get_string('pluginname','block_timetracker_admin'));
    $PAGE->navbar->add($strtitle);

    echo $OUTPUT->header();

    $maintabs = get_admin_tabs($urlparams, $canmanage, $courseid);
    $tabs = array($maintabs);
    print_tabs($tabs);
    //form is shown for the first time
    $mform->display();
    echo $OUTPUT->footer();
}

