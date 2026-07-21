<?php
// One-off tool: crops a source photo down to a tight, passport-style
// square focused on the face, then resizes to a standard output size.
// Usage: php crop_passport.php <src> <dest> <xPct> <yPct> <sizePct> [outSize]

[$script, $src, $dest, $xPct, $yPct, $sizePct] = array_pad($argv, 6, null);
$outSize = (int) ($argv[6] ?? 500);

if (!$src || !$dest || $xPct === null || $yPct === null || $sizePct === null) {
    fwrite(STDERR, "Usage: php crop_passport.php <src> <dest> <xPct> <yPct> <sizePct> [outSize]\n");
    exit(1);
}

$info = getimagesize($src);
[$width, $height] = $info;
$mime = $info['mime'];

$image = match ($mime) {
    'image/jpeg' => imagecreatefromjpeg($src),
    'image/png' => imagecreatefrompng($src),
    default => null,
};
if (!$image) {
    fwrite(STDERR, "Unsupported image type: {$mime}\n");
    exit(1);
}

$cropSize = (int) round($width * ((float) $sizePct / 100));
$srcX = (int) round($width * ((float) $xPct / 100));
$srcY = (int) round($height * ((float) $yPct / 100));

// Clamp so the crop box never runs past the image edges.
$srcX = max(0, min($srcX, $width - $cropSize));
$srcY = max(0, min($srcY, $height - $cropSize));
$cropSize = min($cropSize, $width - $srcX, $height - $srcY);

$dst = imagecreatetruecolor($outSize, $outSize);
imagecopyresampled($dst, $image, 0, 0, $srcX, $srcY, $outSize, $outSize, $cropSize, $cropSize);

imagejpeg($dst, $dest, 92);
echo "Cropped {$src} -> {$dest} (region {$srcX},{$srcY} {$cropSize}x{$cropSize} of {$width}x{$height})\n";
