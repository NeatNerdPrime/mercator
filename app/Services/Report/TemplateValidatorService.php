<?php

namespace App\Services\Report;

use DOMDocument;
use ZipArchive;

/**
 * Validates a .docx file before it is accepted as the global cartography report template.
 */
class TemplateValidatorService
{
    public const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    public const CONTENT_TAG = ':content:';

    public const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @return array<int, string> translation keys describing every validation failure; empty when valid.
     */
    public function validate(string $path): array
    {
        if (! is_file($path)) {
            return ['report_template.errors.not_a_docx'];
        }

        if (filesize($path) > self::MAX_SIZE_BYTES) {
            return ['report_template.errors.too_large'];
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return ['report_template.errors.not_a_docx'];
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($documentXml === false) {
            return ['report_template.errors.not_a_docx'];
        }

        $dom = new DOMDocument;
        $previousUseErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($documentXml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if (! $loaded) {
            return ['report_template.errors.not_a_docx'];
        }

        $occurrences = $this->countContentTagOccurrences($dom);

        if ($occurrences === 0) {
            return ['report_template.errors.tag_missing'];
        }

        if ($occurrences > 1) {
            return ['report_template.errors.tag_multiple'];
        }

        return [];
    }

    /**
     * Counts paragraphs whose concatenated <w:t> text contains the :content: tag. Concatenating
     * every text node within each paragraph (rather than pattern-matching the raw XML, as
     * TemplateProcessor::fixBrokenMacros() does) finds the tag regardless of how Word has split
     * it across multiple <w:r> runs, with no separate "run normalization" pass needed.
     */
    private function countContentTagOccurrences(DOMDocument $dom): int
    {
        $count = 0;

        foreach ($dom->getElementsByTagNameNS(self::WORD_NAMESPACE, 'p') as $paragraph) {
            $text = '';

            foreach ($paragraph->getElementsByTagNameNS(self::WORD_NAMESPACE, 't') as $textNode) {
                $text .= $textNode->textContent;
            }

            if (str_contains($text, self::CONTENT_TAG)) {
                $count++;
            }
        }

        return $count;
    }
}
