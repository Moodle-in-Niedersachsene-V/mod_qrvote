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
use mod_qrvote\event\vote_submitted;
use stdClass;

/**
 * Accepts votes for a round and keeps one vote per voter.
 *
 * A voter is identified by a voter key: a random key the voter's browser keeps for public votes,
 * or the user id for votes that require login. Only an HMAC of the key with the round secret is
 * stored, so the database cannot be used to recognise a device across rounds.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vote_service {
    /**
     * Stores a vote.
     *
     * @param stdClass $qrvote Instance record.
     * @param stdClass $round Round record.
     * @param int[] $optionids Selected option ids.
     * @param string $voterkey Random key of the device (32 hex characters), ignored when $userid is set.
     * @param int $userid Id of the logged-in voter, 0 for a public vote.
     * @param context_module $context Module context.
     * @return void
     * @throws vote_rejected When the round is not open, the voter already voted or the options are invalid.
     */
    public static function submit(
        stdClass $qrvote,
        stdClass $round,
        array $optionids,
        string $voterkey,
        int $userid,
        context_module $context
    ): void {
        global $DB;

        if ((int) $round->qrvoteid !== (int) $qrvote->id) {
            throw new vote_rejected(vote_rejected::ROUND_CLOSED);
        }
        $state = round_manager::state($round);
        if ($state === round_manager::STATE_SCHEDULED) {
            throw new vote_rejected(vote_rejected::ROUND_NOT_OPEN);
        }
        if ($state !== round_manager::STATE_OPEN) {
            throw new vote_rejected(vote_rejected::ROUND_CLOSED);
        }
        $optionids = self::validate_options($qrvote, $optionids);
        $voterhash = self::voter_hash($round, self::voter_key($voterkey, $userid));

        if ($DB->record_exists('qrvote_votes', ['roundid' => $round->id, 'voterhash' => $voterhash])) {
            throw new vote_rejected(vote_rejected::ALREADY_VOTED);
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($optionids as $optionid) {
                $DB->insert_record('qrvote_votes', (object) [
                    'roundid' => $round->id,
                    'optionid' => $optionid,
                    'voterhash' => $voterhash,
                    'userid' => $userid > 0 ? $userid : null,
                    'timecreated' => $now,
                ]);
            }
            $transaction->allow_commit();
        } catch (\dml_write_exception $e) {
            // The unique index caught a second submission that arrived at the same moment.
            $transaction->rollback(new vote_rejected(vote_rejected::ALREADY_VOTED));
        }

        // The event names neither the voter nor the chosen options, so the log does not reveal a choice.
        vote_submitted::create([
            'objectid' => $round->id,
            'context' => $context,
            'anonymous' => $userid > 0 ? 0 : 1,
        ])->trigger();
    }

    /**
     * Tells whether a voter already voted in a round.
     *
     * @param stdClass $round Round record.
     * @param string $voterkey Random key of the device, ignored when $userid is set.
     * @param int $userid Id of the logged-in voter, 0 for a public vote.
     * @return bool True when a vote exists.
     */
    public static function has_voted(stdClass $round, string $voterkey, int $userid): bool {
        global $DB;

        try {
            $voterhash = self::voter_hash($round, self::voter_key($voterkey, $userid));
        } catch (vote_rejected $e) {
            return false;
        }
        return $DB->record_exists('qrvote_votes', ['roundid' => $round->id, 'voterhash' => $voterhash]);
    }

    /**
     * Creates a new random voter key for a device.
     *
     * @return string 32 hex characters.
     */
    public static function new_voter_key(): string {
        return bin2hex(random_bytes(16));
    }

    /**
     * Checks that the options belong to the vote and match the answer mode.
     *
     * @param stdClass $qrvote Instance record.
     * @param int[] $optionids Selected option ids.
     * @return int[] Unique option ids.
     * @throws vote_rejected When the selection is empty, foreign or breaks single choice.
     */
    private static function validate_options(stdClass $qrvote, array $optionids): array {
        $optionids = array_values(array_unique(array_map('intval', $optionids)));
        if (!$optionids) {
            throw new vote_rejected(vote_rejected::INVALID_OPTIONS);
        }
        if (empty($qrvote->multiple) && count($optionids) > 1) {
            throw new vote_rejected(vote_rejected::INVALID_OPTIONS);
        }
        $valid = array_keys(poll::get_options((int) $qrvote->id));
        if (array_diff($optionids, $valid)) {
            throw new vote_rejected(vote_rejected::INVALID_OPTIONS);
        }
        return $optionids;
    }

    /**
     * Returns the key a vote is counted under.
     *
     * @param string $voterkey Random key of the device.
     * @param int $userid Id of the logged-in voter, 0 for a public vote.
     * @return string Voter key.
     * @throws vote_rejected When a public voter key is malformed.
     */
    private static function voter_key(string $voterkey, int $userid): string {
        if ($userid > 0) {
            return 'user:' . $userid;
        }
        if (!preg_match('/^[a-f0-9]{32}$/', $voterkey)) {
            throw new vote_rejected(vote_rejected::INVALID_VOTER);
        }
        return 'device:' . $voterkey;
    }

    /**
     * Returns the stored hash of a voter key for a round.
     *
     * @param stdClass $round Round record with its secret.
     * @param string $key Voter key.
     * @return string 64 hex characters.
     */
    private static function voter_hash(stdClass $round, string $key): string {
        return hash_hmac('sha256', $key, $round->secret);
    }
}
