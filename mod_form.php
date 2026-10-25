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

/**
 * Activity settings form for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_qrvote\local\poll;

/**
 * Activity settings form for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_qrvote_mod_form extends moodleform_mod {
    /** @var int Number of empty option fields on a new activity. */
    private const OPTIONS_DEFAULT = 5;

    /** @var int Number of option fields the add button appends. */
    private const OPTIONS_ADD = 3;

    /**
     * Defines the form fields.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $locked = !empty($this->_instance) && poll::has_votes((int) $this->_instance);

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'votesettings', get_string('votesettings', 'qrvote'));
        $mform->setExpanded('votesettings');

        $mform->addElement('textarea', 'question', get_string('question', 'qrvote'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('question', PARAM_TEXT);
        $mform->addRule('question', null, 'required', null, 'client');
        $mform->addHelpButton('question', 'question', 'qrvote');

        if (get_config('mod_qrvote', 'allowpublic')) {
            $mform->addElement('selectyesno', 'publicvote', get_string('publicvote', 'qrvote'));
            $mform->setDefault('publicvote', 1);
            $mform->addHelpButton('publicvote', 'publicvote', 'qrvote');
        } else {
            $mform->addElement('hidden', 'publicvote', 0);
            $mform->setType('publicvote', PARAM_BOOL);
            $mform->addElement(
                'static',
                'publicdisabled',
                get_string('publicvote', 'qrvote'),
                get_string('publicdisabled', 'qrvote')
            );
        }

        $mform->addElement('selectyesno', 'showresults', get_string('showresults', 'qrvote'));
        $mform->setDefault('showresults', 0);
        $mform->addHelpButton('showresults', 'showresults', 'qrvote');

        $mform->addElement('header', 'optionsheader', get_string('options', 'qrvote'));
        $mform->setExpanded('optionsheader');
        if ($locked) {
            $mform->addElement('static', 'optionslocked', '', get_string('optionslocked', 'qrvote'));
        }

        $mform->addElement('select', 'votetype', get_string('votetype', 'qrvote'), [
            poll::TYPE_CHOICE => get_string('votetypechoice', 'qrvote'),
            poll::TYPE_RATING => get_string('votetyperating', 'qrvote'),
            poll::TYPE_WORDCLOUD => get_string('votetypewordcloud', 'qrvote'),
        ]);
        $mform->setDefault('votetype', poll::TYPE_CHOICE);
        $mform->addHelpButton('votetype', 'votetype', 'qrvote');

        $mform->addElement('static', 'ratingnote', '', get_string('ratingnote', 'qrvote', poll::RATING_MAX));
        $mform->hideIf('ratingnote', 'votetype', 'neq', (string) poll::TYPE_RATING);

        $mform->addElement('static', 'wordcloudnote', '', get_string('wordcloudnote', 'qrvote'));
        $mform->hideIf('wordcloudnote', 'votetype', 'neq', (string) poll::TYPE_WORDCLOUD);
        $mform->addElement(
            'select',
            'maxwords',
            get_string('maxwords', 'qrvote'),
            array_combine(range(1, poll::MAXWORDS_LIMIT), range(1, poll::MAXWORDS_LIMIT))
        );
        $mform->setDefault('maxwords', poll::MAXWORDS_DEFAULT);
        $mform->addHelpButton('maxwords', 'maxwords', 'qrvote');
        $mform->hideIf('maxwords', 'votetype', 'neq', (string) poll::TYPE_WORDCLOUD);

        $mform->addElement('selectyesno', 'multiple', get_string('multiple', 'qrvote'));
        $mform->setDefault('multiple', 0);
        $mform->addHelpButton('multiple', 'multiple', 'qrvote');
        $mform->hideIf('multiple', 'votetype', 'neq', (string) poll::TYPE_CHOICE);

        $repeated = [
            $mform->createElement('text', 'option', get_string('optionno', 'qrvote'), ['size' => '60']),
        ];
        $repeatoptions = [
            'option' => [
                'type' => PARAM_TEXT,
                'helpbutton' => ['option', 'qrvote'],
                'hideif' => ['votetype', 'neq', (string) poll::TYPE_CHOICE],
            ],
        ];
        $existing = empty($this->_instance) ? 0 : count(poll::get_options((int) $this->_instance));
        $repeats = $this->repeat_elements(
            $repeated,
            max(self::OPTIONS_DEFAULT, $existing),
            $repeatoptions,
            'option_repeats',
            'option_add_fields',
            self::OPTIONS_ADD,
            get_string('addoptions', 'qrvote'),
            true
        );

        $mform->hideIf('option_add_fields', 'votetype', 'neq', (string) poll::TYPE_CHOICE);

        if ($locked) {
            // Frozen fields cannot follow the answer type any more, so fields of other types are removed.
            $votetype = (int) ($this->current->votetype ?? poll::TYPE_CHOICE);
            $choice = $votetype === poll::TYPE_CHOICE;
            if ($votetype !== poll::TYPE_RATING) {
                $mform->removeElement('ratingnote');
            }
            if ($votetype !== poll::TYPE_WORDCLOUD) {
                $mform->removeElement('wordcloudnote');
                $mform->removeElement('maxwords');
            }
            $frozen = ['votetype'];
            for ($index = 0; $index < $repeats; $index++) {
                if ($choice) {
                    $frozen[] = 'option[' . $index . ']';
                } else {
                    $mform->removeElement('option[' . $index . ']');
                }
            }
            if ($choice) {
                $frozen[] = 'multiple';
            } else {
                $mform->removeElement('multiple');
            }
            $mform->hardFreeze($frozen);
            $mform->removeElement('option_add_fields');
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Loads the existing options into the repeated option fields.
     *
     * @param array $defaultvalues Form defaults, passed by reference.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        if (empty($this->_instance)) {
            return;
        }
        if (poll::is_rating((object) $defaultvalues) || poll::is_wordcloud((object) $defaultvalues)) {
            // Star options are generated and word clouds have none; the text fields stay empty.
            return;
        }
        $index = 0;
        foreach (poll::get_options((int) $this->_instance) as $option) {
            $defaultvalues['option[' . $index . ']'] = $option->text;
            $index++;
        }
    }

    /**
     * Checks the question and, for text options, that at least two options are filled in.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (trim($data['question'] ?? '') === '') {
            $errors['question'] = get_string('required');
        }

        $locked = !empty($this->_instance) && poll::has_votes((int) $this->_instance);
        $choice = (int) ($data['votetype'] ?? poll::TYPE_CHOICE) === poll::TYPE_CHOICE;
        if (!$locked && $choice) {
            $texts = $data['option'] ?? [];
            if (count(poll::clean_option_texts($texts)) < poll::OPTIONS_MIN) {
                $errors['option[0]'] = get_string('error:toofewoptions', 'qrvote', poll::OPTIONS_MIN);
            }
            foreach ($texts as $index => $text) {
                if (\core_text::strlen(trim((string) $text)) > poll::OPTION_MAXLENGTH) {
                    $errors['option[' . $index . ']'] = get_string('maximumchars', '', poll::OPTION_MAXLENGTH);
                }
            }
        }
        return $errors;
    }
}
