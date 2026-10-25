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
use moodle_url;
use stdClass;

/**
 * Builds the PDF with one A4 page per station to hang at the room.
 *
 * The QR code is drawn by Moodle's PDF library on the server; no external service is used.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class printout {
    /** @var float Page width of A4 in millimetres. */
    private const PAGE_WIDTH = 210.0;

    /** @var float Side margin in millimetres. */
    private const MARGIN = 20.0;

    /** @var float Edge length of the QR code in millimetres. */
    private const QR_SIZE = 120.0;

    /** @var string Font that covers German umlauts. */
    private const FONT = 'freesans';

    /**
     * Returns the PDF for the given stations.
     *
     * @param stdClass $qrvote Instance record.
     * @param stdClass[] $rounds Scheduled rounds, one page each.
     * @param context_module $context Module context.
     * @return string PDF content.
     * @throws \moodle_exception When no station is given.
     */
    public static function build(stdClass $qrvote, array $rounds, context_module $context): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $rounds = array_filter($rounds, [round_manager::class, 'is_scheduled']);
        if (!$rounds) {
            throw new \moodle_exception('error:nostations', 'qrvote');
        }

        $plain = ['context' => $context, 'escape' => false];
        $name = format_string($qrvote->name, true, $plain);
        $question = format_string($qrvote->question, true, $plain);
        $width = self::PAGE_WIDTH - 2 * self::MARGIN;

        $pdf = new \pdf('P', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetTitle($name);
        $pdf->SetCreator('Moodle mod_qrvote');

        foreach ($rounds as $round) {
            $url = (new moodle_url('/mod/qrvote/vote.php', ['t' => $round->token]))->out(false);
            $pdf->AddPage();

            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetFont(self::FONT, '', 12);
            $pdf->MultiCell($width, 0, $name, 0, 'C');
            $pdf->Ln(4);

            $pdf->SetTextColor(0, 0, 0);
            $title = format_string($round->title, true, $plain);
            $pdf->SetFont(self::FONT, 'B', \core_text::strlen($title) > 80 ? 18 : 26);
            $pdf->MultiCell($width, 0, $title, 0, 'C');
            $pdf->Ln(2);

            $pdf->SetFont(self::FONT, '', 15);
            $details = [];
            if (trim($round->location) !== '') {
                $details[] = format_string($round->location, true, $plain);
            }
            $details[] = round_manager::format_window((int) $round->timeopen, (int) $round->timeclose);
            $pdf->MultiCell($width, 0, implode("\n", $details), 0, 'C');
            $pdf->Ln(8);

            $pdf->SetFont(self::FONT, 'B', 18);
            $pdf->MultiCell($width, 0, get_string('scantovote', 'qrvote'), 0, 'C');
            $pdf->Ln(4);

            $top = $pdf->GetY();
            $pdf->write2DBarcode(
                $url,
                'QRCODE,M',
                (self::PAGE_WIDTH - self::QR_SIZE) / 2,
                $top,
                self::QR_SIZE,
                self::QR_SIZE,
                ['border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false]
            );
            $pdf->SetY($top + self::QR_SIZE + 6);

            $pdf->SetFont(self::FONT, '', 14);
            $pdf->MultiCell($width, 0, $question, 0, 'C');
            $pdf->Ln(4);

            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetFont(self::FONT, '', 9);
            $pdf->MultiCell($width, 0, $url, 0, 'C');
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Returns the file name for the PDF.
     *
     * @param stdClass $qrvote Instance record.
     * @return string File name.
     */
    public static function filename(stdClass $qrvote): string {
        return clean_filename(format_string($qrvote->name, true, ['escape' => false]) . '.pdf');
    }
}
