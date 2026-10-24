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
 * Activity page: round controls and live result for teachers, vote link for participants.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_qrvote\local\poll;
use mod_qrvote\local\round_manager;
use mod_qrvote\output\manage_page;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$roundid = optional_param('roundid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'qrvote');
$qrvote = $DB->get_record('qrvote', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/qrvote:view', $context);

$url = new moodle_url('/mod/qrvote/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($qrvote->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($qrvote);

$canmanage = has_capability('mod/qrvote:manage', $context);
$canviewresults = has_capability('mod/qrvote:viewresults', $context);

// Round actions change data and are accepted only by POST with a valid session key.
if ($action !== '' && $canmanage && data_submitted()) {
    require_sesskey();
    require_capability('mod/qrvote:manage', $context);
    if ($action === 'start') {
        $round = round_manager::start($qrvote, $context);
        redirect(new moodle_url($url, ['roundid' => $round->id]));
    }
    $round = round_manager::get((int) $qrvote->id, $roundid);
    if ($action === 'stop') {
        round_manager::close($round, $context);
        redirect(new moodle_url($url, ['roundid' => $round->id]));
    }
    if ($action === 'delete' && $confirm) {
        round_manager::delete($round, $context);
        redirect($url, get_string('rounddeleted', 'qrvote'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'delete') {
        $PAGE->set_title(get_string('deleteround', 'qrvote'));
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('deleteroundconfirm', 'qrvote', userdate($round->timestarted)),
            new moodle_url($url, ['action' => 'delete', 'roundid' => $round->id, 'confirm' => 1]),
            $url
        );
        echo $OUTPUT->footer();
        exit;
    }
    throw new moodle_exception('error:unknownaction', 'qrvote');
}

$completion = new completion_info($course);
$completion->set_module_viewed($cm);
\mod_qrvote\event\course_module_viewed::create([
    'objectid' => $qrvote->id,
    'context' => $context,
])->trigger();

echo $OUTPUT->header();

if ($canviewresults) {
    $round = $roundid ? round_manager::get((int) $qrvote->id, $roundid) : round_manager::get_latest((int) $qrvote->id);
    $page = new manage_page($cm, $qrvote, $context, $round, $canmanage);
    echo $OUTPUT->render_from_template('mod_qrvote/manage', $page->export_for_template($OUTPUT));
    if ($round && round_manager::accepts_votes($round)) {
        $PAGE->requires->js_call_amd('mod_qrvote/live', 'init', [(int) $cm->id, (int) $round->id]);
    }
    $PAGE->requires->js_call_amd('mod_qrvote/fullscreen', 'init');
} else {
    $round = round_manager::get_latest((int) $qrvote->id);
    $open = $round && round_manager::accepts_votes($round);
    $canvote = poll::is_public($qrvote) || has_capability('mod/qrvote:vote', $context);
    echo $OUTPUT->render_from_template('mod_qrvote/student', [
        'question' => format_text($qrvote->question, FORMAT_PLAIN, ['context' => $context]),
        'open' => $open,
        'canvote' => $canvote,
        'voteurl' => $open ? (new moodle_url('/mod/qrvote/vote.php', ['t' => $round->token]))->out(false) : '',
    ]);
}

echo $OUTPUT->footer();
