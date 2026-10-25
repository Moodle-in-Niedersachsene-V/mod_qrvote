<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_qrvote\form;

use mod_qrvote\local\round_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to create or edit a station: a scheduled round with title, location and voting window.
 *
 * Custom data: id (course module id), roundid (0 for a new station).
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schedule_form extends \moodleform {
    /**
     * Defines the fields.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'roundid', $this->_customdata['roundid']);
        $mform->setType('roundid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('stationtitle', 'qrvote'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule(
            'title',
            get_string('maximumchars', '', round_manager::TEXT_MAXLENGTH),
            'maxlength',
            round_manager::TEXT_MAXLENGTH,
            'client'
        );
        $mform->addHelpButton('title', 'stationtitle', 'qrvote');

        $mform->addElement('text', 'location', get_string('stationlocation', 'qrvote'), ['size' => 40]);
        $mform->setType('location', PARAM_TEXT);
        $mform->addRule(
            'location',
            get_string('maximumchars', '', round_manager::TEXT_MAXLENGTH),
            'maxlength',
            round_manager::TEXT_MAXLENGTH,
            'client'
        );
        $mform->addHelpButton('location', 'stationlocation', 'qrvote');

        $mform->addElement('date_time_selector', 'timeopen', get_string('stationtimeopen', 'qrvote'));
        $mform->addHelpButton('timeopen', 'stationtimeopen', 'qrvote');
        $mform->addElement('date_time_selector', 'timeclose', get_string('stationtimeclose', 'qrvote'));
        $mform->addHelpButton('timeclose', 'stationtimeclose', 'qrvote');
        $mform->setDefault('timeclose', time() + HOURSECS);

        $this->add_action_buttons(true, get_string('savestation', 'qrvote'));
    }

    /**
     * Requires a title and an end after the start.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim($data['title'] ?? '') === '') {
            $errors['title'] = get_string('required');
        }
        if ((int) $data['timeclose'] <= (int) $data['timeopen']) {
            $errors['timeclose'] = get_string('error:invalidwindow', 'qrvote');
        }
        return $errors;
    }
}
