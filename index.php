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
 * Lists all QR votes of a course.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);
$coursecontext = context_course::instance($course->id);

\mod_qrvote\event\course_module_instance_list_viewed::create([
    'context' => $coursecontext,
])->trigger();

$PAGE->set_url('/mod/qrvote/index.php', ['id' => $course->id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'qrvote'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'qrvote'));

$table = new html_table();
$table->head = [get_string('name'), get_string('question', 'qrvote')];
$table->data = [];
foreach (get_all_instances_in_course('qrvote', $course) as $qrvote) {
    $link = html_writer::link(
        new moodle_url('/mod/qrvote/view.php', ['id' => $qrvote->coursemodule]),
        format_string($qrvote->name, true, ['context' => context_module::instance($qrvote->coursemodule)]),
        $qrvote->visible ? [] : ['class' => 'dimmed']
    );
    $table->data[] = [$link, s(shorten_text($qrvote->question, 120))];
}

if ($table->data) {
    echo html_writer::table($table);
} else {
    notice(
        get_string('thereareno', 'moodle', get_string('modulenameplural', 'qrvote')),
        new moodle_url('/course/view.php', ['id' => $course->id])
    );
}

echo $OUTPUT->footer();
