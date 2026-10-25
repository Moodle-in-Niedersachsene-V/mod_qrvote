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
use mod_qrvote\event\round_scheduled;
use mod_qrvote\event\round_started;
use mod_qrvote\event\round_updated;
use stdClass;

/**
 * Starts, closes and deletes voting rounds and decides whether a round accepts votes.
 *
 * A round is the unit a QR code points to. There are two kinds:
 * - A live round starts now and gets a fresh random token, so a QR code from an earlier lesson stops
 *   working as soon as the round is closed. It expires after the configured lifetime.
 * - A scheduled round (a station, for example a talk in a room) has a title, a location and a fixed
 *   voting window. Its token exists in advance so the QR code can be printed; votes are accepted
 *   only inside the window. The window is checked on every request, no scheduled task is needed.
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

    /** @var string Scheduled round whose window has not started yet. */
    public const STATE_SCHEDULED = 'scheduled';

    /** @var string Round that accepts votes. */
    public const STATE_OPEN = 'open';

    /** @var string Round that no longer accepts votes. */
    public const STATE_CLOSED = 'closed';

    /** @var int Maximum length of title and location, matches the database columns. */
    public const TEXT_MAXLENGTH = 255;

    /**
     * Starts a new live round. A live round that is still open is closed first; scheduled rounds stay as they are.
     *
     * @param stdClass $qrvote Instance record.
     * @param context_module $context Module context.
     * @return stdClass The new round record.
     */
    public static function start(stdClass $qrvote, context_module $context): stdClass {
        global $DB, $USER;

        $transaction = $DB->start_delegated_transaction();
        $open = $DB->get_records('qrvote_rounds', [
            'qrvoteid' => $qrvote->id,
            'status' => self::STATUS_OPEN,
            'timeopen' => 0,
        ]);
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
            'title' => '',
            'location' => '',
            'timeopen' => 0,
            'timeclose' => 0,
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
     * Creates a scheduled round whose QR code can be printed before the voting window starts.
     *
     * @param stdClass $qrvote Instance record.
     * @param string $title Title, for example of the talk.
     * @param string $location Location, for example the room.
     * @param int $timeopen Start of the voting window.
     * @param int $timeclose End of the voting window.
     * @param context_module $context Module context.
     * @return stdClass The new round record.
     */
    public static function schedule(
        stdClass $qrvote,
        string $title,
        string $location,
        int $timeopen,
        int $timeclose,
        context_module $context
    ): stdClass {
        global $DB, $USER;

        self::validate_window($timeopen, $timeclose);
        $round = (object) [
            'qrvoteid' => (int) $qrvote->id,
            'token' => bin2hex(random_bytes(16)),
            'secret' => bin2hex(random_bytes(32)),
            'status' => self::STATUS_OPEN,
            'timestarted' => $timeopen,
            'timeclosed' => 0,
            'usermodified' => (int) $USER->id,
            'title' => self::clean_text($title),
            'location' => self::clean_text($location),
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
        ];
        $round->id = (int) $DB->insert_record('qrvote_rounds', $round);

        round_scheduled::create([
            'objectid' => $round->id,
            'context' => $context,
        ])->trigger();
        return $round;
    }

    /**
     * Changes title, location and window of a scheduled round. The token stays, so printed codes remain valid.
     *
     * Saving also reopens a station that was closed early; from then on its window decides again.
     *
     * @param stdClass $round Scheduled round record.
     * @param string $title Title.
     * @param string $location Location.
     * @param int $timeopen Start of the voting window.
     * @param int $timeclose End of the voting window.
     * @param context_module $context Module context.
     * @return void
     */
    public static function update_schedule(
        stdClass $round,
        string $title,
        string $location,
        int $timeopen,
        int $timeclose,
        context_module $context
    ): void {
        global $DB;

        if (!self::is_scheduled($round)) {
            throw new \coding_exception('Only scheduled rounds have a voting window.');
        }
        self::validate_window($timeopen, $timeclose);
        $DB->update_record('qrvote_rounds', (object) [
            'id' => $round->id,
            'title' => self::clean_text($title),
            'location' => self::clean_text($location),
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timestarted' => $timeopen,
            'status' => self::STATUS_OPEN,
            'timeclosed' => 0,
        ]);

        round_updated::create([
            'objectid' => $round->id,
            'context' => $context,
        ])->trigger();
    }

    /**
     * Tells whether a round is a scheduled round.
     *
     * @param stdClass $round Round record.
     * @return bool True for a scheduled round.
     */
    public static function is_scheduled(stdClass $round): bool {
        return (int) ($round->timeopen ?? 0) > 0;
    }

    /**
     * Returns the state of a round at a time.
     *
     * @param stdClass $round Round record.
     * @param int|null $now Reference time, defaults to the current time.
     * @return string One of the STATE_ constants.
     */
    public static function state(stdClass $round, ?int $now = null): string {
        $now = $now ?? time();
        if ((int) $round->status !== self::STATUS_OPEN) {
            return self::STATE_CLOSED;
        }
        if (self::is_scheduled($round)) {
            if ($now < (int) $round->timeopen) {
                return self::STATE_SCHEDULED;
            }
            return $now < (int) $round->timeclose ? self::STATE_OPEN : self::STATE_CLOSED;
        }
        return (int) $round->timestarted + self::lifetime() > $now ? self::STATE_OPEN : self::STATE_CLOSED;
    }

    /**
     * Returns the scheduled rounds of an instance in the order of their windows.
     *
     * @param int $qrvoteid Instance id.
     * @return stdClass[] Round records keyed by id.
     */
    public static function get_scheduled(int $qrvoteid): array {
        global $DB;

        return $DB->get_records_select(
            'qrvote_rounds',
            'qrvoteid = ? AND timeopen > 0',
            [$qrvoteid],
            'timeopen ASC, id ASC'
        );
    }

    /**
     * Formats a voting window, with the date only once when start and end fall on the same day.
     *
     * @param int $timeopen Start.
     * @param int $timeclose End.
     * @return string Formatted window.
     */
    public static function format_window(int $timeopen, int $timeclose): string {
        $dateformat = get_string('strftimedaydate', 'langconfig');
        $timeformat = get_string('strftimetime', 'langconfig');
        if (userdate($timeopen, $dateformat) === userdate($timeclose, $dateformat)) {
            return get_string('windowsameday', 'qrvote', (object) [
                'date' => userdate($timeopen, $dateformat),
                'open' => userdate($timeopen, $timeformat),
                'close' => userdate($timeclose, $timeformat),
            ]);
        }
        return get_string('windowdays', 'qrvote', (object) [
            'open' => userdate($timeopen),
            'close' => userdate($timeclose),
        ]);
    }

    /**
     * Checks a voting window.
     *
     * @param int $timeopen Start.
     * @param int $timeclose End.
     * @return void
     * @throws \moodle_exception When the window is empty or reversed.
     */
    private static function validate_window(int $timeopen, int $timeclose): void {
        if ($timeopen <= 0 || $timeclose <= $timeopen) {
            throw new \moodle_exception('error:invalidwindow', 'qrvote');
        }
    }

    /**
     * Cleans a title or location to plain text of the column length.
     *
     * @param string $text Raw text.
     * @return string Cleaned text.
     */
    private static function clean_text(string $text): string {
        return \core_text::substr(trim(clean_param($text, PARAM_TEXT)), 0, self::TEXT_MAXLENGTH);
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
     * A live round expires after the configured lifetime, so a forgotten QR code cannot be used for days.
     * A scheduled round accepts votes only inside its window.
     *
     * @param stdClass $round Round record.
     * @param int|null $now Reference time, defaults to the current time.
     * @return bool True when votes are accepted.
     */
    public static function accepts_votes(stdClass $round, ?int $now = null): bool {
        return self::state($round, $now) === self::STATE_OPEN;
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
     * Returns the most recently started live round of an instance.
     *
     * @param int $qrvoteid Instance id.
     * @return stdClass|null Round record or null when no round exists.
     */
    public static function get_latest(int $qrvoteid): ?stdClass {
        global $DB;

        $rounds = $DB->get_records(
            'qrvote_rounds',
            ['qrvoteid' => $qrvoteid, 'timeopen' => 0],
            'timestarted DESC, id DESC',
            '*',
            0,
            1
        );
        return $rounds ? reset($rounds) : null;
    }

    /**
     * Returns the round the teacher page shows when no round is selected.
     *
     * An open round comes first (the most recently started one), then the latest live round,
     * then the next scheduled round.
     *
     * @param int $qrvoteid Instance id.
     * @param int|null $now Reference time, defaults to the current time.
     * @return stdClass|null Round record or null when no round exists.
     */
    public static function get_default(int $qrvoteid, ?int $now = null): ?stdClass {
        global $DB;

        $now = $now ?? time();
        $rounds = $DB->get_records('qrvote_rounds', ['qrvoteid' => $qrvoteid], 'timestarted DESC, id DESC');
        foreach ($rounds as $round) {
            if (self::state($round, $now) === self::STATE_OPEN) {
                return $round;
            }
        }
        $latest = self::get_latest($qrvoteid);
        if ($latest) {
            return $latest;
        }
        foreach (array_reverse($rounds) as $round) {
            if (self::state($round, $now) === self::STATE_SCHEDULED) {
                return $round;
            }
        }
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

        $sql = 'SELECT r.id, r.status, r.timestarted, r.timeclosed, r.title, r.location, r.timeopen, r.timeclose,
                       COUNT(DISTINCT v.voterhash) AS voters
                  FROM {qrvote_rounds} r
             LEFT JOIN {qrvote_votes} v ON v.roundid = r.id
                 WHERE r.qrvoteid = ?
              GROUP BY r.id, r.status, r.timestarted, r.timeclosed, r.title, r.location, r.timeopen, r.timeclose
              ORDER BY r.timestarted DESC, r.id DESC';
        return $DB->get_records_sql($sql, [$qrvoteid]);
    }
}
