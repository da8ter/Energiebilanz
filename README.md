# Energiebilanz

Eine Stelle für Erzeugung, Verbrauch, Netz, Speicher, Wärmepumpe und Wallbox in Symcon.

Die Instanz liest Zählerstände (kWh) und Leistungen (W) der Geräte und führt daraus eigene Zähler, die **nur steigen**: PV-Erzeugung, Hausverbrauch, Eigenverbrauch, Netzbezug, Einspeisung, Speicher, Wärmepumpe, Wallbox, der Hausverbrauch ohne Wärmepumpe und Wallbox sowie Netzkosten, Einspeiseerlös und Ersparnis in Euro. Dazu kommen die Momentanleistungen – darunter der aktuelle Hausverbrauch in Watt –, Autarkie und Eigenverbrauchsquote des laufenden Tages und die Anzeige **Einspeisung** (ja/nein), die erst ab einer einstellbaren Schwelle (Vorgabe 20 W) umschaltet.

Tag, Woche, Monat und Jahr liefert das Symcon-Archiv – die Zähler werden als Zähler archiviert. Heute-, Monats- oder Jahresvariablen gibt es bewusst nicht.

## Robust gegen Ausreißer

Gerechnet wird mit Zuwächsen. Ein Zuwachs zählt nur, wenn er nicht negativ ist und höchstens der eingestellten Höchstleistung × verstrichene Zeit (mit Reserve) entspricht. Liefert ein Wechselrichter beim Aufwachen einmal 0 oder springt ein Zähler, wird der Wert verworfen und nichts aufaddiert. Bleibt ein Eingang dauerhaft abweichend (etwa nach einem Zählertausch), wird er nach 30 Schritten neu verankert.

Tageszähler, die um Mitternacht auf 0 fallen (z. B. Speicher geladen/entladen), werden als solche markiert; ihr Rücksetzen gilt nicht als Absturz.

## Wärmepumpe

Aus Verdichter- und Heizstab-Strom, den Wärmemengenzählern Heizung und Warmwasser (oder ersatzweise einer Wärme gesamt) und der Betriebsart (Bool: Warmwasser/Heizen) führt die Instanz:

- Strom gesamt, Verdichter und **Heizstab** getrennt; Strom und Wärme nach **Heizen** und **Warmwasser** (der Heizstab zählt zur jeweiligen Betriebsart, nie zu Stand-by)
- **Stand-by-Strom**: im Heizmodus unterhalb der Schwelle *Läuft ab* (Vorgabe 200 W) – das Umschaltventil steht in Ruhe auf Heizen, ohne diese Trennung landete der Ruheverbrauch beim Heizen
- **COP aktuell** aus Wärmeleistung der laufenden Betriebsart und elektrischer Leistung von Verdichter und Heizstab (der Wärmezähler misst die Heizstab-Wärme mit)
- **Arbeitszahl heute** und **Arbeitszahl gesamt**; Monat und Jahr ergeben sich aus dem Archiv

Gebucht wird jeder Zuwachs nach der Betriebsart, die in dem Moment gilt. Damit gibt es keine Rücksprünge wie bei „gesamt − Warmwasser“.

## Hybrid-Wechselrichter

Misst der PV-Zähler den AC-Ausgang eines Hybrid-Wechselrichters (z. B. SolarEdge StorEdge), sind Laden und Entladen des Speichers dort schon verrechnet. Mit dem Schalter *Speicher ist im AC-Zähler enthalten* rechnet die Instanz:

- PV-Erzeugung = PV-AC + zweite Anlage + Laden − Entladen
- Hausverbrauch = PV-AC + zweite Anlage + Netzbezug − Einspeisung
- Eigenverbrauch = PV-Erzeugung − Einspeisung

Ohne Hybrid: Hausverbrauch = PV + Netzbezug − Einspeisung + Entladen − Laden.

## Einrichtung

1. Bibliothek über das Module Control installieren: `https://github.com/da8ter/Energiebilanz.git`
2. Instanz **Energiebilanz** anlegen.
3. Unter *Zähler* die Zählerstände wählen, Tageszähler markieren und die Höchstleistung je Zähler prüfen.
4. Unter *Leistung* die Leistungen wählen; *Faktor* 1000 für kW, −1 dreht das Vorzeichen. Netz: + ist Bezug, Speicher: + ist Laden.
5. Unter *Berechnung* Strompreis und Einspeisevergütung eintragen.

Beim Umzug von einem alten Bestand setzt `EBIL_SetzeStartwerte($id, '{"pv1": 44460.6, "bezug": 33089.9}')` die Startwerte der Summen.

## PHP-Befehle

```php
EBIL_Rechnen(int $InstanzID): void              // sofort rechnen
EBIL_Zustand(int $InstanzID): string             // Zustand des Rechenkerns als JSON
EBIL_SetzeStartwerte(int $InstanzID, string $Werte): bool
```

## Prüfstand

```
php Energiebilanz/tests/BilanzRechnerTest.php
```
