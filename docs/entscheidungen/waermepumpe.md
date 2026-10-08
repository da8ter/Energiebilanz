# Wärmepumpe: Buchung nach Betriebsart

Warum Strom und Wärme der Wärmepumpe so aufgeteilt werden (`BilanzRechner::schritt()`, `arbeitszahlen()`; Build 5, 7, 8).

## Entscheidungen

- **Jeder Zuwachs gehört der Betriebsart, die in dem Moment gilt** (Bool-Variable „Betriebsart“, `WpModusWwIstTrue` legt fest, welcher Wert Warmwasser ist). Damit gibt es keine Rücksprünge wie bei „gesamt − Warmwasser“. Unbekannte Betriebsart zählt als Heizen.
- **Stand-by nur im Heizmodus unter der Schwelle** (`WpAktivW`, Vorgabe 200 W). Das Umschaltventil steht in Ruhe auf Heizen; ohne diese Trennung landete der Ruheverbrauch beim Heizen und drückte die Arbeitszahl.
- **Heizstab getrennt vom Verdichter** (Build 7) und nie Stand-by: Er heizt immer, also zählt er zu Heizen oder Warmwasser. „WP gesamt“ = Verdichter + Heizstab.
- **Getrennte Wärmemengenzähler haben Vorrang.** Sind Heizung oder Warmwasser als Wärmezähler gebunden, zählen sie direkt und ergeben zusammen die Wärme gesamt; „Wärme gesamt“ (`wpwaerme`) ist nur der Ersatz für Anlagen ohne getrennte Zähler.
- **COP und Arbeitszahlen mit dem ganzen Strom** (Verdichter + Heizstab), weil der Wärmezähler die Heizstab-Wärme mitmisst (für die eigene Anlage vom Nutzer bestätigt, nicht am Code prüfbar). Arbeitszahlen gibt es gesamt, für Heizen und für Warmwasser, je heute und über alles; Heizen und Warmwasser rechnen ohne Stand-by. Unter 0,2 kWh (heute) bzw. 1 kWh (gesamt) Strom bleibt die Zahl leer (0).

## Fallen

- **Nicht beides binden:** Wer getrennte Wärmezähler **und** „Wärme gesamt“ einträgt, zählt die Wärme doppelt – der Rechenkern addiert dann beide auf `wpwaerme`. Ein Schutz im Code fehlt (siehe `../stand.md`); das Formular sagt nur „ohne getrennte Wärmezähler“.
- **Ein Wärmezähler, der lange steht, ist nicht kaputt:** Außerhalb der Heizperiode bewegt sich der Heizungs-Wärmezähler wochenlang nicht. Ein Stillstand ist deshalb kein Grund, ihn als defekt zu verwerfen.

Stand: geprüft gegen den Code am 08.10.2026
