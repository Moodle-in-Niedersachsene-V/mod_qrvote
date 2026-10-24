# QR vote (mod_qrvote)

An activity for quick classroom polls. The teacher starts a voting round and shows a QR code,
for example on the projector. Participants scan it with their smartphone and vote, without
logging in if the activity allows it. The result appears live as bars.

Maintained by **Moodle in Niedersachsen e. V.**

## Features

- One question with fixed answers, single or multiple choice.
- Voting rounds: each round has its own random link. Closing the round, or reaching the maximum
  round duration, makes the link and its QR code invalid.
- Voting without login (optional per activity, can be switched off for the whole site).
- Voting with login for enrolled participants, one vote per user and round.
- Live result for teachers, refreshed every three seconds, with a full-screen view for the projector.
- Optional result for voters after they have voted.
- Round history with deletion of single rounds.
- QR codes are generated on the server with Moodle's own library; no external service is used.
- Backup, restore, duplication and course reset.
- Privacy API: public votes store no personal data.

## Requirements

- Moodle 5.0 or later (5.x)
- PHP 8.2, 8.3 or 8.4
- MariaDB, MySQL or PostgreSQL

## Installation

1. Unpack the ZIP file into `mod/qrvote` (for Moodle 5.1 and later: `public/mod/qrvote`).
2. Visit *Site administration > Notifications* to complete the installation.
3. Review the settings under *Site administration > Plugins > Activity modules > QR vote*.

The `amd/build` folder is created with Grunt (`npx grunt amd`) before a release.

## Site settings

| Setting | Default | Meaning |
|---|---|---|
| Allow voting without login | on | If off, every vote requires login and enrolment. |
| Maximum round duration | 4 hours | An open round stops accepting votes after this time. |
| Votes per network address and minute | 200 | Limits submissions; 0 switches the limit off. A school class usually shares one address. |

## How voting without login works

- The QR code points to `mod/qrvote/vote.php?t=<token>`. The token is 32 random hex characters
  and belongs to one round only. This page is deliberately public: it does not call
  `require_login()` when the activity and the site allow public voting.
- The page refuses votes when the round is closed or expired, and when the activity or the
  course is hidden.
- The ballot is a Moodle form, so each submission carries the session key of the visitor's
  session.
- Each browser keeps a random voter key in local storage. The server stores only an HMAC of that
  key with a secret of the round, so devices cannot be recognised across rounds.
- Limits: a second device or a private window can vote again. Use the login mode when votes
  must be binding.
- The log event for a public vote is marked anonymous and names neither the voter nor the choice.

## Capabilities

| Capability | Default roles |
|---|---|
| `mod/qrvote:addinstance` | Editing teacher, manager |
| `mod/qrvote:view` | Guest, student, teacher, editing teacher, manager |
| `mod/qrvote:vote` (login mode only) | Student, teacher, editing teacher, manager |
| `mod/qrvote:manage` (start, close, delete rounds) | Teacher, editing teacher, manager |
| `mod/qrvote:viewresults` | Teacher, editing teacher, manager |

## Privacy

Stored personal data: the user who started a round, and in login mode the user id of a vote.
Votes without login contain no user id. Export and deletion are implemented in the privacy
provider.

## Languages

The plugin ships English and German language strings.

## License

GNU GPL v3 or later, see <https://www.gnu.org/copyleft/gpl.html>.

---

## Deutsch (Kurzfassung)

**QR-Abstimmung** ist eine Aktivität für schnelle Umfragen im Unterricht. Die Lehrkraft startet
eine Runde und zeigt den QR-Code. Die Teilnehmenden scannen ihn mit dem Smartphone und stimmen ab,
auf Wunsch ohne Anmeldung. Das Ergebnis erscheint live als Balken.

- Jede Runde hat einen eigenen Zufallslink, der beim Beenden der Runde oder nach der maximalen
  Rundendauer ungültig wird.
- Abstimmen ohne Anmeldung ist je Aktivität wählbar und lässt sich in der Website-Administration
  für alle Kurse abschalten.
- Ohne Anmeldung wird jeder Browser je Runde einmal gezählt. Ein zweites Gerät oder ein privates
  Fenster kann erneut abstimmen; für verbindliche Abstimmungen den Modus mit Anmeldung verwenden.
- QR-Codes erzeugt Moodle selbst, ohne externen Dienst.
- Stimmen ohne Anmeldung enthalten keine personenbezogenen Daten.
- Voraussetzungen: Moodle 5.x, PHP 8.2 bis 8.4, MariaDB, MySQL oder PostgreSQL.
