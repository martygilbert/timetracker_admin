
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
 * This form will allow administration to batch sign timesheets 
    electronically and export to payroll.
 *
 * @package    Block
 * @subpackage TimeTracker_Admin
 * @copyright  2011 Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */
//define('CLI_SCRIPT', true);
require_once('../../config.php');

require_login();

$courseid   = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

if(!$course){
    print_error("Course with id $courseid does not exist");
}

$PAGE->set_course($course);
$context = $PAGE->context;


global $COURSE, $CFG, $DB, $USER;

$catcontext = get_context_instance(CONTEXT_COURSECAT, $COURSE->category);
require_capability('block/timetracker_admin:viewtransactions', $catcontext);

$courses = get_courses ($COURSE->category, 'shortname ASC', 'c.id,c.shortname,c.fullname');

$strtitle = 'View courses and supervisors';
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');

$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add($strtitle);

echo $OUTPUT->header();

$canmanage = false;
if (has_capability('block/timetracker_admin:managetransactions', $catcontext)) { 
    $canmanage = true;
}

$urlparams['id'] = $courseid;

$tabs = get_admin_tabs($urlparams, $canmanage, $courseid);
$tabs = array($tabs);
print_tabs($tabs,'viewtrans');


echo $OUTPUT->box_start('generalbox boxaligncenter');
echo '<table width="95%">
<tr>
    <th style="text-align: left">Dept shortname</th>
    <th style="text-align: left">Dept fullname</th>
    <th style="text-align: left">Supervisor name</th>
</tr>';

foreach($courses as $course){

    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    $supervisors = get_enrolled_users($context, 'block/timetracker:manageworkers');

    echo '<tr>
        <td>'.$course->shortname.'</td>
        <td>'.$course->fullname.'</td><td>';

    $superstring='';

    foreach ($supervisors as $supervisor){
        //echo $course->shortname.','.$course->fullname.','.$supervisor->lastname.','.
            //$supervisor->firstname.','.strtolower($supervisor->email).'<br />'."\n";
        $superstring .= $supervisor->firstname.' '.$supervisor->lastname.', ';
    }

    //error_log($superstring);
    echo substr($superstring, 0, -2);

    echo '</td></tr>';
    

}
echo '</table>';
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
