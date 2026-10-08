# Energiebilanz — Projektdoku

Versioniertes Projektwissen: **warum** die Bilanz so rechnet und wo die Fallen liegen. Was der Code selbst zeigt, steht hier nicht; Bedienung und Formeln stehen in der README.

Jede Datei endet mit „Stand: geprüft gegen den Code am …“. Ändert ein Commit eine hier beschriebene Entscheidung, wird die Datei im selben Commit nachgezogen.

## Entscheidungen (`entscheidungen/`)

- [rechenkern](entscheidungen/rechenkern.md) – Zuwächse statt Stände, Plausibilität, Tageszähler-Reset, Sammelkorb, Wandlungsverluste, Archiv
- [waermepumpe](entscheidungen/waermepumpe.md) – Buchung nach Betriebsart, Stand-by, Heizstab, Wärmezähler, Arbeitszahlen

## Testen

`php Energiebilanz/tests/BilanzRechnerTest.php` – Rechenkern ohne Symcon, mit den Fällen aus dem Betrieb (Ausreißer 0 → Zählerstand → zurück, Reset auf beliebigen Stand, Zählertausch).

## Symcon-Plattform

Gemessenes Symcon-Verhalten für alle Module: https://github.com/da8ter/SymDo-Family-Organizer/tree/SymDo-Beta/docs/plattform (lokal `../../List/docs/plattform/`).

## Stand

[stand.md](stand.md) – offene Punkte und Widersprüche zwischen Kommentar und Verhalten.

Stand: geprüft gegen den Code am 08.10.2026
