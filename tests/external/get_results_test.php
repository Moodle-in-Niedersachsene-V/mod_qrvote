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

namespace mod_qrvote\external;

use core_external\external_api;
use mod_qrvote\local\poll;
use mod_qrvote\local\round_manager;
use mod_qrvote\local\vote_service;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the live result web service.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_results::class)]
final class get_results_test extends \advanced_testcase {
    /**
     * Creates a course with a teacher, a student and a QR vote with one vote.
     *
     * @return array [instance, round, teacher, student]
     */
    private function create_setup(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $qrvote = $generator->create_module('qrvote', ['course' => $course->id]);
        $context = \context_module::instance($qrvote->cmid);
        $this->setUser($teacher);
        $round = round_manager::start($qrvote, $context);
        $option = array_key_first(poll::get_options((int) $qrvote->id));
        vote_service::submit($qrvote, $round, [$option], vote_service::new_voter_key(), 0, $context);
        return [$qrvote, $round, $teacher, $student];
    }

    public function test_teacher_gets_the_result(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $teacher] = $this->create_setup();
        $this->setUser($teacher);

        $result = external_api::clean_returnvalue(
            get_results::execute_returns(),
            get_results::execute((int) $qrvote->cmid, (int) $round->id)
        );

        $this->assertSame((int) $round->id, $result['roundid']);
        $this->assertTrue($result['open']);
        $this->assertSame(1, $result['voters']);
        $this->assertSame([1, 0, 0], array_column($result['options'], 'votes'));
    }

    public function test_student_without_capability_is_refused(): void {
        $this->resetAfterTest();
        [$qrvote, $round, , $student] = $this->create_setup();
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_results::execute((int) $qrvote->cmid, (int) $round->id);
    }

    public function test_round_of_another_activity_is_refused(): void {
        $this->resetAfterTest();
        [$qrvote, , $teacher] = $this->create_setup();
        [, $foreignround] = $this->create_setup();
        $this->setUser($teacher);

        $this->expectException(\dml_missing_record_exception::class);
        get_results::execute((int) $qrvote->cmid, (int) $foreignround->id);
    }
}
