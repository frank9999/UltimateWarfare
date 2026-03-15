<?php

declare(strict_types=1);

/**
 * Hex Tile Generator - Creates pointy-top hexagonal terrain tiles
 * Generates pixel-art style hex tiles at 194x224 (scaled from hexSize=40 * ~2.8x)
 * These get clipped to hex shape by the canvas renderer.
 */

$width = 194;
$height = 224;

$outputDir = __DIR__ . '/public/images/map';

/**
 * Draw a pointy-top hexagon on the image
 */
function drawHexagon(GdImage $img, int $cx, int $cy, int $size, int $color, bool $fill = true): void
{
    $points = [];
    for ($i = 0; $i < 6; $i++) {
        $angle = deg2rad(60 * $i - 30);
        $points[] = (int)round($cx + $size * cos($angle));
        $points[] = (int)round($cy + $size * sin($angle));
    }

    if ($fill) {
        imagefilledpolygon($img, $points, $color);
    } else {
        imagepolygon($img, $points, $color);
    }
}

/**
 * Draw a filled circle
 */
function drawCircle(GdImage $img, int $cx, int $cy, int $r, int $color): void
{
    imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
}

/**
 * Add noise/texture to an area with scattered pixels
 */
function addNoise(GdImage $img, int $x1, int $y1, int $x2, int $y2, int $color, float $density = 0.05): void
{
    for ($y = $y1; $y < $y2; $y++) {
        for ($x = $x1; $x < $x2; $x++) {
            if (mt_rand(0, 1000) / 1000.0 < $density) {
                imagesetpixel($img, $x, $y, $color);
            }
        }
    }
}

/**
 * Fill hex-shaped area with a color (for backgrounds)
 */
function fillHexArea(GdImage $img, int $w, int $h, int $color): void
{
    drawHexagon($img, (int)($w / 2), (int)($h / 2), min($w, $h) / 2 - 2, $color);
}

/**
 * Simple seeded random for deterministic patterns
 */
function seededRand(int $seed, int $min, int $max): int
{
    mt_srand($seed);
    return mt_rand($min, $max);
}

/**
 * Draw a small triangle (tree shape)
 */
function drawTree(GdImage $img, int $x, int $y, int $size, int $trunkColor, int $leafColor): void
{
    // Trunk
    imagefilledrectangle($img, $x - 1, $y, $x + 1, $y + (int)($size * 0.3), $trunkColor);

    // Canopy layers
    $layers = 3;
    for ($i = 0; $i < $layers; $i++) {
        $layerWidth = $size - $i * (int)($size / $layers);
        $layerY = $y - $i * (int)($size / $layers);
        $points = [
            $x - $layerWidth / 2, $layerY,
            $x + $layerWidth / 2, $layerY,
            $x, $layerY - (int)($size / $layers),
        ];
        imagefilledpolygon($img, array_map('intval', $points), $leafColor);
    }
}

/**
 * Draw a simple mountain shape
 */
function drawMountain(GdImage $img, int $x, int $y, int $w, int $h, int $rockColor, int $snowColor): void
{
    // Mountain body
    $points = [
        $x - (int)($w / 2), $y,
        $x + (int)($w / 2), $y,
        $x + (int)($w * 0.1), $y - $h,
    ];
    imagefilledpolygon($img, $points, $rockColor);

    // Snow cap
    $snowH = (int)($h * 0.3);
    $snowW = (int)($w * 0.25);
    $snowPoints = [
        $x - $snowW, $y - $h + $snowH,
        $x + $snowW, $y - $h + $snowH,
        $x + (int)($w * 0.1), $y - $h,
    ];
    imagefilledpolygon($img, $snowPoints, $snowColor);
}

/**
 * Draw a hill bump
 */
function drawHill(GdImage $img, int $cx, int $cy, int $w, int $h, int $color, int $highlight): void
{
    imagefilledellipse($img, $cx, $cy, $w, $h, $color);
    // Highlight on top
    imagefilledellipse($img, $cx - (int)($w * 0.1), $cy - (int)($h * 0.15), (int)($w * 0.6), (int)($h * 0.5), $hig
hlight);
}

/**
 * Draw wave lines for water
 */
function drawWaves(GdImage $img, int $cx, int $cy, int $w, int $h, int $color, int $count = 3): void
{
    for ($i = 0; $i < $count; $i++) {
        $waveY = $cy - (int)($h * 0.3) + $i * (int)($h * 0.25);
        $waveX = $cx - (int)($w * 0.3) + $i * 5;

        for ($px = -15; $px < 15; $px++) {
            $py = (int)(sin($px * 0.4 + $i) * 3);
            imagesetpixel($img, $waveX + $px, $waveY + $py, $color);
            imagesetpixel($img, $waveX + $px, $waveY + $py + 1, $color);
        }
    }
}

// ========== TILE GENERATORS ==========

function generateDeepWater(int $w, int $h): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

    $cx = (int)($w / 2);
    $cy = (int)($h / 2);
    $size = (int)(min($w, $h) / 2) - 2;

    // Deep ocean blue base
    $baseColor = imagecolorallocate($img, 20, 50, 120);
    drawHexagon($img, $cx, $cy, $size, $baseColor);

    // Darker variation patches
    $dark1 = imagecolorallocate($img, 15, 40, 100);
    $dark2 = imagecolorallocate($img, 25, 55, 130);
    drawCircle($img, $cx - 25, $cy - 15, 30, $dark1);
    drawCircle($img, $cx + 20, $cy + 20, 25, $dark2);
    drawCircle($img, $cx + 10, $cy - 30, 20, $dark1);

    // Re-clip to hex
    drawHexClip($img, $cx, $cy, $size);

    // Subtle wave highlights
    $waveColor = imagecolorallocate($img, 40, 70, 150);
    drawWaves($img, $cx, $cy, $w, $h, $waveColor, 3);

    // Sparse sparkle
    $sparkle = imagecolorallocate($img, 60, 90, 170);
    addNoise($img, 20, 20, $w - 20, $h - 20, $sparkle, 0.01);

    return $img;
}

function generateWater(int $w, int $h): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

    $cx = (int)($w / 2);
    $cy = (int)($h / 2);
    $size = (int)(min($w, $h) / 2) - 2;

    // Medium ocean blue
    $baseColor = imagecolorallocate($img, 35, 80, 165);
    drawHexagon($img, $cx, $cy, $size, $baseColor);

    // Color variations
    $mid1 = imagecolorallocate($img, 40, 90, 175);
    $mid2 = imagecolorallocate($img, 30, 70, 150);
    drawCircle($img, $cx - 20, $cy + 10, 28, $mid1);
    drawCircle($img, $cx + 25, $cy - 15, 22, $mid2);

    drawHexClip($img, $cx, $cy, $size);

    // Wave lines
    $waveColor = imagecolorallocate($img, 65, 115, 200);
    drawWaves($img, $cx, $cy, $w, $h, $waveColor, 4);

    // Light sparkles
    $sparkle = imagecolorallocate($img, 90, 140, 220);
    addNoise($img, 20, 20, $w - 20, $h - 20, $sparkle, 0.02);

    return $img;
}

function generateShallowWater(int $w, int $h): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

    $cx = (int)($w / 2);
    $cy = (int)($h / 2);
    $size = (int)(min($w, $h) / 2) - 2;

    // Light blue-teal
    $baseColor = imagecolorallocate($img, 70, 150, 195);
    drawHexagon($img, $cx, $cy, $size, $baseColor);

    // Sandy patches showing through
    $sandy = imagecolorallocate($img, 100, 170, 190);
    $light = imagecolorallocate($img, 80, 160, 200);
    drawCircle($img, $cx + 15, $cy + 25, 20, $sandy);
    drawCircle($img, $cx - 25, $cy - 10, 18, $light);

    drawHexClip($img, $cx, $cy, $size);

    // Gentle waves
    $waveColor = imagecolorallocate($img, 110, 185, 220);
    drawWaves($img, $cx, $cy, $w, $h, $waveColor, 3);

    // Light reflections
    $sparkle = imagecolorallocate($img, 140, 210, 240);
    addNoise($img, 20, 20, $w - 20, $h - 20, $sparkle, 0.03);

    return $img;
}

function generateSand(int $w, int $h): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

    $cx = (int)($w / 2);
    $cy = (int)($h / 2);
    $size = (int)(min($w, $h) / 2) - 2;

    // Sandy yellow base
    $baseColor = imagecolorallocate($img, 210, 190, 140);
    drawHexagon($img, $cx, $cy, $size, $baseColor);

    // Sand variations
    $light = imagecolorallocate($img, 225, 205, 155);
    $dark = imagecolorallocate($img, 195, 175, 125);
    $warm = imagecolorallocate($img, 220, 195, 135);

    drawCircle($img, $cx - 20, $cy - 20, 25, $light);
    drawCircle($img, $cx + 25, $cy + 15, 20, $dark);
    drawCircle($img, $cx + 5, $cy - 5, 30, $warm);

    drawHexClip($img, $cx, $cy, $size);

    // Sand grain texture
    $grain1 = imagecolorallocate($img, 200, 180, 130);
    $grain2 = imagecolorallocate($img, 230, 215, 165);
    addNoise($img, 20, 20, $w - 20, $h - 20, $grain1, 0.04);
    addNoise($img, 20, 20, $w - 20, $h - 20, $grain2, 0.03);

    // Small dune lines
    $duneLine = imagecolorallocate($img, 190, 170, 120);
    for ($i = 0; $i < 2; $i++) {
        $lineY = $cy - 20 + $i * 35;
        for ($px = $cx - 30; $px < $cx + 30; $px++) {
            $py = (int)(sin(($px - $cx) * 0.15 + $i * 2) * 4);
            imagesetpixel($img, $px, $lineY + $py, $duneLine);
        }
    }

    return $img;
}

function generateGrassland(int $w, int $h): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

    $cx = (int)($w / 2);
    $cy = (int)($h / 2);
    $size = (int)(min($w, $h) / 2) - 2;

    // Green base
    $baseColor = imagecolorallocate($img, 75, 140, 60);
    drawHexagon($img, $cx, $cy, $size, $baseColor);

    // Grass variations
    $light = imagecolorallocate($img, 90, 160, 70);
    $dark = imagecolorallocate($img, 60, 120, 50);
    $yellow = imagecolorallocate($img, 110, 155, 55);

    drawCircle($img, $cx - 15, $cy - 25, 22, $light);
    drawCircle($img, $cx + 20, $cy + 10, 28, $dark);
    drawCircle($img, $cx - 25, $cy + 20, 18, $yellow);
    drawCircle($img, $cx + 10, $cy - 10, 20, $light);

    drawHexClip($img, $cx, $cy, $size);

    // Grass blade details
    $blade1 = imagecolorallocate($img, 55, 110, 45);
    $blade2 = imagecolorallocate($img, 100, 170, 75);
    addNoise($img, 20, 20, $w - 20, $h - 20, $blade1, 0.04);
    addNoise($img, 20, 20, $w - 20, $h - 20, $blade2, 0.03);

    // Small flower dots
    $flower1 = imagecolorallocate($img, 220, 200, 60);
    $flower2 = imagecolorallocate($img, 200, 100, 100);
    addNoise($img, 30, 30, $w - 30, $h - 30, $flower1, 0.005);
    addNoise($img, 30, 30, $w - 30, $h - 30, $flower2, 0.003);

    return $img;
}

function generateForest(int $w, int $h): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

    $cx = (int)($w / 2);
    $cy = (int)($h / 2);
    $size = (int)(min($w, $h) / 2) - 2;

    // Dark green base
    $baseColor = imagecolorallocate($img, 35, 85, 35);
    drawHexagon($img, $cx, $cy, $size, $baseColor);

    // Forest floor variations
    $floor1 = imagecolorallocate($img, 40, 95, 40);
    $floor2 = imagecolorallocate($img, 30, 75, 30);
    drawCircle($img, $cx - 20, $cy + 15, 25, $floor1);
    drawCircle($img, $cx + 15, $cy - 20, 20, $floor2);

    drawHexClip($img, $cx, $cy, $size);

    // Draw trees
    $trunk = imagecolorallocate($img, 80, 55, 30);
    $leaf1 = imagecolorallocate($img, 25, 100, 30);
    $leaf2 = imagecolorallocate($img, 40, 120, 40);
    $leaf3 = imagecolorallocate($img, 20, 80, 25);

    // Tree positions (clustered)
    $trees = [
        [$cx - 25, $cy - 15, 22, $leaf1],
        [$cx + 5, $cy - 30, 20, $leaf2],
        [$cx + 30, $cy - 10, 18, $leaf3],
        [$cx - 10, $cy + 10, 24, $leaf1],
        [$cx + 20, $cy + 20, 16, $leaf2],
        [$cx - 30, $cy + 5, 20, $leaf3],
        [$cx, $cy - 5, 22, $leaf2],
    ];

    foreach ($trees as [$tx, $ty, $ts, $tc]) {
        drawTree($img, $tx, $ty, $ts, $trunk, $tc);
    }

    // Undergrowth texture
    $under = imagecolorallocate($img, 20, 70, 20);
    addNoise($img, 20, 20, $w - 20, $h - 20, $under, 0.03);

    return $img;
}

function generateHills(int $w, int $h): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

    $cx = (int)($w / 2);
    $cy = (int)($h / 2);
    $size = (int)(min($w, $h) / 2) - 2;

    // Green-brown base
    $baseColor = imagecolorallocate($img, 95, 130, 70);
    drawHexagon($img, $cx, $cy, $size, $baseColor);

    drawHexClip($img, $cx, $cy, $size);

    // Draw hills
    $hillBase = imagecolorallocate($img, 110, 145, 75);
    $hillHighlight = imagecolorallocate($img, 130, 165, 90);
    $hillShadow = imagecolorallocate($img, 80, 115, 55);

    // Background hills
    drawHill($img, $cx + 25, $cy + 25, 55, 30, $hillShadow, $hillBase);
    drawHill($img, $cx - 20, $cy + 15, 50, 28, $hillShadow, $hillBase);

    // Foreground hills
    drawHill($img, $cx, $cy - 5, 60, 35, $hillBase, $hillHighlight);
    drawHill($img, $cx - 25, $cy - 20, 45, 25, $hillBase, $hillHighlight);
    drawHill($img, $cx + 20, $cy - 15, 40, 22, $hillBase, $hillHighlight);

    // Grass texture
    $grass = imagecolorallocate($img, 75, 120, 55);
    addNoise($img, 20, 20, $w - 20, $h - 20, $grass, 0.03);

    // Some scattered rocks
    $rock = imagecolorallocate($img, 140, 135, 120);
    addNoise($img, 30, 30, $w - 30, $h - 30, $rock, 0.008);

    return $img;
}

function generateMountains(int $w, int $h): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

    $cx = (int)($w / 2);
    $cy = (int)($h / 2);
    $size = (int)(min($w, $h) / 2) - 2;

    // Rocky gray-brown base
    $baseColor = imagecolorallocate($img, 100, 95, 85);
    drawHexagon($img, $cx, $cy, $size, $baseColor);

    drawHexClip($img, $cx, $cy, $size);

    // Draw mountains
    $rock1 = imagecolorallocate($img, 120, 115, 100);
    $rock2 = imagecolorallocate($img, 90, 85, 75);
    $rock3 = imagecolorallocate($img, 110, 105, 90);
    $snow = imagecolorallocate($img, 230, 235, 240);
    $snowShadow = imagecolorallocate($img, 200, 210, 220);

    // Background mountain
    drawMountain($img, $cx + 30, $cy + 20, 50, 50, $rock2, $snowShadow);
    drawMountain($img, $cx - 25, $cy + 15, 45, 45, $rock2, $snowShadow);

    // Main peaks
    drawMountain($img, $cx, $cy - 5, 65, 70, $rock1, $snow);
    drawMountain($img, $cx - 30, $cy + 5, 50, 55, $rock3, $snow);
    drawMountain($img, $cx + 25, $cy, 45, 50, $rock1, $snow);

    // Rock texture
    $rockTex = imagecolorallocate($img, 80, 75, 65);
    addNoise($img, 20, 20, $w - 20, $h - 20, $rockTex, 0.04);

    // Shadow detail
    $shadow = imagecolorallocate($img, 70, 65, 55);
    addNoise($img, 20, 20, $w - 20, $h - 20, $shadow, 0.02);

    return $img;
}

/**
 * Re-clip to hex by painting transparent outside the hex
 */
function drawHexClip(GdImage $img, int $cx, int $cy, int $size): void
{
    $w = imagesx($img);
    $h = imagesy($img);

    // Create mask
    $mask = imagecreatetruecolor($w, $h);
    $black = imagecolorallocate($mask, 0, 0, 0);
    $white = imagecolorallocate($mask, 255, 255, 255);
    imagefill($mask, 0, 0, $black);
    drawHexagon($mask, $cx, $cy, $size, $white);

    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $maskPixel = imagecolorat($mask, $x, $y);
            if ($maskPixel === $black) {
                imagesetpixel($img, $x, $y, $transparent);
            }
        }
    }

    imagedestroy($mask);
}

// ========== MAIN ==========

$tiles = [
    'deep_water' => 'generateDeepWater',
    'water' => 'generateWater',
    'shallow_water' => 'generateShallowWater',
    'sand' => 'generateSand',
    'grassland' => 'generateGrassland',
    'forest' => 'generateForest',
    'hills' => 'generateHills',
    'mountain' => 'generateMountains',
];

echo "Generating hex tiles ({$width}x{$height})...\n";

foreach ($tiles as $name => $generator) {
    $img = $generator($width, $height);
    $path = "{$outputDir}/{$name}.png";
    imagepng($img, $path, 9);
    imagedestroy($img);
    echo "  Created: {$name}.png\n";
}

echo "\nDone! Generated " . count($tiles) . " tile images in {$outputDir}\n";
