<?php

require_once('../../config.php');
require_once('../timetracker/lib.php');

require_login();

$courseid = required_param('id', PARAM_INT);
$transid = required_param('transid', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

if(!$course){
    print_error("Course with id $courseid does not exist");
}

$transaction = $DB->get_record('block_timetracker_transactn', 
    array('id' => $transid), '*', MUST_EXIST);

if(!$transaction){
    print_error("Transaction with id $transid does not exist, or has not been finalized");
}

$PAGE->set_course($course);
$context = $PAGE->context;

global $COURSE;

$urlparams['id']        = $courseid;
$urlparams['transid']   = $transid;

$viewtransactionsurl = new moodle_url ($CFG->wwwroot.
    '/blocks/timetracker_admin/viewtransactions.php', $urlparams);
$viewtransactionsurl->remove_params('transid');

$catcontext = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);

if (!has_capability('block/timetracker_admin:viewtransactions', $catcontext)) { 
    print_error('You don\'t have the permission to view this transaction');
}

$categoryinfo = $DB->get_record('course_categories', array('id'=>$COURSE->category));
$catname = str_replace(' ','', $categoryinfo->name);

$filename = date("Y_m_d", $transaction->submitted).'_'.$catname.'_Earnings.csv';
header('Content-type: application/ms-excel');
header('Content-Disposition: attachment; filename='.$filename);

//$headers = "Department,Last Name,First Name,Email,Earnings,Max Term Earnings,Remaining \n";
$headers = 
    "DET,DETCode,ID,Hours,Amount,Budget,Department,Last Name,First Name,Max Term Earnings,Remaining \n";

echo $headers;

$timesheets = $DB->get_records('block_timetracker_timesheet',
    array('transactionid'=>$transid));

$courselist = get_courses($COURSE->category, 'fullname ASC', 'c.id, c.shortname');
$workers = $DB->get_records_select('block_timetracker_workerinfo', 
    'courseid in ('.implode(',', array_keys($courselist)).')');

foreach($timesheets as $timesheet){

    
    if($timesheet->reghours == 0) continue;
    
    $worker = $workers[$timesheet->userid];
    $course = $courselist[$timesheet->courseid];
    
    $remaining = $worker->maxtermearnings - ($timesheet->regpay + $timesheet->otpay);
    if($remaining < 0) $remaining = 0;
    
    
    if ($timesheet->regpay > 0){
        $contents =
            "E,Reg,$worker->idnum,".$timesheet->reghours.','.round($timesheet->regpay, 2).','.
            "$worker->budget,$course->shortname,$worker->lastname,$worker->firstname,".
            "$worker->maxtermearnings,$remaining\n";
        echo $contents;
    }
    
    if($timesheet->otpay > 0){
        $contents =
            "E,OT,$worker->idnum,".$timesheet->othours.','.round($timesheet->otpay, 2).','.
            "$worker->budget,$course->shortname,$worker->lastname,$worker->firstname,".
            "$worker->maxtermearnings,$remaining\n";
        echo $contents;
    } 
}    
?>
