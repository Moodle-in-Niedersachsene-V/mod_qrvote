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
use mod_qrvote\form\word_form;
use mod_qrvote\local\poll;
use mod_qrvote\local\rate_limiter;
use mod_qrvote\local\results;
use mod_qrvote\local\round_manager;
use mod_qrvote\local\vote_rejected;
use mod_qrvote\local\vote_service;
use mod_qrvote\local\word_service;

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
$isstation = round_manager::is_scheduled($round);
$pagedata = [
    // Plain text for the templates, which escape it themselves.
    'name' => format_string($qrvote->name, true, ['context' => $context, 'escape' => false]),
    'question' => $question,
    'isstation' => $isstation,
    'title' => $isstation ? format_string($round->title, true, ['context' => $context, 'escape' => false]) : '',
    'location' => $isstation ? format_string($round->location, true, ['context' => $context, 'escape' => false]) : '',
    'window' => $isstation ? round_manager::format_window((int) $round->timeopen, (int) $round->timeclose) : '',
    'showresults' => !empty($qrvote->showresults) && $voted,
    'iswordcloud' => poll::is_wordcloud($qrvote),
    'results' => results::for_round($qrvote, $round, $context),
];

$state = round_manager::state($round);
if ($state !== round_manager::STATE_OPEN) {
    echo $OUTPUT->header();
    $template = $state === round_manager::STATE_SCHEDULED ? 'mod_qrvote/notopen' : 'mod_qrvote/closed';
    echo $OUTPUT->render_from_template($template, $pagedata);
    echo $OUTPUT->footer();
    exit;
}

if (poll::is_wordcloud($qrvote)) {
    // Several terms per voter: the device key of this session counts the terms already sent.
    $voterkey = $SESSION->mod_qrvote_voterkey[$round->id] ?? vote_service::new_voter_key();
    $remaining = word_service::remaining($qrvote, $round, $voterkey, $userid);
    $submitted = $remaining < poll::max_words($qrvote);
    $pagedata['showresults'] = !empty($qrvote->showresults) && $submitted;

    $form = new word_form($url, [
        'token' => $round->token,
        'voterkey' => $voterkey,
        'question' => format_string($qrvote->question, true, ['context' => $context]),
        'remaining' => $remaining,
    ]);
    $error = '';
    if ($remaining > 0 && ($data = $form->get_data())) {
        try {
            rate_limiter::hit(getremoteaddr());
            word_service::submit($qrvote, $round, $data->word, $data->voterkey, $userid, $context);
            $SESSION->mod_qrvote_voterkey[$round->id] = $data->voterkey;
            redirect($url, get_string('wordsaved', 'qrvote'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (vote_rejected $e) {
            $SESSION->mod_qrvote_voterkey[$round->id] = $data->voterkey;
            $error = $e->getMessage();
        }
    }

    echo $OUTPUT->header();
    if ($remaining === 0) {
        echo $OUTPUT->render_from_template('mod_qrvote/voted', $pagedata);
        echo $OUTPUT->footer();
        exit;
    }
    echo $OUTPUT->render_from_template('mod_qrvote/station', $pagedata);
    if ($error !== '') {
        echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
    }
    $form->display();
    if ($pagedata['showresults']) {
        echo $OUTPUT->render_from_template('mod_qrvote/results', $pagedata['results']);
    }
    if ($public) {
        $PAGE->requires->js_call_amd('mod_qrvote/voterkey', 'init');
    }
    echo $OUTPUT->footer();
    exit;
}

if ($voted) {
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_qrvote/voted', $pagedata);
    echo $OUTPUT->footer();
    exit;
}

$rating = poll::is_rating($qrvote);
$options = [];
$stars = [];
foreach (poll::get_options((int) $qrvote->id) as $option) {
    if ($rating) {
        $stars[(int) $option->id] = poll::stars($option);
        $options[(int) $option->id] = results::stars_label(poll::stars($option));
    } else {
        $options[(int) $option->id] = format_string($option->text, true, ['context' => $context]);
    }
}
if ($rating) {
    $PAGE->add_body_class('mod_qrvote-ratingvote');
}
$form = new vote_form($url, [
    'token' => $round->token,
    'voterkey' => vote_service::new_voter_key(),
    'question' => format_string($qrvote->question, true, ['context' => $context]),
    'multiple' => !empty($qrvote->multiple),
    'options' => $options,
    'rating' => $rating,
    'stars' => $stars,
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
echo $OUTPUT->render_from_template('mod_qrvote/station', $pagedata);
if ($error !== '') {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}
$form->display();
if ($public) {
    $PAGE->requires->js_call_amd('mod_qrvote/voterkey', 'init');
}
echo $OUTPUT->footer();
