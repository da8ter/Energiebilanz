# Energiebilanz

Symcon-Modul, das Erzeugung, Verbrauch, Netz, Speicher, Wärmepumpe und Wallbox an einer Stelle bilanziert: aus Zählerständen und Leistungen der Geräte entstehen eigene, nur steigende Zähler, die das Archiv als Zähler führt. Öffentliches Repo `da8ter/Energiebilanz`, Arbeitszweig `master`.

Projektwissen (Entscheidungen, Fallen): **`docs/README.md`**. Offenes: `docs/stand.md`. Betriebsdaten des eigenen Systems stehen in `CLAUDE.local.md` (nicht eingecheckt).

## Aufbau

- **`Energiebilanz/`** (Präfix `EBIL`, Typ 3, `IPSModuleStrict`): `module.php` liest die Eingänge, schreibt Ausgänge, richtet das Archiv ein; Formular in `GetConfigurationForm()` (kein `form.json`).
- **`Energiebilanz/libs/BilanzRechner.php`**: Rechenkern ohne Symcon (Zuwächse, Plausibilität, Sammelkorb, Wärmepumpe, Quoten). Logik gehört hierher, nicht ins Modul.
- Zustand des Rechenkerns als JSON im Attribut `Zustand`; `EBIL_Zustand($id)` gibt ihn aus.
- Öffentliche Funktionen: `EBIL_Rechnen`, `EBIL_Zustand`, `EBIL_SetzeStartwerte`.

## Prüfen

```bash
php Energiebilanz/tests/BilanzRechnerTest.php   # endet mit „N Zusicherungen, 0 Abweichung(en).“
php -l Energiebilanz/module.php
```

Jede Änderung am Rechenkern bekommt eine Zusicherung im Prüfstand; Fälle aus dem Betrieb (Ausreißer, Reset auf beliebigen Stand) als Zahlenbeispiel nachbauen. Gegenprobe: Prüfstand ohne den Fix muss rot werden.

## Regeln

- **Commits:** ein Thema je Commit, deutsche Botschaft, **ohne** Co-Authored-By-Zeile. Prüfungen vorher.
- **Nie** `git checkout`/`git restore` auf Dateien: Arbeitskopien enthalten nicht committete Arbeit.
- **Push und Release nur auf Zuruf.** Release: `build` und `date` in `library.json` hochsetzen; bisher steht „(Build N)“ in der Commit-Botschaft.
- **Reihenfolge der Variablen:** `POSITIONS_STAND` nur hochzählen, wenn das Modul eine neue Ordnung mitbringt – sonst bleibt die Sortierung des Nutzers.
- **Umbenennen:** alte Namen in `ALTE_NAMEN` eintragen; nur diese werden ersetzt, eigene Namen des Nutzers bleiben.
- **Öffentliches Repo:** keine Instanz-IDs, Adressen, Pfade unter `/Users/`, keine Zählerstände, Verbräuche oder Anlagendaten des eigenen Haushalts – auch nicht in Beispielen, Tests oder Commit-Botschaften.
- **Doku nachziehen:** Ändert ein Commit eine Entscheidung aus `docs/`, im selben Commit anpassen und das „Stand“-Datum erneuern.

## Plattformwissen

Gemessenes Symcon-Verhalten für alle Module: https://github.com/da8ter/SymDo-Family-Organizer/tree/SymDo-Beta/docs/plattform (lokal `../List/docs/plattform/`). Für dieses Modul besonders `variablen-und-darstellungen.md` (Archiv, `AC_*`-Grenzen) und `module-strict-und-php.md`.
