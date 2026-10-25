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
 * Tests for word clouds.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(word_service::class)]
#[CoversClass(poll::class)]
#[CoversClass(results::class)]
#[CoversClass(get_results::class)]
final class wordcloud_test extends \advanced_testcase {
    /**
     * Creates a word cloud with an open round.
     *
     * @param int $maxwords Terms per voter.
     * @return array [instance, round, context]
     */
    private function create_cloud(int $maxwords = 3): array {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $qrvote = $this->getDataGenerator()->create_module('qrvote', [
            'course' => $course->id,
            'votetype' => poll::TYPE_WORDCLOUD,
            'maxwords' => $maxwords,
            'multiple' => 1,
            'option' => [],
        ]);
        $context = \context_module::instance($qrvote->cmid);
        return [$qrvote, round_manager::start($qrvote, $context), $context];
    }

    /**
     * Runs a submission and returns the reason it was rejected.
     *
     * @param callable $submit Submission.
     * @return string|null Error code, or null when accepted.
     */
    private function rejection(callable $submit): ?string {
        try {
            $submit();
        } catch (vote_rejected $e) {
            return $e->errorcode;
        }
        return null;
    }

    public function test_cloud_has_no_options_and_no_multiple_choice(): void {
        global $DB;

        $this->resetAfterTest();
        [$qrvote] = $this->create_cloud();

        $record = $DB->get_record('qrvote', ['id' => $qrvote->id]);
        $this->assertTrue(poll::is_wordcloud($record));
        $this->assertEquals(0, $record->multiple);
        $this->assertSame([], poll::get_options((int) $qrvote->id));
    }

    public function test_voter_can_submit_up_to_the_limit(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_cloud(2);
        $key = vote_service::new_voter_key();

        $sink = $this->redirectEvents();
        word_service::submit($qrvote, $round, 'Ideas', $key, 0, $context);
        $this->assertSame(1, word_service::remaining($qrvote, $round, $key, 0));
        word_service::submit($qrvote, $round, 'Topics', $key, 0, $context);
        $events = $sink->get_events();
        $reason = $this->rejection(fn() => word_service::submit($qrvote, $round, 'More', $key, 0, $context));

        $this->assertSame(vote_rejected::WORD_LIMIT, $reason);
        $this->assertSame(0, word_service::remaining($qrvote, $round, $key, 0));
        $this->assertInstanceOf(\mod_qrvote\event\word_submitted::class, end($events));
        $this->assertEquals(1, end($events)->anonymous);
    }

    public function test_same_term_twice_from_one_voter_is_rejected_regardless_of_case(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_cloud();
        $key = vote_service::new_voter_key();
        word_service::submit($qrvote, $round, 'Moodle', $key, 0, $context);

        $reason = $this->rejection(fn() => word_service::submit($qrvote, $round, '  MOODLE ', $key, 0, $context));

        $this->assertSame(vote_rejected::WORD_REPEATED, $reason);
        $this->assertSame(2, word_service::remaining($qrvote, $round, $key, 0));
    }

    public function test_terms_are_cleaned_and_empty_terms_rejected(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_cloud();

        $long = "<b>Long</b>\n  term " . str_repeat('x', 60);
        word_service::submit($qrvote, $round, $long, vote_service::new_voter_key(), 0, $context);
        $reason = $this->rejection(
            fn() => word_service::submit($qrvote, $round, '<script></script>  ', vote_service::new_voter_key(), 0, $context)
        );

        $words = word_service::cloud($round)['words'];
        $this->assertSame(vote_rejected::INVALID_WORD, $reason);
        $this->assertStringStartsWith('Long term x', $words[0]['text']);
        $this->assertSame(word_service::WORD_MAXLENGTH, \core_text::strlen($words[0]['text']));
    }

    public function test_cloud_groups_terms_and_scales_sizes(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_cloud();
        foreach (['AI', 'ai', 'AI', 'AI', 'Privacy', 'Video'] as $word) {
            word_service::submit($qrvote, $round, $word, vote_service::new_voter_key(), 0, $context);
        }

        $cloud = word_service::cloud($round);
        $result = results::for_round($qrvote, $round, $context);

        $this->assertSame(['AI', 'Privacy', 'Video'], array_column($cloud['words'], 'text'));
        $this->assertSame([4, 1, 1], array_column($cloud['words'], 'count'));
        $this->assertSame([word_service::SIZE_STEPS, 1, 1], array_column($cloud['words'], 'size'));
        $this->assertSame(6, $cloud['entries']);
        $this->assertTrue($result['iswordcloud']);
        $this->assertSame(6, $result['voters']);
    }

    public function test_hidden_terms_leave_the_cloud_but_stay_in_moderation(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_cloud();
        word_service::submit($qrvote, $round, 'Spam', vote_service::new_voter_key(), 0, $context);
        word_service::submit($qrvote, $round, 'Ideas', vote_service::new_voter_key(), 0, $context);

        $sink = $this->redirectEvents();
        word_service::set_hidden($round, 'spam', true, $context);
        $events = $sink->get_events();

        $this->assertSame(['Ideas'], array_column(word_service::cloud($round)['words'], 'text'));
        $moderation = array_column(word_service::moderation_list($round), 'hidden', 'text');
        $this->assertTrue($moderation['Spam']);
        $this->assertFalse($moderation['Ideas']);
        $this->assertInstanceOf(\mod_qrvote\event\word_moderated::class, end($events));

        word_service::set_hidden($round, 'spam', false, $context);
        $this->assertCount(2, word_service::cloud($round)['words']);
    }

    public function test_closed_round_rejects_terms(): void {
        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_cloud();
        round_manager::close($round, $context);

        $reason = $this->rejection(
            fn() => word_service::submit($qrvote, $round, 'Late', vote_service::new_voter_key(), 0, $context)
        );

        $this->assertSame(vote_rejected::ROUND_CLOSED, $reason);
    }

    public function test_login_terms_count_per_user(): void {
        global $DB;

        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_cloud(1);
        $user = $this->getDataGenerator()->create_user();

        word_service::submit($qrvote, $round, 'Mine', '', (int) $user->id, $context);
        $reason = $this->rejection(fn() => word_service::submit($qrvote, $round, 'Other', '', (int) $user->id, $context));

        $this->assertSame(vote_rejected::WORD_LIMIT, $reason);
        $this->assertEquals($user->id, $DB->get_field('qrvote_words', 'userid', ['roundid' => $round->id]));
    }

    public function test_deleting_the_round_removes_terms_and_locks_type_before(): void {
        global $DB;

        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_cloud();
        word_service::submit($qrvote, $round, 'Ideas', vote_service::new_voter_key(), 0, $context);

        $this->assertTrue(poll::has_votes((int) $qrvote->id));
        round_manager::delete($round, $context);

        $this->assertFalse($DB->record_exists('qrvote_words', ['roundid' => $round->id]));
        $this->assertFalse(poll::has_votes((int) $qrvote->id));
    }

    public function test_web_service_and_template_show_the_cloud(): void {
        global $PAGE;

        $this->resetAfterTest();
        [$qrvote, $round, $context] = $this->create_cloud();
        word_service::submit($qrvote, $round, 'Rock & Roll', vote_service::new_voter_key(), 0, $context);

        $result = external_api::clean_returnvalue(
            get_results::execute_returns(),
            get_results::execute((int) $qrvote->cmid, (int) $round->id)
        );
        $html = $PAGE->get_renderer('core')->render_from_template('mod_qrvote/results', $result);

        $this->assertTrue($result['iswordcloud']);
        $this->assertSame('Rock & Roll', $result['words'][0]['text']);
        $this->assertStringContainsString('Rock &amp; Roll', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }
}
