<?php

namespace App\Livewire\Cash\Concerns;

use App\Models\Income;
use App\Models\IncomeAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda imágenes adjuntas a un ingreso (con thumbnail). Compartido entre
 * CreateIncome (paso único) e IncomeGallery (gestión posterior).
 */
trait SavesIncomeAttachments
{
    protected function storeIncomeAttachments(Income $income, array $files): int
    {
        $disk = Storage::disk('public');
        $iid = $income->id;

        $absThumbDir = $disk->path("incomes/{$iid}/thumbs");
        if (! is_dir($absThumbDir)) {
            @mkdir($absThumbDir, 0775, true);
        }

        $count = 0;
        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $name = Str::uuid()->toString().'.'.$ext;

            $relPath = "incomes/{$iid}/{$name}";
            $thumbRel = "incomes/{$iid}/thumbs/{$name}";

            $disk->putFileAs("incomes/{$iid}", $file, $name);

            $absSrc = $disk->path($relPath);
            $absThumb = $disk->path($thumbRel);
            $this->makeThumbnail($absSrc, $absThumb, 400);

            IncomeAttachment::create([
                'income_id' => $iid,
                'filename' => $name,
                'original_name' => $file->getClientOriginalName(),
                'path' => $relPath,
                'thumb_path' => is_file($absThumb) ? $thumbRel : null,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->user()->username ?? auth()->user()->name ?? null,
            ]);
            $count++;
        }

        return $count;
    }

    protected function makeThumbnail(string $src, string $dst, int $maxWidth): bool
    {
        if (! is_file($src)) {
            return false;
        }
        $info = @getimagesize($src);
        if (! $info) {
            return false;
        }
        [$w, $h, $type] = $info;

        $ratio = $maxWidth / max(1, $w);
        $newW = $w > $maxWidth ? $maxWidth : $w;
        $newH = (int) round($h * ($w > $maxWidth ? $ratio : 1));

        switch ($type) {
            case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src);
                break;
            case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src);
                break;
            case IMAGETYPE_GIF:  $img = @imagecreatefromgif($src);
                break;
            case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : null;
                break;
            default: $img = null;
        }
        if (! $img) {
            return false;
        }

        $thumb = imagecreatetruecolor($newW, $newH);
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF || $type === IMAGETYPE_WEBP) {
            imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

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
