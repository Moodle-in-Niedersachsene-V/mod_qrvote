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
 * Tests for the submission rate limit.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(rate_limiter::class)]
final class rate_limiter_test extends \advanced_testcase {
    public function test_rejects_submissions_above_the_limit_per_minute(): void {
        $this->resetAfterTest();
        set_config('ratelimit', 3, 'mod_qrvote');
        $now = 1800000000;

        for ($i = 0; $i < 3; $i++) {
            rate_limiter::hit('192.0.2.10', $now);
        }
        $rejected = false;
        try {
            rate_limiter::hit('192.0.2.10', $now);
        } catch (vote_rejected $e) {
            $rejected = $e->errorcode === vote_rejected::RATE_LIMITED;
        }

        $this->assertTrue($rejected);
        // Another address and the next minute start with a fresh count.
        rate_limiter::hit('192.0.2.11', $now);
        rate_limiter::hit('192.0.2.10', $now + MINSECS);
        $this->assertSame(3, rate_limiter::limit());
    }

    public function test_zero_switches_the_limit_off(): void {
        $this->resetAfterTest();
        set_config('ratelimit', 0, 'mod_qrvote');

        for ($i = 0; $i < 500; $i++) {
            rate_limiter::hit('192.0.2.10', 1800000000);
        }

        $this->assertSame(0, rate_limiter::limit());
    }

    public function test_missing_setting_uses_default(): void {
        $this->resetAfterTest();
        unset_config('ratelimit', 'mod_qrvote');

        $this->assertSame(rate_limiter::DEFAULT_LIMIT, rate_limiter::limit());
    }
}
