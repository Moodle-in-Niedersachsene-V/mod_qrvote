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
 * Restore task for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/qrvote/backup/moodle2/restore_qrvote_stepslib.php');

/**
 * Restore task for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qrvote_activity_task extends restore_activity_task {
    /**
     * No specific settings.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Adds the structure step.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_qrvote_activity_structure_step('qrvote_structure', 'qrvote.xml'));
    }

    /**
     * Content fields that may contain encoded links.
     *
     * @return restore_decode_content[] Contents.
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('qrvote', ['intro'], 'qrvote')];
    }

    /**
     * Rules to decode encoded links.
     *
     * @return restore_decode_rule[] Rules.
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('QRVOTEVIEWBYID', '/mod/qrvote/view.php?id=$1', 'course_module'),
            new restore_decode_rule('QRVOTEINDEX', '/mod/qrvote/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log restore rules for the activity.
     *
     * @return restore_log_rule[] Rules.
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Log restore rules for the course.
     *
     * @return restore_log_rule[] Rules.
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
