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
 * Refreshes the result of an open voting round on the teacher page.
 *
 * @module     mod_qrvote/live
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';

/** @type {number} Refresh interval in milliseconds. */
const INTERVAL = 3000;

/**
 * Starts refreshing the result of a round until the round closes.
 *
 * @param {number} cmid Course module id.
 * @param {number} roundid Round id.
 */
export const init = (cmid, roundid) => {
    const region = document.querySelector('[data-region="mod_qrvote-results"][data-roundid="' + roundid + '"]');
    if (!region) {
        return;
    }

    let timer = null;
    let busy = false;

    const stop = () => {
        window.clearInterval(timer);
    };

    const refresh = () => {
        if (busy || document.hidden) {
            return;
        }
        busy = true;
        Ajax.call([{methodname: 'mod_qrvote_get_results', args: {cmid: cmid, roundid: roundid}}])[0]
            .then((result) => {
                if (!result.open) {
                    stop();
                }
                return Templates.renderForPromise('mod_qrvote/results', result);
            })
            .then(({html, js}) => {
                Templates.replaceNodeContents(region, html, js);
                busy = false;
                return true;
            })
            .catch((error) => {
                stop();
                Notification.exception(error);
            });
    };

    timer = window.setInterval(refresh, INTERVAL);
};
