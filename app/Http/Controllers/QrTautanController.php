<?php

namespace App\Http\Controllers;

use App\Models\TautanPendek;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** BR-28: QR berisi URL PENDEK, dibangkitkan on-the-fly, tidak disimpan. */
class QrTautanController extends Controller
{
    public function __invoke(TautanPendek $tautan, string $format): Response
    {
        Gate::authorize('view', $tautan);
        abort_unless(in_array($format, ['svg', 'png'], true), 404);

        $hasil = (new Builder(
            writer: $format === 'svg' ? new SvgWriter : new PngWriter,
            data: $tautan->url_pendek,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 400,
            margin: 12,
        ))->build();

        return response($hasil->getString(), 200, [
            'Content-Type' => $hasil->getMimeType(),
            'Content-Disposition' => 'inline; filename="qr-'.$tautan->kode.'.'.$format.'"',
            'Cache-Control' => 'private, max-age=86400',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
