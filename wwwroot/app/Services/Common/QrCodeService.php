<?php

namespace App\Services\Common;

use BaconQrCode\Writer;
use App\Traits\ServiceTraits;
use BaconQrCode\Renderer\ImageRenderer;
use Illuminate\Support\Facades\Storage;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

class QrCodeService
{
    use ServiceTraits;

    public function excute($text = '', $path = '', $size = 500, $margin = 2)
    {
        $disk = 'public';
        $storage = Storage::disk($disk);

        if ($storage->exists($path)) {
            return [
                'path' => $path,
                'url' => $storage->url($path),
                'exists' => true,
            ];
        }

        $backend = new ImagickImageBackEnd();
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin, null, null, null, ErrorCorrectionLevel::H()),
            $backend
        );
        $writer = new Writer($renderer);
        $png = $writer->writeString($text);

        $storage->put($path, $png);

        return [
            'path' => $path,
            'url' => $storage->url($path),
        ];
    }
}
