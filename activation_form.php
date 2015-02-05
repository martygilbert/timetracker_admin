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
 * Bulk user upload forms
 *
 * @package    tool
 * @subpackage uploaduser
 * @copyright  2007 Dan Poltawski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/formslib.php';


/**
 * Upload a file CVS file with user information.
 *
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activation_form1 extends moodleform {
    function definition () {


        $mform = $this->_form;
        $courseid    = $this->_customdata['id'];

        $mform->addElement('header', 'settingsheader', get_string('upload'));

        $mform->addElement('filepicker', 'userfile', get_string('file'));
        $mform->addRule('userfile', null, 'required');

        $choices = csv_import_reader::get_delimiter_list();

        $mform->addElement('select', 
            'delimiter_name', get_string('csvdelimiter', 'block_timetracker_admin'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = textlib::get_encodings();
        $mform->addElement('select', 
            'encoding', get_string('encoding', 'block_timetracker_admin'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        /*
        $choices = array('10'=>10, '20'=>20, '100'=>100, '1000'=>1000, '100000'=>100000);
        $mform->addElement('select', 
            'previewrows', get_string('rowpreviewnum', 'block_timetracker_admin'), $choices);
        $mform->setType('previewrows', PARAM_INT);
        */

        $mform->addElement('hidden', 'id', $courseid);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(false, 
            get_string('activateworkers', 'block_timetracker_admin'));

    }
}


/**
 * 
 * Specify user upload details
 *
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * Modified form used in upload_user tool for this. Thanks to Petr!
 */
class activation_form2 extends moodleform {
    function definition () {
        global $CFG, $USER;

        $mform   = $this->_form;
        $columns = $this->_customdata['columns'];
        $data    = $this->_customdata['data'];

        // upload settings and file
        //$mform->addElement('header', 'settingsheader', get_string('settings'));

        /*
        $choices = array(UU_WORKER_ADDNEW     => 
                            get_string('uploadtype_addnew', 'block_timetracker_admin'),
                         UU_WORKER_ADD_UPDATE => 
                            get_string('uploadtype_addupdate', 'block_timetracker_admin'),
                         UU_WORKER_UPDATE     => 
                            get_string('uploadtype_update', 'block_timetracker_admin'));
        */

        //$mform->addElement('select', 'uutype', 
            //get_string('uploadtype', 'block_timetracker_admin'), $choices);


        // hidden fields
        $mform->addElement('hidden', 'iid');
        $mform->setType('iid', PARAM_INT);

        $mform->addElement('hidden', 'previewrows');
        $mform->setType('previewrows', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('activateworkers', 'block_timetracker_admin'));

        $this->set_data($data);
    }

    /**
     * Form tweaks that depend on current data.
     */
    function definition_after_data() {
        $mform   = $this->_form;
        $columns = $this->_customdata['columns'];

        foreach ($columns as $column) {
            if ($mform->elementExists($column)) {
                $mform->removeElement($column);
            }
        }
    }

    /**
     * Server side validation.
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $columns = $this->_customdata['columns'];

        // look for other required data
        if (!in_array('email', $columns) and empty($data['email'])) {
            $errors['email'] = get_string('missingfield', 'error', 'email');
        }

        if (!in_array('dept', $columns) and empty($data['dept'])) {
            $errors['dept'] = get_string('missingfield', 'error', 'dept');
        }

        if (!in_array('budget', $columns) and empty($data['budget'])) {
            $errors['budget'] = get_string('missingfield', 'error', 'budget');
        }

        if (!in_array('payrate', $columns) and empty($data['payrate'])) {
            $errors['payrate'] = get_string('missingfield', 'error', 'payrate');
        }

        if (!in_array('idnum', $columns) and empty($data['idnum'])) {
            $errors['idnum'] = get_string('missingfield', 'error', 'idnum');
        }

        return $errors;
    }

    /**
     * Used to reformat the data from the editor component
     *
     * @return stdClass
     */
    function get_data() {
        $data = parent::get_data();

        if ($data !== null and isset($data->description)) {
            $data->descriptionformat = $data->description['format'];
            $data->description = $data->description['text'];
        }

        return $data;
    }
}
