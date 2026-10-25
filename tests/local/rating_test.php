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

use core_external\external_api;
use mod_qrvote\external\get_results;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for star ratings.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(poll::class)]
#[CoversClass(results::class)]
#[CoversClass(vote_service::class)]
#[CoversClass(get_results::class)]
final class rating_test extends \advanced_testcase {
    /**
     * Creates a rating with an open round.
     *
     * @return array [instance, round, context, option ids keyed by number of stars]
     */
    private function create_rating(): array {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', [
            'course' => $course->id,
            'votetype' => poll::TYPE_RATING,
            'multiple' => 1,
            'option' => [],
        ]);
        $context = \context_module::instance($qrvote->cmid);
        $round = round_manager::start($qrvote, $context);
        $bystars = [];
        foreach (poll::get_options((int) $qrvote->id) as $option) {
            $bystars[poll::stars($option)] = (int) $option->id;
        }
        return [$qrvote, $round, $context, $bystars];
    }

    public function test_rating_creates_five_options_and_single_choice(): void {
        global $DB;

        $this->resetAfterTest();
        [$qrvote, , , $bystars] = $this->create_rating();

        $record = $DB->get_record('qrvote', ['id' => $qrvote->id]);
        $this->assertTrue(poll::is_rating($record));
        $this->assertEquals(0, $record->multiple);
        $this->assertSame([1, 2, 3, 4, 5], array_keys($bystars));
    }

    public function test_average_and_distribution_with_five_stars_first(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context, $bystars] = $this->create_rating();
        foreach ([5, 5, 4, 2] as $stars) {
            vote_service::submit($qrvote, $round, [$bystars[$stars]], vote_service::new_voter_key(), 0, $context);
        }

        $result = results::for_round($qrvote, $round, $context);

        $this->assertTrue($result['israting']);
        $this->assertTrue($result['hasaverage']);
        $this->assertSame(format_float(4.0, 1), $result['average']);
        $this->assertSame([2, 1, 0, 1, 0], array_column($result['options'], 'votes'));
        $this->assertSame('5 stars', $result['options'][0]['text']);
        $this->assertSame('1 star', $result['options'][4]['text']);
        $this->assertSame("\u{2605}\u{2605}\u{2605}\u{2605}\u{2606}", $result['options'][1]['stars']);
    }

    public function test_two_stars_at_once_are_rejected(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context, $bystars] = $this->create_rating();

        $this->expectException(vote_rejected::class);
        vote_service::submit($qrvote, $round, [$bystars[1], $bystars[5]], vote_service::new_voter_key(), 0, $context);
    }

    public function test_no_average_without_votes(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_rating();

        $result = results::for_round($qrvote, $round, $context);

        $this->assertFalse($result['hasaverage']);
        $this->assertSame('', $result['average']);
    }

    public function test_switching_type_before_votes_replaces_options(): void {
        global $DB;

        $this->resetAfterTest();
        [$qrvote] = $this->create_rating();
        $record = $DB->get_record('qrvote', ['id' => $qrvote->id]);

        poll::update_instance((object) [
            'instance' => $record->id, 'course' => $record->course, 'name' => $record->name,
            'question' => $record->question, 'votetype' => poll::TYPE_CHOICE, 'option' => ['Yes', 'No'],
        ]);

        $this->assertSame(['Yes', 'No'], array_values(array_column(poll::get_options((int) $record->id), 'text')));
        $this->assertFalse(poll::is_rating($DB->get_record('qrvote', ['id' => $record->id])));
    }

    public function test_type_is_locked_once_votes_exist(): void {
        global $DB;

        $this->resetAfterTest();
        [$qrvote, $round, $context, $bystars] = $this->create_rating();
        vote_service::submit($qrvote, $round, [$bystars[3]], vote_service::new_voter_key(), 0, $context);
        $record = $DB->get_record('qrvote', ['id' => $qrvote->id]);

        poll::update_instance((object) [
            'instance' => $record->id, 'course' => $record->course, 'name' => $record->name,
            'question' => $record->question, 'votetype' => poll::TYPE_CHOICE, 'option' => ['Yes', 'No'],
        ]);

        $this->assertTrue(poll::is_rating($DB->get_record('qrvote', ['id' => $record->id])));
        $this->assertCount(5, poll::get_options((int) $record->id));
    }

    public function test_web_service_and_template_show_the_rating(): void {
        global $PAGE;

        $this->resetAfterTest();
        [$qrvote, $round, $context, $bystars] = $this->create_rating();
        vote_service::submit($qrvote, $round, [$bystars[4]], vote_service::new_voter_key(), 0, $context);

        $result = external_api::clean_returnvalue(
            get_results::execute_returns(),
            get_results::execute((int) $qrvote->cmid, (int) $round->id)
        );
        $html = $PAGE->get_renderer('core')->render_from_template('mod_qrvote/results', $result);

        $this->assertTrue($result['israting']);
        $this->assertSame(format_float(4.0, 1), $result['average']);
        $this->assertStringContainsString('mod_qrvote-average', $html);
        $this->assertStringContainsString('4 stars', $html);
    }
}
