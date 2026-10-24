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

/**
 * English language strings for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addoptions'] = 'Add 3 more option fields';
$string['allowpublic'] = 'Allow voting without login';
$string['allowpublic_desc'] = 'If enabled, teachers can let people vote by QR code without logging in. Access is granted by a random link per round that stops working when the round is closed. If disabled, every vote requires login and enrolment, whatever the activity setting says.';
$string['cachedef_ratelimit'] = 'Vote submissions per network address and minute';
$string['cannotvote'] = 'You cannot vote in this activity.';
$string['deleteround'] = 'Delete round';
$string['deleteroundconfirm'] = 'Do you really want to delete the round started on {$a} with all its votes? This cannot be undone.';
$string['error:alreadyvoted'] = 'You have already voted in this round.';
$string['error:invalidoptions'] = 'The selected answers are not valid for this vote.';
$string['error:invalidtoken'] = 'This vote link is not valid. Please scan the current QR code.';
$string['error:invalidvoter'] = 'Your vote could not be assigned. Please reload the page and try again.';
$string['error:noselection'] = 'Please select an answer.';
$string['error:ratelimited'] = 'Too many votes from this network right now. Please wait a minute and try again.';
$string['error:roundclosed'] = 'This voting round is closed.';
$string['error:toofewoptions'] = 'Please enter at least {$a} answer options.';
$string['error:unknownaction'] = 'Unknown action.';
$string['eventroundclosed'] = 'Voting round closed';
$string['eventrounddeleted'] = 'Voting round deleted';
$string['eventroundstarted'] = 'Voting round started';
$string['eventvotesubmitted'] = 'Vote submitted';
$string['fullscreen'] = 'Full screen';
$string['modelogin'] = 'Voting requires login.';
$string['modemultiple'] = 'Several answers can be selected.';
$string['modepublic'] = 'Voting works without login.';
$string['modulename'] = 'QR vote';
$string['modulename_help'] = 'A QR vote asks one question with fixed answers. The teacher starts a round and shows the QR code, for example on the projector. Participants scan the code with their smartphone and vote, without login if the activity allows it. The result is shown live as bars.

Each round has its own link. When the teacher closes the round, the link stops working, so an old QR code cannot be used again.';
$string['modulenameplural'] = 'QR votes';
$string['multiple'] = 'Allow several answers';
$string['multiple_help'] = 'If set to yes, voters can select more than one answer. The percentage of an answer is then the share of voters who selected it, so the percentages can add up to more than 100.';
$string['noopenround'] = 'No voting round is open at the moment.';
$string['noroundsyet'] = 'No round has been started yet. Start a round to show the QR code.';
$string['option'] = 'Answer option';
$string['option_help'] = 'Enter one answer per field. Empty fields are ignored. At least two answers are needed. Once votes exist, the answers can no longer be changed so that results stay correct.';
$string['optionno'] = 'Answer {no}';
$string['options'] = 'Answer options';
$string['optionslocked'] = 'Votes have already been cast. The answers and the answer mode can no longer be changed. Delete all rounds to edit them again.';
$string['pluginadministration'] = 'QR vote administration';
$string['pluginname'] = 'QR vote';
$string['privacy:metadata:qrvote_rounds'] = 'Voting rounds of a QR vote.';
$string['privacy:metadata:qrvote_rounds:timestarted'] = 'The time the round was started.';
$string['privacy:metadata:qrvote_rounds:usermodified'] = 'The user who started the round.';
$string['privacy:metadata:qrvote_votes'] = 'Votes of a round. Votes without login contain no user id and only a keyed hash of a random device key.';
$string['privacy:metadata:qrvote_votes:optionid'] = 'The answer the user selected.';
$string['privacy:metadata:qrvote_votes:timecreated'] = 'The time of the vote.';
$string['privacy:metadata:qrvote_votes:userid'] = 'The user who voted, stored only when the vote requires login.';
$string['privacy:roundsstarted'] = 'Rounds started';
$string['privacy:votes'] = 'Votes';
$string['publicdisabled'] = 'Disabled by the site administrator. Voting requires login.';
$string['publicvote'] = 'Vote without login';
$string['publicvote_help'] = 'If set to yes, anyone who scans the QR code of an open round can vote without logging in or registering. Each browser is counted once per round. This is suited for quick polls; it cannot fully prevent someone from voting twice with a second device or a private window.

If set to no, voters must log in and be enrolled in the course.';
$string['qrcode'] = 'QR code';
$string['qrcode_help'] = 'Participants scan this code with the camera of their smartphone to open the vote. The link below the code leads to the same page. The code is only valid while the round is open.';
$string['qrcodealt'] = 'QR code for the vote';
$string['qrvote:addinstance'] = 'Add a new QR vote';
$string['qrvote:manage'] = 'Start, close and delete voting rounds';
$string['qrvote:view'] = 'View QR vote';
$string['qrvote:viewresults'] = 'View results of QR votes';
$string['qrvote:vote'] = 'Vote in QR votes that require login';
$string['question'] = 'Question';
$string['question_help'] = 'The question voters see above the answers, for example on their smartphone.';
$string['ratelimit'] = 'Votes per network address and minute';
$string['ratelimit_desc'] = 'Maximum number of votes accepted from one network address per minute. A school class usually shares one address, so the value should be well above the size of a class. 0 switches the limit off.';
$string['resetrounds'] = 'Delete all voting rounds and votes';
$string['roundclosed'] = 'Closed';
$string['rounddeleted'] = 'The round has been deleted.';
$string['roundhistory'] = 'Rounds';
$string['roundlifetime'] = 'Maximum round duration';
$string['roundlifetime_desc'] = 'An open round stops accepting votes after this time, even if the teacher forgets to close it. This keeps old QR codes from being used later.';
$string['roundopen'] = 'Open';
$string['roundstart'] = 'Started';
$string['roundstarted'] = 'Round started on {$a}';
$string['scantovote'] = 'Scan to vote';
$string['showresults'] = 'Show result to voters';
$string['showresults_help'] = 'If set to yes, voters see the current result of the round after they have voted.';
$string['startround'] = 'Start new round';
$string['status'] = 'Status';
$string['stopround'] = 'Close round';
$string['submitvote'] = 'Vote';
$string['votenow'] = 'Vote now';
$string['voters'] = 'Voters';
$string['votersnum'] = '{$a} voters';
$string['votesaved'] = 'Thank you, your vote has been saved.';
$string['votesettings'] = 'Vote';
$string['votesnum'] = '{$a->votes} ({$a->percent} %)';
