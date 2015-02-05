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

require_login();

$courseid = required_param('id', PARAM_INT);
$timesheetid = required_param('timesheetid', PARAM_INT);
$transid = required_param('transid', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

if(!$course){
    print_error("Course with id $courseid does not exist");
}

$PAGE->set_course($course);
$context = $PAGE->context;

global $COURSE;

$urlparams['id']        = $courseid;
$urlparams['transid']   = $transid;

$transactiondetailurl = new moodle_url ($CFG->wwwroot.
    '/blocks/timetracker_admin/transactiondetail.php', $urlparams);

$catcontext = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);

if (!has_capability('block/timetracker_admin:managetransactions', $catcontext)) { 
    print_error('You don\'t have the permission to remove this timesheet');
}


//essentially, remove the transactionid and 'submitted' out of this timesheet
$timesheet = $DB->get_record('block_timetracker_timesheet',
    array('id'=>$timesheetid));

if(!$timesheet){
    $status = 'No timesheet with that ID can be found';
} else {
    $timesheet->submitted       = 0;
    $timesheet->transactionid   = 0;
    $res = $DB->update_record('block_timetracker_timesheet', $timesheet);
    if(!$res){
        $status = 'Removing timesheet failed';
    } else {
        $status = 'Timesheet removed successfully';
    }

}

redirect($transactiondetailurl, $status, 2);
