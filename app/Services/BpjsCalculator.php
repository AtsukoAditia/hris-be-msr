<?php

namespace App\Services;

class BpjsCalculator
{
    public const JKK_RATE = 0.0024;
    public const JKM_RATE = 0.003;
    public const JHT_ER_RATE = 0.037;
    public const JP_ER_RATE = 0.02;
    public const JHT_EE_RATE = 0.02;
    public const JP_EE_RATE = 0.01;
    public const KES_EE_RATE = 0.01;

    public const JHT_SALARY_CAP = 11_934_000;
    public const JP_SALARY_CAP = 11_934_000;
    public const KES_SALARY_CAP = 12_000_000;
    public const JKK_SALARY_CAP = 11_934_000;
    public const JKM_SALARY_CAP = 11_934_000;

    public function calculate(float $grossSalary): array
    {
        $jkkS = min($grossSalary, self::JKK_SALARY_CAP);
        $jkmS = min($grossSalary, self::JKM_SALARY_CAP);
        $jhtS = min($grossSalary, self::JHT_SALARY_CAP);
        $jpS  = min($grossSalary, self::JP_SALARY_CAP);
        $kesS = min($grossSalary, self::KES_SALARY_CAP);

        return [
            'bpjs_jkk'    => round($jkkS * self::JKK_RATE),
            'bpjs_jkm'    => round($jkmS * self::JKM_RATE),
            'bpjs_jht_er' => round($jhtS * self::JHT_ER_RATE),
            'bpjs_jp_er'  => round($jpS * self::JP_ER_RATE),
            'bpjs_jht_ee' => round($jhtS * self::JHT_EE_RATE),
            'bpjs_jp_ee'  => round($jpS * self::JP_EE_RATE),
            'bpjs_kes_ee' => round($kesS * self::KES_EE_RATE),
        ];
    }

    public function totalEmployerContribution(float $grossSalary): float
    {
        $r = $this->calculate($grossSalary);
        return $r['bpjs_jkk'] + $r['bpjs_jkm'] + $r['bpjs_jht_er'] + $r['bpjs_jp_er'];
    }

    public function totalEmployeeDeduction(float $grossSalary): float
    {
        $r = $this->calculate($grossSalary);
        return $r['bpjs_jht_ee'] + $r['bpjs_jp_ee'] + $r['bpjs_kes_ee'];
    }
}
