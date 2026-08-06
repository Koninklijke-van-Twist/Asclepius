<?php

declare(strict_types=1);

const ASCLEPIUS_AVATAR_WIDTH = 30;
const ASCLEPIUS_AVATAR_HEIGHT = 30;
const ASCLEPIUS_AVATAR_HALF_WIDTH = 15;

function asclepius_user_avatar_dir(): string
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'user_avatars';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    return $dir;
}

function asclepius_user_avatar_path(string $email, ?string $colorKey = null): string
{
    $email = strtolower(trim($email));
    $safeEmail = preg_replace('/[^a-z0-9._\-]/', '_', $email);
    if ($colorKey === null) {
        $colorKey = asclepius_user_avatar_color_key($email);
    }
    $safeColor = preg_replace('/[^a-z0-9]/', '', strtolower($colorKey)) ?: 'auto';

    return asclepius_user_avatar_dir() . DIRECTORY_SEPARATOR . $safeEmail . '_' . $safeColor . '.png';
}

function asclepius_normalize_hex_color(string $rawColor): ?string
{
    $rawColor = trim($rawColor);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $rawColor) === 1) {
        return strtolower($rawColor);
    }
    if (preg_match('/^#[0-9a-fA-F]{3}$/', $rawColor) === 1) {
        $expanded = strtolower($rawColor);

        return '#' . $expanded[1] . $expanded[1] . $expanded[2] . $expanded[2] . $expanded[3] . $expanded[3];
    }

    return null;
}

function asclepius_configured_user_color(string $email): ?string
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }

    if (function_exists('getConfiguredIctUserColor')) {
        return getConfiguredIctUserColor($email);
    }

    if (!isset($GLOBALS['ictUserColors']) || !is_array($GLOBALS['ictUserColors'])) {
        return null;
    }

    return asclepius_normalize_hex_color((string) ($GLOBALS['ictUserColors'][$email] ?? ''));
}

function asclepius_user_avatar_color_key(string $email): string
{
    $configured = asclepius_configured_user_color($email);

    return $configured !== null ? substr($configured, 1) : 'auto';
}

function asclepius_hex_to_rgb(string $hex): array
{
    $hex = asclepius_normalize_hex_color($hex) ?? '#64748b';

    return [
        hexdec(substr($hex, 1, 2)),
        hexdec(substr($hex, 3, 2)),
        hexdec(substr($hex, 5, 2)),
    ];
}

function asclepius_relative_luminance(int $red, int $green, int $blue): float
{
    $channel = static function (int $value): float {
        $normalized = $value / 255;
        return $normalized <= 0.03928
            ? $normalized / 12.92
            : pow(($normalized + 0.055) / 1.055, 2.4);
    };

    return (0.2126 * $channel($red)) + (0.7152 * $channel($green)) + (0.0722 * $channel($blue));
}

function asclepius_mix_hex_with_white(string $hex, float $amountTowardWhite): string
{
    [$red, $green, $blue] = asclepius_hex_to_rgb($hex);
    $amountTowardWhite = max(0.0, min(1.0, $amountTowardWhite));
    $mix = static function (int $channel) use ($amountTowardWhite): int {
        return (int) round($channel + ((255 - $channel) * $amountTowardWhite));
    };

    return sprintf('#%02x%02x%02x', $mix($red), $mix($green), $mix($blue));
}

function asclepius_chat_colors_from_hex(string $hex): array
{
    $hex = asclepius_normalize_hex_color($hex) ?? '#64748b';
    [$red, $green, $blue] = asclepius_hex_to_rgb($hex);
    $chipTextColor = asclepius_relative_luminance($red, $green, $blue) >= 0.55 ? '#1e293b' : '#ffffff';

    return [
        'border' => $hex,
        'dark' => $hex,
        'light' => asclepius_mix_hex_with_white($hex, 0.35),
        'chipBackground' => $hex,
        'cardBackground' => asclepius_mix_hex_with_white($hex, 0.92),
        'chipTextColor' => $chipTextColor,
    ];
}

function asclepius_chat_colors_for_email(string $email): array
{
    $configured = asclepius_configured_user_color($email);
    if ($configured !== null) {
        return asclepius_chat_colors_from_hex($configured);
    }

    return asclepius_color_from_text($email);
}

function asclepius_hash_text_for_color(string $text): int
{
    $hash = 0;
    $length = strlen($text);
    for ($index = 0; $index < $length; $index++) {
        $hash = (int) (ord($text[$index]) + (($hash << 5) - $hash));
        $hash &= 0x7FFFFFFF;
    }

    return $hash;
}

function asclepius_color_from_text(string $text): array
{
    $normalized = strtolower(trim($text));
    if ($normalized === '') {
        return [
            'border' => '#cbd5e1',
            'dark' => '#64748b',
            'light' => '#94a3b8',
            'chipBackground' => '#e2e8f0',
            'cardBackground' => '#ffffff',
            'chipTextColor' => '#334155',
        ];
    }

    $hash = asclepius_hash_text_for_color($normalized);
    $hue = abs($hash) % 360;
    $saturation = 72 + (abs($hash >> 8) % 14);
    $lightness = 56 + (abs($hash >> 16) % 10);
    $borderLightness = max($lightness - 6, 48);
    $darkLightness = max($lightness - 18, 32);
    $chipTextColor = $lightness >= 58 ? '#1e293b' : '#ffffff';

    return [
        'border' => "hsl({$hue}, {$saturation}%, {$borderLightness}%)",
        'dark' => "hsl({$hue}, {$saturation}%, {$darkLightness}%)",
        'light' => "hsl({$hue}, {$saturation}%, {$lightness}%)",
        'chipBackground' => "hsl({$hue}, {$saturation}%, {$lightness}%)",
        'cardBackground' => 'hsl(' . $hue . ', ' . min($saturation, 48) . '%, 96%)',
        'chipTextColor' => $chipTextColor,
    ];
}

function asclepius_parse_hsl_color(string $hsl): ?array
{
    if (!preg_match('/hsl\(\s*([\d.]+)\s*,\s*([\d.]+)%\s*,\s*([\d.]+)%\s*\)/', $hsl, $matches)) {
        return null;
    }

    return [
        (float) $matches[1],
        (float) $matches[2],
        (float) $matches[3],
    ];
}

function asclepius_hsl_to_rgb(float $hue, float $saturation, float $lightness): array
{
    $saturation /= 100;
    $lightness /= 100;
    $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
    $huePrime = fmod($hue, 360.0) / 60.0;
    $second = $chroma * (1 - abs(fmod($huePrime, 2) - 1));

    $red = $green = $blue = 0.0;
    if ($huePrime >= 0 && $huePrime < 1) {
        $red = $chroma;
        $green = $second;
    } elseif ($huePrime < 2) {
        $red = $second;
        $green = $chroma;
    } elseif ($huePrime < 3) {
        $green = $chroma;
        $blue = $second;
    } elseif ($huePrime < 4) {
        $green = $second;
        $blue = $chroma;
    } elseif ($huePrime < 5) {
        $red = $second;
        $blue = $chroma;
    } else {
        $red = $chroma;
        $blue = $second;
    }

    $match = $lightness - ($chroma / 2);

    return [
        (int) round(($red + $match) * 255),
        (int) round(($green + $match) * 255),
        (int) round(($blue + $match) * 255),
    ];
}

function asclepius_color_dark_rgb(array $colors): array
{
    $hsl = asclepius_parse_hsl_color((string) ($colors['dark'] ?? ''));
    if ($hsl === null) {
        return [100, 116, 139];
    }

    return asclepius_hsl_to_rgb($hsl[0], $hsl[1], $hsl[2]);
}

function asclepius_user_avatar_seed(string $email): int
{
    return asclepius_hash_text_for_color(strtolower(trim($email)));
}

function asclepius_avatar_lattice_value(int $seed, int $x, int $y): float
{
    $n = $seed & 0x7FFFFFFF;
    $n ^= (int) (($x * 374761393) & 0x7FFFFFFF);
    $n ^= (int) (($y * 668265263) & 0x7FFFFFFF);
    $n &= 0x7FFFFFFF;
    $n ^= ($n >> 13);
    $n = (int) (($n * 1274126177) & 0x7FFFFFFF);
    $n ^= ($n >> 16);

    return ($n & 0x7FFFFFFF) / 2147483647;
}

function asclepius_avatar_smoothstep(float $value): float
{
    return $value * $value * (3 - (2 * $value));
}

function asclepius_avatar_smooth_noise(float $x, float $y, int $seed): float
{
    $x0 = (int) floor($x);
    $y0 = (int) floor($y);
    $fx = asclepius_avatar_smoothstep($x - $x0);
    $fy = asclepius_avatar_smoothstep($y - $y0);

    $n00 = asclepius_avatar_lattice_value($seed, $x0, $y0);
    $n10 = asclepius_avatar_lattice_value($seed, $x0 + 1, $y0);
    $n01 = asclepius_avatar_lattice_value($seed, $x0, $y0 + 1);
    $n11 = asclepius_avatar_lattice_value($seed, $x0 + 1, $y0 + 1);
    $nx0 = $n00 + (($n10 - $n00) * $fx);
    $nx1 = $n01 + (($n11 - $n01) * $fx);

    return $nx0 + (($nx1 - $nx0) * $fy);
}

function asclepius_avatar_fractal_noise(float $x, float $y, int $seed): float
{
    $value = 0.0;
    $amplitude = 1.0;
    $frequency = 1.0;
    $total = 0.0;

    for ($octave = 0; $octave < 4; $octave++) {
        $value += asclepius_avatar_smooth_noise($x * $frequency, $y * $frequency, $seed + ($octave * 1013)) * $amplitude;
        $total += $amplitude;
        $amplitude *= 0.5;
        $frequency *= 2.0;
    }

    return $total > 0 ? $value / $total : 0.0;
}

function asclepius_generate_user_avatar_png(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $path = asclepius_user_avatar_path($email);
    $seed = asclepius_user_avatar_seed($email);
    $configured = asclepius_configured_user_color($email);
    if ($configured !== null) {
        [$red, $green, $blue] = asclepius_hex_to_rgb($configured);
    } else {
        $colors = asclepius_color_from_text($email);
        [$red, $green, $blue] = asclepius_color_dark_rgb($colors);
    }

    $image = imagecreatetruecolor(ASCLEPIUS_AVATAR_WIDTH, ASCLEPIUS_AVATAR_HEIGHT);
    if ($image === false) {
        return false;
    }

    imagealphablending($image, true);
    imagesavealpha($image, true);

    $white = imagecolorallocate($image, 255, 255, 255);
    $fill = imagecolorallocate($image, $red, $green, $blue);
    imagefilledrectangle($image, 0, 0, ASCLEPIUS_AVATAR_WIDTH - 1, ASCLEPIUS_AVATAR_HEIGHT - 1, $white);

    for ($y = 0; $y < ASCLEPIUS_AVATAR_HEIGHT; $y++) {
        for ($x = 0; $x < ASCLEPIUS_AVATAR_HALF_WIDTH; $x++) {
            $noise = asclepius_avatar_fractal_noise($x / 4.5, $y / 7.5, $seed);
            if ($noise < 0.54) {
                continue;
            }

            imagesetpixel($image, $x, $y, $fill);
            imagesetpixel($image, (ASCLEPIUS_AVATAR_WIDTH - 1) - $x, $y, $fill);
        }
    }

    $saved = imagepng($image, $path);
    imagedestroy($image);

    return $saved;
}

function asclepius_ensure_user_avatar(string $email): bool
{
    $path = asclepius_user_avatar_path($email);
    if (is_file($path)) {
        return true;
    }

    return asclepius_generate_user_avatar_png($email);
}

function asclepius_user_avatar_url(string $email): string
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    return 'user_avatar.php?email=' . rawurlencode($email);
}
