<?php

declare(strict_types=1);

/**
 * Rechenkern der Energiebilanz — ohne Symcon, damit der Prüfstand ihn direkt fährt.
 *
 * Gerechnet wird mit ZUWÄCHSEN, nicht mit Zählerständen: Jeder Eingangszähler
 * liefert je Schritt einen Zuwachs, und nur ein plausibler Zuwachs (≥ 0 und
 * höchstens Höchstleistung × verstrichene Zeit, mit Reserve) wird aufaddiert.
 * Damit kann ein Ausgang nie springen — der Auslöser war am 03.10.2026 ein
 * Wechselrichter, der beim Aufwachen einmal 0 lieferte; der alte Bestand buchte
 * den Rücksprung als 39 358 kWh an einem Tag.
 *
 * Zwei Zählerarten:
 *  - Zählerstand: steigt nur. Fällt er, ist es ein Ausreißer und wird verworfen.
 *  - Tageszähler: fällt um Mitternacht auf ~0. Ein Fallen gilt als Rücksetzung,
 *    der neue Stand selbst ist dann der Zuwachs.
 *
 * Bleibt ein Eingang dauerhaft unplausibel (Zähler getauscht, Rücksetzung
 * außerhalb der Nacht), wird er nach NEU_VERANKERN Schritten neu verankert —
 * ohne etwas aufzuaddieren.
 */
final class BilanzRechner
{
    /** Eingangszähler in kWh, in dieser Reihenfolge im Zustand. */
    public const ZAEHLER = ['pv1', 'pv2', 'bezug', 'einspeisung', 'laden', 'entladen', 'wp', 'wallbox'];

    /** Ausgangszähler in kWh bzw. € (Geld). */
    public const AUSGAENGE = ['pv', 'haus', 'eigen', 'bezug', 'einspeisung', 'laden', 'entladen', 'wp', 'wallbox', 'rest',
                              'kosten', 'erloes', 'ersparnis'];

    public const NEU_VERANKERN = 30;
    /** Reserve auf die Höchstleistung, und ein fester Sockel gegen Rundung der Zähler. */
    private const RESERVE = 1.5;
    private const SOCKEL_KWH = 0.05;
    /** Kürzester angesetzter Abstand: ein zweiter Wert in derselben Sekunde darf trotzdem etwas tragen. */
    private const MIN_STUNDEN = 10 / 3600;

    /** Leerer Zustand. */
    public static function neu(): array
    {
        return ['basis' => [], 'zeit' => [], 'abweichung' => [], 'summe' => array_fill_keys(self::ZAEHLER, 0.0),
                'geld' => ['kosten' => 0.0, 'erloes' => 0.0, 'ersparnis' => 0.0],
                'tag' => '', 'tagStart' => [], 'verworfen' => []];
    }

    /**
     * Ein Schritt.
     *
     * @param array<string, array{wert: ?float, tageszaehler?: bool, maxKw: float}> $messung je Rolle; wert null = Eingang fehlt
     * @param array{speicherImAc: bool, preisBezug: float, preisEinspeisung: float} $optionen
     */
    public static function schritt(array $stand, array $messung, int $jetzt, array $optionen): array
    {
        $stand += self::neu();
        $zuwachs = array_fill_keys(self::ZAEHLER, 0.0);
        $stand['verworfen'] = [];

        foreach ($messung as $rolle => $m) {
            if (!in_array($rolle, self::ZAEHLER, true) || $m['wert'] === null) {
                continue;
            }
            $wert = (float)$m['wert'];
            if (!isset($stand['basis'][$rolle])) {
                // Erster Wert: nur verankern.
                $stand['basis'][$rolle] = $wert;
                $stand['zeit'][$rolle] = $jetzt;
                $stand['abweichung'][$rolle] = 0;
                continue;
            }
            $d = $wert - (float)$stand['basis'][$rolle];
            if ($d < 0 && ($m['tageszaehler'] ?? false)) {
                $d = $wert;     // Rücksetzung: der neue Stand ist der Zuwachs seit 0
            }
            $stunden = max(($jetzt - (int)$stand['zeit'][$rolle]) / 3600, self::MIN_STUNDEN);
            $grenze = max(0.0, (float)$m['maxKw']) * $stunden * self::RESERVE + self::SOCKEL_KWH;

            if ($d >= 0 && $d <= $grenze) {
                $zuwachs[$rolle] = $d;
                $stand['basis'][$rolle] = $wert;
                $stand['zeit'][$rolle] = $jetzt;
                $stand['abweichung'][$rolle] = 0;
                continue;
            }
            $stand['verworfen'][$rolle] = round($d, 3);
            $stand['abweichung'][$rolle] = (int)($stand['abweichung'][$rolle] ?? 0) + 1;
            if ($stand['abweichung'][$rolle] >= self::NEU_VERANKERN) {
                $stand['basis'][$rolle] = $wert;
                $stand['zeit'][$rolle] = $jetzt;
                $stand['abweichung'][$rolle] = 0;
            }
        }

        foreach ($zuwachs as $rolle => $d) {
            $stand['summe'][$rolle] = (float)($stand['summe'][$rolle] ?? 0.0) + $d;
        }
        // Geld aus den Zuwächsen zum JETZT gültigen Preis: eine Preisänderung
        // wirkt ab sofort und schreibt keine Vergangenheit um.
        $b = self::bilanz($zuwachs, $optionen['speicherImAc']);
        $stand['geld']['kosten'] += $b['bezug'] * $optionen['preisBezug'];
        $stand['geld']['erloes'] += $b['einspeisung'] * $optionen['preisEinspeisung'];
        $stand['geld']['ersparnis'] += max(0.0, $b['eigen']) * $optionen['preisBezug'];

        // Tagesanfang merken (für die Quoten des laufenden Tages).
        $heute = date('Y-m-d', $jetzt);
        if ($stand['tag'] !== $heute) {
            $stand['tag'] = $heute;
            $stand['tagStart'] = $stand['summe'];
        }
        return $stand;
    }

    /**
     * Die Bilanz aus Zählern (Summen oder Zuwächsen).
     *
     * speicherImAc: Der Zähler pv1 misst den AC-Ausgang eines Hybrid-Wechselrichters,
     * in dem Laden und Entladen schon verrechnet sind (SolarEdge StorEdge). Die
     * reine PV-Erzeugung ist dann pv1 + Laden − Entladen; der Hausverbrauch bleibt
     * pv1 + pv2 + Bezug − Einspeisung, ohne den Speicher doppelt zu zählen.
     *
     * @param array<string, float> $z
     * @return array<string, float>
     */
    public static function bilanz(array $z, bool $speicherImAc): array
    {
        $g = static fn(string $k): float => (float)($z[$k] ?? 0.0);
        $pvRoh = $g('pv1') + $g('pv2');
        $pv = $speicherImAc ? $pvRoh + $g('laden') - $g('entladen') : $pvRoh;
        $haus = $pv + $g('bezug') - $g('einspeisung') + $g('entladen') - $g('laden');
        return [
            'pv'          => $pv,
            'haus'        => $haus,
            'eigen'       => $pv - $g('einspeisung'),
            'bezug'       => $g('bezug'),
            'einspeisung' => $g('einspeisung'),
            'laden'       => $g('laden'),
            'entladen'    => $g('entladen'),
            'wp'          => $g('wp'),
            'wallbox'     => $g('wallbox'),
            'rest'        => $haus - $g('wp') - $g('wallbox'),
        ];
    }

    /** Alle Ausgangszähler aus dem Zustand. */
    public static function ausgaenge(array $stand, bool $speicherImAc): array
    {
        return self::bilanz($stand['summe'] ?? [], $speicherImAc) + ($stand['geld'] ?? []);
    }

    /**
     * Quoten des laufenden Tages in Prozent; null, solange es nichts zu teilen gibt.
     *
     * @return array{autarkie: ?float, eigenquote: ?float}
     */
    public static function tagesquoten(array $stand, bool $speicherImAc): array
    {
        $tag = [];
        foreach (self::ZAEHLER as $k) {
            $tag[$k] = (float)($stand['summe'][$k] ?? 0.0) - (float)($stand['tagStart'][$k] ?? 0.0);
        }
        $b = self::bilanz($tag, $speicherImAc);
        return [
            'autarkie'   => $b['haus'] > 0.01 ? max(0.0, min(100.0, (1 - $b['bezug'] / $b['haus']) * 100)) : null,
            'eigenquote' => $b['pv'] > 0.01 ? max(0.0, min(100.0, $b['eigen'] / $b['pv'] * 100)) : null,
        ];
    }

    /**
     * Momentanleistung in W. Vorzeichen: Netz + = Bezug, Speicher + = Laden.
     *
     * @param array<string, ?float> $w
     * @return array<string, float>
     */
    public static function leistung(array $w, bool $speicherImAc): array
    {
        $g = static fn(string $k): float => (float)($w[$k] ?? 0.0);
        $pvRoh = $g('pv1') + $g('pv2');
        $pv = $speicherImAc ? $pvRoh + $g('speicher') : $pvRoh;
        $haus = $pv + $g('netz') - $g('speicher');
        return ['pv' => $pv, 'netz' => $g('netz'), 'speicher' => $g('speicher'), 'haus' => $haus,
                'wp' => $g('wp'), 'wallbox' => $g('wallbox'), 'rest' => $haus - $g('wp') - $g('wallbox')];
    }
}
