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

use context_module;
use mod_qrvote\event\word_moderated;
use mod_qrvote\event\word_submitted;
use stdClass;

/**
 * Accepts terms for a word cloud round, limits them per voter and lets teachers hide terms.
 *
 * Voters are identified as in vote_service: by a random device key or by the user id, stored only
 * as an HMAC with the round secret. Equal terms are grouped case-insensitively; one voter cannot
 * submit the same term twice in a round, so a single device cannot inflate a term.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class word_service {
    /** @var int Maximum length of a term in characters, matches the database column. */
    public const WORD_MAXLENGTH = 40;

    /** @var int Maximum number of distinct terms shown in a cloud. */
    public const CLOUD_LIMIT = 100;

    /** @var int Number of font size steps in the cloud. */
    public const SIZE_STEPS = 5;

    /**
     * Stores a term.
     *
     * @param stdClass $qrvote Instance record.
     * @param stdClass $round Round record.
     * @param string $word Term as entered.
     * @param string $voterkey Random key of the device (32 hex characters), ignored when $userid is set.
     * @param int $userid Id of the logged-in voter, 0 for a public vote.
     * @param context_module $context Module context.
     * @return void
     * @throws vote_rejected When the round is not open, the term is invalid or repeated, or the limit is reached.
     */
    public static function submit(
        stdClass $qrvote,
        stdClass $round,
        string $word,
        string $voterkey,
        int $userid,
        context_module $context
    ): void {
        global $DB;

        vote_service::check_round($qrvote, $round);
        $word = self::clean($word);
        if ($word === '') {
            throw new vote_rejected(vote_rejected::INVALID_WORD);
        }
        $normalized = self::normalize($word);
        $voterhash = vote_service::voter_hash($round, vote_service::voter_key($voterkey, $userid));

        if ($DB->record_exists('qrvote_words', ['roundid' => $round->id, 'voterhash' => $voterhash, 'normalized' => $normalized])) {
            throw new vote_rejected(vote_rejected::WORD_REPEATED);
        }
        if (self::count_for_hash($round, $voterhash) >= poll::max_words($qrvote)) {
            throw new vote_rejected(vote_rejected::WORD_LIMIT);
        }

        try {
            $DB->insert_record('qrvote_words', (object) [
                'roundid' => $round->id,
                'word' => $word,
                'normalized' => $normalized,
                'voterhash' => $voterhash,
                'userid' => $userid > 0 ? $userid : null,
                'hidden' => 0,
                'timecreated' => time(),
            ]);
        } catch (\dml_write_exception $e) {
            // The unique index caught the same term sent twice at the same moment.
            throw new vote_rejected(vote_rejected::WORD_REPEATED);
        }

        // The event names neither the voter nor the term.
        word_submitted::create([
            'objectid' => $round->id,
            'context' => $context,
            'anonymous' => $userid > 0 ? 0 : 1,
        ])->trigger();
    }

    /**
     * Returns how many terms a voter may still submit in a round.
     *
     * @param stdClass $qrvote Instance record.
     * @param stdClass $round Round record.
     * @param string $voterkey Random key of the device, ignored when $userid is set.
     * @param int $userid Id of the logged-in voter, 0 for a public vote.
     * @return int Remaining number of terms.
     */
    public static function remaining(stdClass $qrvote, stdClass $round, string $voterkey, int $userid): int {
        try {
            $voterhash = vote_service::voter_hash($round, vote_service::voter_key($voterkey, $userid));
        } catch (vote_rejected $e) {
            return poll::max_words($qrvote);
        }
        return max(0, poll::max_words($qrvote) - self::count_for_hash($round, $voterhash));
    }

    /**
     * Hides or shows all entries of a term in a round.
     *
     * @param stdClass $round Round record.
     * @param string $normalized Grouped term as returned in the moderation list.
     * @param bool $hidden True to hide, false to show again.
     * @param context_module $context Module context.
     * @return void
     */
    public static function set_hidden(stdClass $round, string $normalized, bool $hidden, context_module $context): void {
        global $DB;

        $DB->set_field('qrvote_words', 'hidden', $hidden ? 1 : 0, ['roundid' => $round->id, 'normalized' => $normalized]);

        word_moderated::create([
            'objectid' => $round->id,
            'context' => $context,
            'other' => ['hidden' => $hidden],
        ])->trigger();
    }

    /**
     * Returns the visible terms of a round grouped for the cloud, alphabetically, with a size step.
     *
     * @param stdClass $round Round record.
     * @return array Keys entries (number of visible entries) and words (text, count, size).
     */
    public static function cloud(stdClass $round): array {
        $groups = self::groups($round, false);
        uasort($groups, fn($a, $b) => $b->count <=> $a->count);
        $groups = array_slice($groups, 0, self::CLOUD_LIMIT, true);
        $max = $groups ? max(array_map(fn($g) => $g->count, $groups)) : 0;

        $words = [];
        $entries = 0;
        foreach ($groups as $group) {
            $entries += $group->count;
            $words[] = [
                'text' => $group->word,
                'count' => $group->count,
                'size' => $max > 1 ? 1 + (int) round((self::SIZE_STEPS - 1) * ($group->count - 1) / ($max - 1)) : 1,
            ];
        }
        usort($words, fn($a, $b) => strcmp(\core_text::strtolower($a['text']), \core_text::strtolower($b['text'])));
        return ['entries' => $entries, 'words' => $words];
    }

    /**
     * Returns all terms of a round for moderation, most frequent first.
     *
     * @param stdClass $round Round record.
     * @return array List of term data: text, normalized, count, hidden.
     */
    public static function moderation_list(stdClass $round): array {
        $list = [];
        foreach (self::groups($round, true) as $group) {
            $list[] = [
                'text' => $group->word,
                'normalized' => $group->normalized,
                'count' => $group->count,
                'hidden' => $group->hidden,
            ];
        }
        usort($list, fn($a, $b) => [$b['count'], $a['text']] <=> [$a['count'], $b['text']]);
        return $list;
    }

    /**
     * Cleans a term: plain text, single spaces, cut to the maximum length.
     *
     * @param string $word Raw term.
     * @return string Cleaned term, possibly empty.
     */
    public static function clean(string $word): string {
        $word = clean_param($word, PARAM_TEXT);
        $word = trim(preg_replace('/\s+/u', ' ', $word));
        return \core_text::substr($word, 0, self::WORD_MAXLENGTH);
    }

    /**
     * Returns the grouping key of a term.
     *
     * @param string $word Cleaned term.
     * @return string Lower-case term.
     */
    public static function normalize(string $word): string {
        return \core_text::strtolower($word);
    }

    /**
     * Groups the terms of a round by their normalized form.
     *
     * @param stdClass $round Round record.
     * @param bool $includehidden Whether hidden terms are included.
     * @return stdClass[] Groups with word (first spelling), normalized, count and hidden, keyed by normalized.
     */
    private static function groups(stdClass $round, bool $includehidden): array {
        global $DB;

        $params = ['roundid' => $round->id];
        $select = 'roundid = :roundid';
        if (!$includehidden) {
            $select .= ' AND hidden = 0';
        }
        $groups = [];
        $rows = $DB->get_records_select('qrvote_words', $select, $params, 'id ASC', 'id, word, normalized, hidden');
        foreach ($rows as $row) {
            if (!isset($groups[$row->normalized])) {
                $groups[$row->normalized] = (object) [
                    'word' => $row->word,
                    'normalized' => $row->normalized,
                    'count' => 0,
                    'hidden' => (bool) $row->hidden,
                ];
            }
            $groups[$row->normalized]->count++;
        }
        return $groups;
    }

    /**
     * Counts the terms a voter has submitted in a round.
     *
     * @param stdClass $round Round record.
     * @param string $voterhash Voter hash.
     * @return int Number of terms.
     */
    private static function count_for_hash(stdClass $round, string $voterhash): int {
        global $DB;

        return $DB->count_records('qrvote_words', ['roundid' => $round->id, 'voterhash' => $voterhash]);
    }
}
