# Rechenkern: Zuwächse statt Zählerstände

Warum die Energiebilanz so rechnet, wie sie rechnet (`Energiebilanz/libs/BilanzRechner.php`). Die Formeln selbst stehen in der README.

## Entscheidungen

- **Gerechnet wird mit Zuwächsen, nicht mit Zählerständen.** Anlass (03.10.2026): Ein Wechselrichter lieferte beim Aufwachen einmal 0 statt seines Lebenszeitzählers. Ein Bestand, der mit Ständen rechnete, buchte den Rücksprung als Zehntausende kWh an einem Tag. Mit Zuwächsen kann ein einzelner falscher Eingangswert keinen Ausgang springen lassen.
- **Ein Zuwachs zählt nur, wenn er plausibel ist:** ≥ 0 und höchstens Höchstleistung × verstrichene Zeit × 1,5 + 0,05 kWh (`RESERVE`, `SOCKEL_KWH`; kürzester angesetzter Abstand 10 s). Die Höchstleistung ist je Eingang einstellbar (`MaxKw_*`). Verworfenes steht im Zustand unter `verworfen` und im Formular.
- **Neu verankern statt ewig verwerfen:** Bleibt ein Eingang 30 Schritte lang unplausibel (`NEU_VERANKERN`, etwa nach einem Zählertausch), wird sein aktueller Stand zur neuen Basis, ohne etwas aufzuaddieren. Wird ein Eingang auf eine andere Variable umgestellt, wird sofort neu verankert (Build 3).
- **Tageszähler: ein Fallen ist ein Reset, und es wird nichts gebucht** (Build 13). SolarEdge setzt die Speicherzähler zweimal täglich zurück, und zwar nicht auf 0, sondern auf einen beliebigen kleinen Stand; was seit dem Reset floss, ist nicht ablesbar. Verloren geht höchstens ein Abfrageintervall. Tageszähler sind ein Haken je Eingang (Vorgabe an für Speicher laden/entladen), keine automatische Erkennung.
- **Abgeleitete Zähler aus einem Sammelkorb** (PV, Hausverbrauch, Eigenverbrauch, Rest; Build 13). Wechselrichter und Speicher werden zu verschiedenen Zeiten gelesen (im eigenen System 10 s gegen 60 s); schrittweise gerechnet flackerte „PV = AC + Laden − Entladen“ um den wahren Wert, und jede Vorzeichenregel summierte das Flackern auf. Gebucht wird erst, wenn die Speicherzähler nachgezogen haben oder der Speicher 90 s still ist (`KORB_STILL_S`).
- **Negativer PV-Zuwachs ist Wandlungsverlust,** nicht Rauschen: Beim Entladen gibt der Hybrid-Wechselrichter weniger AC ab, als der Speicher liefert. Der Verlust bekommt einen eigenen Zähler (`VERLUSTE_KWH`), statt die PV ins Minus zu drücken; die angezeigte PV-Leistung ist nie negativ (Build 11).
- **Hybrid-Schalter** (`SpeicherImAc`, Vorgabe an): Der AC-Zähler eines Hybrid-Wechselrichters (z. B. SolarEdge StorEdge) enthält Laden und Entladen schon. Ohne den Schalter würde der Speicher doppelt gezählt.
- **Geld zum jetzt gültigen Preis:** Kosten, Erlös und Ersparnis werden je Schritt aus dem Zuwachs gebucht; eine Preisänderung wirkt ab sofort und schreibt keine Vergangenheit um.
- **Keine Heute-, Monats- oder Jahresvariablen.** Die Ausgänge sind Zähler; das Archiv führt sie mit Aggregation „Zähler“ und liefert Tag, Woche, Monat und Jahr. Leistungen, Quoten und der Bool „Einspeisung“ laufen als Standard. Optional verdichtet das Modul sofort auf einen Wert pro Minute (`VerdichtungMinute`, Build 9/10). Eingerichtet wird nur, was fehlt.
- **„Einspeisung“ mit Schwelle:** Zwischen −Schwelle und +Schwelle (Vorgabe 20 W) bleibt der letzte Zustand stehen, sonst flattert die Anzeige um 0 W.
- **Variablenreihenfolge nur einmal je Ordnung setzen** (Build 15, Attribut `PositionsStand`): Wer in der Konsole umsortiert, behält das, bis das Modul eine neue Ordnung mitbringt.

## Fallen

- **`EBIL_SetzeStartwerte` erwartet Eingangsrollen** (`pv1`, `bezug`, …, dazu die Wärmepumpen-Teile und `kosten`/`erloes`/`ersparnis`). Unbekannte Schlüssel wie `pv` werden still ignoriert. Danach werden PV, Haus, Eigenverbrauch und Rest aus den neuen Summen neu aufgesetzt.
- **`AC_SetLoggingStatus` loggt sofort den aktuellen Wert** (gemessen 05.10.2026, nicht am Code prüfbar). `ArchivEinrichten()` schaltet das Logging für fehlende Ausgänge ein; wer danach Werte ins Archiv importiert, muss diesen ersten Punkt kennen und gegebenenfalls entfernen.
- **Startwerte springen die Ausgangszähler.** Ist das Archiv dabei schon aktiv, sieht die Zähler-Aggregation den Sprung als Verbrauch (Folgerung aus der Archivlogik, nicht gemessen). Da `ApplyChanges` das Archiv schon beim Anlegen einrichtet (Vorgabe `Archivieren` an), die Startwerte gleich nach dem Anlegen setzen und den Archivbereich der betroffenen Zähler danach bereinigen.

Stand: geprüft gegen den Code am 08.10.2026
