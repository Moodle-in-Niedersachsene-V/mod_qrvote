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
 * Sends the PDF with one A4 page per station, or for a single station.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_qrvote\local\printout;
use mod_qrvote\local\round_manager;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$roundid = optional_param('roundid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'qrvote');
$qrvote = $DB->get_record('qrvote', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/qrvote:viewresults', $context);

if ($roundid) {
    $rounds = [round_manager::get((int) $qrvote->id, $roundid)];
} else {
    $rounds = round_manager::get_scheduled((int) $qrvote->id);
}

$content = printout::build($qrvote, $rounds, $context);
send_file($content, printout::filename($qrvote), 0, 0, true, false, 'application/pdf');
