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
use mod_qrvote\event\round_closed;
use mod_qrvote\event\round_deleted;
use mod_qrvote\event\round_started;
use stdClass;

/**
 * Starts, closes and deletes voting rounds and decides whether a round accepts votes.
 *
 * A round is the unit a QR code points to: starting a round creates a fresh random token,
 * so a QR code from an earlier lesson stops working as soon as the round is closed.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class round_manager {
    /** @var int Round accepts votes. */
    public const STATUS_OPEN = 0;

    /** @var int Round was closed by a teacher. */
    public const STATUS_CLOSED = 1;

    /** @var int Default lifetime of an open round in seconds when the setting is missing. */
    public const DEFAULT_LIFETIME = 4 * HOURSECS;

    /**
     * Starts a new round. A round that is still open is closed first.
     *
     * @param stdClass $qrvote Instance record.
     * @param context_module $context Module context.
     * @return stdClass The new round record.
     */
    public static function start(stdClass $qrvote, context_module $context): stdClass {
        global $DB, $USER;

        $transaction = $DB->start_delegated_transaction();
        $open = $DB->get_records('qrvote_rounds', ['qrvoteid' => $qrvote->id, 'status' => self::STATUS_OPEN]);
        foreach ($open as $round) {
            self::close($round, $context);
        }

        $round = (object) [
            'qrvoteid' => (int) $qrvote->id,
            'token' => bin2hex(random_bytes(16)),
            'secret' => bin2hex(random_bytes(32)),
            'status' => self::STATUS_OPEN,
            'timestarted' => time(),
            'timeclosed' => 0,
            'usermodified' => (int) $USER->id,
        ];
        $round->id = (int) $DB->insert_record('qrvote_rounds', $round);
        $transaction->allow_commit();

        round_started::create([
            'objectid' => $round->id,
            'context' => $context,
        ])->trigger();
        return $round;
    }

    /**
     * Closes a round so its token no longer accepts votes.
     *
     * @param stdClass $round Round record.
     * @param context_module $context Module context.
     * @return void
     */
    public static function close(stdClass $round, context_module $context): void {
        global $DB;

        if ((int) $round->status === self::STATUS_CLOSED) {
            return;
        }
        $round->status = self::STATUS_CLOSED;
        $round->timeclosed = time();
        $DB->update_record('qrvote_rounds', (object) [
            'id' => $round->id,
            'status' => $round->status,
            'timeclosed' => $round->timeclosed,
        ]);

        round_closed::create([
            'objectid' => $round->id,
            'context' => $context,
        ])->trigger();
    }

    /**
     * Deletes a round and all its votes.
     *
     * @param stdClass $round Round record.
     * @param context_module $context Module context.
     * @return void
     */
    public static function delete(stdClass $round, context_module $context): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('qrvote_votes', ['roundid' => $round->id]);
        $DB->delete_records('qrvote_rounds', ['id' => $round->id]);
        $transaction->allow_commit();

        round_deleted::create([
            'objectid' => $round->id,
            'context' => $context,
        ])->trigger();
    }

    /**
     * Tells whether a round accepts votes right now.
     *
     * An open round expires after the configured lifetime, so a forgotten QR code cannot be used for days.
     *
     * @param stdClass $round Round record.
     * @param int|null $now Reference time, defaults to the current time.
     * @return bool True when votes are accepted.
     */
    public static function accepts_votes(stdClass $round, ?int $now = null): bool {
        $now = $now ?? time();
        if ((int) $round->status !== self::STATUS_OPEN) {
            return false;
        }
        return (int) $round->timestarted + self::lifetime() > $now;
    }

    /**
     * Returns the configured lifetime of an open round in seconds.
     *
     * @return int Lifetime in seconds.
     */
    public static function lifetime(): int {
        $lifetime = (int) get_config('mod_qrvote', 'roundlifetime');
        return $lifetime > 0 ? $lifetime : self::DEFAULT_LIFETIME;
    }

    /**
     * Returns the round a token belongs to.
     *
     * @param string $token Token from the QR code URL.
     * @return stdClass|null Round record or null when the token is unknown.
     */
    public static function get_by_token(string $token): ?stdClass {
        global $DB;

        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $round = $DB->get_record('qrvote_rounds', ['token' => $token]);
        return $round ?: null;
    }

    /**
     * Returns a round of an instance.
     *
     * @param int $qrvoteid Instance id.
     * @param int $roundid Round id.
     * @return stdClass Round record.
     */
    public static function get(int $qrvoteid, int $roundid): stdClass {
        global $DB;

        return $DB->get_record('qrvote_rounds', ['id' => $roundid, 'qrvoteid' => $qrvoteid], '*', MUST_EXIST);
    }

    /**
     * Returns the most recently started round of an instance.
     *
     * @param int $qrvoteid Instance id.
     * @return stdClass|null Round record or null when no round exists.
     */
    public static function get_latest(int $qrvoteid): ?stdClass {
        global $DB;

        $rounds = $DB->get_records('qrvote_rounds', ['qrvoteid' => $qrvoteid], 'timestarted DESC, id DESC', '*', 0, 1);
        return $rounds ? reset($rounds) : null;
    }

    /**
     * Returns all rounds of an instance with the number of voters, newest first.
     *
     * @param int $qrvoteid Instance id.
     * @return stdClass[] Round records with an additional field voters.
     */
    public static function list_rounds(int $qrvoteid): array {
        global $DB;

        $sql = 'SELECT r.id, r.status, r.timestarted, r.timeclosed, COUNT(DISTINCT v.voterhash) AS voters
                  FROM {qrvote_rounds} r
             LEFT JOIN {qrvote_votes} v ON v.roundid = r.id
                 WHERE r.qrvoteid = ?
              GROUP BY r.id, r.status, r.timestarted, r.timeclosed
              ORDER BY r.timestarted DESC, r.id DESC';
        return $DB->get_records_sql($sql, [$qrvoteid]);
    }
}
