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
 * Backup structure step for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the backup structure. Round tokens and secrets are not backed up.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qrvote_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the structure of qrvote.xml.
     *
     * @return backup_nested_element Root element.
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $qrvote = new backup_nested_element('qrvote', ['id'], [
            'name', 'intro', 'introformat', 'question', 'multiple', 'publicvote', 'showresults',
            'timecreated', 'timemodified',
        ]);
        $options = new backup_nested_element('options');
        $option = new backup_nested_element('option', ['id'], ['text', 'sortorder']);
        $rounds = new backup_nested_element('rounds');
        $round = new backup_nested_element('round', ['id'], ['status', 'timestarted', 'timeclosed', 'usermodified']);
        $votes = new backup_nested_element('votes');
        $vote = new backup_nested_element('vote', ['id'], ['optionid', 'voterhash', 'userid', 'timecreated']);

        $qrvote->add_child($options);
        $options->add_child($option);
        $qrvote->add_child($rounds);
        $rounds->add_child($round);
        $round->add_child($votes);
        $votes->add_child($vote);

        $qrvote->set_source_table('qrvote', ['id' => backup::VAR_ACTIVITYID]);
        $option->set_source_table('qrvote_options', ['qrvoteid' => backup::VAR_PARENTID], 'sortorder ASC');
        if ($userinfo) {
            $round->set_source_table('qrvote_rounds', ['qrvoteid' => backup::VAR_PARENTID], 'id ASC');
            $vote->set_source_table('qrvote_votes', ['roundid' => backup::VAR_PARENTID], 'id ASC');
        }

        $round->annotate_ids('user', 'usermodified');
        $vote->annotate_ids('user', 'userid');
        $qrvote->annotate_files('mod_qrvote', 'intro', null);

        return $this->prepare_activity_structure($qrvote);
    }
}
