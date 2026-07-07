<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Yüklenen görseli kare kırpıp küçültür (GD) — oyuncu kartı fotoğrafı için.
 * Telefon fotoğraflarındaki EXIF dönüklüğünü de düzeltir.
 */
class SquareImage
{
    /** Ortadan kare kırpılmış, en fazla $size piksel, JPEG (kalite 85) binary döndürür. */
    public static function make(UploadedFile $file, int $size = 512): string
    {
        $raw = file_get_contents($file->getRealPath());
        $img = @imagecreatefromstring($raw);

        if ($img === false) {
            throw new RuntimeException('Görsel okunamadı.');
        }

        $img = self::applyExifRotation($img, $file->getRealPath());

        $w = imagesx($img);
        $h = imagesy($img);
        $side = min($w, $h);
        $target = min($size, $side); // küçük görseli büyütme

        $out = imagecreatetruecolor($target, $target);
        imagecopyresampled(
            $out, $img,
            0, 0,
            (int) (($w - $side) / 2), (int) (($h - $side) / 2), // ortadan kırp
            $target, $target, $side, $side,
        );
        imagedestroy($img);

        ob_start();
        imagejpeg($out, null, 85);
        imagedestroy($out);

        return ob_get_clean();
    }

    /** Telefonların EXIF orientation etiketini uygular (exif yoksa sessizce geçer). */
    protected static function applyExifRotation(\GdImage $img, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }

        $orientation = @exif_read_data($path)['Orientation'] ?? 1;

        $rotated = match ((int) $orientation) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };

        return $rotated === false ? $img : $rotated;
    }
}
