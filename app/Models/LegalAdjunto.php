<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LegalAdjunto extends Model
{
    protected $table = 'legal_adjuntos';

    protected $fillable = [
        'adjuntable_type', 'adjuntable_id', 'filename', 'original_name',
        'path', 'thumb_path', 'mime', 'size', 'uploaded_by',
    ];

    public function adjuntable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return '/storage/'.ltrim($this->path, '/');
    }

    public function thumbUrl(): string
    {
        return $this->thumb_path
            ? '/storage/'.ltrim($this->thumb_path, '/')
            : $this->url();
    }
}
