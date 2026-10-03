<?php

declare(strict_types=1);

require_once __DIR__ . '/libs/BilanzRechner.php';

/**
 * Energiebilanz — EINE Stelle für Erzeugung, Verbrauch, Netz, Speicher,
 * Wärmepumpe und Wallbox.
 *
 * Eingänge sind Zählerstände (kWh) und Leistungen (W) der Geräte. Ausgänge sind
 * eigene, nur steigende Zähler (Archiv: Zähler) — Tag, Woche, Monat und Jahr
 * liefert das Archiv, es gibt bewusst keine Heute-/Monat-Variablen. Gerechnet
 * wird im Rechenkern (libs/BilanzRechner.php) mit plausiblen Zuwächsen; ein
 * Ausreißer am Eingang kann deshalb keinen Ausgang springen lassen.
 */
class Energiebilanz extends IPSModuleStrict
{
    private const ARCHIVE_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';

    /** Zähler-Eingänge: Rolle => [Beschriftung, Tageszähler-Vorgabe, Höchstleistung kW]. */
    private const ZAEHLER = [
        'pv1'         => ['PV inverter (AC lifetime)', false, 15.0],
        'pv2'         => ['PV second system (e.g. balcony)', false, 2.0],
        'bezug'       => ['Grid import', false, 45.0],
        'einspeisung' => ['Grid export', false, 30.0],
        'laden'       => ['Battery charged', true, 10.0],
        'entladen'    => ['Battery discharged', true, 10.0],
        'wp'          => ['Heat pump compressor', false, 15.0],
        'heizstab'    => ['Heat pump heating rod', false, 12.0],
        'wallbox'     => ['Wallbox', false, 25.0],
        'wpwaerme'    => ['Heat pump heat produced (without separate heat meters)', false, 40.0],
        'waermeheiz'  => ['Heat meter heating', false, 40.0],
        'waermeww'    => ['Heat meter hot water', false, 40.0],
    ];

    /** Leistungs-Eingänge: Rolle => Beschriftung. */
    private const LEISTUNG = [
        'pv1' => 'PV inverter AC power', 'pv2' => 'PV second system power', 'netz' => 'Grid power (+ import)',
        'speicher' => 'Battery power (+ charging)', 'wp' => 'Heat pump power', 'heizstab' => 'Heating rod power', 'wallbox' => 'Wallbox power',
        'waerme_heiz' => 'Heat pump heating output', 'waerme_ww' => 'Heat pump hot water output',
    ];

    /** Ausgänge: Ident => [Name, Typ (kwh|eur|w|pct), Position]. */
    private const AUSGABE = [
        'PV_KWH' => ['PV generation', 'kwh', 10], 'HAUS_KWH' => ['House consumption', 'kwh', 11],
        'EIGEN_KWH' => ['Self-consumption', 'kwh', 12], 'BEZUG_KWH' => ['Grid import', 'kwh', 13],
        'EINSPEISUNG_KWH' => ['Grid export', 'kwh', 14], 'LADEN_KWH' => ['Battery charged', 'kwh', 15],
        'ENTLADEN_KWH' => ['Battery discharged', 'kwh', 16], 'WP_KWH' => ['Heat pump electricity total', 'kwh', 17], 'WP_VERDICHTER_KWH' => ['Heat pump compressor', 'kwh', 20],
        'HEIZSTAB_KWH' => ['Heating rod', 'kwh', 21],
        'WALLBOX_KWH' => ['Wallbox', 'kwh', 18], 'REST_KWH' => ['House without heat pump and wallbox', 'kwh', 19],
        'KOSTEN_EUR' => ['Grid costs', 'eur', 30], 'ERLOES_EUR' => ['Feed-in revenue', 'eur', 31],
        'ERSPARNIS_EUR' => ['Savings from own power', 'eur', 32],
        'PV_W' => ['PV power', 'w', 40], 'PV1_W' => ['PV power main system', 'w', 38], 'PV2_W' => ['PV power second system', 'w', 39], 'NETZ_W' => ['Grid power', 'w', 41], 'SPEICHER_W' => ['Battery power', 'w', 42],
        'HAUS_W' => ['House power', 'w', 43], 'WP_W' => ['Heat pump power', 'w', 44], 'HEIZSTAB_W' => ['Heating rod power', 'w', 44], 'WALLBOX_W' => ['Wallbox power', 'w', 45],
        'REST_W' => ['House power without heat pump and wallbox', 'w', 46],
        'AUTARKIE' => ['Self-sufficiency today', 'pct', 50], 'EIGENQUOTE' => ['Self-consumption rate today', 'pct', 51],
        'WP_WAERME_KWH' => ['Heat pump heat total', 'kwh', 60], 'WP_STROM_HEIZ_KWH' => ['Heat pump power for heating', 'kwh', 61],
        'WP_STROM_WW_KWH' => ['Heat pump power for hot water', 'kwh', 62], 'WP_STROM_STANDBY_KWH' => ['Heat pump standby power', 'kwh', 63],
        'WP_WAERME_HEIZ_KWH' => ['Heat for heating', 'kwh', 64], 'WP_WAERME_WW_KWH' => ['Heat for hot water', 'kwh', 65],
        'WP_WAERME_W' => ['Heat pump heat output', 'w', 66], 'WP_COP' => ['Heat pump COP now', 'zahl', 67],
        'WP_AZ_HEUTE' => ['Heat pump performance factor today', 'zahl', 68], 'WP_AZ_GESAMT' => ['Heat pump performance factor total', 'zahl', 69],
        'WP_AZ_HEIZ_HEUTE' => ['Performance factor heating today', 'zahl', 70], 'WP_AZ_HEIZ_GESAMT' => ['Performance factor heating total', 'zahl', 71],
        'WP_AZ_WW_HEUTE' => ['Performance factor hot water today', 'zahl', 72], 'WP_AZ_WW_GESAMT' => ['Performance factor hot water total', 'zahl', 73],
    ];

    /** Frühere Namen, die beim Update umbenannt werden — eigene Umbenennungen des Nutzers bleiben stehen. */
    private const ALTE_NAMEN = [
        'HAUS_W' => ['Hausleistung', 'House power'],
        'REST_W' => ['Hausleistung ohne Wärmepumpe und Wallbox', 'House power without heat pump and wallbox'],
        'WP_KWH' => ['Wärmepumpe', 'Heat pump'],
        'WP_WAERME_KWH' => ['Wärmepumpe Wärme', 'Heat pump heat'],
    ];

    /** Ausgangszähler-Ident => Schlüssel im Rechenkern. */
    private const ZAEHLER_AUSGANG = [
        'PV_KWH' => 'pv', 'HAUS_KWH' => 'haus', 'EIGEN_KWH' => 'eigen', 'BEZUG_KWH' => 'bezug',
        'EINSPEISUNG_KWH' => 'einspeisung', 'LADEN_KWH' => 'laden', 'ENTLADEN_KWH' => 'entladen', 'WP_KWH' => 'wpgesamt',
        'WALLBOX_KWH' => 'wallbox', 'WP_VERDICHTER_KWH' => 'wp', 'HEIZSTAB_KWH' => 'heizstab', 'REST_KWH' => 'rest', 'KOSTEN_EUR' => 'kosten', 'ERLOES_EUR' => 'erloes',
        'ERSPARNIS_EUR' => 'ersparnis',
        'WP_WAERME_KWH' => 'wpwaerme', 'WP_STROM_HEIZ_KWH' => 'wp_heiz', 'WP_STROM_WW_KWH' => 'wp_ww',
        'WP_STROM_STANDBY_KWH' => 'wp_standby', 'WP_WAERME_HEIZ_KWH' => 'waerme_heiz', 'WP_WAERME_WW_KWH' => 'waerme_ww',
    ];

    public function Create(): void
    {
        parent::Create();
        foreach (self::ZAEHLER as $rolle => [, $tages, $maxKw]) {
            $this->RegisterPropertyInteger('Var_' . $rolle, 0);
            $this->RegisterPropertyBoolean('Tages_' . $rolle, $tages);
            $this->RegisterPropertyFloat('MaxKw_' . $rolle, $maxKw);
        }
        foreach (array_keys(self::LEISTUNG) as $rolle) {
            $this->RegisterPropertyInteger('W_' . $rolle, 0);
            $this->RegisterPropertyFloat('Faktor_' . $rolle, 1.0);
        }
        $this->RegisterPropertyBoolean('SpeicherImAc', true);
        $this->RegisterPropertyFloat('PreisBezug', 0.30);
        $this->RegisterPropertyFloat('PreisEinspeisung', 0.08);
        $this->RegisterPropertyInteger('Intervall', 10);
        $this->RegisterPropertyBoolean('Archivieren', true);
        $this->RegisterPropertyBoolean('VerdichtungMinute', true);
        $this->RegisterPropertyFloat('SchwelleW', 20.0);
        $this->RegisterPropertyInteger('WpModus', 0);
        $this->RegisterPropertyBoolean('WpModusWwIstTrue', true);
        $this->RegisterPropertyFloat('WpAktivW', 200.0);
        $this->RegisterAttributeString('Zustand', '');
        $this->RegisterTimer('Rechnen', 0, 'EBIL_Rechnen($_IPS[\'TARGET\']);');

        foreach (self::AUSGABE as $ident => [$name, $typ, $pos]) {
            $this->RegisterVariableFloat($ident, $this->Translate($name), $this->Darstellung($typ), $pos);
        }
        $this->RegisterVariableBoolean('EINSPEISUNG', $this->Translate('Feeding in'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'OPTIONS'      => json_encode([
                ['Value' => false, 'Caption' => $this->Translate('Grid import'), 'IconActive' => true, 'IconValue' => 'HollowArrowDown',
                 'ColorActive' => true, 'ColorValue' => 0xFF6B6B, 'ContentColorActive' => false, 'ContentColorValue' => -1],
                ['Value' => true, 'Caption' => $this->Translate('Feeding in'), 'IconActive' => true, 'IconValue' => 'HollowArrowUp',
                 'ColorActive' => true, 'ColorValue' => 0x2ECC71, 'ContentColorActive' => false, 'ContentColorValue' => -1],
            ], JSON_UNESCAPED_UNICODE),
        ], 47);
    }

    /** Namen nachziehen, wenn sie sich im Modul geändert haben (RegisterVariable* benennt nicht um). */
    private function NamenNachziehen(): void
    {
        foreach (self::AUSGABE as $ident => [$name]) {
            $id = @$this->GetIDForIdent($ident);
            $soll = $this->Translate($name);
            if ($id !== false && $id > 0 && IPS_GetName($id) !== $soll && in_array(IPS_GetName($id), self::ALTE_NAMEN[$ident] ?? [], true)) {
                IPS_SetName($id, $soll);
            }
        }
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        $this->NamenNachziehen();
        foreach ($this->GetReferenceList() as $ref) {
            $this->UnregisterReference($ref);
        }
        foreach ($this->EingangsVariablen() as $v) {
            $this->RegisterReference($v);
        }
        if ($this->ReadPropertyBoolean('Archivieren')) {
            $this->ArchivEinrichten();
        }
        $fehlt = $this->ReadPropertyInteger('Var_pv1') <= 0 && $this->ReadPropertyInteger('Var_bezug') <= 0;
        $this->SetStatus($fehlt ? 104 : 102);
        $this->SetTimerInterval('Rechnen', $fehlt ? 0 : max(5, $this->ReadPropertyInteger('Intervall')) * 1000);
        if (!$fehlt) {
            $this->Rechnen();
        }
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->ApplyChanges();
        }
    }

    /** Ein Rechenschritt: Eingänge lesen, Zustand fortschreiben, Ausgänge setzen. */
    public function Rechnen(): void
    {
        $roh = $this->ReadAttributeString('Zustand');
        $stand = $roh !== '' ? (json_decode($roh, true) ?: BilanzRechner::neu()) : BilanzRechner::neu();
        $opt = [
            'speicherImAc'     => $this->ReadPropertyBoolean('SpeicherImAc'),
            'preisBezug'       => $this->ReadPropertyFloat('PreisBezug'),
            'preisEinspeisung' => $this->ReadPropertyFloat('PreisEinspeisung'),
        ];
        // Eingang auf eine andere Variable umgestellt: sofort neu verankern statt
        // 30 Schritte lang den Sprung zwischen alter und neuer Quelle zu verwerfen.
        foreach (array_keys(self::ZAEHLER) as $rolle) {
            $quelle = $this->ReadPropertyInteger('Var_' . $rolle);
            if (($stand['quelle'][$rolle] ?? $quelle) !== $quelle) {
                unset($stand['basis'][$rolle], $stand['zeit'][$rolle], $stand['abweichung'][$rolle]);
            }
            $stand['quelle'][$rolle] = $quelle;
        }
        $w = [];
        foreach (array_keys(self::LEISTUNG) as $rolle) {
            $wert = $this->Lesen($this->ReadPropertyInteger('W_' . $rolle));
            $w[$rolle] = $wert === null ? null : $wert * $this->ReadPropertyFloat('Faktor_' . $rolle);
        }
        $modusId = $this->ReadPropertyInteger('WpModus');
        $wwModus = null;
        if ($modusId > 0 && IPS_VariableExists($modusId)) {
            $wwModus = ((bool)GetValue($modusId)) === $this->ReadPropertyBoolean('WpModusWwIstTrue');
        }
        $opt['wpModusWw'] = $wwModus;
        $opt['wpAktiv'] = $w['wp'] === null || $w['wp'] >= $this->ReadPropertyFloat('WpAktivW');

        $messung = [];
        foreach (array_keys(self::ZAEHLER) as $rolle) {
            $messung[$rolle] = [
                'wert'         => $this->Lesen($this->ReadPropertyInteger('Var_' . $rolle)),
                'tageszaehler' => $this->ReadPropertyBoolean('Tages_' . $rolle),
                'maxKw'        => $this->ReadPropertyFloat('MaxKw_' . $rolle),
            ];
        }
        $stand = BilanzRechner::schritt($stand, $messung, time(), $opt);
        $this->WriteAttributeString('Zustand', (string)json_encode($stand));
        if ($stand['verworfen'] !== []) {
            $this->SendDebug('Verworfen', (string)json_encode($stand['verworfen']), 0);
        }

        $aus = BilanzRechner::ausgaenge($stand, $opt['speicherImAc']);
        foreach (self::ZAEHLER_AUSGANG as $ident => $schluessel) {
            $this->Setzen($ident, round((float)($aus[$schluessel] ?? 0.0), 4));
        }
        $leistung = BilanzRechner::leistung($w, $opt['speicherImAc']);
        foreach ($leistung as $k => $wert) {
            $this->Setzen(strtoupper($k) . '_W', round($wert, 1));
        }
        // Die beiden Anlagen einzeln (z. B. für eine Energieverteilung): die
        // Hauptanlage als reine Erzeugung, beim Hybrid also AC + Laden.
        $pv1 = (float)($w['pv1'] ?? 0.0) + ($opt['speicherImAc'] ? (float)($w['speicher'] ?? 0.0) : 0.0);
        $this->Setzen('PV1_W', round($pv1, 1));
        $this->Setzen('PV2_W', round((float)($w['pv2'] ?? 0.0), 1));
        // Einspeisung ja/nein mit Schwelle: zwischen −Schwelle und +Schwelle bleibt
        // der letzte Zustand stehen, sonst flattert die Anzeige um 0 W.
        if ($w['netz'] !== null) {
            $schwelle = abs($this->ReadPropertyFloat('SchwelleW'));
            $id = @$this->GetIDForIdent('EINSPEISUNG');
            if ($id !== false && $id > 0) {
                $neu = $leistung['netz'] < -$schwelle ? true : ($leistung['netz'] > $schwelle ? false : GetValueBoolean($id));
                if ($neu !== GetValueBoolean($id)) {
                    $this->SetValue('EINSPEISUNG', $neu);
                }
            }
        }
        // Wärmepumpe: Wärmeleistung der laufenden Betriebsart und COP daraus.
        $waermeW = $wwModus === true ? ($w['waerme_ww'] ?? null) : ($w['waerme_heiz'] ?? null);
        $this->Setzen('WP_WAERME_W', round((float)($waermeW ?? 0.0), 0));
        // COP mit dem ganzen Strom: der Wärmezähler misst die Heizstab-Wärme mit.
        $stromW = (float)($w['wp'] ?? 0.0) + (float)($w['heizstab'] ?? 0.0);
        $cop = ($waermeW !== null && $stromW >= $this->ReadPropertyFloat('WpAktivW')) ? $waermeW / $stromW : 0.0;
        $this->Setzen('WP_COP', round(max(0.0, min(10.0, $cop)), 2));
        $az = BilanzRechner::arbeitszahlen($stand);
        $this->Setzen('WP_AZ_HEUTE', round((float)($az['heute'] ?? 0.0), 2));
        $this->Setzen('WP_AZ_GESAMT', round((float)($az['gesamt'] ?? 0.0), 2));
        $this->Setzen('WP_AZ_HEIZ_HEUTE', round((float)($az['heiz_heute'] ?? 0.0), 2));
        $this->Setzen('WP_AZ_HEIZ_GESAMT', round((float)($az['heiz_gesamt'] ?? 0.0), 2));
        $this->Setzen('WP_AZ_WW_HEUTE', round((float)($az['ww_heute'] ?? 0.0), 2));
        $this->Setzen('WP_AZ_WW_GESAMT', round((float)($az['ww_gesamt'] ?? 0.0), 2));

        $q = BilanzRechner::tagesquoten($stand, $opt['speicherImAc']);
        $this->Setzen('AUTARKIE', round((float)($q['autarkie'] ?? 0.0), 1));
        $this->Setzen('EIGENQUOTE', round((float)($q['eigenquote'] ?? 0.0), 1));
    }

    /** Der Zustand des Rechenkerns als JSON — für Fehlersuche und Prüfung. */
    public function Zustand(): string
    {
        return $this->ReadAttributeString('Zustand');
    }

    /**
     * Startwerte der Ausgangszähler setzen, etwa beim Umzug vom alten Bestand.
     * Erwartet JSON {"pv": 44460.6, "bezug": 33089.9, …} mit Eingangsrollen;
     * gesetzt wird die Summe, die Verankerung der Eingänge bleibt.
     */
    public function SetzeStartwerte(string $Werte): bool
    {
        $neu = json_decode($Werte, true);
        if (!is_array($neu)) {
            return false;
        }
        $roh = $this->ReadAttributeString('Zustand');
        $stand = $roh !== '' ? (json_decode($roh, true) ?: BilanzRechner::neu()) : BilanzRechner::neu();
        foreach ($neu as $rolle => $wert) {
            if (in_array($rolle, BilanzRechner::WP_TEILE, true)) {
                $stand['summe'][$rolle] = (float)$wert;
            } elseif (in_array($rolle, BilanzRechner::ZAEHLER, true)) {
                $stand['summe'][$rolle] = (float)$wert;
                // Neu verankern: der nächste Schritt nimmt den aktuellen Stand als Basis.
                unset($stand['basis'][$rolle], $stand['zeit'][$rolle], $stand['abweichung'][$rolle]);
            } elseif (array_key_exists($rolle, $stand['geld'])) {
                $stand['geld'][$rolle] = (float)$wert;
            }
        }
        $stand['tag'] = '';     // Tagesanfang neu setzen, sonst springen die Quoten
        $this->WriteAttributeString('Zustand', (string)json_encode($stand));
        $this->Rechnen();
        return true;
    }

    // ───────────────────────────── Hilfen ─────────────────────────────

    private function Lesen(int $id): ?float
    {
        if ($id <= 0 || !IPS_VariableExists($id)) {
            return null;
        }
        $v = GetValue($id);
        return is_numeric($v) ? (float)$v : null;
    }

    private function Setzen(string $ident, float $wert): void
    {
        $id = @$this->GetIDForIdent($ident);
        if ($id !== false && $id > 0 && abs(GetValueFloat($id) - $wert) > 1e-9) {
            $this->SetValue($ident, $wert);
        }
    }

    /** @return list<int> */
    private function EingangsVariablen(): array
    {
        $ids = [];
        foreach (array_keys(self::ZAEHLER) as $rolle) {
            $ids[] = $this->ReadPropertyInteger('Var_' . $rolle);
        }
        foreach (array_keys(self::LEISTUNG) as $rolle) {
            $ids[] = $this->ReadPropertyInteger('W_' . $rolle);
        }
        $ids[] = $this->ReadPropertyInteger('WpModus');
        return array_values(array_unique(array_filter($ids, static fn(int $v): bool => $v > 0)));
    }

    /** Zähler als Zähler, Leistungen und Quoten als Standard archivieren — nur, wo es noch fehlt. */
    private function ArchivEinrichten(): void
    {
        $ac = @IPS_GetInstanceListByModuleID(self::ARCHIVE_GUID);
        if (!is_array($ac) || $ac === []) {
            return;
        }
        $ac = (int)$ac[0];
        $geaendert = false;
        $neuAggregieren = [];
        foreach (self::AUSGABE as $ident => [, $typ]) {
            $id = @$this->GetIDForIdent($ident);
            if ($id === false || $id <= 0) {
                continue;
            }
            $agg = in_array($typ, ['kwh', 'eur'], true) ? 1 : 0;
            if (!AC_GetLoggingStatus($ac, $id)) {
                AC_SetLoggingStatus($ac, $id, true);
                $geaendert = true;
            }
            if (AC_GetAggregationType($ac, $id) !== $agg) {
                AC_SetAggregationType($ac, $id, $agg);
                $geaendert = true;
            }
            // Verdichtung: sofort ein Wert pro Minute (MonthOffset −1, Typ 0).
            if ($this->ReadPropertyBoolean('VerdichtungMinute')) {
                $hat = false;
                foreach ((array)@AC_GetCompaction($ac, $id) as $e) {
                    if ((int)($e['MonthOffset'] ?? 99) === -1 && (int)($e['CompactionType'] ?? 99) === 0) {
                        $hat = true;
                    }
                }
                if (!$hat) {
                    AC_SetCompaction($ac, $id, -1, 0);
                    $neuAggregieren[] = $id;
                    $geaendert = true;
                }
            }
        }
        if ($geaendert) {
            IPS_ApplyChanges($ac);
        }
        foreach ($neuAggregieren as $id) {
            @AC_ReAggregateVariable($ac, $id);
        }
    }

    private function Darstellung(string $typ): array
    {
        return match ($typ) {
            'kwh' => ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON' => 'Electricity', 'SUFFIX' => ' kWh', 'DIGITS' => 2],
            'eur' => ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON' => 'Euro', 'SUFFIX' => ' €', 'DIGITS' => 2],
            'w'   => ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON' => 'Electricity', 'SUFFIX' => ' W', 'DIGITS' => 0],
            'zahl' => ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON' => 'Gauge', 'DIGITS' => 2],
            default => ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON' => 'Intensity', 'SUFFIX' => ' %', 'DIGITS' => 1],
        };
    }

    public function GetConfigurationForm(): string
    {
        $zaehler = [];
        foreach (self::ZAEHLER as $rolle => [$name]) {
            $zaehler[] = ['type' => 'RowLayout', 'items' => [
                ['type' => 'SelectVariable', 'name' => 'Var_' . $rolle, 'caption' => $this->Translate($name), 'width' => '420px', 'validVariableTypes' => [1, 2]],
                ['type' => 'CheckBox', 'name' => 'Tages_' . $rolle, 'caption' => $this->Translate('Daily counter')],
                ['type' => 'NumberSpinner', 'name' => 'MaxKw_' . $rolle, 'caption' => $this->Translate('Max. kW'), 'digits' => 1, 'width' => '110px'],
            ]];
        }
        $leistung = [];
        foreach (self::LEISTUNG as $rolle => $name) {
            $leistung[] = ['type' => 'RowLayout', 'items' => [
                ['type' => 'SelectVariable', 'name' => 'W_' . $rolle, 'caption' => $this->Translate($name), 'width' => '420px', 'validVariableTypes' => [1, 2]],
                ['type' => 'NumberSpinner', 'name' => 'Faktor_' . $rolle, 'caption' => $this->Translate('Factor to W'), 'digits' => 3, 'width' => '130px'],
            ]];
        }
        $stand = json_decode($this->ReadAttributeString('Zustand'), true);
        $verworfen = is_array($stand) && ($stand['verworfen'] ?? []) !== []
            ? $this->Translate('Last step discarded: ') . json_encode($stand['verworfen'])
            : $this->Translate('Last step: all inputs plausible.');

        return (string)json_encode([
            'elements' => [
                ['type' => 'Label', 'caption' => $this->Translate('One place for generation, consumption, grid, battery, heat pump and wallbox. The outputs are counters that only rise; day, week, month and year come from the archive.')],
                ['type' => 'ExpansionPanel', 'caption' => $this->Translate('Meters (kWh)'), 'expanded' => true, 'items' => array_merge([
                    ['type' => 'Label', 'caption' => $this->Translate('Daily counter: resets to 0 at midnight. Max. kW: highest plausible power — a larger increase is discarded as an outlier.')],
                ], $zaehler)],
                ['type' => 'ExpansionPanel', 'caption' => $this->Translate('Power (W)'), 'items' => array_merge([
                    ['type' => 'Label', 'caption' => $this->Translate('Factor: 1000 for kW, −1 to flip the sign. Grid: + is import. Battery: + is charging.')],
                ], $leistung)],
                ['type' => 'ExpansionPanel', 'caption' => $this->Translate('Heat pump'), 'items' => [
                    ['type' => 'Label', 'caption' => $this->Translate('Electricity and heat are booked to heating or hot water by operating mode. In heating mode below the threshold the electricity counts as standby.')],
                    ['type' => 'SelectVariable', 'name' => 'WpModus', 'caption' => $this->Translate('Operating mode (bool)'), 'width' => '420px', 'validVariableTypes' => [0]],
                    ['type' => 'CheckBox', 'name' => 'WpModusWwIstTrue', 'caption' => $this->Translate('true means hot water')],
                    ['type' => 'NumberSpinner', 'name' => 'WpAktivW', 'caption' => $this->Translate('Running from'), 'suffix' => ' W', 'digits' => 0],
                ]],
                ['type' => 'ExpansionPanel', 'caption' => $this->Translate('Calculation'), 'items' => [
                    ['type' => 'CheckBox', 'name' => 'SpeicherImAc', 'caption' => $this->Translate('Battery is included in the inverter AC meter (hybrid inverter)')],
                    ['type' => 'NumberSpinner', 'name' => 'PreisBezug', 'caption' => $this->Translate('Grid price (€/kWh)'), 'digits' => 4],
                    ['type' => 'NumberSpinner', 'name' => 'PreisEinspeisung', 'caption' => $this->Translate('Feed-in tariff (€/kWh)'), 'digits' => 4],
                    ['type' => 'NumberSpinner', 'name' => 'SchwelleW', 'caption' => $this->Translate('Threshold for feeding in / import'), 'suffix' => ' W', 'digits' => 0],
                    ['type' => 'NumberSpinner', 'name' => 'Intervall', 'caption' => $this->Translate('Interval'), 'suffix' => ' s', 'minimum' => 5],
                    ['type' => 'CheckBox', 'name' => 'Archivieren', 'caption' => $this->Translate('Set up archive logging for the outputs')],
                    ['type' => 'CheckBox', 'name' => 'VerdichtungMinute', 'caption' => $this->Translate('Compact the archive to one value per minute')],
                ]],
            ],
            'actions' => [
                ['type' => 'Label', 'caption' => $verworfen],
                ['type' => 'Button', 'caption' => $this->Translate('Calculate now'), 'onClick' => 'EBIL_Rechnen($id);'],
            ],
            'status' => [
                ['code' => 102, 'icon' => 'active', 'caption' => $this->Translate('Active')],
                ['code' => 104, 'icon' => 'inactive', 'caption' => $this->Translate('No meters selected')],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
