<?php

namespace App\Support;

/**
 * Miniatura de una imagen con GD (misma lógica que los adjuntos de caja,
 * disponible para cualquier módulo). Devuelve false si no pudo.
 */
class Miniatura
{
    public static function crear(string $src, string $dst, int $maxWidth = 400): bool
    {
        if (! is_file($src)) {
            return false;
        }
        $info = @getimagesize($src);
        if (! $info) {
            return false;
        }
        [$w, $h, $type] = $info;
        $newW = $w > $maxWidth ? $maxWidth : $w;
        $newH = (int) round($h * ($w > $maxWidth ? $maxWidth / max(1, $w) : 1));

        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG => @imagecreatefrompng($src),
            IMAGETYPE_GIF => @imagecreatefromgif($src),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : null,
            default => null,
        };
        if (! $img) {
            return false;
        }
        $thumb = imagecreatetruecolor($newW, $newH);
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        @mkdir(dirname($dst), 0775, true);
        $ok = match ($type) {
            IMAGETYPE_JPEG => @imagejpeg($thumb, $dst, 85),
            IMAGETYPE_PNG => @imagepng($thumb, $dst, 6),
            IMAGETYPE_GIF => @imagegif($thumb, $dst),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? @imagewebp($thumb, $dst, 85) : false,
            default => false,
        };
        imagedestroy($img);
        imagedestroy($thumb);

        return (bool) $ok;
    }
}
