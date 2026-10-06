<?php

namespace Modules\Construction\Http\Controllers;

use Illuminate\Http\Response;

class PrintFontController extends BaseController
{
    public function show(string $weight)
    {
        $files = [
            'regular' => 'IBMPlexSansArabic-Regular.ttf',
            'medium' => 'IBMPlexSansArabic-Medium.ttf',
            'semibold' => 'IBMPlexSansArabic-SemiBold.ttf',
            'bold' => 'IBMPlexSansArabic-Bold.ttf',
        ];

        abort_unless(isset($files[$weight]), Response::HTTP_NOT_FOUND);

        return response()->file(module_path('Construction', 'Resources/fonts/'.$files[$weight]), [
            'Content-Type' => 'font/ttf',
            'Cache-Control' => 'private, max-age=31536000',
        ]);
    }
}
