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
 * Restore structure step for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores a QR vote. Restored rounds get a new token, so old QR codes stay invalid. Live rounds are
 * restored as closed; stations keep their state, because their time window decides when they accept votes.
 *
 * Semgrep rule moodle-fehlender-sesskey reports the inserts in this class. They run inside the
 * restore controller, which checks the restore capabilities and is not reached by a form submission
 * of this plugin, so a session key check does not apply here.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qrvote_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the restore paths.
     *
     * @return restore_path_element[] Paths.
     */
    protected function define_structure() {
        $paths = [
            new restore_path_element('qrvote', '/activity/qrvote'),
            new restore_path_element('qrvote_option', '/activity/qrvote/options/option'),
            new restore_path_element('qrvote_round', '/activity/qrvote/rounds/round'),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('qrvote_vote', '/activity/qrvote/rounds/round/votes/vote');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the instance.
     *
     * @param array $data Element data.
     * @return void
     */
    protected function process_qrvote($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $newid = $DB->insert_record('qrvote', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restores an option.
     *
     * @param array $data Element data.
     * @return void
     */
    protected function process_qrvote_option($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->qrvoteid = $this->get_new_parentid('qrvote');
        $newid = $DB->insert_record('qrvote_options', $data);
        $this->set_mapping('qrvote_option', $oldid, $newid);
    }

    /**
     * Restores a round with a fresh token and secret; live rounds are closed.
     *
     * @param array $data Element data.
     * @return void
     */
    protected function process_qrvote_round($data) {
        global $DB;

        $data = (object) $data;
        if (empty($data->timeopen) && !$this->get_setting_value('userinfo')) {
            // Live rounds only make sense with their votes.
            return;
        }
        $oldid = $data->id;
        $data->qrvoteid = $this->get_new_parentid('qrvote');
        $data->token = bin2hex(random_bytes(16));
        $data->secret = bin2hex(random_bytes(32));
        $data->title = $data->title ?? '';
        $data->location = $data->location ?? '';
        $data->timeopen = (int) ($data->timeopen ?? 0);
        $data->timeclose = (int) ($data->timeclose ?? 0);
        if ($data->timeopen === 0) {
            $data->status = \mod_qrvote\local\round_manager::STATUS_CLOSED;
            $data->timeclosed = empty($data->timeclosed) ? $data->timestarted : $data->timeclosed;
        }
        $data->usermodified = $this->get_mappingid('user', $data->usermodified, 0);
        $newid = $DB->insert_record('qrvote_rounds', $data);
        $this->set_mapping('qrvote_round', $oldid, $newid);
    }

    /**
     * Restores a vote.
     *
     * @param array $data Element data.
     * @return void
     */
    protected function process_qrvote_vote($data) {
        global $DB;

        $data = (object) $data;
        $data->roundid = $this->get_new_parentid('qrvote_round');
        $data->optionid = $this->get_mappingid('qrvote_option', $data->optionid);
        if (!$data->optionid) {
            return;
        }
        $data->userid = empty($data->userid) ? null : $this->get_mappingid('user', $data->userid, null);
        $DB->insert_record('qrvote_votes', $data);
    }

    /**
     * Restores the intro files.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_qrvote', 'intro', null);
    }
}
