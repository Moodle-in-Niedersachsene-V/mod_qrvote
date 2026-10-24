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

namespace mod_qrvote\event;

/**
 * Event raised when a vote is submitted. It names neither the voter's choice nor, for public votes, the voter.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vote_submitted extends \core\event\base {
    /**
     * Sets the basic event properties.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'qrvote_rounds';
    }

    /**
     * Returns the localised event name.
     *
     * @return string Event name.
     */
    public static function get_name() {
        return get_string('eventvotesubmitted', 'mod_qrvote');
    }

    /**
     * Returns a non-localised description for the logs.
     *
     * @return string Description.
     */
    public function get_description() {
        return "A vote was submitted in the voting round with id '$this->objectid' " .
            "of the QR vote with course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns the page the event relates to.
     *
     * @return \moodle_url Activity page.
     */
    public function get_url() {
        return new \moodle_url('/mod/qrvote/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Maps the round id for restoring logs.
     *
     * @return array Mapping definition.
     */
    public static function get_objectid_mapping() {
        return ['db' => 'qrvote_rounds', 'restore' => 'qrvote_round'];
    }
}
