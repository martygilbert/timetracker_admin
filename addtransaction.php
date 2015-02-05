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
require('timetracker_admin_add_transaction_form.php');

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
$viewtransactionsurl = new moodle_url ($CFG->wwwroot.
    '/blocks/timetracker_admin/viewtransactions.php', $urlparams);

$num = $DB->count_records('block_timetracker_transactn', 
    array('categoryid'=>$COURSE->category, 'submitted'=>0));
if($num >= 1){
    redirect($viewtransactionsurl, 'You have an outstanding transaction. You must '.
        'either finalize it or delete it before beginning a new one', 3);
}



$PAGE->set_url(new moodle_url($CFG->wwwroot.
    '/blocks/timetracker_admin/addtransaction.php',$urlparams));

$strtitle = get_string('addtransactionheader','block_timetracker_admin'); 
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');

$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add($strtitle);

$mform = new timetracker_admin_add_transaction_form();

if ($mform->is_cancelled()){ //user clicked cancel
    redirect($viewtransactionsurl);

} else if ($formdata=$mform->get_data()){

    //get the data, create a new transaction
    unset($formdata->id); //is courseid, not transid
    $formdata->created = time();
    $result = $DB->insert_record('block_timetracker_transactn', $formdata);

    $status = 'Transaction created successfully';
    if(!$result){
        $status = 'Error creating new transaction';
    }

    redirect($viewtransactionsurl, $status, 2);


} else {
    //form is shown for the first time
    echo $OUTPUT->header();
    $tabs = get_admin_tabs($urlparams, $canmanage, $courseid);
    $tabs = array($tabs);
    print_tabs($tabs);
    

    $mform->display();
    echo $OUTPUT->footer();
}
