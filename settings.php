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
 * Site settings for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'mod_qrvote/allowpublic',
        get_string('allowpublic', 'qrvote'),
        get_string('allowpublic_desc', 'qrvote'),
        1
    ));
    $settings->add(new admin_setting_configduration(
        'mod_qrvote/roundlifetime',
        get_string('roundlifetime', 'qrvote'),
        get_string('roundlifetime_desc', 'qrvote'),
        4 * HOURSECS,
        HOURSECS
    ));
    $settings->add(new admin_setting_configtext(
        'mod_qrvote/ratelimit',
        get_string('ratelimit', 'qrvote'),
        get_string('ratelimit_desc', 'qrvote'),
        200,
        PARAM_INT
    ));
}
