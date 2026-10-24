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

/**
 * Shows the QR code and the result in full screen for the projector.
 *
 * @module     mod_qrvote/fullscreen
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';

/**
 * Toggles full screen for the round stage of the clicked button.
 */
export const init = () => {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="mod_qrvote-fullscreen"]');
        if (!button) {
            return;
        }
        const stage = button.closest('[data-region="mod_qrvote-stage"]');
        if (!stage || !stage.requestFullscreen) {
            return;
        }
        const request = document.fullscreenElement ? document.exitFullscreen() : stage.requestFullscreen();
        request.catch(Notification.exception);
    });
};
