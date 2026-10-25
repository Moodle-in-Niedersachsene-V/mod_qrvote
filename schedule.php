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
 * Creates or edits a station: a scheduled round with title, location and voting window.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_qrvote\form\schedule_form;
use mod_qrvote\local\round_manager;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$roundid = optional_param('roundid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'qrvote');
$qrvote = $DB->get_record('qrvote', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/qrvote:manage', $context);

$round = null;
if ($roundid) {
    $round = round_manager::get((int) $qrvote->id, $roundid);
    if (!round_manager::is_scheduled($round)) {
        throw new moodle_exception('error:notastation', 'qrvote');
    }
}

$url = new moodle_url('/mod/qrvote/schedule.php', ['id' => $cm->id, 'roundid' => $roundid]);
$returnurl = new moodle_url('/mod/qrvote/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($qrvote->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($qrvote);

$form = new schedule_form($url, ['id' => $cm->id, 'roundid' => $roundid]);
if ($round) {
    $form->set_data([
        'title' => $round->title,
        'location' => $round->location,
        'timeopen' => $round->timeopen,
        'timeclose' => $round->timeclose,
    ]);
}

if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    if ($round) {
        round_manager::update_schedule(
            $round,
            $data->title,
            $data->location,
            (int) $data->timeopen,
            (int) $data->timeclose,
            $context
        );
    } else {
        $round = round_manager::schedule(
            $qrvote,
            $data->title,
            $data->location,
            (int) $data->timeopen,
            (int) $data->timeclose,
            $context
        );
    }
    redirect(new moodle_url($returnurl, ['roundid' => $round->id]), get_string('stationsaved', 'qrvote'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($round ? 'editstation' : 'addstation', 'qrvote'));
echo html_writer::div(get_string('stationintro', 'qrvote'), 'alert alert-info');
$form->display();
echo $OUTPUT->footer();
