# Kachel

Die Instanz bringt seit dem 11.11.2026 eine eigene Kachel mit (HTML-SDK): Energiefluss zwischen PV, Netz, Speicher, Haus und den Verbrauchern, darunter die Kennzahlen des Tages. Code: `Energiebilanz/module.html`, `libs/KachelTrait.php`.

## Entscheidungen

- **Reine Anzeige.** Die Kachel hat kein `RequestAction`; es gibt nichts zu schalten und damit keine Schnittstelle, die zu prüfen wäre.
- **Nabenmodell:** Alle Flüsse laufen über das Haus. Das Modul kennt nur Summen (Netz, Speicher, PV), nicht, welcher Anteil der PV in den Speicher und welcher ins Netz geht; ein Bild mit Direktlinien PV → Speicher wäre erfunden.
- **Dauer-Animation als Wahl des Nutzers** (Schalter `Animation`, Vorgabe an; Nutzerentscheid 11.11.2026). Eine laufende Animation lässt die Kachelvisu die ganze Seite ständig neu zeichnen (in den SymDo-Kacheln am 28.09.2026 mit 0,15–0,3 CPU-Kernen gemessen; für diese Kachel nicht eigens gemessen). Ausgeschaltet oder bei `prefers-reduced-motion` stehen die Linien still und zeigen die Richtung mit einer Pfeilspitze.
- **Tempo stufenlos nach Leistung** (Nutzerwunsch 11.11.2026; die drei festen Stufen davor waren kaum zu unterscheiden): Das Skript setzt die Abspielrate der laufenden Animation (`playbackRate`, logarithmisch von 0,25-fach bei 20 W bis 4-fach ab 15 kW (Nutzervorgabe, damit eine Wallbox mit 11 kW noch unter dem Anschlag liegt); eine erste, flachere Kurve ließ 22 W und 484 W gleich aussehen) statt der Dauer, denn ein Wechsel der Dauer lässt die Punkte springen. Die Strichstärke bleibt in drei Stufen. Unter der Schwelle (`SchwelleW`) fließt nichts.
- **Schweif** (Nutzerwunsch 11.11.2026): Hinter jedem Punkt liegen drei kürzer werdende, durchscheinende Striche auf demselben Pfad, mit derselben Animation und Abspielrate wie der Punkt. Ein Verlauf entlang eines Pfades geht in SVG nicht direkt; die Schichten ergeben das Ausblenden. Läuft die Linie rückwärts, liegt der Schweif auf der anderen Seite des Punktes.
- **Linien folgen den Kreisen:** Das Skript misst die Kreise und zeichnet die Linien neu, wenn sich Größe oder Knoten ändern; bei neuen Werten ändern sich nur Attribute, die Animation läuft durch. Jeder Verbraucher hat seine eigene Linie ab dem Haus mit runden Ecken (Nutzerwunsch 11.11.2026; davor eine Sammelschiene mit Abzweigen, deren Ecken die Punkte unterbrachen). Damit sich nichts kreuzt, starten die äußeren Verbraucher weiter außen am Haus und biegen höher ab, die inneren tiefer; ein Verbraucher genau unter dem Haus läuft gerade.
- **Zwei PV-Anlagen nebeneinander** (Nutzerwunsch 11.11.2026): Ist die Leistung der zweiten Anlage gebunden, stehen beide Anlagen oben nebeneinander, die Summe zentriert darüber, und jede hat ihre eigene Linie zum Haus: seitlich aus dem Kreis zur Mitte hin, dann mit runder Ecke nach unten. Mit einer Anlage bleibt es bei einem Knoten mit der Summe.
- **Knoten nach Eingängen:** Speicher, Wärmepumpe, Heizstab und Wallbox erscheinen nur, wenn ihre Leistung gebunden ist. Ohne einen dieser Verbraucher fehlt die untere Reihe ganz, weil „Sonstiges“ dann der Hausverbrauch wäre.
- **Tageswerte aus dem Rechenkern,** nicht aus dem Archiv (`BilanzRechner::tageswerte()`): Die Kachel soll ohne Archiv laufen und bei jedem Seitenaufbau keine Archivabfrage kosten. Der Kern merkt sich dafür am Tagesanfang auch den Geldstand (`tagStartGeld`).
- **Ladestand (SOC)** ist ein Eingang ohne Rechnung: `Var_soc` wird auf 0–100 begrenzt in `SPEICHER_SOC` gespiegelt, damit die Kachel und andere Abnehmer eine feste Variable haben.
- **Ladestand des Fahrzeugs** (`Var_soc_wallbox`, Nutzerwunsch 11.11.2026): Ring an der Wallbox, direkt vom Eingang gelesen und ohne eigene Variable, weil das Fahrzeugmodul den Wert schon führt.
- **Ring der Wärmepumpe** (`WpWaermeMaxW`, Nutzerwunsch 11.11.2026): `WP_WAERME_W` gegen eine einstellbare Höchstleistung. Vorgabe 0 = kein Ring, weil die Höchstleistung von der Anlage abhängt und ein geratener Wert einen falschen Ring zeigte.
- **Font-Awesome-Symbole** (`fa-light`, Nutzerwunsch 11.11.2026; die erste Fassung hatte eigene Inline-SVG): `/icons.js` kommt über den geteilten Baustein `symcon-icons-shared`, der es einmal im Hauptfenster der Visu lädt statt in jeder Kachel. Der Block im `<head>` wird per `verteilen.py` gepflegt und nie von Hand geändert; die Kachel steht dort in `ZIELE`. Der Prüfstand läuft ohne `/icons.js`, die Symbole bleiben dort leere `<i>`.
- **Gesendet wird nach jedem Rechenschritt, aber nur bei Änderung** (Vergleich mit dem letzten Zustand im Buffer). `ApplyChanges` schickt `reload`, weil Knoten und Texte an der Konfiguration hängen.
- **Startzustand als `handleMessage({…})`** hinter dem HTML: So erkennt ihn das Screenshot-Werkzeug des Release-Skills ohne Anker.

## Fallen

- **Zustand aus der Zeit vor `tagStartGeld`:** Bis zum ersten Tageswechsel nach dem Update zeigt die Kachel 0 € für Kosten, Erlös und Ersparnis (abgesichert im Prüfstand).
- **Negative Verbraucherwerte** (Rest unter 0, wenn die Eingänge nicht zusammenpassen) zeigt die Kachel als 0 W.
- **`<i>` ist kursiv:** Die Kachel nutzt `<i>` für Textteile und setzt `font-style: normal`.

Stand: geprüft gegen den Code am 11.11.2026
