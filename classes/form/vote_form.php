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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Ballot shown on the vote page.
 *
 * Custom data: token (string), voterkey (string), question (formatted string), multiple (bool),
 * options (array of id => formatted text).
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vote_form extends \moodleform {
    /**
     * Defines the ballot.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $options = $this->_customdata['options'];

        $mform->addElement('hidden', 't', $this->_customdata['token']);
        $mform->setType('t', PARAM_ALPHANUM);
        $mform->addElement('hidden', 'voterkey', $this->_customdata['voterkey']);
        $mform->setType('voterkey', PARAM_ALPHANUM);

        $elements = [];
        if ($this->_customdata['multiple']) {
            foreach ($options as $id => $text) {
                $elements[] = $mform->createElement('advcheckbox', 'option' . $id, '', $text, ['group' => 1], [0, 1]);
            }
        } else {
            foreach ($options as $id => $text) {
                $elements[] = $mform->createElement('radio', 'choice', '', $text, $id);
            }
        }
        $mform->addGroup($elements, 'ballot', $this->_customdata['question'], \html_writer::empty_tag('br'), false);
        if ($this->_customdata['multiple']) {
            foreach (array_keys($options) as $id) {
                $mform->setType('option' . $id, PARAM_BOOL);
            }
        } else {
            $mform->setType('choice', PARAM_INT);
        }

        $this->add_action_buttons(false, get_string('submitvote', 'qrvote'));
    }

    /**
     * Requires at least one selected option.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!$this->get_selected_options((object) $data)) {
            $errors['ballot'] = get_string('error:noselection', 'qrvote');
        }
        return $errors;
    }

    /**
     * Returns the option ids selected in submitted data.
     *
     * @param \stdClass $data Submitted data.
     * @return int[] Selected option ids.
     */
    public function get_selected_options(\stdClass $data): array {
        $selected = [];
        if ($this->_customdata['multiple']) {
            foreach (array_keys($this->_customdata['options']) as $id) {
                if (!empty($data->{'option' . $id})) {
                    $selected[] = (int) $id;
                }
            }
        } else if (!empty($data->choice)) {
            $selected[] = (int) $data->choice;
        }
        return $selected;
    }
}
