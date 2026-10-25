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

namespace mod_qrvote\output;

use cm_info;
use context_module;
use core\output\help_icon;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use mod_qrvote\local\poll;
use mod_qrvote\local\results;
use mod_qrvote\local\round_manager;
use mod_qrvote\local\word_service;
use moodle_url;
use stdClass;

/**
 * Teacher page: QR code of the selected round, live result, round controls and round history.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_page implements renderable, templatable {
    /**
     * Creates the page.
     *
     * @param cm_info $cm Course module.
     * @param stdClass $qrvote Instance record.
     * @param context_module $context Module context.
     * @param stdClass|null $round Round to show, null when no round exists yet.
     * @param bool $canmanage Whether the user may start, stop and delete rounds.
     */
    public function __construct(
        /** @var cm_info Course module. */
        private cm_info $cm,
        /** @var stdClass Instance record. */
        private stdClass $qrvote,
        /** @var context_module Module context. */
        private context_module $context,
        /** @var stdClass|null Round to show. */
        private ?stdClass $round,
        /** @var bool Whether the user may manage rounds. */
        private bool $canmanage
    ) {
    }

    /**
     * Exports the data for the template mod_qrvote/manage.
     *
     * @param renderer_base $output Renderer.
     * @return array Template context.
     */
    public function export_for_template(renderer_base $output): array {
        $viewurl = new moodle_url('/mod/qrvote/view.php', ['id' => $this->cm->id]);
        $stations = round_manager::get_scheduled((int) $this->qrvote->id);
        $data = [
            'cmid' => $this->cm->id,
            'question' => format_text($this->qrvote->question, FORMAT_PLAIN, ['context' => $this->context]),
            'canmanage' => $this->canmanage,
            'actionurl' => $viewurl->out(false),
            'sesskey' => sesskey(),
            'ispublic' => poll::is_public($this->qrvote),
            'multiple' => !empty($this->qrvote->multiple),
            'addstationurl' => (new moodle_url('/mod/qrvote/schedule.php', ['id' => $this->cm->id]))->out(false),
            'hasstations' => !empty($stations),
            'printallurl' => (new moodle_url('/mod/qrvote/print.php', ['id' => $this->cm->id]))->out(false),
            'hasround' => false,
            'rounds' => [],
            'hasrounds' => false,
            'qrhelp' => (new help_icon('qrcode', 'qrvote'))->export_for_template($output),
        ];

        if ($this->round) {
            $results = results::for_round($this->qrvote, $this->round, $this->context);
            $voteurl = new moodle_url('/mod/qrvote/vote.php', ['t' => $this->round->token]);
            $state = round_manager::state($this->round);
            $data['hasround'] = true;
            $data['round'] = $this->describe($this->round, $viewurl) + [
                'voteurl' => $voteurl->out(false),
                // A station keeps its QR code until it closes, because the code is printed in advance.
                'showqr' => $state !== round_manager::STATE_CLOSED,
                'qrcode' => $state !== round_manager::STATE_CLOSED ? self::qr_data_uri($voteurl->out(false)) : '',
                'results' => $results,
                'moderation' => [],
                'hasmoderation' => false,
            ];
            if (poll::is_wordcloud($this->qrvote) && $this->canmanage) {
                foreach (word_service::moderation_list($this->round) as $word) {
                    $data['round']['moderation'][] = $word;
                }
                $data['round']['hasmoderation'] = !empty($data['round']['moderation']);
            }
        }

        foreach (round_manager::list_rounds((int) $this->qrvote->id) as $round) {
            $data['rounds'][] = $this->describe($round, $viewurl) + [
                'voters' => (int) $round->voters,
                'selected' => $this->round && (int) $this->round->id === (int) $round->id,
            ];
        }
        $data['hasrounds'] = !empty($data['rounds']);
        return $data;
    }

    /**
     * Describes a round for the template: label, window, state and links.
     *
     * @param stdClass $round Round record.
     * @param moodle_url $viewurl URL of the activity page.
     * @return array Template data.
     */
    private function describe(stdClass $round, moodle_url $viewurl): array {
        $state = round_manager::state($round);
        $scheduled = round_manager::is_scheduled($round);
        // Title and location as plain text; the template escapes them.
        $plain = ['context' => $this->context, 'escape' => false];
        $data = [
            'id' => (int) $round->id,
            'started' => userdate($round->timestarted),
            'isstation' => $scheduled,
            'title' => $scheduled ? format_string($round->title, true, $plain) : '',
            'location' => $scheduled ? format_string($round->location, true, $plain) : '',
            'window' => '',
            'open' => $state === round_manager::STATE_OPEN,
            'scheduled' => $state === round_manager::STATE_SCHEDULED,
            'url' => (new moodle_url($viewurl, ['roundid' => $round->id]))->out(false),
            'editurl' => '',
            'printurl' => '',
        ];
        if ($scheduled) {
            $data['window'] = round_manager::format_window((int) $round->timeopen, (int) $round->timeclose);
            $data['editurl'] = (new moodle_url('/mod/qrvote/schedule.php', [
                'id' => $this->cm->id,
                'roundid' => $round->id,
            ]))->out(false);
            $data['printurl'] = (new moodle_url('/mod/qrvote/print.php', [
                'id' => $this->cm->id,
                'roundid' => $round->id,
            ]))->out(false);
        }
        return $data;
    }

    /**
     * Renders a QR code for a URL as an SVG data URI, generated locally without external services.
     *
     * @param string $url URL to encode.
     * @return string Data URI.
     */
    public static function qr_data_uri(string $url): string {
        $qrcode = new \core_qrcode($url);
        $svg = $qrcode->getBarcodeSVGcode(8, 8, 'black');
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
