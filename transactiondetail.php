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
require('timetracker_admin_transaction_detail_form.php');

require_login();

$courseid   = required_param('id', PARAM_INT);
$transid    = required_param('transid', PARAM_INT);
$sort       = optional_param('sort', 'name', PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

if(!$course){
    print_error("Course with id $courseid does not exist");
}

$transaction = $DB->get_record('block_timetracker_transactn', 
    array('id'=>$transid));

if(!$transaction){
    print_error("Transaction with id $transid does not exist");
}


$PAGE->set_course($course);
$context = $PAGE->context;

global $COURSE;

$catcontext = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);

$canmanage = false;
if (has_capability('block/timetracker_admin:managetransactions', $catcontext)) { 
    $canmanage = true;
}

$canview = false;
if (has_capability('block/timetracker_admin:viewtransactions', $catcontext)) { 
    $canview = true;
}

if(!$canmanage && !$canview){
    print_error('nocatpermission', 'block_timetracker_admin');
}

$urlparams['id']        = $courseid;
$urlparams['transid']   = $transid;
$transdetailurl = new moodle_url($CFG->wwwroot.
    '/blocks/timetracker_admin/transactiondetail.php',$urlparams);
$PAGE->set_url($transdetailurl);
$PAGE->set_pagelayout('base');

$strtitle = get_string('eligibleheader','block_timetracker_admin'); 
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');

$PAGE->navbar->add(get_string('blocks'));
//$PAGE->navbar->add(get_string('pluginname','block_timetracker'), $);
$PAGE->navbar->add($strtitle);

$mform = new timetracker_admin_transaction_detail_form($transid, $canmanage, 
    $transaction->submitted, $sort);

if ($mform->is_cancelled()){ //user clicked cancel
    //redirect($nextpage);
    redirect($index, $indexparams);

} else if ($formdata=$mform->get_data()){

    /*
    * foreach timesheet listed, set set its transactionid, but not it's SUBMITTED
    * timestamp until the transaction is finalized.
    */

    if(!in_array(1, $formdata->signid)){
        $status = 'No work units selected';
    }

    $count = 0;
    foreach($formdata->signid as $timesheetid=>$value){
        //give the timesheet a transaction id
        if($value == 0) continue;
        
        $timesheet = $DB->get_record('block_timetracker_timesheet',
            array('id'=>$timesheetid));
        if(!$timesheet){
            print_error('Invalid timesheet id selected');
        } else {
            $timesheet->transactionid = $transid;
            $DB->update_record('block_timetracker_timesheet', $timesheet);
        }
    }

    redirect($transdetailurl);

} else {
    //form is shown for the first time
    echo $OUTPUT->header();
    $tabs = get_admin_tabs($urlparams, $canmanage, $courseid);
    $tabs = array($tabs);
    print_tabs($tabs,'home');
    $mform->display();
    echo $OUTPUT->footer();
}
