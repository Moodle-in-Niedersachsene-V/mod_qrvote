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
 * Library callbacks for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_qrvote\local\poll;

/**
 * Declares the features this activity supports.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True, false, null or a purpose string.
 */
function qrvote_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COMMUNICATION;
        default:
            return null;
    }
}

/**
 * Creates a new activity instance with its options.
 *
 * @param stdClass $data Form data.
 * @param mod_qrvote_mod_form|null $mform The form.
 * @return int New instance id.
 */
function qrvote_add_instance($data, $mform = null) {
    return poll::create_instance($data);
}

/**
 * Updates an activity instance and, while no votes exist, its options.
 *
 * @param stdClass $data Form data.
 * @param mod_qrvote_mod_form|null $mform The form.
 * @return bool True on success.
 */
function qrvote_update_instance($data, $mform = null) {
    poll::update_instance($data);
    return true;
}

/**
 * Deletes an activity instance with options, rounds and votes.
 *
 * @param int $id Instance id.
 * @return bool True when the instance existed and was deleted.
 */
function qrvote_delete_instance($id) {
    return poll::delete_instance((int) $id);
}

/**
 * Adds the reset option for this activity to the course reset form.
 *
 * @param MoodleQuickForm $mform The course reset form.
 * @return void
 */
function qrvote_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'qrvoteheader', get_string('modulenameplural', 'qrvote'));
    $mform->addElement('advcheckbox', 'reset_qrvote_rounds', get_string('resetrounds', 'qrvote'));
}

/**
 * Default values for the course reset form.
 *
 * @param stdClass $course The course.
 * @return array Defaults.
 */
function qrvote_reset_course_form_defaults($course) {
    return ['reset_qrvote_rounds' => 1];
}

/**
 * Deletes all rounds and votes of the course when requested by the course reset.
 *
 * @param stdClass $data Reset form data.
 * @return array Status entries.
 */
function qrvote_reset_userdata($data) {
    global $DB;

    $status = [];
    if (empty($data->reset_qrvote_rounds)) {
        return $status;
    }
    $instances = $DB->get_fieldset_select('qrvote', 'id', 'course = ?', [$data->courseid]);
    foreach ($instances as $instanceid) {
        poll::delete_rounds((int) $instanceid);
    }
    $status[] = [
        'component' => get_string('modulenameplural', 'qrvote'),
        'item' => get_string('resetrounds', 'qrvote'),
        'error' => false,
    ];
    return $status;
}
