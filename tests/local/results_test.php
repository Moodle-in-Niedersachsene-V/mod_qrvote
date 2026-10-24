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
 * Tests for result aggregation.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(results::class)]
final class results_test extends \advanced_testcase {
    public function test_counts_votes_and_percent_of_voters(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id, 'multiple' => 1]);
        $context = \context_module::instance($qrvote->cmid);
        $round = round_manager::start($qrvote, $context);
        $options = array_keys(poll::get_options((int) $qrvote->id));

        vote_service::submit($qrvote, $round, [$options[0], $options[1]], vote_service::new_voter_key(), 0, $context);
        vote_service::submit($qrvote, $round, [$options[0]], vote_service::new_voter_key(), 0, $context);
        vote_service::submit($qrvote, $round, [$options[0]], vote_service::new_voter_key(), 0, $context);
        vote_service::submit($qrvote, $round, [$options[1]], vote_service::new_voter_key(), 0, $context);

        $result = results::for_round($qrvote, $round, $context);

        $this->assertSame(4, $result['voters']);
        $this->assertTrue($result['open']);
        $this->assertSame(['Fractions', 'Percentages', 'Equations'], array_column($result['options'], 'text'));
        $this->assertSame([3, 2, 0], array_column($result['options'], 'votes'));
        $this->assertSame([75, 50, 0], array_column($result['options'], 'percent'));
    }

    public function test_option_text_is_plain_text_and_escaped_once_when_rendered(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', [
            'course' => $course->id,
            'option' => ['Red & green', 'Blue'],
        ]);
        $context = \context_module::instance($qrvote->cmid);
        $round = round_manager::start($qrvote, $context);

        $result = results::for_round($qrvote, $round, $context);
        $html = $PAGE->get_renderer('core')->render_from_template('mod_qrvote/results', $result);

        $this->assertSame('Red & green', $result['options'][0]['text']);
        $this->assertStringContainsString('Red &amp; green', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function test_round_without_votes_has_zero_percent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);
        $context = \context_module::instance($qrvote->cmid);
        $round = round_manager::start($qrvote, $context);

        $result = results::for_round($qrvote, $round, $context);

        $this->assertSame(0, $result['voters']);
        $this->assertSame([0, 0, 0], array_column($result['options'], 'percent'));
    }
}
