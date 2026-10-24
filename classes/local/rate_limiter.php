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

/**
 * Limits vote submissions per network address and minute.
 *
 * The address is only kept as a keyed hash in a cache entry that expires after a minute;
 * it is never written to the database. A school class usually shares one public address,
 * so the default limit is set high enough for a full class voting at once.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter {
    /** @var int Default submissions per address and minute when the setting is missing. */
    public const DEFAULT_LIMIT = 200;

    /**
     * Counts one submission and refuses it when the address exceeded the limit in the current minute.
     *
     * @param string $address Client network address.
     * @param int|null $now Reference time, defaults to the current time.
     * @return void
     * @throws vote_rejected When the limit is exceeded.
     */
    public static function hit(string $address, ?int $now = null): void {
        $limit = self::limit();
        if ($limit === 0) {
            return;
        }
        $now = $now ?? time();
        $key = hash_hmac('sha256', $address, get_site_identifier()) . '_' . intdiv($now, MINSECS);

        $cache = \cache::make('mod_qrvote', 'ratelimit');
        $count = (int) $cache->get($key) + 1;
        $cache->set($key, $count);
        if ($count > $limit) {
            throw new vote_rejected(vote_rejected::RATE_LIMITED);
        }
    }

    /**
     * Returns the configured limit; 0 switches the limit off.
     *
     * @return int Submissions per address and minute.
     */
    public static function limit(): int {
        $limit = get_config('mod_qrvote', 'ratelimit');
        if ($limit === false || $limit === '') {
            return self::DEFAULT_LIMIT;
        }
        return max(0, (int) $limit);
    }
}
