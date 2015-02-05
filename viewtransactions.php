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

$urlparams['id'] = $courseid;
$viewtransactionsurl = new moodle_url ($CFG->wwwroot.
    '/blocks/timetracker_admin/viewtransactions.php', $urlparams);

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

//gets here, can at least view

$PAGE->set_url($viewtransactionsurl);

//$PAGE->set_pagelayout('base');

$strtitle = get_string('viewtransheader','block_timetracker_admin'); 
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');

$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add($strtitle);

echo $OUTPUT->header();
$tabs = get_admin_tabs($urlparams, $canmanage, $courseid);
$tabs = array($tabs);
print_tabs($tabs,'viewtrans');


echo $OUTPUT->box_start('generalbox boxaligncenter');

$transactions = $DB->get_records('block_timetracker_transactn', 
    array('categoryid'=>$COURSE->category), 'created DESC');

$addtransurl = new moodle_url($CFG->wwwroot.
        '/blocks/timetracker_admin/addtransaction.php', $urlparams);
$addtransicon = new pix_icon('add', 'Add transaction', 'block_timetracker');
$addtransaction = $OUTPUT->action_icon($addtransurl, $addtransicon);

if(!$transactions){
    
    echo '<span style="font-weight: bold">'.
        'No transactions for this category</span><br /><br />';
    echo $addtransaction.' '.$OUTPUT->action_link($addtransurl,
        'Add a new transaction').'<br />';

} else {
    echo $addtransaction.' '.$OUTPUT->action_link($addtransurl,'Add transaction').'<br />';
    echo '<br /><table border="1" cellspacing="2" cellpadding="2" width="95%">';
    echo '<tr>'.
        '<td style="font-weight: bold; text-align:center">Status</td>'.
        '<td style="font-weight: bold">Created</td>'.
        '<td style="font-weight: bold">Description</td>'.
        '<td style="font-weight: bold">Status</td>'.
        '<td style="font-weight: bold; text-align:center">Action</td>'.
        '</tr>';

    foreach ($transactions as $transaction){
        $row    = '<tr>';
        $row    .= '<td style="text-align: center">';
        if($transaction->submitted > 0){
            $pending = false;
            //a seal?
            $row .=
                ' '.html_writer::empty_tag('img',
                array('src' =>
                $CFG->wwwroot.'/blocks/timetracker/pix/certified.png',
                'class' => 'icon'));
        } else {
            $pending = true;
            //a seal?
            $row .=
                ' '.html_writer::empty_tag('img',
                array('src' =>
                $CFG->wwwroot.'/blocks/timetracker/pix/wait.png',
                'class' => 'icon'));
        }
        $row .= '</td>';

        $row    .= '<td>'.userdate($transaction->created,
            get_string('datetimeformat', 'block_timetracker')).'</td>';
        $row    .= '<td>'.$transaction->description.'</td>';

        if(!$pending){
            $row    .= '<td>Processed on '.
                userdate($transaction->submitted, get_string('dateformat',
                'block_timetracker')). 
                '</td>';
        } else {
            $row    .= '<td style="font-weight: bold">Pending</td>';
        }

        $row    .= '<td style="text-align:center">';

        
        if($canmanage && $pending){
            //FINALIZE THE TRANSACTION
            $finalizeurl = new moodle_url($CFG->wwwroot.
                '/blocks/timetracker_admin/finalizetransaction.php', $urlparams);
            $finalizeurl->params(array('transid'=>$transaction->id));
            $finalizeaction=$OUTPUT->action_icon($finalizeurl, 
                new pix_icon('approve', 'Finalize transaction','block_timetracker'),
                new confirm_action(get_string('finalizeconfirm', 
                'block_timetracker_admin')));

            //delete transaction
            $deletetransurl = new moodle_url($CFG->wwwroot.
                '/blocks/timetracker_admin/deletetransaction.php', $urlparams);
            $deletetransurl->params(array('transid'=>$transaction->id));
            $deletetransaction=$OUTPUT->action_icon($deletetransurl, 
                new pix_icon('delete', 'Delete transaction','block_timetracker'),
                new confirm_action(get_string('deletetransconfirm', 
                'block_timetracker_admin')));

            $row .= ' '.$finalizeaction.' '.$deletetransaction;
        } else if ($canmanage && !$pending) {

            //generate the CSV for this "TRANSACTION"
            $exportsurl = new moodle_url($CFG->wwwroot.
                '/blocks/timetracker_admin/exporttransaction.php', $urlparams);
            $exportsurl->params(array('transid'=>$transaction->id));
            $exportsaction=$OUTPUT->action_icon($exportsurl, 
                new pix_icon('export', 'Export data','block_timetracker'),
                new confirm_action(get_string('longprocessconfirm',
                'block_timetracker_admin')));
            $row .= ' '.$exportsaction;

        }

        $transactiondetailurl = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker_admin/transactiondetail.php', $urlparams);
        $transactiondetailurl->params(array('transid'=>$transaction->id));
        $transdetailaction= $OUTPUT->action_icon($transactiondetailurl, 
            new pix_icon('info', 'Transaction details','block_timetracker'));
        $row .= ' '.$transdetailaction;

        //add button for download all to pdf
        $downloadzipurl     = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker_admin/timesheets_from_transid.php', $urlparams);
        $downloadzipurl->params(array('transid'=>$transaction->id));
        $downloadzipaction  = $OUTPUT->action_icon($downloadzipurl,
            new pix_icon('zip', 'Download Zip', 'block_timetracker_admin'),
            new confirm_action(get_string('longprocessconfirm',
            'block_timetracker_admin')));
        
        $row .= ' '.$downloadzipaction;
        $row    .= '</td>';
        
        echo $row."\n";
    }
    echo '</table>';
}

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
