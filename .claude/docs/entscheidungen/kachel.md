# Kachel

Die Instanz bringt seit dem 11.11.2026 eine eigene Kachel mit (HTML-SDK): Energiefluss zwischen PV, Netz, Speicher, Haus und den Verbrauchern, darunter die Kennzahlen des Tages. Code: `Energiebilanz/module.html`, `libs/KachelTrait.php`.

## Entscheidungen

- **Reine Anzeige.** Die Kachel hat kein `RequestAction`; es gibt nichts zu schalten und damit keine Schnittstelle, die zu prüfen wäre.
- **Nabenmodell:** Alle Flüsse laufen über das Haus. Das Modul kennt nur Summen (Netz, Speicher, PV), nicht, welcher Anteil der PV in den Speicher und welcher ins Netz geht; ein Bild mit Direktlinien PV → Speicher wäre erfunden.
- **Dauer-Animation als Wahl des Nutzers** (Schalter `Animation`, Vorgabe an; Nutzerentscheid 11.11.2026). Eine laufende Animation lässt die Kachelvisu die ganze Seite ständig neu zeichnen (in den SymDo-Kacheln am 28.09.2026 mit 0,15–0,3 CPU-Kernen gemessen; für diese Kachel nicht eigens gemessen). Ausgeschaltet oder bei `prefers-reduced-motion` stehen die Linien still und zeigen die Richtung mit einer Pfeilspitze.
- **Tempo stufenlos nach Leistung** (Nutzerwunsch 11.11.2026; die drei festen Stufen davor waren kaum zu unterscheiden): Das Skript setzt die Abspielrate der laufenden Animation (`playbackRate`, logarithmisch von 0,25-fach bei 20 W bis 4-fach ab 5 kW; eine erste, flachere Kurve ließ 22 W und 484 W gleich aussehen) statt der Dauer, denn ein Wechsel der Dauer lässt die Punkte springen. Die Strichstärke bleibt in drei Stufen. Unter der Schwelle (`SchwelleW`) fließt nichts.
- **Linien folgen den Kreisen:** Das Skript misst die Kreise und zeichnet die Linien neu, wenn sich Größe oder Knoten ändern; bei neuen Werten ändern sich nur Attribute, die Animation läuft durch. Die Sammelschiene der Verbraucher ist in Stücke geteilt, jedes Stück trägt die Summe der Verbraucher dahinter.
- **Knoten nach Eingängen:** Speicher, Wärmepumpe, Heizstab und Wallbox erscheinen nur, wenn ihre Leistung gebunden ist. Ohne einen dieser Verbraucher fehlt die untere Reihe ganz, weil „Sonstiges“ dann der Hausverbrauch wäre.
- **Tageswerte aus dem Rechenkern,** nicht aus dem Archiv (`BilanzRechner::tageswerte()`): Die Kachel soll ohne Archiv laufen und bei jedem Seitenaufbau keine Archivabfrage kosten. Der Kern merkt sich dafür am Tagesanfang auch den Geldstand (`tagStartGeld`).
- **Ladestand (SOC)** ist ein Eingang ohne Rechnung: `Var_soc` wird auf 0–100 begrenzt in `SPEICHER_SOC` gespiegelt, damit die Kachel und andere Abnehmer eine feste Variable haben.
- **Gesendet wird nach jedem Rechenschritt, aber nur bei Änderung** (Vergleich mit dem letzten Zustand im Buffer). `ApplyChanges` schickt `reload`, weil Knoten und Texte an der Konfiguration hängen.
- **Startzustand als `handleMessage({…})`** hinter dem HTML: So erkennt ihn das Screenshot-Werkzeug des Release-Skills ohne Anker.

## Fallen

- **Zustand aus der Zeit vor `tagStartGeld`:** Bis zum ersten Tageswechsel nach dem Update zeigt die Kachel 0 € für Kosten, Erlös und Ersparnis (abgesichert im Prüfstand).
- **Negative Verbraucherwerte** (Rest unter 0, wenn die Eingänge nicht zusammenpassen) zeigt die Kachel als 0 W.
- **`<i>` ist kursiv:** Die Kachel nutzt `<i>` für Textteile und setzt `font-style: normal`.

Stand: geprüft gegen den Code am 11.11.2026
