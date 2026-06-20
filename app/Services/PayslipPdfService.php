<?php

namespace App\Services;

use App\Models\Payroll;

class PayslipPdfService
{
    public function generate(Payroll $payroll): string
    {
        $payroll->loadMissing(['period', 'employee.user', 'employee.departmentMaster', 'employee.positionMaster', 'items']);

        $lines = [
            'SMART ATTENDANCE HRIS',
            'PAYSLIP',
            '',
            'Period          : '.($payroll->period?->name ?? '-'),
            'Employee        : '.($payroll->employee?->user?->name ?? '-'),
            'Employee Number : '.($payroll->employee?->employee_number ?? '-'),
            'Department      : '.($payroll->employee?->departmentMaster?->name ?? $payroll->employee?->department ?? '-'),
            'Position        : '.($payroll->employee?->positionMaster?->name ?? $payroll->employee?->position ?? '-'),
            'Status          : '.strtoupper($payroll->status),
            '',
            'EARNINGS',
        ];

        foreach ($payroll->items->where('type', 'earning') as $item) {
            $lines[] = $this->moneyLine($item->name, $item->amount, $payroll->currency);
        }

        $lines[] = $this->moneyLine('Total Earnings', $payroll->total_earnings, $payroll->currency);
        $lines[] = '';
        $lines[] = 'DEDUCTIONS';

        foreach ($payroll->items->where('type', 'deduction') as $item) {
            $lines[] = $this->moneyLine($item->name, $item->amount, $payroll->currency);
        }

        $lines[] = $this->moneyLine('Total Deductions', $payroll->total_deductions, $payroll->currency);
        $lines[] = '';
        $lines[] = $this->moneyLine('NET SALARY', $payroll->net_salary, $payroll->currency);
        $lines[] = '';
        $lines[] = 'Attendance Days  : '.$payroll->attendance_days;
        $lines[] = 'Absent Days      : '.$payroll->absent_days;
        $lines[] = 'Late Minutes     : '.$payroll->late_minutes;
        $lines[] = 'Unpaid Leave     : '.$payroll->unpaid_leave_days.' day(s)';
        $lines[] = 'Overtime Minutes : '.$payroll->overtime_minutes;
        $lines[] = '';
        $lines[] = 'This document is generated electronically by Smart Attendance HRIS.';

        return $this->buildPdf($lines);
    }

    private function moneyLine(string $label, mixed $amount, string $currency): string
    {
        $formatted = number_format((float) $amount, 2, '.', ',');

        return str_pad($label, 34).$currency.' '.$formatted;
    }

    private function buildPdf(array $lines): string
    {
        $chunks = array_chunk($lines, 48);
        $pageCount = count($chunks);
        $pageObjectStart = 3;
        $contentObjectStart = $pageObjectStart + $pageCount;
        $fontObject = $contentObjectStart + $pageCount;
        $objects = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($index = 0; $index < $pageCount; $index++) {
            $kids[] = ($pageObjectStart + $index).' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';

        foreach ($chunks as $index => $chunk) {
            $pageObject = $pageObjectStart + $index;
            $contentObject = $contentObjectStart + $index;
            $stream = "BT\n/F1 10 Tf\n40 800 Td\n";

            foreach ($chunk as $lineIndex => $line) {
                if ($lineIndex > 0) {
                    $stream .= "0 -15 Td\n";
                }
                $stream .= '('.$this->escapeText($line).") Tj\n";
            }

            $stream .= "ET";
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontObject.' 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $objects[$contentObject] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }

        $objects[$fontObject] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = $fontObject + 1;
        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($number = 1; $number < $size; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapeText(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
