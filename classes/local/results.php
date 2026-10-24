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
use stdClass;

/**
 * Aggregates the votes of a round per option.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class results {
    /**
     * Returns the result of a round in the shape the results template and the web service use.
     *
     * The percentage of an option is the share of voters who selected it, so with multiple
     * choice the percentages can add up to more than 100.
     *
     * @param stdClass $qrvote Instance record.
     * @param stdClass $round Round record.
     * @param context_module $context Module context, used to format option texts.
     * @return array Keys roundid, open, voters and options (id, text as plain text, votes, percent).
     */
    public static function for_round(stdClass $qrvote, stdClass $round, context_module $context): array {
        global $DB;

        $sql = 'SELECT o.id, o.text, o.sortorder, COUNT(v.id) AS votes
                  FROM {qrvote_options} o
             LEFT JOIN {qrvote_votes} v ON v.optionid = o.id AND v.roundid = :roundid
                 WHERE o.qrvoteid = :qrvoteid
              GROUP BY o.id, o.text, o.sortorder
              ORDER BY o.sortorder ASC, o.id ASC';
        $rows = $DB->get_records_sql($sql, ['roundid' => $round->id, 'qrvoteid' => $qrvote->id]);
        $voters = $DB->count_records_sql(
            'SELECT COUNT(DISTINCT voterhash) FROM {qrvote_votes} WHERE roundid = ?',
            [$round->id]
        );

        $options = [];
        foreach ($rows as $row) {
            $votes = (int) $row->votes;
            $options[] = [
                'id' => (int) $row->id,
                // Not HTML-escaped here: templates and web service clients escape the text themselves.
                'text' => format_string($row->text, true, ['context' => $context, 'escape' => false]),
                'votes' => $votes,
                'percent' => $voters > 0 ? (int) round(100 * $votes / $voters) : 0,
            ];
        }

        return [
            'roundid' => (int) $round->id,
            'open' => round_manager::accepts_votes($round),
            'voters' => (int) $voters,
            'options' => $options,
        ];
    }
}
