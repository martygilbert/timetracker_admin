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
 * Bulk user registration script from a comma separated file
 *
 * @package    tool
 * @subpackage uploaduser
 * @copyright  2004 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir.'/csvlib.class.php');
require_once('activation_form.php');
require_once('lib.php');
require_once('../timetracker/lib.php');


//DELETE ME!!
//require_once($CFG->libdir.'/adminlib.php');
//require_once($CFG->dirroot.'/user/profile/lib.php');
//require_once($CFG->dirroot.'/group/lib.php');
//require_once($CFG->dirroot.'/cohort/lib.php');
//require_once('locallib.php');

$courseid       = required_param('id', PARAM_INT);

$iid            = optional_param('iid', '', PARAM_INT);
$previewrows    = optional_param('previewrows', 10, PARAM_INT);

@set_time_limit(60*60); // 1 hour should be enough
raise_memory_limit(MEMORY_HUGE);

require_login();

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/blocks/timetracker_admin/upload_activation.php',
    array('id'=>$courseid)));
$context = $PAGE->context;
global $COURSE;


$strtitle = get_string('activateworkers','block_timetracker_admin'); 
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');

//$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add($strtitle);

$supercatcontext = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);

//admin_externalpage_setup('tooluploaduser');
require_capability('block/timetracker_admin:managetransactions', $supercatcontext);

$returnurl = new moodle_url('/course/view.php', array('id'=>$courseid));

// array of all valid fields for validation
$STD_FIELDS = array('dept', 'lastname', 'email', 'firstname', 
        'budget', 'payrate', 'maxterm', 'idnum', 'supervisor', 'position', 'institution');

if (empty($iid)) {

    $mform1 = new activation_form1(null, array('id'=>$courseid));

    if ($formdata = $mform1->get_data()) {
        $iid = csv_import_reader::get_new_iid('activateworkers');
        $cir = new csv_import_reader($iid, 'activateworkers');

        $content = $mform1->get_file_content('userfile');

        $readcount = 
            $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);

        unset($content);

        if ($readcount === false) {
            print_error('csvloaderror', '', $returnurl);
        } else if ($readcount == 0) {
            print_error('csvemptyfile', 'error', $returnurl);
        }
        // test if columns ok
        $filecolumns = tt_validate_upload_columns($cir, $STD_FIELDS, $returnurl);
        // continue to form2

    } else {

        echo $OUTPUT->header();

        
        echo $OUTPUT->heading_with_help(
            get_string('activateworkers', 'block_timetracker_admin'), 'activateworkers', 
            'block_timetracker_admin');
        $viewsupers = $CFG->wwwroot.'/blocks/timetracker_admin/listsupers.php?id='.
            $courseid;

        $instructions = $OUTPUT->box_start('box generalbox boxaligncenter');
        $instructions .= '<h2>Upload instructions</h2>';
        $instructions .= '<ol>';
        $instructions .= '<li>Prepare a spreadsheet that includes <i>at least</i>'.
            ' the following column headings, plus a row with each workers\' data:';
        $instructions .= '<ul>';
            $instructions .= '<li><b>email</b> - the email address of the worker</li>';
            $instructions .= '<li><b>dept</b> - the <i>exact</i> '.
                'shortname of the department. See the '.
                '<a href="'.$viewsupers.'">View supers</a> page for an exact list'.
                ' for this category</li>';
            $instructions .= '<li><b>budget</b> - the budget number</li>';
            $instructions .= '<li><b>idnum</b> - the payroll id number</li>';
            $instructions .= '<li><b>payrate</b> - the hourly payrate</li>';
        $instructions .= '</ul> Other fields are optional, and can be found by '.
            'clicking the help button beside "Activate workers" above</li>';

        $instructions .= '<li>Highlight the columns to the right of your data, '.
            'and press the delete key</li>';
        $instructions .= '<li>Highlight the rows below your data, and press the '.
            'delete key</li>';
        $instructions .= '<li>Use \'Save-as\' to save the document as a \'CSV\' file</li>';
        $instructions .= '<li>Click \'Choose a file\' below, and upload your file</li>';
        $instructions .= '<li>Click \'Activate workers\'</li>';
        $instructions .= '<li>Fix any errors in your file, re-upload and try again</li>';
        $instructions .= '</ol>';
        $instructions .= $OUTPUT->box_end();
        
        print_collapsible_region($instructions, '', 'activateinstructions',
            'Upload instructions', '', true);

        $mform1->display();
        echo $OUTPUT->footer();
        die;
    }
} else {
    $cir = new csv_import_reader($iid, 'activateworkers');
    $filecolumns = tt_validate_upload_columns($cir, $STD_FIELDS, $returnurl);
}

$mform2 = new activation_form2(null, 
    array('columns'=>$filecolumns, 'data'=>array('iid'=>$iid, 'previewrows'=>$previewrows,
    'id'=>$courseid)));

// If a file has been uploaded, then process it
if ($formdata = $mform2->is_cancelled()) {
    $cir->cleanup(true);
    redirect($returnurl);

} else if ($formdata = $mform2->get_data()) {
    // Print the header
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('activateworkersresult', 'block_timetracker_admin'));

    //$optype = $formdata->uutype;

    //$updatetype        = isset($formdata->uuupdatetype) ? $formdata->uuupdatetype : 0;

    // verification moved to two places: after upload and into form2

    $workersnew      = 0;
    $workersupdated  = 0;
    $workerserrors   = 0;

    // caches
    // course cache - do not fetch all courses here, we  will not probably use them all anyway!

    $ccache         = array(); 
    $manualcache    = array(); // cache of used manual enrol plugins in each course

    // we use only manual enrol plugin here, if it is disabled no enrol is done
    if (enrol_is_enabled('manual')) {
        $manual = enrol_get_plugin('manual');
    } else {
        $manual = NULL;
    }


    // init csv import helper
    $cir->init();
    $linenum = 1; //column header is first line

    // init upload progress tracker
    $ttpt = new tt_progress_tracker();
    $ttpt->start(); // start table

    while ($line = $cir->next()) {
        $ttpt->flush();
        $linenum++;

        $ttpt->track('line', $linenum);

        $worker = new stdClass();

        // add fields to user object
        foreach ($line as $keynum => $value) {
            if (!isset($filecolumns[$keynum])) {
                // this should not happen
                continue;
            }
            $key = $filecolumns[$keynum];
            $worker->$key = $value;

        }

        // make sure we really have email
        if (empty($worker->email)) {
            $ttpt->track('status', get_string('missingfield', 'error', 'email'), 'error');
            $ttpt->track('email', 'Missing', 'error');
            $workerserrors++;
            continue;
        }

        if ($worker->email !== clean_param($worker->email, PARAM_EMAIL)) {
            $ttpt->track('status', get_string('invalidemail', 'error', 'email'), 'error');
            $ttpt->track('email', 'Invalid email', 'error');
            $workerserrors++;
            continue;
        }

         
        $existinguser = $DB->get_record('user', array('email'=>$worker->email));
        if(!$existinguser){
            $ttpt->track('status', 'Invalid email', 'error');
            $ttpt->track('email', 'No user with this email', 'error');
            $workerserrors++;
            continue;
        }
        

        $ttpt->track('email', $existinguser->email);
        $ttpt->track('lastname', $existinguser->lastname);
        $ttpt->track('firstname', $existinguser->firstname);



        if (empty($worker->dept)) {
            $workerserrors++;
            $ttpt->track('dept', 'No department set', 'error'); 
            continue;
        }

        $shortname = $worker->dept;
        if (!array_key_exists($shortname, $ccache)) {
            if (!$course = $DB->get_record('course', array('shortname'=>$shortname), 
                'id, shortname, category')) {
                $ttpt->track('dept', get_string('unknowncourse', 'error', 
                    s($shortname)), 'error');
                $workerserrors++;
                continue;
            }
            $ccache[$shortname] = $course;
        }

        $catcontext = get_context_instance(CONTEXT_COURSECAT, $ccache[$shortname]->category);
        if(!has_capability('block/timetracker_admin:managetransactions', $catcontext)){
            $ttpt->track('dept', 'No permission to add worker for '.$shortname, 'error');
            $workerserrors++;
            continue;
        }

        $courseid      = $ccache[$shortname]->id;
        $coursecontext = context_course::instance($courseid);
        if(!is_enrolled($coursecontext, $existinguser)){

            if(!has_capability('enrol/manual:enrol', $coursecontext)){
                $ttpt->track('dept', 'No permission to enroll worker for '.$shortname, 'error');
                $workerserrors++;
                continue;
            }
    
            if (!isset($manualcache[$courseid])) {
                $manualcache[$courseid] = false;
                if ($manual) {
                    if ($instances = enrol_get_instances($courseid, false)) {
                        foreach ($instances as $instance) {
                            if ($instance->enrol === 'manual') {
                                $manualcache[$courseid] = $instance;
                                break;
                            }
                        }
                    }
                }
            }
    
            if ($manual and $manualcache[$courseid]) {
    
                $roleid = $DB->get_field('role', 'id', array('archetype'=>'student'));
                if($roleid){
                    $manual->enrol_user($manualcache[$courseid], 
                        $existinguser->id, $roleid, time());
                } else {
                    $manual->enrol_user($manualcache[$courseid], $existinguser->id, 5, time());
                    //echo "Using 5 instead of the lookup value for $rolename\n";
                }
    
            } 
        } else {
            //already enrolled
            $ttpt->track('status', 'Already Enrolled');
        }//end of enrollment

        $config = get_timetracker_config($courseid);

        $worker->mdluserid          = $existinguser->id;
        $worker->courseid           = $courseid;
        $worker->active             = 1;

        if(!isset($worker->firstname))
            $worker->firstname      = $existinguser->firstname;
        if(!isset($worker->lastname))
            $worker->lastname       = $existinguser->lastname;
        if(!isset($worker->position))
            $worker->position       = $config['position'];
        if(!isset($worker->instituion))
            $worker->institution    = $config['institution'];
        if(!isset($worker->maxterm))
            $worker->maxterm        = $config['default_max_earnings'];
        if(!isset($worker->payrate))
            $worker->payrate        = $config['curr_pay_rate'];
        if(!isset($worker->supervisor))
            $worker->supervisor     = $config['supname'];

        $worker->timetrackermethod = $config['trackermethod'];

        //to match DB fields
        $worker->maxtermearnings = $worker->maxterm;
        $worker->currpayrate = $worker->payrate;

        
        //$ttpt->track('position', truncatestr($worker->position, 10, '...'));
        //$ttpt->track('institution', $worker->institution);
        $ttpt->track('maxterm', '$'.number_format($worker->maxterm, 2));
        $ttpt->track('payrate', '$'.number_format($worker->payrate, 2));
        $ttpt->track('idnum', $worker->idnum);
        $ttpt->track('budget', $worker->budget);
        $ttpt->track('dept', truncatestr($worker->dept,10,'...'));
        //$ttpt->track('supervisor', $worker->supervisor);

        //now they're enrolled, do everything else!
        $exists = $DB->get_record('block_timetracker_workerinfo',
            array('courseid'=>$courseid, 'mdluserid'=>$worker->mdluserid));

        if(!$exists){ //insert
            unset($worker->id);
            $result = $DB->insert_record('block_timetracker_workerinfo', $worker);
            $workersnew++;
        } else { //update
            $worker->id = $exists->id;
            $result = $DB->update_record('block_timetracker_workerinfo', $worker);
            $workersupdated++;
        }

        if(!$result){
            $ttpt->track('status', "ERROR", 'error');
            $workerserrors++;
        }  else {
            $ttpt->track('status', 'Success');
        }

        
    }
    $ttpt->close(); // close table

    $cir->close();
    $cir->cleanup(true);

    echo $OUTPUT->box_start('boxwidthnarrow boxaligncenter generalbox', 'uploadresults');
    echo '<p>';
    //echo get_string('userscreated', 'tool_uploaduser').': '.$usersnew.'<br />';
    //echo get_string('usersdeleted', 'tool_uploaduser').': '.$deletes.'<br />';
    echo 'New workers activated: '.$workersnew.'<br />';
    echo 'Existing workers updated and activated: '.$workersupdated.'<br />';
    if ($workerserrors) {
        echo get_string('workersskipped', 'tool_uploaduser').': '.$workersskipped.'<br />';
    }
    //echo get_string('usersweakpassword', 'tool_uploaduser').': '.$weakpasswords.'<br />';
    //echo get_string('errors', 'tool_uploaduser').': '.$userserrors.'</p>';
    echo $OUTPUT->box_end();

    echo $OUTPUT->continue_button($returnurl);
    echo $OUTPUT->footer();
    die;
}

// Print the header
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('activateworkers_preview', 'block_timetracker_admin'));

// NOTE: this is JUST csv processing preview, we must not prevent import from here if there is something in the file!!

// preview table data
$data = array();
$cir->init();
$linenum = 1; //column header is first line
$haserror = 0; // Keep status of any error.
//while ($linenum <= $previewrows and $fields = $cir->next()) {
while ($fields = $cir->next()) {
    $errorthistime = $haserror;
    $linenum++;
    $rowcols = array();
    $rowcols['line'] = $linenum;
    foreach($fields as $key => $field) {
        $rowcols[$filecolumns[$key]] = $field;
    }
    $rowcols['status'] = array();

    //email is req'd
    if (isset($rowcols['email'])) {
        if (!validate_email($rowcols['email'])) {
            $rowcols['status'][] = get_string('invalidemail');
            $haserror++;
        }
        if (!$DB->record_exists('user', array('email'=>$rowcols['email']))) {
            $rowcols['status'][] = 'Email address does NOT exist in TimeTracker';
            $haserror++;
        }
    } else {
        $rowcols['status'][] = get_string('missingemail');
        $haserror++;
    }

    //dept is required - display error if not there
    if (isset($rowcols['dept'])){
        if(!$DB->record_exists('course', array('shortname'=>$rowcols['dept']))){
            $rowcols['status'][] = 'Dept does NOT exist.';
            $haserror++;
        }
    } else {
        $rowcols['status'][] = 'Department is REQUIRED';
        $haserror++;
    } 

    //budget is required - display error if not there
    if (!isset($rowcols['budget'])){
        $rowcols['status'][] = 'Budget is REQUIRED';
        $haserror++;
    } 

    //idnum is required - display error if not there
    if (!isset($rowcols['idnum'])){
        $rowcols['status'][] = 'TimeTracker IdNumber is REQUIRED';
        $haserror++;
    } 

    //payrate is required - display error if not there
    if (!isset($rowcols['payrate'])){
        $rowcols['status'][] = 'Pay rate is REQUIRED';
        $haserror++;
    } else {
        if(!is_numeric($rowcols['payrate'])){
            $rowcols['status'][] = 'Pay rate must be a number';
            $haserror++;
        }
    }

    if($errorthistime == $noerror){
        $rowcols['status'][] = 'Ok';
    }

    $rowcols['status'] = implode('<br />', $rowcols['status']);
    $data[] = $rowcols;
}
if ($fields = $cir->next()) {
    $data[] = array_fill(0, count($fields) + 2, '...');
}
$cir->close();

$table = new html_table();
$table->id = "ttpreview";
$table->attributes['class'] = 'generaltable';
$table->tablealign = 'center';
$table->summary = get_string('activateworkers_preview', 'block_timetracker_admin');
$table->head = array();
$table->data = $data;

$table->head[] = get_string('csvline', 'block_timetracker_admin');
foreach ($filecolumns as $column) {
    $table->head[] = $column;
}
$table->head[] = get_string('status');

echo html_writer::tag('div', html_writer::table($table), array('class'=>'flexible-wrap'));

// Print the form if valid values are available
if (!$haserror) {
    $mform2->display();
} else {
    echo "<strong>Errors exist. Correct the errors in the CSV file, click 'back', re-upload, and try again.<br /></strong>";
}
echo $OUTPUT->footer();
die;

