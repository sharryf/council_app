<?php

namespace App\Support;

/**
 * Builds an 11-shade Filament color ramp (50–950) from a single hex
 * color by holding its hue and saturation constant and varying only
 * lightness.
 *
 * Filament\Support\Colors\Color::hex() generates ramps in OKLCH and
 * snaps low-chroma inputs (e.g. a pale cream or a muted sage) to zero
 * chroma at every shade, which erases the tint entirely — including at
 * shade 50, the lightest shade and typically the light-mode page
 * background. Holding H/S fixed here keeps the tint visible at every
 * step instead.
 */
class ColorPalette
{
    private const LIGHTNESS_STEPS = [
        50 => 0.95, 100 => 0.90, 200 => 0.82, 300 => 0.72, 400 => 0.60,
        500 => 0.50,
        600 => 0.42, 700 => 0.34, 800 => 0.26, 900 => 0.18, 950 => 0.10,
    ];

    /**
     * @return array<int, string>
     */
    public static function tintedScale(string $hex): array
    {
        [$hue, $saturation] = static::hexToHueSaturation($hex);

        return array_map(
            fn (float $lightness): string => static::hslToHex($hue, $saturation, $lightness),
            self::LIGHTNESS_STEPS,
        );
    }

    /**
     * @return array{0: float, 1: float} [hue in degrees, saturation 0–1]
     */
    private static function hexToHueSaturation(string $hex): array
    {
        [$r, $g, $b] = array_map(
            fn (string $channel): float => hexdec($channel) / 255,
            str_split(ltrim($hex, '#'), 2),
        );

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $lightness = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0];
        }

        $delta = $max - $min;
        $saturation = $lightness > 0.5
            ? $delta / (2 - $max - $min)
            : $delta / ($max + $min);

        $hue = match ($max) {
            $r => fmod(($g - $b) / $delta, 6),
            $g => (($b - $r) / $delta) + 2,
            default => (($r - $g) / $delta) + 4,
        } * 60;

        return [$hue < 0 ? $hue + 360 : $hue, $saturation];
    }

    private static function hslToHex(float $hue, float $saturation, float $lightness): string
    {
        if ($saturation === 0.0) {
            $r = $g = $b = $lightness;
        } else {
            $q = $lightness < 0.5
                ? $lightness * (1 + $saturation)
                : $lightness + $saturation - ($lightness * $saturation);
            $p = (2 * $lightness) - $q;
            $normalizedHue = $hue / 360;

            $r = static::hueToRgbChannel($p, $q, $normalizedHue + (1 / 3));
            $g = static::hueToRgbChannel($p, $q, $normalizedHue);
            $b = static::hueToRgbChannel($p, $q, $normalizedHue - (1 / 3));
        }

        return sprintf('#%02x%02x%02x', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }

    private static function hueToRgbChannel(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }

        if ($t > 1) {
            $t -= 1;
        }

        return match (true) {
            $t < 1 / 6 => $p + (($q - $p) * 6 * $t),
            $t < 1 / 2 => $q,
            $t < 2 / 3 => $p + (($q - $p) * ((2 / 3) - $t) * 6),
            default => $p,
        };
    }
}
