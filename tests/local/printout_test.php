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
 * Tests for the station notice PDF.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(printout::class)]
final class printout_test extends \advanced_testcase {
    public function test_one_page_per_station_and_live_rounds_are_skipped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id, 'name' => 'Tagung Ü&Ä']);
        $context = \context_module::instance($qrvote->cmid);
        $live = round_manager::start($qrvote, $context);
        $first = round_manager::schedule($qrvote, 'Vortrag Ä', 'Raum 1', time(), time() + HOURSECS, $context);
        $second = round_manager::schedule($qrvote, 'Workshop', '', time(), time() + HOURSECS, $context);

        $pdf = printout::build($qrvote, [$live, $first, $second], $context);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(2, preg_match_all('#/Type\s*/Page[^s]#', $pdf));
        $this->assertStringEndsWith('.pdf', printout::filename($qrvote));
    }

    public function test_no_station_fails_loudly(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);
        $context = \context_module::instance($qrvote->cmid);
        $live = round_manager::start($qrvote, $context);

        $this->expectException(\moodle_exception::class);
        printout::build($qrvote, [$live], $context);
    }
}
