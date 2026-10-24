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

namespace mod_qrvote\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use mod_qrvote\local\poll;
use mod_qrvote\local\round_manager;
use mod_qrvote\local\vote_service;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the privacy provider.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /**
     * Creates a login vote with a round started by a teacher, one login vote and one public vote.
     *
     * @return array [context, teacher, student, round]
     */
    private function create_setup(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $qrvote = $generator->create_module('qrvote', ['course' => $course->id, 'publicvote' => 0]);
        $context = \context_module::instance($qrvote->cmid);
        $options = array_keys(poll::get_options((int) $qrvote->id));

        $this->setUser($teacher);
        $round = round_manager::start($qrvote, $context);
        $this->setUser($student);
        vote_service::submit($qrvote, $round, [$options[1]], '', (int) $student->id, $context);
        vote_service::submit($qrvote, $round, [$options[0]], vote_service::new_voter_key(), 0, $context);
        $this->setUser();
        return [$context, $teacher, $student, $round];
    }

    public function test_contexts_and_users_cover_starter_and_voter(): void {
        $this->resetAfterTest();
        [$context, $teacher, $student] = $this->create_setup();
        $other = $this->getDataGenerator()->create_user();

        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $teacher->id)->get_contextids());
        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $student->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid((int) $other->id)->get_contextids());

        $userlist = new userlist($context, 'mod_qrvote');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        sort($userids);
        $expected = [(int) $teacher->id, (int) $student->id];
        sort($expected);
        $this->assertEquals($expected, $userids);
    }

    public function test_export_contains_the_users_vote(): void {
        $this->resetAfterTest();
        [$context, , $student] = $this->create_setup();

        $this->export_context_data_for_user((int) $student->id, $context, 'mod_qrvote');

        $data = writer::with_context($context)->get_data([]);
        $votes = $data->{get_string('privacy:votes', 'qrvote')};
        $this->assertCount(1, $votes);
        $this->assertSame('Percentages', $votes[0]->answer);
    }

    public function test_delete_for_user_removes_vote_and_anonymises_rounds(): void {
        global $DB;

        $this->resetAfterTest();
        [$context, $teacher, $student, $round] = $this->create_setup();

        provider::delete_data_for_user(new approved_contextlist($student, 'mod_qrvote', [$context->id]));
        provider::delete_data_for_users(new approved_userlist($context, 'mod_qrvote', [(int) $teacher->id]));

        $this->assertFalse($DB->record_exists('qrvote_votes', ['userid' => $student->id]));
        $this->assertEquals(1, $DB->count_records('qrvote_votes', ['roundid' => $round->id]));
        $this->assertEquals(0, $DB->get_field('qrvote_rounds', 'usermodified', ['id' => $round->id]));
    }

    public function test_delete_for_context_keeps_public_votes(): void {
        global $DB;

        $this->resetAfterTest();
        [$context, , , $round] = $this->create_setup();

        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(0, $DB->count_records_select('qrvote_votes', 'userid IS NOT NULL'));
        $this->assertEquals(1, $DB->count_records('qrvote_votes', ['roundid' => $round->id]));
        $this->assertEquals(0, $DB->get_field('qrvote_rounds', 'usermodified', ['id' => $round->id]));
    }
}
