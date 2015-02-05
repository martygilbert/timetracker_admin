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
require('timetracker_admin_transfer_worker_form.php');

require_login();

$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

if(!$course){
    print_error("Course with id $courseid does not exist");
}

$PAGE->set_course($course);
$context = $PAGE->context;
global $COURSE;

$catcontext = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);
$canmanage = false;
if (has_capability('block/timetracker_admin:managetransactions', $catcontext)) { 
    $canmanage = true;
}

if(!$canmanage){
    print_error('nocatpermission', 'block_timetracker_admin');
}

$urlparams['id'] = $courseid;
$courseurl = new moodle_url ($CFG->wwwroot.
    '/course/view.php', $urlparams);

$PAGE->set_url(new moodle_url($CFG->wwwroot.
    '/blocks/timetracker_admin/transferworker.php',$urlparams));

$strtitle = get_string('transferworker','block_timetracker_admin'); 
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');

$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add($strtitle);

$mform = new timetracker_admin_transfer_worker_form();

if ($mform->is_cancelled()){ //user clicked cancel
    redirect($courseurl);

} else if ($formdata=$mform->get_data()){

    //what to do?
    //we should have 'origworker' (workerinfo.id) and 'todept' (course.id)
    //set deleted to 1 in workerinfo.id, then make a new entry in course.id,
    //if the worker doesn't already HAVE an entry in workerinfo for that course.id
    //may be best to write a function to do a transfer, right?


} else {
    //form is shown for the first time
    echo $OUTPUT->header();
    $tabs = get_admin_tabs($urlparams, $canmanage, $courseid);
    $tabs = array($tabs);
    print_tabs($tabs);
    

    $mform->display();
    echo $OUTPUT->footer();
}
