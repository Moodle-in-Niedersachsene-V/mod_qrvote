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

use mod_qrvote\local\word_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form on the vote page of a word cloud: one term per submission.
 *
 * Custom data: token (string), voterkey (string), question (formatted string), remaining (int).
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class word_form extends \moodleform {
    /**
     * Defines the fields.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 't', $this->_customdata['token']);
        $mform->setType('t', PARAM_ALPHANUM);
        $mform->addElement('hidden', 'voterkey', $this->_customdata['voterkey']);
        $mform->setType('voterkey', PARAM_ALPHANUM);

        $mform->addElement('text', 'word', $this->_customdata['question'], [
            'size' => 30,
            'maxlength' => word_service::WORD_MAXLENGTH,
            'autocomplete' => 'off',
        ]);
        $mform->setType('word', PARAM_TEXT);
        $mform->addRule('word', null, 'required', null, 'client');
        $mform->addRule(
            'word',
            get_string('maximumchars', '', word_service::WORD_MAXLENGTH),
            'maxlength',
            word_service::WORD_MAXLENGTH,
            'client'
        );
        $remaining = (int) $this->_customdata['remaining'];
        $mform->addElement(
            'static',
            'remaining',
            '',
            $remaining === 1 ? get_string('wordsremainingone', 'qrvote') : get_string('wordsremaining', 'qrvote', $remaining)
        );

        $this->add_action_buttons(false, get_string('submitword', 'qrvote'));
    }

    /**
     * Requires a term that is not empty after cleaning.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (word_service::clean((string) ($data['word'] ?? '')) === '') {
            $errors['word'] = get_string('error:invalidword', 'qrvote');
        }
        return $errors;
    }
}
