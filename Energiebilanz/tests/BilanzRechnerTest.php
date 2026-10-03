<?php

declare(strict_types=1);

/**
 * Prüfstand für den Rechenkern der Energiebilanz.
 *
 *   php Energiebilanz/tests/BilanzRechnerTest.php
 */

require_once __DIR__ . '/../libs/BilanzRechner.php';

date_default_timezone_set('Europe/Berlin');
$fehler = 0;
$anzahl = 0;
function pruefe(string $name, mixed $ist, mixed $soll): void
{
    global $fehler, $anzahl;
    $anzahl++;
    $a = json_encode($ist);
    $b = json_encode($soll);
    $ok = $a === $b;
    if (!$ok) {
        $fehler++;
    }
    printf("%-4s %-70s%s\n", $ok ? 'OK' : 'FEHL', $name, $ok ? '' : "\n     ist:  $a\n     soll: $b");
}
$r = static fn(float $x): float => round($x, 3);

$opt = ['speicherImAc' => true, 'preisBezug' => 0.30, 'preisEinspeisung' => 0.08];
$m = static fn(array $w, array $tages = []) => array_map(
    static fn($k) => ['wert' => $w[$k] ?? null, 'tageszaehler' => in_array($k, $tages, true), 'maxKw' => ['pv1' => 15, 'pv2' => 2, 'bezug' => 45, 'einspeisung' => 30, 'laden' => 10, 'entladen' => 10, 'wp' => 15, 'wallbox' => 25, 'wpwaerme' => 40][$k]],
    array_combine(BilanzRechner::ZAEHLER, BilanzRechner::ZAEHLER)
);
$t0 = mktime(7, 0, 0, 10, 3, 2026);

// ── Verankern und normale Zuwächse ─────────────────────────────────────────
$s = BilanzRechner::schritt(BilanzRechner::neu(), $m(['pv1' => 43000, 'pv2' => 1130, 'bezug' => 33000, 'einspeisung' => 14600, 'laden' => 0, 'entladen' => 0, 'wp' => 14000, 'wallbox' => 2440], ['laden', 'entladen']), $t0, $opt);
pruefe('Erster Schritt verankert nur, summiert nichts', array_sum($s['summe']), 0.0);

$s = BilanzRechner::schritt($s, $m(['pv1' => 43001, 'pv2' => 1130.1, 'bezug' => 33000.2, 'einspeisung' => 14600.3, 'laden' => 0.4, 'entladen' => 0.1, 'wp' => 14000.1, 'wallbox' => 2440], ['laden', 'entladen']), $t0 + 3600, $opt);
$a = BilanzRechner::ausgaenge($s, true);
pruefe('PV = pv1 + pv2 + Laden − Entladen (Hybrid)', $r($a['pv']), 1.4);
pruefe('Haus = pv1 + pv2 + Bezug − Einspeisung', $r($a['haus']), 1.0);
pruefe('Eigenverbrauch = PV − Einspeisung', $r($a['eigen']), 1.1);
pruefe('Rest = Haus − WP − Wallbox', $r($a['rest']), 0.9);
pruefe('Geld: Kosten, Erlös, Ersparnis', [$r($a['kosten']), $r($a['erloes']), $r($a['ersparnis'])], [0.06, 0.024, 0.33]);

// ── Ausreißer wie am 03.10.2026: 0 → 3970 und zurück ───────────────────────
$vor = $a['pv'];
$s = BilanzRechner::schritt($s, $m(['pv1' => 3970, 'pv2' => 1130.1, 'bezug' => 33000.2, 'einspeisung' => 14600.3, 'laden' => 0.4, 'entladen' => 0.1, 'wp' => 14000.1, 'wallbox' => 2440], ['laden', 'entladen']), $t0 + 3610, $opt);
pruefe('Absturz wird verworfen und gemeldet', isset($s['verworfen']['pv1']), true);
$s = BilanzRechner::schritt($s, $m(['pv1' => 43001.01, 'pv2' => 1130.1, 'bezug' => 33000.2, 'einspeisung' => 14600.3, 'laden' => 0.4, 'entladen' => 0.1, 'wp' => 14000.1, 'wallbox' => 2440], ['laden', 'entladen']), $t0 + 3620, $opt);
pruefe('Rückkehr zählt nur den echten Zuwachs (0,01 kWh)', $r(BilanzRechner::ausgaenge($s, true)['pv'] - $vor), 0.01);

// ── Sprung nach oben ───────────────────────────────────────────────────────
$s2 = BilanzRechner::schritt($s, $m(['pv1' => 83000]), $t0 + 3630, $opt);
pruefe('Sprung nach oben (+40 000 kWh in 10 s) wird verworfen', [isset($s2['verworfen']['pv1']), $r($s2['summe']['pv1'])], [true, $r($s['summe']['pv1'])]);

// ── Tageszähler: Rücksetzung um Mitternacht ────────────────────────────────
$mitternacht = mktime(0, 0, 30, 10, 4, 2026);
$s3 = BilanzRechner::schritt($s, $m(['laden' => 5.2], ['laden', 'entladen']), $mitternacht - 120, $opt);   // 23:58: +4,8 kWh … zu viel?
pruefe('Tageszähler: 4,8 kWh in 16,5 h sind plausibel', $r($s3['summe']['laden']), 5.2);
$s3 = BilanzRechner::schritt($s3, $m(['laden' => 0.02], ['laden', 'entladen']), $mitternacht, $opt);
pruefe('Tageszähler fällt auf 0,02: Rücksetzung, Zuwachs 0,02', $r($s3['summe']['laden']), 5.22);
pruefe('Neuer Tag: Tagesanfang neu gesetzt', $s3['tag'], '2026-10-04');

// ── Neu verankern nach Zählertausch ────────────────────────────────────────
$s4 = $s;
for ($i = 1; $i <= BilanzRechner::NEU_VERANKERN; $i++) {
    $s4 = BilanzRechner::schritt($s4, $m(['wp' => 5.0]), $t0 + 4000 + $i * 10, $opt);
}
pruefe('Dauerhaft tieferer Zähler: nach 30 Schritten neu verankert, nichts addiert', [$s4['basis']['wp'], $r($s4['summe']['wp'])], [5.0, $r($s['summe']['wp'])]);
$s4 = BilanzRechner::schritt($s4, $m(['wp' => 5.3]), $t0 + 4000 + 31 * 10 + 600, $opt);
pruefe('Nach dem Verankern zählt der neue Zähler weiter', $r($s4['summe']['wp'] - $s['summe']['wp']), 0.3);

// ── Fehlender Eingang ──────────────────────────────────────────────────────
$s5 = BilanzRechner::schritt($s, $m(['pv1' => null]), $t0 + 5000, $opt);
pruefe('Fehlender Eingang ändert nichts', $s5['summe'], $s['summe']);

// ── Ohne Hybrid ────────────────────────────────────────────────────────────
$b = BilanzRechner::bilanz(['pv1' => 10, 'bezug' => 2, 'einspeisung' => 3, 'laden' => 4, 'entladen' => 1], false);
pruefe('Ohne Hybrid: PV = pv1, Haus = PV + Bezug − Einspeisung + Entladen − Laden', [$b['pv'], $b['haus']], [10.0, 6.0]);

// ── Leistung ───────────────────────────────────────────────────────────────
$l = BilanzRechner::leistung(['pv1' => 190, 'pv2' => 0, 'netz' => 20, 'speicher' => 3035, 'wp' => 13, 'wallbox' => 0], true);
pruefe('Leistung Hybrid: PV = AC + Laden, Haus = AC + pv2 + Netz', [$l['pv'], $l['haus'], $l['rest']], [3225.0, 210.0, 197.0]);

// ── Quoten ─────────────────────────────────────────────────────────────────
$q = BilanzRechner::tagesquoten(['summe' => ['pv1' => 10, 'bezug' => 1, 'einspeisung' => 4], 'tagStart' => []], false);
pruefe('Autarkie 1 − 1/7 = 85,7 %, Eigenquote 6/10 = 60 %', [round($q['autarkie'], 1), round($q['eigenquote'], 1)], [85.7, 60.0]);
pruefe('Ohne Verbrauch keine Quote', BilanzRechner::tagesquoten(BilanzRechner::neu(), true), ['autarkie' => null, 'eigenquote' => null]);

// ── Wärmepumpe nach Betriebsart ────────────────────────────────────────────
$w0 = BilanzRechner::schritt(BilanzRechner::neu(), $m(['wp' => 100.0, 'wpwaerme' => 300.0]), $t0, $opt);
$w1 = BilanzRechner::schritt($w0, $m(['wp' => 101.0, 'wpwaerme' => 304.0]), $t0 + 3600, $opt + ['wpModusWw' => false, 'wpAktiv' => true]);
$w2 = BilanzRechner::schritt($w1, $m(['wp' => 102.0, 'wpwaerme' => 306.5]), $t0 + 7200, $opt + ['wpModusWw' => true, 'wpAktiv' => true]);
$w3 = BilanzRechner::schritt($w2, $m(['wp' => 102.02, 'wpwaerme' => 306.5]), $t0 + 10800, $opt + ['wpModusWw' => false, 'wpAktiv' => false]);
$a = BilanzRechner::ausgaenge($w3, true);
pruefe('WP: Strom Heizen 1, Warmwasser 1, Stand-by 0,02', [$r($a['wp_heiz']), $r($a['wp_ww']), $r($a['wp_standby'])], [1.0, 1.0, 0.02]);
pruefe('WP: Wärme Heizen 4, Warmwasser 2,5', [$r($a['waerme_heiz']), $r($a['waerme_ww'])], [4.0, 2.5]);
pruefe('WP: Teile ergeben die Summe', $r($a['wp_heiz'] + $a['wp_ww'] + $a['wp_standby']), $r($a['wp']));
pruefe('WP: Arbeitszahl gesamt 6,5 / 2,02', round(BilanzRechner::arbeitszahlen($w3)['gesamt'], 2), 3.22);
$w4 = BilanzRechner::schritt($w3, $m(['wp' => 102.0]), $t0 + 10810, $opt + ['wpModusWw' => false, 'wpAktiv' => true]);
pruefe('WP: fallender Zähler wird nicht gebucht', $r(BilanzRechner::ausgaenge($w4, true)['wp_heiz']), 1.0);

printf("\n%d Zusicherungen, %d Abweichung(en).\n", $anzahl, $fehler);
exit($fehler === 0 ? 0 : 1);
