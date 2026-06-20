<?php

namespace App\Support;

use InvalidArgumentException;

final class PayrollMoney
{
    public static function toCents(int|float|string|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');

        if (! preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException('Invalid monetary value.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = substr(str_pad($fraction, 3, '0'), 0, 3);
        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $formatted = sprintf('%d.%02d', intdiv($absolute, 100), $absolute % 100);

        return $negative ? '-'.$formatted : $formatted;
    }

    public static function percentage(int $baseCents, int|float|string|null $percentage): int
    {
        $scaledPercentage = self::toScaledInteger($percentage, 4);
        $denominator = 100 * 10_000;
        $product = $baseCents * $scaledPercentage;

        return self::roundedDivision($product, $denominator);
    }

    public static function ratio(int $baseCents, int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Ratio denominator must be greater than zero.');
        }

        return self::roundedDivision($baseCents * $numerator, $denominator);
    }

    public static function multipliedRatio(
        int $baseCents,
        int $numerator,
        int|float|string|null $multiplier,
        int $denominator,
    ): int {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Ratio denominator must be greater than zero.');
        }

        $scaledMultiplier = self::toScaledInteger($multiplier, 4);

        return self::roundedDivision(
            $baseCents * $numerator * $scaledMultiplier,
            $denominator * 10_000,
        );
    }

    private static function toScaledInteger(int|float|string|null $value, int $scale): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');

        if (! preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $scaled = ((int) $whole * (10 ** $scale)) + (int) $fraction;

        return $negative ? -$scaled : $scaled;
    }

    private static function roundedDivision(int $numerator, int $denominator): int
    {
        $negative = $numerator < 0;
        $absolute = abs($numerator);
        $result = intdiv($absolute + intdiv($denominator, 2), $denominator);

        return $negative ? -$result : $result;
    }
}
