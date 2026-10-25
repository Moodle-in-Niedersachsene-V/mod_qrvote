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
 * Tests for stations: scheduled rounds with a fixed voting window.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(round_manager::class)]
#[CoversClass(vote_service::class)]
#[CoversClass(results::class)]
final class stations_test extends \advanced_testcase {
    /**
     * Creates a QR vote.
     *
     * @return array [instance, context, option ids]
     */
    private function create_vote(): array {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);
        return [$qrvote, \context_module::instance($qrvote->cmid), array_keys(poll::get_options((int) $qrvote->id))];
    }

    public function test_station_accepts_votes_only_inside_its_window(): void {
        $this->resetAfterTest();
        [$qrvote, $context] = $this->create_vote();
        $open = 1900000000;

        $sink = $this->redirectEvents();
        $round = round_manager::schedule($qrvote, 'Moodle and AI', 'Room 104', $open, $open + 10 * HOURSECS, $context);
        $events = $sink->get_events();

        $this->assertTrue(round_manager::is_scheduled($round));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $round->token);
        $this->assertSame(round_manager::STATE_SCHEDULED, round_manager::state($round, $open - 1));
        $this->assertSame(round_manager::STATE_OPEN, round_manager::state($round, $open));
        // The lifetime of live rounds does not cut a long window short.
        $this->assertSame(round_manager::STATE_OPEN, round_manager::state($round, $open + 9 * HOURSECS));
        $this->assertSame(round_manager::STATE_CLOSED, round_manager::state($round, $open + 10 * HOURSECS));
        $this->assertInstanceOf(\mod_qrvote\event\round_scheduled::class, end($events));
    }

    public function test_title_and_location_are_cleaned(): void {
        $this->resetAfterTest();
        [$qrvote, $context] = $this->create_vote();

        $round = round_manager::schedule($qrvote, '  <b>Talk</b> ', str_repeat('x', 300), time(), time() + HOURSECS, $context);

        $this->assertSame('Talk', $round->title);
        $this->assertSame(round_manager::TEXT_MAXLENGTH, \core_text::strlen($round->location));
    }

    public function test_invalid_window_fails_loudly(): void {
        $this->resetAfterTest();
        [$qrvote, $context] = $this->create_vote();

        $this->expectException(\moodle_exception::class);
        round_manager::schedule($qrvote, 'Talk', '', time(), time(), $context);
    }

    public function test_update_keeps_the_printed_token_and_reopens_the_station(): void {
        $this->resetAfterTest();
        [$qrvote, $context] = $this->create_vote();
        $round = round_manager::schedule($qrvote, 'Talk', 'Room 1', time() + DAYSECS, time() + DAYSECS + HOURSECS, $context);

        round_manager::close($round, $context);
        round_manager::update_schedule($round, 'Workshop', 'Room 2', time() - MINSECS, time() + HOURSECS, $context);

        $stored = round_manager::get((int) $qrvote->id, (int) $round->id);
        $this->assertSame($round->token, $stored->token);
        $this->assertSame('Workshop', $stored->title);
        $this->assertSame('Room 2', $stored->location);
        $this->assertTrue(round_manager::accepts_votes($stored));
    }

    public function test_live_round_has_no_window_to_update(): void {
        $this->resetAfterTest();
        [$qrvote, $context] = $this->create_vote();
        $live = round_manager::start($qrvote, $context);

        $this->expectException(\coding_exception::class);
        round_manager::update_schedule($live, 'Talk', '', time(), time() + HOURSECS, $context);
    }

    public function test_starting_a_live_round_leaves_stations_open(): void {
        $this->resetAfterTest();
        [$qrvote, $context] = $this->create_vote();
        $station = round_manager::schedule($qrvote, 'Talk', '', time() - MINSECS, time() + HOURSECS, $context);

        round_manager::start($qrvote, $context);
        round_manager::start($qrvote, $context);

        $this->assertTrue(round_manager::accepts_votes(round_manager::get((int) $qrvote->id, (int) $station->id)));
        $this->assertCount(1, round_manager::get_scheduled((int) $qrvote->id));
    }

    public function test_parallel_stations_count_votes_separately(): void {
        $this->resetAfterTest();
        [$qrvote, $context, $options] = $this->create_vote();
        $first = round_manager::schedule($qrvote, 'Talk A', 'Room 1', time() - MINSECS, time() + HOURSECS, $context);
        $second = round_manager::schedule($qrvote, 'Talk B', 'Room 2', time() - MINSECS, time() + HOURSECS, $context);
        $key = vote_service::new_voter_key();

        vote_service::submit($qrvote, $first, [$options[0]], $key, 0, $context);
        vote_service::submit($qrvote, $second, [$options[1]], $key, 0, $context);

        $this->assertSame([1, 0, 0], array_column(results::for_round($qrvote, $first, $context)['options'], 'votes'));
        $this->assertSame([0, 1, 0], array_column(results::for_round($qrvote, $second, $context)['options'], 'votes'));
    }

    public function test_vote_before_the_window_is_rejected(): void {
        $this->resetAfterTest();
        [$qrvote, $context, $options] = $this->create_vote();
        $round = round_manager::schedule($qrvote, 'Talk', '', time() + HOURSECS, time() + 2 * HOURSECS, $context);

        $reason = null;
        try {
            vote_service::submit($qrvote, $round, [$options[0]], vote_service::new_voter_key(), 0, $context);
        } catch (vote_rejected $e) {
            $reason = $e->errorcode;
        }

        $this->assertSame(vote_rejected::ROUND_NOT_OPEN, $reason);
        $this->assertTrue(results::for_round($qrvote, $round, $context)['scheduled']);
    }

    public function test_default_round_prefers_open_station_then_live_then_next_station(): void {
        $this->resetAfterTest();
        [$qrvote, $context] = $this->create_vote();
        $now = time();
        $later = round_manager::schedule($qrvote, 'Later', '', $now + 2 * HOURSECS, $now + 3 * HOURSECS, $context);
        $next = round_manager::schedule($qrvote, 'Next', '', $now + HOURSECS, $now + 2 * HOURSECS, $context);

        $this->assertEquals($next->id, round_manager::get_default((int) $qrvote->id, $now)->id);

        $live = round_manager::start($qrvote, $context);
        round_manager::close($live, $context);
        $this->assertEquals($live->id, round_manager::get_default((int) $qrvote->id, $now)->id);

        $this->assertEquals($later->id, round_manager::get_default((int) $qrvote->id, $now + 2 * HOURSECS + 1)->id);
    }

    public function test_window_is_formatted_with_one_date_on_the_same_day(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Berlin');
        $open = make_timestamp(2026, 11, 5, 10, 0);

        $sameday = round_manager::format_window($open, $open + 90 * MINSECS);
        $twodays = round_manager::format_window($open, $open + DAYSECS);

        $this->assertSame(1, substr_count($sameday, '5 November'));
        $this->assertSame(1, substr_count($twodays, '5 November'));
        $this->assertSame(1, substr_count($twodays, '6 November'));
    }
}
