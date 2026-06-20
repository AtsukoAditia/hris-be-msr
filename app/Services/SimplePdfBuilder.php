<?php

namespace App\Services;

final class SimplePdfBuilder
{
    private const PAGE_WIDTH = 595;

    private const PAGE_HEIGHT = 842;

    private const LEFT_MARGIN = 42;

    private const TOP_Y = 800;

    private const LINE_HEIGHT = 14;

    private const MAX_LINES = 52;

    public function render(array $lines, string $title = 'Document'): string
    {
        $pages = array_chunk($lines === [] ? [''] : $lines, self::MAX_LINES);
        $objects = [];
        $pageObjectIds = [];
        $nextId = 4;

        foreach ($pages as $pageLines) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageObjectIds[] = $pageId;

            $stream = $this->contentStream($pageLines, $title);
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentId,
            );
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn (int $id) => "{$id} 0 R", $pageObjectIds)).'] /Count '.count($pageObjectIds).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxId + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0)."\n";
        }
        $pdf .= "trailer\n<< /Size ".($maxId + 1).' /Root 1 0 R >>'."\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function contentStream(array $lines, string $title): string
    {
        $commands = [
            'BT',
            '/F1 16 Tf',
            self::LEFT_MARGIN.' '.self::TOP_Y.' Td',
            '('.$this->escape($title).') Tj',
            '0 -24 Td',
            '/F1 9 Tf',
        ];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $commands[] = '0 -'.self::LINE_HEIGHT.' Td';
            }
            $commands[] = '('.$this->escape((string) $line).') Tj';
        }
        $commands[] = 'ET';

        return implode("\n", $commands);
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
