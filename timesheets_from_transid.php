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
 *
 * @package    TimeTracker
 * @copyright  Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/blocks/timetracker/timesheet_pdf.php');
require_once($CFG->dirroot.'/lib/filelib.php');

require_login();

$courseid = required_param('id', PARAM_INT);
$transid = required_param('transid', PARAM_INT);

//check capabilities here
//XXX
//TODO

//see if any timesheets go with this transaction
$timesheets     = $DB->get_records('block_timetracker_timesheet', 
    array('transactionid'=>$transid));

$transaction    = $DB->get_record('block_timetracker_transactn',
    array('id'=>$transid));

$nexturl = new moodle_url($CFG->wwwroot.
    '/blocks/timetracker_admin/viewtransactions.php',
    array('id'=>$courseid));

if(!$timesheets){
    redirect($nexturl, 'No timesheets associated with this transaction', 3);
}

$basepath = $CFG->dataroot.'/temp/timetracker/'.$courseid.'_'.
    $USER->id.'_'.sesskey();

$status = check_dir_exists($basepath, true);
if(!$status){
    print_error('Error creating temporary directory to store pdf files');
    return;
}

$files = array();
foreach($timesheets as $timesheet){
    
    $fn = generate_pdf_from_timesheetid($timesheet->id, 
        $timesheet->userid, $timesheet->courseid, 'F', $basepath);
    $files[$fn] = $basepath.'/'.$fn;
    
}

$desc   = userdate($transaction->submitted, '%Y%m%d_%H%M', 99,false);

$zipfile    = $basepath.'/'.$desc.'_Transaction.zip';
$zippacker  = get_file_packer('application/zip');
$zippacker->archive_to_pathname($files, $zipfile);
send_file($zipfile, $desc.'_Transaction.zip', 'default', '0',
    false, false, '', true);

fulldelete($basepath);

