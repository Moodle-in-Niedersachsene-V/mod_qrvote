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
 * Vote page the QR code points to.
 *
 * Deliberately public: when the activity and the site allow public voting, this page does not call
 * require_login(). Access is granted by the random round token in the URL, which stops working when
 * the teacher closes the round or the round lifetime ends. Activities that require login call
 * require_login() and require_capability() below. The ballot is a moodleform, so every submission
 * carries the session key of the visitor's session, logged in or not.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- Public by design, see the file docblock; access is checked by token.

use mod_qrvote\form\vote_form;
use mod_qrvote\local\poll;
use mod_qrvote\local\rate_limiter;
use mod_qrvote\local\results;
use mod_qrvote\local\round_manager;
use mod_qrvote\local\vote_rejected;
use mod_qrvote\local\vote_service;

require(__DIR__ . '/../../config.php');

$token = required_param('t', PARAM_ALPHANUM);

$round = round_manager::get_by_token($token);
if (!$round) {
    throw new moodle_exception('error:invalidtoken', 'qrvote');
}
$qrvote = $DB->get_record('qrvote', ['id' => $round->qrvoteid], '*', MUST_EXIST);
[$course, $cm] = get_course_and_cm_from_instance($qrvote, 'qrvote');
$context = context_module::instance($cm->id);
$public = poll::is_public($qrvote);

if ($public) {
    // A hidden activity or course must not be reachable through an old link.
    if (!$cm->visible || !$course->visible || !empty($cm->deletioninprogress)) {
        throw new moodle_exception('error:invalidtoken', 'qrvote');
    }
    $userid = 0;
    $PAGE->set_context($context);
} else {
    require_login($course, false, $cm);
    require_capability('mod/qrvote:vote', $context);
    $userid = (int) $USER->id;
}

$url = new moodle_url('/mod/qrvote/vote.php', ['t' => $round->token]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(format_string($qrvote->name));
$PAGE->add_body_class('mod_qrvote-votepage');

$question = format_text($qrvote->question, FORMAT_PLAIN, ['context' => $context]);
$voted = !empty($SESSION->mod_qrvote_voted[$round->id]) ||
    ($userid > 0 && vote_service::has_voted($round, '', $userid));
$resultsdata = [
    'question' => $question,
    'showresults' => !empty($qrvote->showresults) && $voted,
    'results' => results::for_round($qrvote, $round, $context),
];

if (!round_manager::accepts_votes($round)) {
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_qrvote/closed', $resultsdata);
    echo $OUTPUT->footer();
    exit;
}

if ($voted) {
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_qrvote/voted', $resultsdata);
    echo $OUTPUT->footer();
    exit;
}

$options = [];
foreach (poll::get_options((int) $qrvote->id) as $option) {
    $options[(int) $option->id] = format_string($option->text, true, ['context' => $context]);
}
$form = new vote_form($url, [
    'token' => $round->token,
    'voterkey' => vote_service::new_voter_key(),
    'question' => format_string($qrvote->question, true, ['context' => $context]),
    'multiple' => !empty($qrvote->multiple),
    'options' => $options,
]);

$error = '';
if ($data = $form->get_data()) {
    try {
        rate_limiter::hit(getremoteaddr());
        vote_service::submit($qrvote, $round, $form->get_selected_options($data), $data->voterkey, $userid, $context);
        $SESSION->mod_qrvote_voted[$round->id] = true;
        redirect($url);
    } catch (vote_rejected $e) {
        if ($e->errorcode === vote_rejected::ALREADY_VOTED) {
            $SESSION->mod_qrvote_voted[$round->id] = true;
        }
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($qrvote->name), 2);
if ($error !== '') {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}
$form->display();
if ($public) {
    $PAGE->requires->js_call_amd('mod_qrvote/voterkey', 'init');
}
echo $OUTPUT->footer();
