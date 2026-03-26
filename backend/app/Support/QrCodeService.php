<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeService
{
    public static function svg(string $payload, int $scale = 6): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => $scale,
            'imageBase64' => false,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($options))->render($payload);
    }

    public static function png(string $payload, int $scale = 8): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => $scale,
            'imageBase64' => false,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($options))->render($payload);
    }

    public static function dataUri(string $payload, int $scale = 6): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($payload, $scale));
    }

    public static function studentCardPng(string $payload, string $fullName, string $classroom, int $scale = 8): string
    {
        $qrPng = self::png($payload, $scale);
        $qrImage = imagecreatefromstring($qrPng);

        if ($qrImage === false) {
            return $qrPng;
        }

        $qrWidth = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);
        $canvasWidth = max(560, $qrWidth + 80);
        $canvasHeight = $qrHeight + 150;
        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);

        if ($canvas === false) {
            imagedestroy($qrImage);

            return $qrPng;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $dark = imagecolorallocate($canvas, 22, 52, 95);
        $muted = imagecolorallocate($canvas, 78, 96, 125);

        imagefill($canvas, 0, 0, $white);

        $qrOffsetX = (int) floor(($canvasWidth - $qrWidth) / 2);
        imagecopy($canvas, $qrImage, $qrOffsetX, 8, 0, 0, $qrWidth, $qrHeight);

        $fontRegular = self::resolveFontPath(false);
        $fontBold = self::resolveFontPath(true) ?? $fontRegular;
        $name = trim($fullName) !== '' ? trim($fullName) : 'Uchenik';
        $class = trim($classroom) !== '' ? trim($classroom) : '-';
        $nameY = $qrHeight + 56;
        $classY = $qrHeight + 92;

        if ($fontRegular !== null) {
            self::drawCenteredText($canvas, $name, $fontBold ?? $fontRegular, 22, $nameY, $dark, $canvasWidth);
            self::drawCenteredText($canvas, $class, $fontRegular, 18, $classY, $muted, $canvasWidth);
        } else {
            imagestring($canvas, 5, max(12, (int) (($canvasWidth - imagefontwidth(5) * strlen($name)) / 2)), $nameY - 20, $name, $dark);
            imagestring($canvas, 4, max(12, (int) (($canvasWidth - imagefontwidth(4) * strlen($class)) / 2)), $classY - 16, $class, $muted);
        }

        ob_start();
        imagepng($canvas);
        $binary = (string) ob_get_clean();

        imagedestroy($qrImage);
        imagedestroy($canvas);

        return $binary;
    }

    private static function drawCenteredText($image, string $text, string $fontPath, int $fontSize, int $baselineY, int $color, int $canvasWidth): void
    {
        $text = self::truncate($text, $fontSize === 22 ? 42 : 26);
        $box = imagettfbbox($fontSize, 0, $fontPath, $text);

        if ($box === false) {
            return;
        }

        $textWidth = (int) abs($box[2] - $box[0]);
        $x = (int) floor(($canvasWidth - $textWidth) / 2);
        imagettftext($image, $fontSize, 0, $x, $baselineY, $color, $fontPath, $text);
    }

    private static function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) > $limit
            ? rtrim(mb_substr($value, 0, $limit - 1)).'…'
            : $value;
    }

    private static function resolveFontPath(bool $bold = false): ?string
    {
        $paths = $bold
            ? [
                'C:\\Windows\\Fonts\\arialbd.ttf',
                'C:\\Windows\\Fonts\\segoeuib.ttf',
                'C:\\Windows\\Fonts\\tahomabd.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            ]
            : [
                'C:\\Windows\\Fonts\\arial.ttf',
                'C:\\Windows\\Fonts\\segoeui.ttf',
                'C:\\Windows\\Fonts\\tahoma.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}