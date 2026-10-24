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

namespace mod_qrvote\output;

use mod_qrvote\local\poll;
use mod_qrvote\local\round_manager;
use mod_qrvote\local\vote_service;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the teacher page.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(manage_page::class)]
final class manage_page_test extends \advanced_testcase {
    public function test_open_round_renders_qr_code_result_and_history(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);
        $context = \context_module::instance($qrvote->cmid);
        $round = round_manager::start($qrvote, $context);
        $option = array_key_first(poll::get_options((int) $qrvote->id));
        vote_service::submit($qrvote, $round, [$option], vote_service::new_voter_key(), 0, $context);
        $cm = get_fast_modinfo($course)->get_cm($qrvote->cmid);
        $PAGE->set_url('/mod/qrvote/view.php', ['id' => $cm->id]);
        $output = $PAGE->get_renderer('core');

        $data = (new manage_page($cm, $qrvote, $context, $round, true))->export_for_template($output);
        $html = $output->render_from_template('mod_qrvote/manage', $data);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $data['round']['qrcode']);
        $this->assertStringContainsString('<svg', base64_decode(substr($data['round']['qrcode'], 26)));
        $this->assertStringContainsString('vote.php?t=' . $round->token, $data['round']['voteurl']);
        $this->assertSame(1, $data['round']['results']['voters']);
        $this->assertCount(1, $data['rounds']);
        $this->assertStringContainsString('Fractions', $html);
        $this->assertStringContainsString('name="action" value="stop"', $html);
    }

    public function test_closed_round_shows_no_qr_code(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);
        $context = \context_module::instance($qrvote->cmid);
        $round = round_manager::start($qrvote, $context);
        round_manager::close($round, $context);
        $cm = get_fast_modinfo($course)->get_cm($qrvote->cmid);
        $PAGE->set_url('/mod/qrvote/view.php', ['id' => $cm->id]);
        $output = $PAGE->get_renderer('core');

        $data = (new manage_page($cm, $qrvote, $context, $round, false))->export_for_template($output);
        $html = $output->render_from_template('mod_qrvote/manage', $data);

        $this->assertSame('', $data['round']['qrcode']);
        $this->assertStringNotContainsString('name="action"', $html);
    }
}
