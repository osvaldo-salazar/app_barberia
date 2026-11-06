<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProofStore
{
    private const MAX_SIDE   = 1400;
    private const MAX_PIXELS = 40_000_000;

    public function store(UploadedFile $file, Appointment $a): string
    {
        $real = $file->getRealPath();
        $info = @getimagesize($real);

        if (! $info || $info[0] * $info[1] > self::MAX_PIXELS) {
            $this->fail();
        }

        $img = @imagecreatefromstring(file_get_contents($real));
        if (! $img) {
            $this->fail();
        }

        // Corrige la orientación si la foto trae datos EXIF
        if (function_exists('exif_read_data') && $info[2] === IMAGETYPE_JPEG) {
            $exif  = @exif_read_data($real);
            $angle = [3 => 180, 6 => -90, 8 => 90][is_array($exif) ? ($exif['Orientation'] ?? 1) : 1] ?? 0;
            if ($angle && ($rot = imagerotate($img, $angle, 0))) {
                $img = $rot;
            }
        }

        $w     = imagesx($img);
        $h     = imagesy($img);
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $nw    = max(1, (int) round($w * $scale));
        $nh    = max(1, (int) round($h * $scale));

        // Fondo blanco (para PNG con transparencia) y reducción
        $canvas = imagecreatetruecolor($nw, $nh);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        ob_start();
        imagejpeg($canvas, null, 82);
        $jpg = ob_get_clean();
        imagedestroy($canvas);

        $path = 'proofs/' . $a->starts_at->format('Y/m') . '/' . Str::uuid() . '.jpg';
        Storage::disk('local')->put($path, $jpg);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    /** Miniatura en base64 para incrustar en los PDF */
    public function thumbDataUri(?string $path, int $width = 520): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $img = @imagecreatefromstring(Storage::disk('local')->get($path));
        if (! $img) {
            return null;
        }

        if (imagesx($img) > $width) {
            $scaled = imagescale($img, $width);
            imagedestroy($img);
            $img = $scaled;
        }

        ob_start();
        imagejpeg($img, null, 70);
        $jpg = ob_get_clean();
        imagedestroy($img);

        return 'data:image/jpeg;base64,' . base64_encode($jpg);
    }

    private function fail(): never
    {
        throw ValidationException::withMessages(['proof' => 'No se pudo leer la foto. Prueba con otra imagen.']);
    }
}