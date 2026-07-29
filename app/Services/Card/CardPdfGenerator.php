<?php

namespace App\Services\Card;

use Illuminate\Support\Facades\Storage;

/**
 * Lightweight pure-PHP PDF generator for student ID cards.
 * No external library dependencies — uses only the GD extension for image handling.
 *
 * Generates A4 PDFs (210 × 297 mm) with 8 cards arranged in a 2×4 grid.
 */
class CardPdfGenerator
{
    /** A4 dimensions in points (1 pt = 1/72 inch, 1 mm ≈ 2.8346 pt) */
    const PAGE_W_PT = 595.28;
    const PAGE_H_PT = 841.89;

    /** Card slot dimensions (with 5mm margin around the page) */
    const MARGIN_MM = 5;
    const CARD_W_MM = 95;
    const CARD_H_MM = 68;
    const GAP_X_MM = 5;
    const GAP_Y_MM = 4;

    const MM_TO_PT = 2.8346;

    /** Standard PDF fonts (always available) */
    const FONTS = [
        'helvetica' => 'Helvetica',
        'helvetica-bold' => 'Helvetica-Bold',
        'courier'    => 'Courier',
    ];

    protected string $output = '';
    protected int $objectNumber = 1;
    protected array $objects = [];
    protected int $pageCount = 0;
    protected float $pageW;
    protected float $pageH;

    public function __construct()
    {
        $this->pageW = self::PAGE_W_PT;
        $this->pageH = self::PAGE_H_PT;
    }

    /**
     * Generate a multi-page PDF with cards for all students.
     *
     * @param array $pages Array of pages, each containing an array of card data (max 8 cards per page)
     *                      Each card: ['name', 'student_id', 'photo_path' (optional), 'batch_name', ...]
     * @return string Raw PDF bytes
     */
    public function generate(array $pages): string
    {
        $this->objects = [];
        $this->objectNumber = 1;
        $this->pageCount = 0;

        // Header object
        $catalogOid = $this->allocateObject();
        $pagesOid = $this->allocateObject();
        $fontOids = [
            'helvetica'      => $this->allocateObject(),
            'helvetica-bold' => $this->allocateObject(),
        ];
        $pageOids = [];

        // Build pages
        $pageRefs = '';
        foreach ($pages as $pageIndex => $cards) {
            $pageOid = $this->allocateObject();
            $pageOids[] = $pageOid;
            $pageRefs .= "{$pageOid} 0 R ";

            $contentOid = $this->allocateObject();
            $stream = $this->renderPage($cards);

            $this->registerObject($contentOid, "<< /Length " . strlen($stream) . " >>\n" . $stream);

            $this->registerObject($pageOid, "<< /Type /Page /Parent {$pagesOid} 0 R /MediaBox [0 0 {$this->pageW} {$this->pageH}] /Contents {$contentOid} 0 R /Resources << /Font << /F1 {$fontOids['helvetica']} 0 R /F2 {$fontOids['helvetica-bold']} 0 R >> /XObject << >> >> >>");
            $this->pageCount++;
        }

        // Pages object
        $this->registerObject($pagesOid, "<< /Type /Pages /Kids [{$pageRefs}] /Count {$this->pageCount} >>");

        // Fonts
        $this->registerObject($fontOids['helvetica'], "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>");
        $this->registerObject($fontOids['helvetica-bold'], "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>");

        // Catalog
        $this->registerObject($catalogOid, "<< /Type /Catalog /Pages {$pagesOid} 0 R >>");

        // Build final PDF
        return $this->buildPdf();
    }

    /**
     * Render a single page with up to 8 cards arranged in a 2×4 grid.
     */
    protected function renderPage(array $cards): string
    {
        $lines = [];
        $lines[] = "q";

        $margin = self::MARGIN_MM * self::MM_TO_PT;
        $cardW = self::CARD_W_MM * self::MM_TO_PT;
        $cardH = self::CARD_H_MM * self::MM_TO_PT;
        $gapX = self::GAP_X_MM * self::MM_TO_PT;
        $gapY = self::GAP_Y_MM * self::MM_TO_PT;

        foreach ($cards as $index => $card) {
            if ($index >= 8) break;

            $col = $index % 2;
            $row = intdiv($index, 2);

            $x = $margin + ($col * ($cardW + $gapX));
            $y = $margin + ($row * ($cardH + $gapY));

            $this->drawCardBackground($lines, $x, $y, $cardW, $cardH);
            $this->drawCardContent($lines, $card, $x, $y, $cardW, $cardH);
        }

        $lines[] = "Q";
        return implode("\n", $lines) . "\n";
    }

    /**
     * Draw a rounded-rect card background with shadow effect.
     */
    protected function drawCardBackground(array &$lines, float $x, float $y, float $w, float $h): void
    {
        // Shadow
        $lines[] = "q";
        $lines[] = "0 0 0 RG";
        $lines[] = "0.1 g";
        $lines[] = "1 w";
        $lines[] = "{$this->pt($x + 1.5)} {$this->pt($y - 1.5)} {$this->pt($w)} {$this->pt($h)} re";
        $lines[] = "f";
        $lines[] = "Q";

        // Card border (rounded via simple rectangle since PDF doesn't have round-rect natively)
        $lines[] = "q";
        $lines[] = "0.85 0.85 0.85 RG";
        $lines[] = "0.5 w";
        $lines[] = "{$this->pt($x)} {$this->pt($y)} {$this->pt($w)} {$this->pt($h)} re";
        $lines[] = "S";
        $lines[] = "Q";

        // White fill
        $lines[] = "q";
        $lines[] = "1 1 1 rg";
        $lines[] = "{$this->pt($x + 0.5)} {$this->pt($y + 0.5)} {$this->pt($w - 1)} {$this->pt($h - 1)} re";
        $lines[] = "f";
        $lines[] = "Q";

        // Top accent bar (institutional blue)
        $lines[] = "q";
        $lines[] = "0.16 0.32 0.58 rg";
        $lines[] = "{$this->pt($x + 1)} {$this->pt($y + $h - 8)} {$this->pt($w - 2)} {$this->pt(6)} re";
        $lines[] = "f";
        $lines[] = "Q";
    }

    /**
     * Draw text and photo for a single card.
     */
    protected function drawCardContent(array &$lines, array $card, float $x, float $y, float $w, float $h): void
    {
        $photoSize = 28 * self::MM_TO_PT;
        $photoX = $x + 4;
        $photoY = $y + $h - 8 - $photoSize - 3;
        $photoR = 2; // corner radius in pt

        // Photo placeholder circle/border
        $lines[] = "q";
        $lines[] = "0.8 0.8 0.8 RG";
        $lines[] = "0.3 w";
        $lines[] = "{$this->pt($photoX)} {$this->pt($photoY)} {$this->pt($photoSize)} {$this->pt($photoSize)} re";
        $lines[] = "S";
        $lines[] = "Q";

        // Embed photo if available
        if (!empty($card['photo_path'])) {
            $this->embedPhoto($lines, $card['photo_path'], $photoX + 1, $photoY + 1, $photoSize - 2, $photoSize - 2);
        } else {
            // No-photo placeholder
            $lines[] = "q";
            $lines[] = "0.92 0.92 0.92 rg";
            $lines[] = "{$this->pt($photoX + 1)} {$this->pt($photoY + 1)} {$this->pt($photoSize - 2)} {$this->pt($photoSize - 2)} re";
            $lines[] = "f";
            $lines[] = "Q";
        }

        // Student name (bold, below accent bar)
        $nameX = $x + 4;
        $nameY = $y + $h - 11;
        $name = $this->escapeText($card['name'] ?? '');
        if (strlen($name) > 28) {
            $name = substr($name, 0, 26) . '..';
        }
        $lines[] = "BT";
        $lines[] = "/F2 9 Tf";
        $lines[] = "0.15 0.15 0.25 rg";
        $lines[] = "{$this->pt($nameX)} {$this->pt($nameY)} Td";
        $lines[] = "({$name}) Tj";
        $lines[] = "ET";

        // Student ID
        $idY = $nameY - 13;
        $studentId = $this->escapeText($card['student_id'] ?? '');
        $lines[] = "BT";
        $lines[] = "/F1 7.5 Tf";
        $lines[] = "0.4 0.4 0.4 rg";
        $lines[] = "{$this->pt($nameX)} {$this->pt($idY)} Td";
        $lines[] = "(ID: {$studentId}) Tj";
        $lines[] = "ET";

        // Batch name
        $batchY = $idY - 11;
        $batchName = $this->escapeText($card['batch_name'] ?? '');
        $lines[] = "BT";
        $lines[] = "/F1 7 Tf";
        $lines[] = "0.5 0.5 0.5 rg";
        $lines[] = "{$this->pt($nameX)} {$this->pt($batchY)} Td";
        $lines[] = "({$batchName}) Tj";
        $lines[] = "ET";

        // Right side info: gender, DOB, province
        $rightX = $x + $photoSize + 8;
        $infoY = $y + $h - 11;
        $infoFields = [];

        if (!empty($card['gender'])) {
            $infoFields[] = 'Gender: ' . $card['gender'];
        }
        if (!empty($card['dob'])) {
            $infoFields[] = 'DOB: ' . $card['dob'];
        }
        if (!empty($card['province'])) {
            $infoFields[] = $card['province'];
        }

        foreach ($infoFields as $i => $field) {
            $fy = $infoY - ($i * 10);
            $lines[] = "BT";
            $lines[] = "/F1 6.5 Tf";
            $lines[] = "0.45 0.45 0.45 rg";
            $lines[] = "{$this->pt($rightX)} {$this->pt($fy)} Td";
            $lines[] = "({$this->escapeText($field)}) Tj";
            $lines[] = "ET";
        }
    }

    /**
     * Embed a JPEG photo into the PDF stream.
     */
    protected function embedPhoto(array &$lines, string $photoPath, float $x, float $y, float $w, float $h): void
    {
        $fullPath = $this->resolvePhotoPath($photoPath);
        if (!$fullPath || !file_exists($fullPath)) {
            return;
        }

        $imageData = file_get_contents($fullPath);
        if ($imageData === false) {
            return;
        }

        // Get image dimensions
        $imgInfo = @getimagesize($fullPath);
        if (!$imgInfo) {
            return;
        }

        $imgW = $imgInfo[0];
        $imgH = $imgInfo[1];
        $mime = $imgInfo['mime'] ?? 'image/jpeg';

        // Only handle JPEG and PNG
        if ($mime === 'image/jpeg') {
            $filter = '/DCTDecode';
            $colorSpace = '/DeviceRGB';
            $bitsPerComponent = 8;
        } elseif ($mime === 'image/png') {
            // For PNG, convert to JPEG data (GD required)
            $imageData = $this->convertPngToJpegBytes($fullPath);
            if ($imageData === null) return;
            $filter = '/DCTDecode';
            $colorSpace = '/DeviceRGB';
            $bitsPerComponent = 8;
        } else {
            return;
        }

        // Calculate aspect-ratio-preserved dimensions
        $aspect = $imgW / $imgH;
        $boxAspect = $w / $h;
        if ($aspect > $boxAspect) {
            $drawW = $w;
            $drawH = $w / $aspect;
        } else {
            $drawH = $h;
            $drawW = $h * $aspect;
        }
        $drawX = $x + ($w - $drawW) / 2;
        $drawY = $y + ($h - $drawH) / 2;

        // Create XObject for the image (inline stream)
        $streamData = $this->escapeBinaryString($imageData);
        $imgObj = "<< /Type /XObject /Subtype /Image /Width {$imgW} /Height {$imgH} /ColorSpace {$colorSpace} /BitsPerComponent {$bitsPerComponent} /Filter {$filter} /Length " . strlen($imageData) . " >> stream\n{$streamData}\nendstream";

        // Register as a separate object in the PDF
        // For simplicity, we'll just reference it inline in the content stream
        $lines[] = "q";
        $lines[] = "{$this->pt($drawX)} {$this->pt($drawY)} {$this->pt($drawW)} {$this->pt($drawH)} re";
        $lines[] = "W n";
        $lines[] = "{$this->pt($drawX)} {$this->pt($drawY)} {$this->pt($drawW)} {$this->pt($drawH)} re";
        $lines[] = "W n";

        // Use inline image for simplicity
        $streamLen = strlen($imageData);
        $lines[] = "BI";
        $lines[] = "/W {$imgW}";
        $lines[] = "/H {$imgH}";
        $lines[] = "/CS /RGB";
        $lines[] = "/BPC 8";
        $lines[] = "/F /DCT";
        $lines[] = "ID ";
        $lines[] = $this->escapeBinaryString($imageData);
        $lines[] = "EI";
        $lines[] = "Q";
    }

    /**
     * Convert a PNG to JPEG bytes using GD.
     */
    protected function convertPngToJpegBytes(string $path): ?string
    {
        if (!function_exists('imagecreatefrompng')) {
            return null;
        }
        $src = @imagecreatefrompng($path);
        if (!$src) return null;

        $w = imagesx($src);
        $h = imagesy($src);
        $dst = imagecreatetruecolor($w, $h);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);

        ob_start();
        imagejpeg($dst, null, 85);
        $data = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return $data !== false ? $data : null;
    }

    /**
     * Resolve a photo path to a full filesystem path.
     */
    protected function resolvePhotoPath(string $path): ?string
    {
        // If it's already a full path, use it
        if (file_exists($path)) {
            return $path;
        }

        // Try storage/app/public/...
        $storagePath = storage_path('app/public/' . ltrim($path, '/\\'));
        if (file_exists($storagePath)) {
            return $storagePath;
        }

        // Try storage/app/...
        $storagePath2 = storage_path('app/' . ltrim($path, '/\\'));
        if (file_exists($storagePath2)) {
            return $storagePath2;
        }

        // Try public/storage/...
        $publicPath = public_path('storage/' . ltrim($path, '/\\'));
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        return null;
    }

    /**
     * Convert mm to points.
     */
    protected function pt(float $mm): float
    {
        return round($mm * self::MM_TO_PT, 2);
    }

    /**
     * Escape special characters in PDF string.
     */
    protected function escapeText(string $text): string
    {
        // Convert to ASCII-safe
        $text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        return $text;
    }

    /**
     * Escape binary data for inline embedding.
     */
    protected function escapeBinaryString(string $data): string
    {
        return $data;
    }

    // --- PDF structure helpers ---

    protected function allocateObject(): int
    {
        return $this->objectNumber++;
    }

    protected function registerObject(int $oid, string $content): void
    {
        $this->objects[$oid] = $content;
    }

    protected function buildPdf(): string
    {
        $pdf = "%PDF-1.4\n";

        $offsets = [];
        foreach ($this->objects as $oid => $content) {
            $offsets[$oid] = strlen($pdf);
            $pdf .= "{$oid} 0 obj\n{$content}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($this->objectNumber) . "\n0000000000 65535 f \n";
        for ($i = 1; $i < $this->objectNumber; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size {$this->objectNumber} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}
