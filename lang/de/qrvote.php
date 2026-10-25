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
 * German language strings for mod_qrvote.
 *
 * @package    mod_qrvote
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addoptions'] = '3 weitere Antwortfelder hinzufügen';
$string['addstation'] = 'Station anlegen';
$string['allowpublic'] = 'Abstimmen ohne Anmeldung erlauben';
$string['allowpublic_desc'] = 'Wenn aktiviert, können Lehrkräfte die Abstimmung per QR-Code ohne Anmeldung zulassen. Den Zugang gewährt ein zufälliger Link je Runde, der beim Beenden der Runde ungültig wird. Wenn deaktiviert, erfordert jede Abstimmung Anmeldung und Kurseinschreibung, unabhängig von der Einstellung in der Aktivität.';
$string['cachedef_ratelimit'] = 'Abstimmungen je Netzwerkadresse und Minute';
$string['cannotvote'] = 'Sie können in dieser Aktivität nicht abstimmen.';
$string['deleteround'] = 'Runde löschen';
$string['deleteroundconfirm'] = 'Möchten Sie die am {$a} gestartete Runde mit allen Stimmen wirklich löschen? Das kann nicht rückgängig gemacht werden.';
$string['editstation'] = 'Station bearbeiten';
$string['error:alreadyvoted'] = 'Sie haben in dieser Runde bereits abgestimmt.';
$string['error:invalidoptions'] = 'Die gewählten Antworten sind für diese Abstimmung nicht gültig.';
$string['error:invalidtoken'] = 'Dieser Abstimmungslink ist ungültig. Bitte scannen Sie den aktuellen QR-Code.';
$string['error:invalidvoter'] = 'Ihre Stimme konnte nicht zugeordnet werden. Bitte laden Sie die Seite neu und versuchen Sie es erneut.';
$string['error:invalidwindow'] = 'Das Ende muss nach dem Beginn liegen.';
$string['error:noselection'] = 'Bitte wählen Sie eine Antwort aus.';
$string['error:nostations'] = 'Es gibt keine Stationen zum Drucken.';
$string['error:notastation'] = 'Diese Runde ist keine Station.';
$string['error:ratelimited'] = 'Gerade kommen zu viele Stimmen aus diesem Netzwerk. Bitte warten Sie eine Minute und versuchen Sie es erneut.';
$string['error:roundclosed'] = 'Diese Abstimmungsrunde ist beendet.';
$string['error:roundnotopen'] = 'Die Abstimmung für diese Station hat noch nicht begonnen. Bitte kommen Sie im Abstimmungszeitraum wieder.';
$string['error:toofewoptions'] = 'Bitte geben Sie mindestens {$a} Antwortmöglichkeiten ein.';
$string['error:unknownaction'] = 'Unbekannte Aktion.';
$string['eventroundclosed'] = 'Abstimmungsrunde beendet';
$string['eventrounddeleted'] = 'Abstimmungsrunde gelöscht';
$string['eventroundscheduled'] = 'Station angelegt';
$string['eventroundstarted'] = 'Abstimmungsrunde gestartet';
$string['eventroundupdated'] = 'Station geändert';
$string['eventvotesubmitted'] = 'Stimme abgegeben';
$string['fullscreen'] = 'Vollbild';
$string['modelogin'] = 'Abstimmen erfordert eine Anmeldung.';
$string['modemultiple'] = 'Mehrere Antworten sind möglich.';
$string['modepublic'] = 'Abstimmen ist ohne Anmeldung möglich.';
$string['modulename'] = 'QR-Abstimmung';
$string['modulename_help'] = 'Eine QR-Abstimmung stellt eine Frage mit festen Antworten. Die Lehrkraft startet eine Runde und zeigt den QR-Code, zum Beispiel am Beamer. Die Teilnehmenden scannen den Code mit dem Smartphone und stimmen ab, ohne Anmeldung, wenn die Aktivität das erlaubt. Das Ergebnis erscheint live als Balken.

Jede Runde hat einen eigenen Link. Beendet die Lehrkraft die Runde, wird der Link ungültig, sodass ein alter QR-Code nicht erneut verwendet werden kann.

Für Tagungen oder Projekttage lassen sich Stationen anlegen: Runden mit Titel, Raum und festem Zeitfenster. Ihre QR-Codes können vorab ausgedruckt und am Raum ausgehängt werden; Stimmen werden nur im Zeitfenster angenommen.';
$string['modulenameplural'] = 'QR-Abstimmungen';
$string['multiple'] = 'Mehrere Antworten erlauben';
$string['multiple_help'] = 'Bei "Ja" können Abstimmende mehr als eine Antwort wählen. Der Prozentwert einer Antwort ist dann der Anteil der Abstimmenden, die sie gewählt haben; die Summe kann daher über 100 liegen.';
$string['noopenround'] = 'Zurzeit ist keine Abstimmungsrunde geöffnet.';
$string['noroundsyet'] = 'Es wurde noch keine Runde gestartet. Starten Sie eine Runde, um den QR-Code anzuzeigen.';
$string['option'] = 'Antwortmöglichkeit';
$string['option_help'] = 'Geben Sie je Feld eine Antwort ein. Leere Felder werden ignoriert. Es sind mindestens zwei Antworten nötig. Sobald Stimmen vorliegen, lassen sich die Antworten nicht mehr ändern, damit die Ergebnisse stimmen.';
$string['optionno'] = 'Antwort {no}';
$string['options'] = 'Antwortmöglichkeiten';
$string['optionslocked'] = 'Es wurden bereits Stimmen abgegeben. Antworten und Antwortmodus können nicht mehr geändert werden. Löschen Sie alle Runden, um sie wieder zu bearbeiten.';
$string['pluginadministration'] = 'QR-Abstimmung verwalten';
$string['pluginname'] = 'QR-Abstimmung';
$string['print'] = 'Drucken';
$string['printall'] = 'Alle Stationen drucken (PDF)';
$string['printstation'] = 'Aushang drucken (PDF)';
$string['privacy:metadata:qrvote_rounds'] = 'Abstimmungsrunden einer QR-Abstimmung.';
$string['privacy:metadata:qrvote_rounds:timestarted'] = 'Der Zeitpunkt, zu dem die Runde gestartet wurde.';
$string['privacy:metadata:qrvote_rounds:usermodified'] = 'Die Person, die die Runde gestartet hat.';
$string['privacy:metadata:qrvote_votes'] = 'Stimmen einer Runde. Stimmen ohne Anmeldung enthalten keine Nutzer-ID, sondern nur einen verschlüsselten Hash eines zufälligen Geräteschlüssels.';
$string['privacy:metadata:qrvote_votes:optionid'] = 'Die gewählte Antwort.';
$string['privacy:metadata:qrvote_votes:timecreated'] = 'Der Zeitpunkt der Stimmabgabe.';
$string['privacy:metadata:qrvote_votes:userid'] = 'Die abstimmende Person, nur gespeichert, wenn die Abstimmung eine Anmeldung erfordert.';
$string['privacy:roundsstarted'] = 'Gestartete Runden';
$string['privacy:votes'] = 'Stimmen';
$string['publicdisabled'] = 'Von der Administration deaktiviert. Abstimmen erfordert eine Anmeldung.';
$string['publicvote'] = 'Abstimmen ohne Anmeldung';
$string['publicvote_help'] = 'Bei "Ja" kann jede Person, die den QR-Code einer offenen Runde scannt, ohne Anmeldung oder Registrierung abstimmen. Jeder Browser wird je Runde einmal gezählt. Das eignet sich für schnelle Umfragen; eine doppelte Stimmabgabe mit einem zweiten Gerät oder einem privaten Fenster lässt sich damit nicht vollständig verhindern.

Bei "Nein" müssen sich Abstimmende anmelden und im Kurs eingeschrieben sein.';
$string['qrcode'] = 'QR-Code';
$string['qrcode_help'] = 'Die Teilnehmenden scannen diesen Code mit der Kamera ihres Smartphones, um die Abstimmung zu öffnen. Der Link unter dem Code führt zur selben Seite. Der Code gilt nur, solange die Runde geöffnet ist, bei einer Station nur in ihrem Zeitfenster.';
$string['qrcodealt'] = 'QR-Code zur Abstimmung';
$string['qrvote:addinstance'] = 'Neue QR-Abstimmung hinzufügen';
$string['qrvote:manage'] = 'Abstimmungsrunden starten, beenden und löschen';
$string['qrvote:view'] = 'QR-Abstimmung ansehen';
$string['qrvote:viewresults'] = 'Ergebnisse von QR-Abstimmungen ansehen';
$string['qrvote:vote'] = 'In QR-Abstimmungen mit Anmeldung abstimmen';
$string['question'] = 'Frage';
$string['question_help'] = 'Die Frage, die Abstimmende über den Antworten sehen, zum Beispiel auf dem Smartphone.';
$string['ratelimit'] = 'Stimmen je Netzwerkadresse und Minute';
$string['ratelimit_desc'] = 'Höchstzahl an Stimmen, die je Minute von einer Netzwerkadresse angenommen werden. Eine Schulklasse teilt sich meist eine Adresse, daher sollte der Wert deutlich über der Klassengröße liegen. 0 schaltet die Begrenzung ab.';
$string['resetrounds'] = 'Alle Abstimmungsrunden und Stimmen löschen';
$string['round'] = 'Runde';
$string['roundclosed'] = 'Beendet';
$string['rounddeleted'] = 'Die Runde wurde gelöscht.';
$string['roundhistory'] = 'Runden';
$string['roundlifetime'] = 'Maximale Rundendauer';
$string['roundlifetime_desc'] = 'Eine offene Runde nimmt nach dieser Zeit keine Stimmen mehr an, auch wenn die Lehrkraft vergisst, sie zu beenden. So lassen sich alte QR-Codes später nicht mehr verwenden.';
$string['roundopen'] = 'Geöffnet';
$string['roundscheduled'] = 'Geplant';
$string['roundstart'] = 'Gestartet';
$string['roundstarted'] = 'Runde gestartet am {$a}';
$string['savestation'] = 'Station speichern';
$string['scantovote'] = 'Zum Abstimmen scannen';
$string['showresults'] = 'Ergebnis den Abstimmenden zeigen';
$string['showresults_help'] = 'Bei "Ja" sehen Abstimmende nach ihrer Stimmabgabe das aktuelle Ergebnis der Runde.';
$string['startround'] = 'Neue Runde starten';
$string['stationintro'] = 'Eine Station ist eine Abstimmungsrunde mit festem Zeitfenster, zum Beispiel ein Vortrag in einem Raum. Ihr QR-Code steht sofort fest und lässt sich vorab ausdrucken. Stimmen werden nur zwischen Beginn und Ende angenommen. Alle Stationen dieser Aktivität verwenden dieselbe Frage und dieselben Antworten; jede Station hat ihr eigenes Ergebnis.';
$string['stationlocation'] = 'Ort';
$string['stationlocation_help'] = 'Wo die Station stattfindet, zum Beispiel der Raum. Steht auf dem Aushang.';
$string['stationsaved'] = 'Die Station wurde gespeichert. Ihr QR-Code bleibt gleich, wenn Sie die Angaben später ändern. Speichern öffnet eine vorzeitig beendete Station wieder.';
$string['stationtimeclose'] = 'Abstimmung endet';
$string['stationtimeclose_help'] = 'Ab diesem Zeitpunkt nimmt der QR-Code keine Stimmen mehr an. Ein Ende ist Pflicht, weil ein Aushang von allen gescannt werden kann, die vorbeikommen.';
$string['stationtimeopen'] = 'Abstimmung beginnt';
$string['stationtimeopen_help'] = 'Vor diesem Zeitpunkt zeigt der QR-Code einen Hinweis, dass die Abstimmung noch nicht begonnen hat.';
$string['stationtitle'] = 'Titel';
$string['stationtitle_help'] = 'Titel des Vortrags oder Workshops. Steht auf dem Aushang und auf der Abstimmungsseite.';
$string['status'] = 'Status';
$string['stopround'] = 'Runde beenden';
$string['submitvote'] = 'Abstimmen';
$string['votenow'] = 'Jetzt abstimmen';
$string['voters'] = 'Abstimmende';
$string['votersnum'] = '{$a} Abstimmende';
$string['votesaved'] = 'Vielen Dank, Ihre Stimme wurde gespeichert.';
$string['votesettings'] = 'Abstimmung';
$string['votesnum'] = '{$a->votes} ({$a->percent} %)';
$string['windowdays'] = '{$a->open} bis {$a->close}';
$string['windowsameday'] = '{$a->date}, {$a->open} bis {$a->close}';
