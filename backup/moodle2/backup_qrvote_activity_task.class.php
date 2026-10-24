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
 * Backup task for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/qrvote/backup/moodle2/backup_qrvote_stepslib.php');

/**
 * Backup task for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qrvote_activity_task extends backup_activity_task {
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
        $this->add_step(new backup_qrvote_activity_structure_step('qrvote_structure', 'qrvote.xml'));
    }

    /**
     * Encodes links to the activity in content.
     *
     * @param string $content Content.
     * @return string Encoded content.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace('/(' . $base . '\/mod\/qrvote\/index.php\?id\=)([0-9]+)/', '$@QRVOTEINDEX*$2@$', $content);
        $content = preg_replace('/(' . $base . '\/mod\/qrvote\/view.php\?id\=)([0-9]+)/', '$@QRVOTEVIEWBYID*$2@$', $content);
        return $content;
    }
}
