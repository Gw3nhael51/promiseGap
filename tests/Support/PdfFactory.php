<?php

namespace App\Tests\Support;

/**
 * Builds minimal valid PDF files for extraction tests
 */
final class PdfFactory
{
    /**
     * Builds a PDF with one page per entry (an empty string produces a page without text layer)
     *
     * @param string[] $pageTexts Text of each page
     * @return string Raw PDF content
     */
    public static function build(array $pageTexts): string
    {
        $pageCount = count($pageTexts);
        $objects = [];
        $kids = [];
        for ($i = 0; $i < $pageCount; ++$i) {
            $kids[] = sprintf('%d 0 R', 4 + $i * 2);
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), $pageCount);
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        foreach ($pageTexts as $i => $text) {
            $pageId = 4 + $i * 2;
            $contentId = $pageId + 1;
            $stream = $text === '' ? '' : sprintf('BT /F1 12 Tf 72 720 Td (%s) Tj ET', addcslashes($text, '()\\'));
            $objects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents %d 0 R /Resources << /Font << /F1 3 0 R >> >> >>', $contentId);
            $objects[$contentId] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($stream), $stream);
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $id, $body);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n0000000000 65535 f \n", count($objects) + 1);
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", count($objects) + 1, $xrefOffset);

        return $pdf;
    }

    /**
     * Writes a PDF to a temporary file
     *
     * @param string[] $pageTexts Text of each page
     * @return string Absolute path of the file
     */
    public static function write(array $pageTexts): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
        file_put_contents($path, self::build($pageTexts));

        return $path;
    }
}
