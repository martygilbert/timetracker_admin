<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the terms of the GNU
// General Public License as published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
// without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
// PURPOSE.  See the GNU General
// Public License for more details.
//
// You should have received a copy of the GNU General Public License along with Moodle.
// If not, see <http://www.gnu.org/licenses/>.

/** Strings for component 'block_timetracker_admin', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package Block 
 * @subpackage TimeTracker 
 * @copyright  2012 Marty Gilbert
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'TimeTrackerAdmin';
$string['blocktitle'] = 'TimeTrackerAdmin';

//Capabilities
$string['timetracker_admin:managetransactions'] = 'Manage the transactions for a category';
$string['timetracker_admin:viewtransactions'] = 'View the transactions for a category';
$string['timetracker_admin:addinstance'] = 'Add block to course';

//EDIT_FORM STRINGS
$string['categoryconfig']       = 'Category configuration';
$string['workcategorydesc']     = 'Category ID';


//TRANSACTION STRING
$string['nocatpermission']  = 'You do not have the correct category permissions to
view this page. Contact your TimeTracker Administrator (timetracker@mhc.edu).';
$string['eligibleheader']   = 'Eligible Timesheets';


//transaction detail page
$string['signheader']       = 'Eligible timesheets';
$string['nocourseserror']   = 'No courses exist for this category';
$string['notstosign']       = 'No eligible timesheets exist';
$string['rejectts']         = 'If you choose to reject this timesheet, the hours will be
unsigned and the worker/supervisor will have to re-sign the hours. Proceed?';
$string['removets']         = 'Are you sure you would like to remove this timesheet from
this transaction?';
$string['addtocurrtrans']   = 'Add to transaction';
$string['addedtimesheets']  = 'Included timesheets ({$a})';


//add transaction form
$string['addtransactionheader'] = 'Add a new transaction';
$string['createtrans']          = 'Create transaction';

//view transactions page
$string['viewtransheader']      = 'View transactions';
$string['deletetransconfirm']   = 'Are you sure you would like to delete this transaction?
Every timesheet that is currently included in this transaction will be put back into the
elibile timesheets pool. Procced?';
$string['finalizeconfirm']      = 'Finalizing this transaction will include all of the
selected timesheets for payment. Proceed?';
$string['longprocessconfirm']   = 'This process can take quite a bit of time to complete. Proceed?';

//Transfer worker page
$string['transferworker']   = 'Transfer Worker';
$string['workerstotrans']   = 'Worker(s) to transfer';
$string['todepart']         = 'Destination department';


//Activation Upload Form
$string['csvdelimiter'] = 'CSV delimiter';
$string['encoding'] = 'Encoding';
$string['rowpreviewnum']    = 'Preview rows';
$string['activateworkers']  = 'Activate workers';
$string['uploadtype']   = 'Upload type';
$string['uploadtype_addnew']   = 'Activate new only, skip existing workers';
$string['uploadtype_addupdate']   = 'Activate new and update existing users';
$string['uploadtype_update']   = 'Update existing workers only';
$string['activateworkers_help'] = 
'Workers may be activated via text file. The format of the file should be as
follows:

* Each line of the file contains one record
* Each record is a series of data separated by commas (or other delimiters)
* The first record contains a list of fieldnames defining the format of the rest of the file
* Required fieldnames are email, dept, budget, idnum, payrate.
* Optional fieldnames: maxterm, firstname, lastname, position, institution, supervisor';
$string['activateworkersresult']    = 'Activate workers result';
$string['activateworkers_preview'] = 'Activate workers preview';
$string['csvline'] = 'CSV line';
$string['idnum'] = 'ID Number';
$string['payrate'] = 'Pay rate';
$string['maxterm'] = 'Max term earnings';
$string['budget'] = 'Budget';
$string['dept'] = 'Department';
$string['supervisor'] = 'Supervisor';
$string['position'] = 'Position';
$string['institution'] = 'Institution';



$string['remessagesent'] = 'The student and supervisor(s) has/have been notified that this student\'s timesheet has been rejected and requires a new student signature.';
