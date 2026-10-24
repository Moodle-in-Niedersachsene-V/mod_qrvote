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
 * Tests for storing and removing instances.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(poll::class)]
final class poll_test extends \advanced_testcase {
    /**
     * Returns form data to update an instance.
     *
     * @param \stdClass $qrvote Instance record.
     * @param array $changes Changed fields.
     * @return \stdClass Form data.
     */
    private function form_data(\stdClass $qrvote, array $changes): \stdClass {
        return (object) ($changes + [
            'instance' => $qrvote->id,
            'course' => $qrvote->course,
            'name' => $qrvote->name,
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'question' => $qrvote->question,
            'multiple' => $qrvote->multiple,
            'publicvote' => $qrvote->publicvote,
            'showresults' => $qrvote->showresults,
        ]);
    }

    public function test_create_stores_cleaned_options_in_order(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $qrvote = $this->getDataGenerator()->create_module('qrvote', [
            'course' => $course->id,
            'option' => ['  Yes ', '', '<b>No</b>', '   '],
        ]);

        $this->assertSame(['Yes', 'No'], array_values(array_column(poll::get_options((int) $qrvote->id), 'text')));
    }

    public function test_update_replaces_options_while_no_votes_exist(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);

        poll::update_instance($this->form_data($qrvote, ['option' => ['A', 'B'], 'multiple' => 1]));

        $this->assertSame(['A', 'B'], array_values(array_column(poll::get_options((int) $qrvote->id), 'text')));
        $this->assertEquals(1, $this->get_record($qrvote)->multiple);
    }

    public function test_update_keeps_options_and_mode_once_votes_exist(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);
        $context = \context_module::instance($qrvote->cmid);
        $round = round_manager::start($qrvote, $context);
        $before = poll::get_options((int) $qrvote->id);
        vote_service::submit($qrvote, $round, [array_key_first($before)], vote_service::new_voter_key(), 0, $context);

        poll::update_instance($this->form_data($qrvote, ['option' => ['A', 'B'], 'multiple' => 1, 'name' => 'Renamed']));

        $this->assertEquals(array_keys($before), array_keys(poll::get_options((int) $qrvote->id)));
        $this->assertEquals(0, $this->get_record($qrvote)->multiple);
        $this->assertSame('Renamed', $this->get_record($qrvote)->name);
    }

    public function test_too_few_options_fail_loudly(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);

        $this->expectException(\moodle_exception::class);
        poll::update_instance($this->form_data($qrvote, ['option' => ['Only one']]));
    }

    public function test_delete_removes_everything(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);
        $context = \context_module::instance($qrvote->cmid);
        $round = round_manager::start($qrvote, $context);
        $option = array_key_first(poll::get_options((int) $qrvote->id));
        vote_service::submit($qrvote, $round, [$option], vote_service::new_voter_key(), 0, $context);

        $this->assertTrue(poll::delete_instance((int) $qrvote->id));

        $this->assertFalse($DB->record_exists('qrvote', ['id' => $qrvote->id]));
        $this->assertFalse($DB->record_exists('qrvote_options', ['qrvoteid' => $qrvote->id]));
        $this->assertFalse($DB->record_exists('qrvote_rounds', ['qrvoteid' => $qrvote->id]));
        $this->assertEquals(0, $DB->count_records('qrvote_votes'));
        $this->assertFalse(poll::delete_instance((int) $qrvote->id));
    }

    public function test_site_setting_overrides_public_voting(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id, 'publicvote' => 1]);

        set_config('allowpublic', 1, 'mod_qrvote');
        $this->assertTrue(poll::is_public($qrvote));
        set_config('allowpublic', 0, 'mod_qrvote');
        $this->assertFalse(poll::is_public($qrvote));
    }

    public function test_course_reset_deletes_rounds(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/qrvote/lib.php');

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);
        round_manager::start($qrvote, \context_module::instance($qrvote->cmid));

        $status = qrvote_reset_userdata((object) ['courseid' => $course->id, 'reset_qrvote_rounds' => 1]);

        $this->assertFalse($DB->record_exists('qrvote_rounds', ['qrvoteid' => $qrvote->id]));
        $this->assertFalse($status[0]['error']);
    }

    public function test_duplicate_copies_options_but_no_rounds(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', ['course' => $course->id]);
        round_manager::start($qrvote, \context_module::instance($qrvote->cmid));

        $newcm = duplicate_module($course, get_fast_modinfo($course)->get_cm($qrvote->cmid));

        $this->assertSame(
            ['Fractions', 'Percentages', 'Equations'],
            array_values(array_column(poll::get_options((int) $newcm->instance), 'text'))
        );
        $this->assertFalse($DB->record_exists('qrvote_rounds', ['qrvoteid' => $newcm->instance]));
    }

    /**
     * Reads the stored instance.
     *
     * @param \stdClass $qrvote Instance record.
     * @return \stdClass Current record.
     */
    private function get_record(\stdClass $qrvote): \stdClass {
        global $DB;

        return $DB->get_record('qrvote', ['id' => $qrvote->id], '*', MUST_EXIST);
    }
}
