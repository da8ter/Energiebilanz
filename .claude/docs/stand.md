# Stand

Offenes und bekannte Widersprüche. Erledigtes wird hier gestrichen, nicht abgehakt. Veröffentlicht ist Build 15 (`master`, 04.10.2026).

## Offen

- **Doppelzählung der Wärme** ist nicht abgesichert, wenn getrennte Wärmezähler und „Wärme gesamt“ gleichzeitig gebunden sind ([waermepumpe](entscheidungen/waermepumpe.md)). Entweder `wpwaerme` bei vorhandenen Wärmezählern ignorieren oder im Formular sperren.
- **Prüfstand** deckt das Modul selbst nicht ab (Archiv-Einrichtung, Namen und Positionen nachziehen, Einspeise-Schwelle, `KachelTrait`); nur der Rechenkern und das Kachel-Skript haben Zusicherungen.
- **Kachel** ([kachel](entscheidungen/kachel.md), seit 11.11.2026, noch nicht veröffentlicht): Die Last der Dauer-Animation ist für diese Kachel nicht gemessen, und in der echten Visu ist sie bisher nur über die Vorschau des Docker-Symcon angesehen.

## Widersprüche zwischen Kommentar und Verhalten

- Der Klassenkommentar in `libs/BilanzRechner.php` sagt noch „Tageszähler: fällt um Mitternacht auf ~0 … der neue Stand selbst ist dann der Zuwachs“. Seit Build 13 wird bei einem Fallen nichts gebucht (Code, README und Prüfstand stimmen damit überein). Ebenso sagt der Formulartext „Daily counter: resets to 0 at midnight“, obwohl der Haken gerade für Zähler gedacht ist, die auf einen beliebigen kleinen Stand und mehrmals täglich zurückspringen.
- Der Kommentar an `SetzeStartwerte()` in `module.php` nennt als Beispiel `{"pv": …}`; `pv` ist keine Eingangsrolle und wird ignoriert. Die README nutzt richtig `pv1`.

Stand: geprüft gegen den Code am 11.11.2026
