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

use stdClass;

/**
 * Stores and removes activity instances together with their options.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class poll {
    /** @var int Maximum length of an option text, matches the database column. */
    public const OPTION_MAXLENGTH = 255;

    /** @var int Minimum number of options a vote needs. */
    public const OPTIONS_MIN = 2;

    /**
     * Creates an instance and its options from the activity form data.
     *
     * @param stdClass $data Form data with name, intro, question, flags and option texts.
     * @return int New instance id.
     */
    public static function create_instance(stdClass $data): int {
        global $DB;

        $now = time();
        $record = self::instance_record($data);
        $record->timecreated = $now;
        $record->timemodified = $now;

        $transaction = $DB->start_delegated_transaction();
        $id = (int) $DB->insert_record('qrvote', $record);
        self::save_options($id, self::clean_option_texts($data->option ?? []));
        $transaction->allow_commit();
        return $id;
    }

    /**
     * Updates an instance. Options change only while no round has votes, so results stay consistent.
     *
     * @param stdClass $data Form data including the instance id.
     * @return void
     */
    public static function update_instance(stdClass $data): void {
        global $DB;

        $record = self::instance_record($data);
        $record->id = (int) $data->instance;
        $record->timemodified = time();

        $transaction = $DB->start_delegated_transaction();
        if (self::has_votes($record->id)) {
            // The answer mode stays as voted while results exist.
            unset($record->multiple);
        } else {
            self::save_options($record->id, self::clean_option_texts($data->option ?? []));
        }
        $DB->update_record('qrvote', $record);
        $transaction->allow_commit();
    }

    /**
     * Deletes an instance with options, rounds and votes.
     *
     * @param int $id Instance id.
     * @return bool False when the instance does not exist.
     */
    public static function delete_instance(int $id): bool {
        global $DB;

        if (!$DB->record_exists('qrvote', ['id' => $id])) {
            return false;
        }
        $transaction = $DB->start_delegated_transaction();
        self::delete_rounds($id);
        $DB->delete_records('qrvote_options', ['qrvoteid' => $id]);
        $DB->delete_records('qrvote', ['id' => $id]);
        $transaction->allow_commit();
        return true;
    }

    /**
     * Deletes all rounds and votes of an instance.
     *
     * @param int $qrvoteid Instance id.
     * @return void
     */
    public static function delete_rounds(int $qrvoteid): void {
        global $DB;

        $DB->delete_records_select(
            'qrvote_votes',
            'roundid IN (SELECT id FROM {qrvote_rounds} WHERE qrvoteid = ?)',
            [$qrvoteid]
        );
        $DB->delete_records('qrvote_rounds', ['qrvoteid' => $qrvoteid]);
    }

    /**
     * Returns the options of an instance in display order.
     *
     * @param int $qrvoteid Instance id.
     * @return stdClass[] Option records keyed by id.
     */
    public static function get_options(int $qrvoteid): array {
        global $DB;

        return $DB->get_records('qrvote_options', ['qrvoteid' => $qrvoteid], 'sortorder ASC, id ASC');
    }

    /**
     * Tells whether any round of the instance has received votes.
     *
     * @param int $qrvoteid Instance id.
     * @return bool True when at least one vote exists.
     */
    public static function has_votes(int $qrvoteid): bool {
        global $DB;

        $sql = 'SELECT 1
                  FROM {qrvote_votes} v
                  JOIN {qrvote_rounds} r ON r.id = v.roundid
                 WHERE r.qrvoteid = ?';
        return $DB->record_exists_sql($sql, [$qrvoteid]);
    }

    /**
     * Tells whether voting without login is allowed for an instance.
     *
     * The site setting overrides the activity setting so administrators can switch public voting off everywhere.
     *
     * @param stdClass $qrvote Instance record.
     * @return bool True when the vote page works without login.
     */
    public static function is_public(stdClass $qrvote): bool {
        return !empty($qrvote->publicvote) && (bool) get_config('mod_qrvote', 'allowpublic');
    }

    /**
     * Trims option texts and drops empty ones, keeping the order.
     *
     * @param array $texts Raw option texts from the form.
     * @return string[] Cleaned option texts.
     */
    public static function clean_option_texts(array $texts): array {
        $clean = [];
        foreach ($texts as $text) {
            $text = trim(clean_param((string) $text, PARAM_TEXT));
            if ($text !== '') {
                $clean[] = \core_text::substr($text, 0, self::OPTION_MAXLENGTH);
            }
        }
        return $clean;
    }

    /**
     * Builds the instance record from form data.
     *
     * @param stdClass $data Form data.
     * @return stdClass Record without id and timestamps.
     */
    private static function instance_record(stdClass $data): stdClass {
        return (object) [
            'course' => (int) $data->course,
            'name' => $data->name,
            'intro' => $data->intro ?? '',
            'introformat' => (int) ($data->introformat ?? FORMAT_HTML),
            'question' => trim($data->question),
            'multiple' => empty($data->multiple) ? 0 : 1,
            'publicvote' => empty($data->publicvote) ? 0 : 1,
            'showresults' => empty($data->showresults) ? 0 : 1,
        ];
    }

    /**
     * Replaces the options of an instance.
     *
     * @param int $qrvoteid Instance id.
     * @param string[] $texts Cleaned option texts in display order.
     * @return void
     */
    private static function save_options(int $qrvoteid, array $texts): void {
        global $DB;

        if (count($texts) < self::OPTIONS_MIN) {
            throw new \moodle_exception('error:toofewoptions', 'qrvote', '', self::OPTIONS_MIN);
        }
        $DB->delete_records('qrvote_options', ['qrvoteid' => $qrvoteid]);
        foreach ($texts as $index => $text) {
            $DB->insert_record('qrvote_options', (object) [
                'qrvoteid' => $qrvoteid,
                'text' => $text,
                'sortorder' => $index + 1,
            ]);
        }
    }
}
