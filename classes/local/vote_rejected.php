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
 * A vote was refused for a reason the voter should read, such as a closed round or a second vote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vote_rejected extends \moodle_exception {
    /** @var string The round no longer accepts votes. */
    public const ROUND_CLOSED = 'error:roundclosed';

    /** @var string This device or user has already voted in the round. */
    public const ALREADY_VOTED = 'error:alreadyvoted';

    /** @var string The submitted options do not belong to the vote or break the answer mode. */
    public const INVALID_OPTIONS = 'error:invalidoptions';

    /** @var string The voter key is malformed. */
    public const INVALID_VOTER = 'error:invalidvoter';

    /** @var string Too many submissions from the same network address. */
    public const RATE_LIMITED = 'error:ratelimited';

    /**
     * Creates the exception for one of the reason constants.
     *
     * @param string $reason One of the class constants, a string key of mod_qrvote.
     */
    public function __construct(string $reason) {
        parent::__construct($reason, 'qrvote');
    }
}
