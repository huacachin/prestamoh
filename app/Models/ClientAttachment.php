<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClientAttachment extends Model
{
    protected $fillable = [
        'client_id', 'filename', 'original_name',
        'path', 'thumb_path', 'mime', 'size', 'uploaded_by',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function url(): string
    {
        return '/storage/' . ltrim($this->path, '/');
    }

    public function thumbUrl(): string
    {
        return $this->thumb_path
            ? '/storage/' . ltrim($this->thumb_path, '/')
            : $this->url();
    }
}
