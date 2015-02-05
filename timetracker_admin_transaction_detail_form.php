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

require_once("$CFG->libdir/formslib.php");
//require_once('lib.php');
require_once('../timetracker/lib.php');

class timetracker_admin_transaction_detail_form extends moodleform {
    function timetracker_admin_transaction_detail_form($transid, $canmanage, $submitted=0,
        $sort='name'){

       $this->transid   = $transid;
       $this->canmanage = $canmanage;
       $this->submitted = $submitted;
       $this->sort      = $sort;
       parent::__construct();
    }

    function definition() {
        global $CFG, $DB, $COURSE, $USER, $OUTPUT;

        $mform =& $this->_form;
        
        $mform->addElement('hidden','transid', $this->transid);
        $mform->setType('transid', PARAM_INT);

        $mform->addElement('hidden','id', $COURSE->id);
        $mform->setType('id', PARAM_INT);

        if(!$this->submitted){
            $mform->addElement('header','general',
                get_string('signheader','block_timetracker_admin'));
            $courses = get_courses($COURSE->category, 'fullname ASC', 'c.id,c.shortname');
    
            if($courses){
                $sql = 'SELECT sheet.*,firstname,lastname,maxtermearnings,'.
                    '(reghours + othours) AS SUM_HOURS,(regpay + otpay) AS SUM_PAY '. 
                    'from '.$CFG->prefix.
                    'block_timetracker_timesheet as sheet,'.
                    $CFG->prefix.'block_timetracker_workerinfo as worker '. 
                    'where sheet.userid=worker.id AND transactionid=0 AND '.
                    'workersignature != 0 AND supervisorsignature != 0 AND '.
                    'sheet.courseid in (';
                $list = implode(",", array_keys($courses));   
                $sql .= $list.')';

                if($this->sort == 'name'){
                    $sql .= ' ORDER BY lastname,firstname';
                } else if ($this->sort == 'hours'){
                    $sql .= ' GROUP BY sheet.id ORDER BY SUM_HOURS DESC';
                } else if ($this->sort == 'pay'){
                    $sql .= ' GROUP BY sheet.id ORDER BY SUM_PAY DESC';
                }

                $timesheets = $DB->get_records_sql($sql);
            } else {
                print_error('nocourseserror','block_timetracker_admin');
            }
            
            if(!$timesheets){
                $mform->addElement('html',get_string('notstosign','block_timetracker_admin'));
            } else {

                $sortparams['id'] = $COURSE->id;
                $sortparams['transid'] = $this->transid;
                $sorturl = new moodle_url($CFG->wwwroot.
                    '/blocks/timetracker_admin/transactiondetail.php', $sortparams);

                $this->add_checkbox_controller(1);
                $mform->addElement('html','<table align="center" border="1" cellspacing="10px"
                    cellpadding="5px" width="95%">');
                    $row = '<tr>';
                    if($this->canmanage)
                        $row .= '<td style="font-weight: bold; text-align:center">Select</td>';

                    $row .= '<td style="font-weight: bold;">Worker ';
                    if($this->sort == 'name'){
                        $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
                    } else {
                        $sorturl->params(array('sort'=>'name'));
                        $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort',
                            'sort'));
                    } 
                    $row .= '</td>';

                    $row .= '<td style="font-weight: bold;">Supervisor</td>';

                    $row .= '<td style="font-weight: bold; text-align: right">Hours ';
                    if($this->sort == 'hours'){
                        $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
                    } else {
                        $sorturl->params(array('sort'=>'hours'));
                        $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort',
                            'sort'));
                    }
                    $row .= '</td>';

                    $row .= '<td style="font-weight: bold; text-align: right">Pay ';
                    if($this->sort == 'pay'){
                        $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
                    } else {
                        $sorturl->params(array('sort'=>'pay'));
                        $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort',
                            'sort'));
                    }
                    $row .= '</td>';

                    $row .= '<td style="font-weight: bold;">Range</td>
                        <td style="font-weight: bold; text-align: right">Term</td>
                        <td style="font-weight: bold; text-align: right">Submitted</td>
                        <td style="font-weight: bold; text-align: right">Overall</td>
                        <td style="font-weight: bold; text-align: center">Actions</td>
                    </tr>';
                $mform->addElement('html',$row);
    
                foreach ($timesheets as $timesheet){
                    $limitsql = 'SELECT MIN(timein) as min, MAX(timeout) as max from '.
                        $CFG->prefix.'block_timetracker_workunit WHERE timesheetid='.
                        $timesheet->id;

                    $limits = $DB->get_record_sql($limitsql);
    
                    if($timesheet->otpay > 0){
                        $mform->addElement('html','<tr style="background: yellow">');
                    } else {
                        $mform->addElement('html','<tr>');
                    }
                    if($this->canmanage){
                        $mform->addElement('html', '<td style="text-align: center">');
                        $mform->addElement('advcheckbox', 'signid['.$timesheet->id.']','',
                            null, array('','group'=>1));
                        $mform->addElement('html', '</td>');
                    }
    

                    $reporturl = new moodle_url($CFG->wwwroot.'/blocks/timetracker/reports.php',
                        array('id'=>$timesheet->courseid,
                        'userid'=>$timesheet->userid,
                        'repstart'=>$limits->min,
                        'repend'=>$limits->max));
                    
                    $workername = $OUTPUT->action_link($reporturl, truncatestr($timesheet->lastname.', '.$timesheet->firstname));

                    $mform->addElement('html','<td>');
                    $mform->addElement('html',$workername);
                    $mform->addElement('html','</td>');
    
                    $mform->addElement('html','<td>');
                    $super = $DB->get_record('user', 
                        array('id'=>$timesheet->supermdlid));
                    if(!$super){
                        $name = 'Undefined';
                    } else {
                        $name = $super->lastname.', '.$super->firstname;
                        $name = truncatestr($name);
                    }
                    $mform->addElement('html', $name);
                    $mform->addElement('html','</td>');
    
                    $hours = 0;
                    $pay = 0;
                    $hours += $timesheet->reghours;
                    $hours += $timesheet->othours;
                    $pay += $timesheet->regpay;
                    $pay += $timesheet->otpay;
        
                    $mform->addElement('html','<td style="text-align: right">');
                    $mform->addElement('html',number_format(round($hours,3),3));
                    $mform->addElement('html','</td><td style="text-align: right">');
                    $mform->addElement('html','$'.number_format(round($pay,2),2));
                    $mform->addElement('html','</td>');


                    $mform->addElement('html','<td>');
                    $mform->addElement('html',
                        userdate($limits->min, 
                        get_string('simpledate', 'block_timetracker')).'-'.
                        userdate($limits->max, 
                        get_string('simpledate', 'block_timetracker'))
                        );
                    $mform->addElement('html','</td>');


                    //Bug/Feature #266 - add Earnings column with
                    //Submitted/total lifetime earnings 
                    $total      = get_total_earnings($timesheet->userid, $timesheet->courseid);
                    $official   = get_total_earnings($timesheet->userid, $timesheet->courseid,
                        true); 
                    $term       = get_earnings_this_term($timesheet->userid,
                        $timesheet->courseid, $limits->max, true);

                    if($timesheet->maxtermearnings > 0 &&
                        ($term+$pay) > $timesheet->maxtermearnings){
                        $earnings_style = 'text-align: right; background: #ff9f17;';
                    } else {
                        $earnings_style = 'text-align: right';
                    }

                    $mform->addElement('html', '<td style="'.$earnings_style.'">');
                    $mform->addElement('html', '$'.number_format($term, 2));
                    $mform->addElement('html', '</td>');

                    $mform->addElement('html', '<td style="text-align: right">');
                    $mform->addElement('html', '$'.number_format($official, 2));
                    $mform->addElement('html', '</td>');

                    $mform->addElement('html', '<td style="text-align: right">');
                    $mform->addElement('html', '$'.number_format($total, 2));
                    $mform->addElement('html', '</td>');
                    
                    $mform->addElement('html','<td style="text-align: center">');

                    $viewparams['id'] = $timesheet->courseid;
                    $viewparams['userid'] = $timesheet->userid;
                    $viewparams['timesheetid'] = $timesheet->id;
                    $viewurl = 
                        new moodle_url($CFG->wwwroot.
                            '/blocks/timetracker/timesheet_fromid.php', $viewparams);
                    $viewaction = 
                        $OUTPUT->action_icon($viewurl, new pix_icon('date','View Timesheet',
                        'block_timetracker'));
                    
                    $rejectparams['id']             = $COURSE->id;
                    $rejectparams['transid']        = $this->transid;
                    $rejectparams['timesheetid']    = $timesheet->id;
                    $rejecturl = 
                        new moodle_url($CFG->wwwroot.
                        '/blocks/timetracker_admin/timesheetreject.php', $rejectparams);
    
                    $rejecticon = new pix_icon('delete', 
                        get_string('reject'),'block_timetracker');
                    $rejectaction = $OUTPUT->action_icon($rejecturl, $rejecticon,
                        new confirm_action(get_string('rejectts','block_timetracker_admin')));
    
                    $mform->addElement('html',$viewaction);
                    $mform->addElement('html',' ');
                    if($this->canmanage)
                        $mform->addElement('html',$rejectaction);
                    $mform->addElement('html','</tr>');
                }
    
                $mform->addElement('html','</table>');
            
                if($this->canmanage){
                    $buttonarray=array();
                    $buttonarray[] = &$mform->createElement('submit',
                        'signbutton',get_string('addtocurrtrans','block_timetracker_admin'));
                    $mform->addGroup($buttonarray, 'buttonar','',array(' '), false);
                }
                //$mform->disabledIf('buttonar','supervisorsig');
            }
        }

        $sql = 'SELECT sheet.*,firstname,lastname,'.
            '(reghours + othours) AS SUM_HOURS,(regpay + otpay) AS SUM_PAY '. 
            'from '.$CFG->prefix.
            'block_timetracker_timesheet as sheet,'.
            $CFG->prefix.'block_timetracker_workerinfo as worker '. 
            'where transactionid='.$this->transid.' AND '.
            'sheet.userid=worker.id'; 

        if($this->sort == 'name'){
            $sql .= ' ORDER BY lastname,firstname';
        } else if ($this->sort == 'hours'){
            $sql .= ' GROUP BY sheet.id ORDER BY SUM_HOURS DESC';
        } else if ($this->sort == 'pay'){
            $sql .= ' GROUP BY sheet.id ORDER BY SUM_PAY DESC';
        }

        $timesheets = $DB->get_records_sql($sql);

        $mform->addElement('header', 'general',
            get_string('addedtimesheets', 'block_timetracker_admin', sizeof($timesheets)));

        $row = '';
        if(!$timesheets){
            $mform->addElement('html',
                '<br />No timesheets have been added to this transaction<br />');
        } else {
            $mform->addElement('html','<br /><table align="center" border="1" cellspacing="10px"
                cellpadding="5px" width="95%">');

            $sortparams['id'] = $COURSE->id;
            $sortparams['transid'] = $this->transid;
            $sorturl = new moodle_url($CFG->wwwroot.
                '/blocks/timetracker_admin/transactiondetail.php', $sortparams);

            $row .= '<td style="font-weight: bold;">Worker ';
            if($this->sort == 'name'){
                $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
            } else {
                $sorturl->params(array('sort'=>'name'));
                $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort', 'sort'));
            } 
            $row .= '</td>';

            $row .= '<td style="font-weight: bold;">Supervisor</td>';

            $row .= '<td style="font-weight: bold; text-align: right">Hours ';
            if($this->sort == 'hours'){
                $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
            } else {
                $sorturl->params(array('sort'=>'hours'));
                $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort', 'sort'));
            }
            $row .= '</td>';

            $row .= '<td style="font-weight: bold; text-align: right">Pay ';
            if($this->sort == 'pay'){
                $row .= $OUTPUT->pix_icon('t/sort_desc', 'sorted');
            } else {
                $sorturl->params(array('sort'=>'pay'));
                $row .= $OUTPUT->action_icon($sorturl, new pix_icon('t/sort', 'sort'));
            }
            $row .= '</td>';

            $row .= '<td style="font-weight: bold;">Range</td>
                <td style="font-weight: bold; text-align: center">Actions</td>
            </tr>';

            $mform->addElement('html',$row);

            $totalhours = 0;
            $totalpay = 0;
            foreach($timesheets as $timesheet){
                $limitsql = 'SELECT MIN(timein) as min,MAX(timeout) as max from '.$CFG->prefix.
                    'block_timetracker_workunit WHERE timesheetid='.$timesheet->id;
                $limits = $DB->get_record_sql($limitsql);

                $mform->addElement('html','<tr>');

                $mform->addElement('html','<td>');
                $workername = $timesheet->lastname.', '.$timesheet->firstname;
                $workername = truncatestr($workername);
                $mform->addElement('html',$workername);
                $mform->addElement('html','</td>');

                $mform->addElement('html','<td>');
                $super = $DB->get_record('user', 
                    array('id'=>$timesheet->supermdlid));
                if(!$super){
                    $name = 'Undefined';
                } else {
                    $name = $super->lastname.', '.$super->firstname;
                    $name = truncatestr($name);
                }
                $mform->addElement('html', $name);
                $mform->addElement('html','</td>');

                $hours      = 0;
                $pay        = 0;
                $hours      += $timesheet->reghours;
                $hours      += $timesheet->othours;
                $pay        += $timesheet->regpay;
                $pay        += $timesheet->otpay;

                $totalpay   += $pay;
                $totalhours += $hours;

                $mform->addElement('html','<td style="text-align: right">');
                $mform->addElement('html',number_format(round($hours,3),3));
                $mform->addElement('html','</td><td style="text-align: right">');
                $mform->addElement('html','$'.number_format(round($pay,2),2));
                $mform->addElement('html','</td>');


                $mform->addElement('html','<td>');
                $mform->addElement('html',
                    userdate($limits->min, 
                    get_string('simpledate', 'block_timetracker')).'-'.
                    userdate($limits->max, 
                    get_string('simpledate', 'block_timetracker'))
                    );
                $mform->addElement('html','</td>');
                
                $mform->addElement('html','<td style="text-align: center">');
                
                $viewparams['id'] = $timesheet->courseid;
                $viewparams['userid'] = $timesheet->userid;
                $viewparams['timesheetid'] = $timesheet->id;
                $viewurl = 
                    new moodle_url($CFG->wwwroot.'/blocks/timetracker/timesheet_fromid.php',
                    $viewparams);
                $viewaction = 
                    $OUTPUT->action_icon($viewurl, new pix_icon('date','View Timesheet',
                    'block_timetracker'));
                
                $removeparams['id'] = $timesheet->courseid;
                $removeparams['timesheetid'] = $timesheet->id;
                $removeparams['transid'] = $timesheet->transactionid;

                $removeurl = 
                    new moodle_url($CFG->wwwroot.
                    '/blocks/timetracker_admin/removetimesheet.php', $removeparams);

                $removeicon = new pix_icon('delete', get_string('remove'),'block_timetracker');
                $removeaction = $OUTPUT->action_icon($removeurl, $removeicon,
                    new confirm_action(get_string('removets','block_timetracker_admin')));

                $mform->addElement('html',$viewaction);
                $mform->addElement('html',' ');
                if($this->canmanage && !$this->submitted)
                    $mform->addElement('html',$removeaction);

                $mform->addElement('html','</tr>');
            }
            if($timesheets){
                $row = '<tr>
                    <td>&nbsp;</td>
                    <td style="text-align: right; font-weight: bold">Totals:</td>
                    <td style="text-align: right;">'.
                        number_format(round($totalhours,3),3).'</td>
                    <td style="text-align: right;">$'.
                        number_format(round($totalpay,2),2).'</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>';

                $mform->addElement('html', $row);
            }
            $mform->addElement('html', '</table>');
        }

        
    }

}
?>
