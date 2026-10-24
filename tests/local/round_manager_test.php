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

namespace mod_qrvote\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the round manager.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(round_manager::class)]
final class round_manager_test extends \advanced_testcase {
    /**
     * Creates a course with a QR vote and returns the instance and its context.
     *
     * @return array [instance record, module context]
     */
    private function create_vote(): array {
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);
        return [$qrvote, \context_module::instance($qrvote->cmid)];
    }

    public function test_start_creates_open_round_with_random_token(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$qrvote, $context] = $this->create_vote();

        $sink = $this->redirectEvents();
        $round = round_manager::start($qrvote, $context);
        $events = $sink->get_events();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $round->token);
        $this->assertSame(64, strlen($round->secret));
        $this->assertTrue(round_manager::accepts_votes($round));
        $this->assertInstanceOf(\mod_qrvote\event\round_started::class, end($events));
        $this->assertEquals($round->id, round_manager::get_by_token($round->token)->id);
    }

    public function test_start_closes_the_previous_open_round(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$qrvote, $context] = $this->create_vote();

        $first = round_manager::start($qrvote, $context);
        $second = round_manager::start($qrvote, $context);

        $first = round_manager::get((int) $qrvote->id, (int) $first->id);
        $this->assertFalse(round_manager::accepts_votes($first));
        $this->assertTrue(round_manager::accepts_votes($second));
        $this->assertNotSame($first->token, $second->token);
        $this->assertEquals($second->id, round_manager::get_latest((int) $qrvote->id)->id);
    }

    public function test_open_round_expires_after_lifetime(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('roundlifetime', HOURSECS, 'mod_qrvote');
        [$qrvote, $context] = $this->create_vote();

        $round = round_manager::start($qrvote, $context);

        $this->assertTrue(round_manager::accepts_votes($round, $round->timestarted + HOURSECS - 1));
        $this->assertFalse(round_manager::accepts_votes($round, $round->timestarted + HOURSECS));
    }

    public function test_close_stops_accepting_votes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$qrvote, $context] = $this->create_vote();
        $round = round_manager::start($qrvote, $context);

        round_manager::close($round, $context);

        $stored = round_manager::get((int) $qrvote->id, (int) $round->id);
        $this->assertEquals(round_manager::STATUS_CLOSED, $stored->status);
        $this->assertGreaterThan(0, (int) $stored->timeclosed);
        $this->assertFalse(round_manager::accepts_votes($stored));
    }

    public function test_delete_removes_round_and_votes(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$qrvote, $context] = $this->create_vote();
        $round = round_manager::start($qrvote, $context);
        $option = array_key_first(poll::get_options((int) $qrvote->id));
        vote_service::submit($qrvote, $round, [$option], vote_service::new_voter_key(), 0, $context);

        round_manager::delete($round, $context);

        $this->assertFalse($DB->record_exists('qrvote_rounds', ['id' => $round->id]));
        $this->assertFalse($DB->record_exists('qrvote_votes', ['roundid' => $round->id]));
    }

    public function test_get_by_token_rejects_malformed_tokens(): void {
        $this->resetAfterTest();

        $this->assertNull(round_manager::get_by_token('abc'));
        $this->assertNull(round_manager::get_by_token(str_repeat('a', 32)));
    }

    public function test_list_rounds_counts_voters(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$qrvote, $context] = $this->create_vote();
        $round = round_manager::start($qrvote, $context);
        $option = array_key_first(poll::get_options((int) $qrvote->id));
        vote_service::submit($qrvote, $round, [$option], vote_service::new_voter_key(), 0, $context);
        vote_service::submit($qrvote, $round, [$option], vote_service::new_voter_key(), 0, $context);

        $rounds = round_manager::list_rounds((int) $qrvote->id);

        $this->assertCount(1, $rounds);
        $this->assertEquals(2, reset($rounds)->voters);
    }
}
