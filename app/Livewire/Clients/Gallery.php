<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\ClientAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Gallery extends Component
{
    use WithFileUploads;

    public Client $client;

    public int $clientId;

    public array $files = [];

    public bool $puedeEliminar = true;

    protected function rules(): array
    {
        return [
            'files' => 'required|array|min:1',
            'files.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:10240',
        ];
    }

    protected function messages(): array
    {
        return [
            'files.required' => 'Selecciona o arrastra al menos una imagen.',
            'files.*.image' => 'Cada archivo debe ser una imagen.',
            'files.*.mimes' => 'Formatos válidos: JPG, PNG, GIF o WebP.',
            'files.*.max' => 'Cada imagen debe pesar máximo 10 MB.',
        ];
    }

    /** true cuando se renderiza dentro de una pestaña (sin cabecera ni card propios). */
    public bool $embebido = false;

    public function mount(int $id, bool $embebido = false): void
    {
        $this->embebido = $embebido;
        $this->client = Client::findOrFail($id);

        // Analista (scope-propio): solo SUS clientes
        abort_if(
            (auth()->user()?->can('clientes.scope-propio') ?? false)
            && (int) $this->client->asesor_id !== (int) auth()->id(),
            403, 'Este cliente no pertenece a tu cartera.'
        );
        $this->clientId = $id;

        $this->puedeEliminar = auth()->user()?->can('clientes.eliminar') ?? false;
    }

    public function save()
    {
        $this->validate();

        $disk = Storage::disk('public');
        $clientId = $this->client->id;

        $absThumbDir = $disk->path("clients/{$clientId}/thumbs");
        if (! is_dir($absThumbDir)) {
            @mkdir($absThumbDir, 0775, true);
        }

        $count = 0;
        foreach ($this->files as $file) {
            if (! $file) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $name = Str::uuid()->toString().'.'.$ext;

            $relPath = "clients/{$clientId}/{$name}";
            $thumbRel = "clients/{$clientId}/thumbs/{$name}";

            $disk->putFileAs("clients/{$clientId}", $file, $name);

            $absSrc = $disk->path($relPath);
            $absThumb = $disk->path($thumbRel);
            $this->makeThumbnail($absSrc, $absThumb, 200);

            ClientAttachment::create([
                'client_id' => $clientId,
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

        $this->files = [];
        $msg = $count === 1 ? 'Imagen subida.' : "$count imágenes subidas.";
        $this->dispatch('successAlert', ['message' => $msg]);
    }

    public function removeFile(int $i): void
    {
        if (isset($this->files[$i])) {
            unset($this->files[$i]);
            $this->files = array_values($this->files);
        }
    }

    public function questionDelete(int $id): void
    {
        if (! $this->puedeEliminar) {
            return;
        }
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function deleteAttachment(int $id): void
    {
        if (! $this->puedeEliminar) {
            return;
        }

        $att = ClientAttachment::where('client_id', $this->clientId)->find($id);
        if (! $att) {
            return;
        }

        $disk = Storage::disk('public');
        if ($att->path && $disk->exists($att->path)) {
            $disk->delete($att->path);
        }
        if ($att->thumb_path && $disk->exists($att->thumb_path)) {
            $disk->delete($att->thumb_path);
        }

        $att->delete();
        // Nota: no dispatcheamos successAlert porque custom.js ya muestra
        // el modal "Eliminado!" automáticamente tras confirmar questionDelete.
    }

    private function makeThumbnail(string $src, string $dst, int $maxWidth): bool
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

    public function render()
    {
        $attachments = ClientAttachment::where('client_id', $this->clientId)
            ->orderByDesc('id')
            ->get();

        return view('livewire.clients.gallery', compact('attachments'));
    }
}
