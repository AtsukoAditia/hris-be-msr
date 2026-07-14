<?php

namespace App\Services;

class Pph21Calculator
{
    // PTKP (Penghasilan Tidak Kena Pajak) annual deduction
    public const PTKP = [
        'TK0' => 54_000_000, // Tidak Kawin, 0 tanggungan
        'TK1' => 58_500_000,
        'TK2' => 63_000_000,
        'TK3' => 67_500_000,
        'K0'  => 58_500_000, // Kawin
        'K1'  => 63_000_000,
        'K2'  => 67_500_000,
        'K3'  => 72_000_000,
        'K/I_0' => 112_500_000, // Kawin, istri tidak bekerja
        'K/I_1' => 117_000_000,
        'K/I_2' => 121_500_000,
        'K/I_3' => 126_000_000,
    ];

    // Progressive tax brackets (annual)
    public const BRACKETS = [
        ['limit' => 60_000_000, 'rate' => 0.05],
        ['limit' => 250_000_000, 'rate' => 0.15],
        ['limit' => 500_000_000, 'rate' => 0.25],
        ['limit' => 5_000_000_000, 'rate' => 0.30],
        ['limit' => PHP_INT_MAX, 'rate' => 0.35],
    ];

    private BpjsCalculator $bpjsCalc;

    public function __construct()
    {
        $this->bpjsCalc = new BpjsCalculator;
    }

    public function calculate(
        float $annualGross,
        string $ptkpCode = 'TK0',
        bool $hasNpwp = true,
    ): array {
        $ptkp = self::PTKP[$ptkpCode] ?? self::PTKP['TK0'];
        $bpjsEmployee = $this->bpjsCalc->totalEmployeeDeduction($annualGross / 12) * 12;

        $pkp = max(0, $annualGross - $ptkp - $bpjsEmployee);
        $pph21 = $this->applyBrackets($pkp);

        // Penalty 20% if no NPWP
        if (! $hasNpwp) {
            $pph21 *= 1.2;
        }

        return [
            'annual_gross'    => $annualGross,
            'ptkp'            => $ptkp,
            'bpjs_employee'   => round($bpjsEmployee),
            'pkp'             => round($pkp),
            'pph21_annual'    => round($pph21),
            'pph21_monthly'   => round($pph21 / 12),
            'effective_rate'  => $annualGross > 0 ? round($pph21 / $annualGross * 100, 2) : 0,
        ];
    }

    public function calculateMonthly(float $monthlyGross, string $ptkpCode = 'TK0', bool $hasNpwp = true): array
    {
        $result = $this->calculate($monthlyGross * 12, $ptkpCode, $hasNpwp);
        $result['monthly_gross'] = $monthlyGross;
        return $result;
    }

    private function applyBrackets(float $pkp): float
    {
        $tax = 0;
        $remaining = $pkp;
        $prevLimit = 0;

        foreach (self::BRACKETS as ['limit' => $limit, 'rate' => $rate]) {
            $bracketSize = $limit - $prevLimit;
            $taxable = min($remaining, $bracketSize);
            $tax += $taxable * $rate;
            $remaining -= $taxable;
            $prevLimit = $limit;
            if ($remaining <= 0) break;
        }

        return $tax;
    }
}
