<?php

declare(strict_types=1);

/**
 * Die Kachel der Energiebilanz: Energiefluss und Kennzahlen des Tages.
 *
 * Reine Anzeige (kein RequestAction). Der Zustand kommt aus den eigenen
 * Variablen und den Tageswerten des Rechenkerns; gesendet wird nach jedem
 * Rechenschritt, aber nur, wenn sich etwas geändert hat.
 */
trait KachelTrait
{
    /** Texte der Kachel, englisch wie in der locale.json. */
    private const KACHEL_TEXTE = [
        'Photovoltaics', 'Grid', 'Grid import', 'Feeding in', 'Battery', 'charging', 'discharging',
        'Heat pump', 'Wallbox', 'Heating rod', 'Other', 'PV', 'PV today', 'Consumption', 'Import', 'Export',
        'Self-sufficiency', 'Self-consumption', 'Savings', 'Revenue', 'Costs', 'COP', 'PF',
    ];

    public function GetVisualizationTile(): string
    {
        $texte = [];
        foreach (self::KACHEL_TEXTE as $text) {
            $texte[$text] = $this->Translate($text);
        }
        $start = json_encode($this->KachelZustand() + ['texts' => $texte], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        return (string)file_get_contents(__DIR__ . '/../module.html') . '<script>handleMessage(' . $start . ');</script>';
    }

    /** Nach dem Rechnen: offene Kacheln nachziehen, wenn sich der Zustand geändert hat. */
    private function KachelSenden(): void
    {
        $neu = (string)json_encode($this->KachelZustand(), JSON_UNESCAPED_UNICODE);
        if ($neu !== $this->GetBuffer('Kachel')) {
            $this->SetBuffer('Kachel', $neu);
            $this->UpdateVisualizationValue($neu);
        }
    }

    private function KachelZustand(): array
    {
        $wert = fn(string $ident): float => (float)$this->GetValue($ident);
        $gebunden = fn(string $property): bool => $this->ReadPropertyInteger($property) > 0;
        $stand = json_decode($this->ReadAttributeString('Zustand'), true);
        $tag = BilanzRechner::tageswerte(is_array($stand) ? $stand : [], $this->ReadPropertyBoolean('SpeicherImAc'));
        // Der Ladestand des Fahrzeugs ist nur Anzeige: direkt vom Eingang, ohne eigene Variable.
        $auto = $this->Lesen($this->ReadPropertyInteger('Var_soc_wallbox'));
        $w = [];
        foreach (['pv', 'pv1', 'pv2', 'netz', 'speicher', 'haus', 'wp', 'heizstab', 'wallbox', 'rest'] as $k) {
            $w[$k] = (int)round($wert(strtoupper($k) . '_W'));
        }
        return [
            'type'       => 'state',
            'w'          => $w,
            'soc'        => $gebunden('Var_soc') ? (int)round($wert('SPEICHER_SOC')) : null,
            'socWallbox' => $auto === null ? null : (int)round(max(0.0, min(100.0, $auto))),
            'cop'        => round($wert('WP_COP'), 1),
            'az'         => round($wert('WP_AZ_HEUTE'), 1),
            'autarkie'   => (int)round($wert('AUTARKIE')),
            'eigenquote' => (int)round($wert('EIGENQUOTE')),
            'tag'        => array_map(static fn(float $v): float => round($v, 2), $tag),
            'knoten'     => [
                'pv2'      => $gebunden('W_pv2'),
                'speicher' => $gebunden('W_speicher'),
                'wp'       => $gebunden('W_wp'),
                'heizstab' => $gebunden('W_heizstab'),
                'wallbox'  => $gebunden('W_wallbox'),
            ],
            'schwelle'   => abs($this->ReadPropertyFloat('SchwelleW')),
            'animation'  => $this->ReadPropertyBoolean('Animation'),
        ];
    }
}
