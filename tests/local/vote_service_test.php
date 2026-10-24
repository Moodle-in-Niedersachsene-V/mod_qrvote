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
 * Tests for storing votes.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(vote_service::class)]
#[CoversClass(vote_rejected::class)]
final class vote_service_test extends \advanced_testcase {
    /**
     * Creates a QR vote with an open round.
     *
     * @param array $record Instance fields.
     * @return array [instance, round, context, option ids]
     */
    private function create_round(array $record = []): array {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id] + $record);
        $context = \context_module::instance($qrvote->cmid);
        $round = round_manager::start($qrvote, $context);
        return [$qrvote, $round, $context, array_keys(poll::get_options((int) $qrvote->id))];
    }

    /**
     * Runs a submission and returns the reason it was rejected.
     *
     * @param callable $submit Submission.
     * @return string|null Error code, or null when the vote was accepted.
     */
    private function rejection(callable $submit): ?string {
        try {
            $submit();
        } catch (vote_rejected $e) {
            return $e->errorcode;
        }
        return null;
    }

    public function test_public_vote_is_stored_without_user(): void {
        global $DB;

        $this->resetAfterTest();
        [$qrvote, $round, $context, $options] = $this->create_round();
        $key = vote_service::new_voter_key();
        $this->setUser(0);

        $sink = $this->redirectEvents();
        vote_service::submit($qrvote, $round, [$options[1]], $key, 0, $context);
        $events = $sink->get_events();

        $vote = $DB->get_record('qrvote_votes', ['roundid' => $round->id], '*', MUST_EXIST);
        $this->assertEquals($options[1], $vote->optionid);
        $this->assertNull($vote->userid);
        $this->assertStringNotContainsString($key, $vote->voterhash);
        $this->assertTrue(vote_service::has_voted($round, $key, 0));
        $this->assertInstanceOf(\mod_qrvote\event\vote_submitted::class, end($events));
        $this->assertEquals(1, end($events)->anonymous);
    }

    public function test_second_vote_from_same_device_is_rejected(): void {
        global $DB;

        $this->resetAfterTest();
        [$qrvote, $round, $context, $options] = $this->create_round();
        $key = vote_service::new_voter_key();
        vote_service::submit($qrvote, $round, [$options[0]], $key, 0, $context);

        $reason = $this->rejection(fn() => vote_service::submit($qrvote, $round, [$options[1]], $key, 0, $context));

        $this->assertSame(vote_rejected::ALREADY_VOTED, $reason);
        $this->assertEquals(1, $DB->count_records('qrvote_votes', ['roundid' => $round->id]));
    }

    public function test_same_device_hash_differs_between_rounds(): void {
        global $DB;

        $this->resetAfterTest();
        [$qrvote, $round, $context, $options] = $this->create_round();
        $key = vote_service::new_voter_key();
        vote_service::submit($qrvote, $round, [$options[0]], $key, 0, $context);
        $next = round_manager::start($qrvote, $context);

        vote_service::submit($qrvote, $next, [$options[0]], $key, 0, $context);

        $first = $DB->get_field('qrvote_votes', 'voterhash', ['roundid' => $round->id]);
        $second = $DB->get_field('qrvote_votes', 'voterhash', ['roundid' => $next->id]);
        $this->assertNotSame($first, $second);
    }

    public function test_malformed_voter_key_is_rejected(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context, $options] = $this->create_round();

        $reason = $this->rejection(fn() => vote_service::submit($qrvote, $round, [$options[0]], 'nothex', 0, $context));

        $this->assertSame(vote_rejected::INVALID_VOTER, $reason);
    }

    public function test_option_of_another_vote_is_rejected(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_round();
        [, , , $foreign] = $this->create_round();
        $key = vote_service::new_voter_key();

        $reason = $this->rejection(fn() => vote_service::submit($qrvote, $round, [$foreign[0]], $key, 0, $context));

        $this->assertSame(vote_rejected::INVALID_OPTIONS, $reason);
    }

    public function test_several_options_are_rejected_for_single_choice(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context, $options] = $this->create_round();
        $key = vote_service::new_voter_key();

        $reason = $this->rejection(fn() => vote_service::submit($qrvote, $round, $options, $key, 0, $context));

        $this->assertSame(vote_rejected::INVALID_OPTIONS, $reason);
    }

    public function test_empty_selection_is_rejected(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_round();
        $key = vote_service::new_voter_key();

        $reason = $this->rejection(fn() => vote_service::submit($qrvote, $round, [], $key, 0, $context));

        $this->assertSame(vote_rejected::INVALID_OPTIONS, $reason);
    }

    public function test_multiple_choice_stores_one_row_per_option(): void {
        global $DB;

        $this->resetAfterTest();
        [$qrvote, $round, $context, $options] = $this->create_round(['multiple' => 1]);

        vote_service::submit($qrvote, $round, [$options[0], $options[2]], vote_service::new_voter_key(), 0, $context);

        $this->assertEquals(2, $DB->count_records('qrvote_votes', ['roundid' => $round->id]));
    }

    public function test_closed_round_rejects_votes(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context, $options] = $this->create_round();
        round_manager::close($round, $context);
        $key = vote_service::new_voter_key();

        $reason = $this->rejection(fn() => vote_service::submit($qrvote, $round, [$options[0]], $key, 0, $context));

        $this->assertSame(vote_rejected::ROUND_CLOSED, $reason);
    }

    public function test_round_of_another_vote_is_rejected(): void {
        $this->resetAfterTest();
        [$qrvote, , $context, $options] = $this->create_round();
        [, $foreignround] = $this->create_round();
        $key = vote_service::new_voter_key();

        $reason = $this->rejection(fn() => vote_service::submit($qrvote, $foreignround, [$options[0]], $key, 0, $context));

        $this->assertSame(vote_rejected::ROUND_CLOSED, $reason);
    }

    public function test_login_vote_stores_user_and_counts_once_per_user(): void {
        global $DB;

        $this->resetAfterTest();
        [$qrvote, $round, $context, $options] = $this->create_round(['publicvote' => 0]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        vote_service::submit($qrvote, $round, [$options[0]], vote_service::new_voter_key(), (int) $user->id, $context);
        $reason = $this->rejection(
            fn() => vote_service::submit($qrvote, $round, [$options[1]], vote_service::new_voter_key(), (int) $user->id, $context)
        );

        $this->assertSame(vote_rejected::ALREADY_VOTED, $reason);
        $this->assertEquals($user->id, $DB->get_field('qrvote_votes', 'userid', ['roundid' => $round->id]));
        $this->assertTrue(vote_service::has_voted($round, '', (int) $user->id));
    }
}
