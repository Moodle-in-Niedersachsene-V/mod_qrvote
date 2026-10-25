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

namespace mod_qrvote\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_qrvote\local\results;
use mod_qrvote\local\round_manager;

/**
 * Returns the live result of a voting round to teachers.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_results extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters Parameter definition.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'roundid' => new external_value(PARAM_INT, 'Round id'),
        ]);
    }

    /**
     * Returns the result of a round.
     *
     * @param int $cmid Course module id.
     * @param int $roundid Round id.
     * @return array Result as returned by results::for_round().
     */
    public static function execute(int $cmid, int $roundid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'roundid' => $roundid,
        ]);

        $cm = get_coursemodule_from_id('qrvote', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/qrvote:viewresults', $context);

        $qrvote = $DB->get_record('qrvote', ['id' => $cm->instance], '*', MUST_EXIST);
        $round = round_manager::get((int) $qrvote->id, $params['roundid']);
        return results::for_round($qrvote, $round, $context);
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure Return definition.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'roundid' => new external_value(PARAM_INT, 'Round id'),
            'open' => new external_value(PARAM_BOOL, 'Whether the round accepts votes'),
            'scheduled' => new external_value(PARAM_BOOL, 'Whether the voting window of a scheduled round has not started yet'),
            'voters' => new external_value(PARAM_INT, 'Number of voters'),
            'israting' => new external_value(PARAM_BOOL, 'Whether voters give 1 to 5 stars'),
            'hasaverage' => new external_value(PARAM_BOOL, 'Whether an average rating exists'),
            'average' => new external_value(PARAM_TEXT, 'Average rating, formatted with one decimal; empty without votes'),
            'averagestars' => new external_value(PARAM_TEXT, 'Average rating as star glyphs; empty without votes'),
            'iswordcloud' => new external_value(PARAM_BOOL, 'Whether voters submit terms for a word cloud'),
            'haswords' => new external_value(PARAM_BOOL, 'Whether visible terms exist'),
            'entries' => new external_value(PARAM_INT, 'Number of visible submitted terms'),
            'words' => new external_multiple_structure(
                new external_single_structure([
                    'text' => new external_value(PARAM_TEXT, 'Term as plain text'),
                    'count' => new external_value(PARAM_INT, 'How often the term was submitted'),
                    'size' => new external_value(PARAM_INT, 'Font size step from 1 to 5'),
                ])
            ),
            'options' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Option id'),
                    'text' => new external_value(PARAM_TEXT, 'Option text as plain text, filtered with format_string()'),
                    'votes' => new external_value(PARAM_INT, 'Number of votes'),
                    'percent' => new external_value(PARAM_INT, 'Share of voters who chose the option'),
                    'stars' => new external_value(PARAM_TEXT, 'Star glyphs of a rating option; empty for text options'),
                ])
            ),
        ]);
    }
}
