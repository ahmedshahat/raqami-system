<?php

namespace Modules\Construction\Support;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

class PrintPdfFactory
{
    public const FONT = 'ibmplexsansarabic';

    public static function make(string $orientation): Mpdf
    {
        $fontDirectories = (new ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new FontVariables())->getDefaults()['fontdata'];
        $fontData[self::FONT] = [
            'R' => 'IBMPlexSansArabic-Regular.ttf',
            'B' => 'IBMPlexSansArabic-Bold.ttf',
            'useOTL' => 0xFF,
            'useKashida' => 75,
        ];
        $fontData['ibmplexsansarabicmedium'] = [
            'R' => 'IBMPlexSansArabic-Medium.ttf',
            'useOTL' => 0xFF,
        ];
        $fontData['ibmplexsansarabicsemibold'] = [
            'R' => 'IBMPlexSansArabic-SemiBold.ttf',
            'useOTL' => 0xFF,
        ];
        $fontData['ibmplexsansarabicbold'] = [
            'R' => 'IBMPlexSansArabic-Bold.ttf',
            'useOTL' => 0xFF,
        ];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => $orientation,
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'tempDir' => public_path('uploads/temp'),
            'fontDir' => array_merge($fontDirectories, [module_path('Construction', 'Resources/fonts')]),
            'fontdata' => $fontData,
            'default_font' => self::FONT,
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'autoArabic' => true,
            'useSubstitutions' => true,
        ]);
        $mpdf->SetDirectionality('rtl');

        return $mpdf;
    }
}
