<?php

namespace App\Services\Report;

use DOMDocument;
use DOMElement;
use DOMNode;
use Generator;
use Illuminate\Support\Carbon;
use RuntimeException;
use ZipArchive;

/**
 * Merges a PhpWord-generated report body into a user-supplied .docx template, at the ZIP/OOXML
 * level (ZipArchive + DOMDocument only — never PhpWord's IOFactory::load() or
 * TemplateProcessor::setComplexBlock(), which round-trip through PhpWord's own object model and
 * would lose template features it doesn't understand).
 *
 * The template's paragraph containing the literal ":content:" tag (see
 * TemplateValidatorService::CONTENT_TAG) is replaced by the entire body of the generated report,
 * except for the report's own trailing <w:sectPr> — the template's own section properties
 * (margins, orientation, headers/footers) govern the merged document instead.
 */
class TemplateMergerService
{
    private const WORD_NS = TemplateValidatorService::WORD_NAMESPACE;

    private const RELS_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PACKAGE_RELS_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const DCTERMS_NS = 'http://purl.org/dc/terms/';

    /**
     * Optional, freely repeatable text tags: unlike TemplateValidatorService::CONTENT_TAG, a
     * template may place these anywhere (cover page, header, footer), any number of times, or not
     * at all -- they are substituted in place as plain text, not used to locate a paragraph to
     * replace wholesale.
     */
    private const TIMESTAMP_TAG = ':timestamp:';

    private const VERSION_TAG = ':version:';

    private const TIMESTAMP_FORMAT = 'd/m/Y H:i';

    /**
     * PhpWord 1.4's Word2007 writer embeds images as legacy VML (<w:pict><v:shape><v:imagedata
     * r:id="...">), not modern DrawingML (<w:drawing>/<a:blip r:embed="...">) — confirmed by
     * inspecting an actually-generated cartography report. Media merging below targets that
     * representation; a future PhpWord upgrade switching to DrawingML would need this updated.
     */
    private const VML_NS = 'urn:schemas-microsoft-com:vml';

    private const OFFICE_NS = 'urn:schemas-microsoft-com:office:office';

    private const OFFICE_WORD_NS = 'urn:schemas-microsoft-com:office:word';

    private const HYPERLINK_REL_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';

    private const IMAGE_EXTENSION_MIME_MAP = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'tiff' => 'image/tiff',
        'svg' => 'image/svg+xml',
    ];

    /**
     * CT_TblPrBase's schema child sequence (see fixTablePropertyChildOrder()). Elements PhpWord
     * never emits (tblStyle, tblOverlap, tblStyleRowBandSize, tblStyleColBandSize, tblCaption,
     * tblDescription) are listed anyway so the ordering stays correct if a future template merge
     * ever needs them.
     */
    private const TBL_PR_CHILD_ORDER = [
        'tblStyle', 'tblpPr', 'tblOverlap', 'bidiVisual', 'tblStyleRowBandSize',
        'tblStyleColBandSize', 'tblW', 'jc', 'tblCellSpacing', 'tblInd',
        'tblBorders', 'shd', 'tblLayout', 'tblCellMar', 'tblLook',
        'tblCaption', 'tblDescription',
    ];

    public function merge(string $templatePath, string $bodyReportPath, string $outputPath): void
    {
        copy($templatePath, $outputPath);

        $output = new ZipArchive;
        if ($output->open($outputPath) !== true) {
            throw new RuntimeException("Unable to open {$outputPath} for merging.");
        }

        $report = new ZipArchive;
        if ($report->open($bodyReportPath) !== true) {
            $output->close();

            throw new RuntimeException("Unable to open {$bodyReportPath} for merging.");
        }

        try {
            $this->performMerge($output, $report);
        } catch (\Throwable $e) {
            $output->close();
            $report->close();
            @unlink($outputPath);

            throw $e;
        }

        $output->close();
        $report->close();
    }

    private function performMerge(ZipArchive $output, ZipArchive $report): void
    {
        $templateDoc = $this->loadXmlEntry($output, 'word/document.xml');
        $reportDoc = $this->loadXmlEntry($report, 'word/document.xml');

        $targetParagraph = $this->findContentTagParagraph($templateDoc);

        if ($targetParagraph === null) {
            throw new RuntimeException('The template does not contain the ":content:" tag.');
        }

        $this->ensureNamespaceDeclared($templateDoc, 'w', self::WORD_NS);
        $this->ensureNamespaceDeclared($templateDoc, 'r', self::RELS_NS);
        $this->ensureNamespaceDeclared($templateDoc, 'v', self::VML_NS);
        $this->ensureNamespaceDeclared($templateDoc, 'o', self::OFFICE_NS);
        $this->ensureNamespaceDeclared($templateDoc, 'w10', self::OFFICE_WORD_NS);

        $now = Carbon::now();

        // Run before the fragment is inserted below, so this only ever scans the template's own
        // (small) cover page / header / footer content -- the generated report body never contains
        // these tags, so there is nothing to gain from also scanning it.
        $this->substituteTemplateTags($output, $templateDoc, $now);

        $fragmentNodes = $this->extractBodyContentNodes($reportDoc);

        $this->fixTablePropertyOrder($fragmentNodes);
        $this->fixTablePropertyChildOrder($fragmentNodes);
        $this->fixUnwrappedLineBreaks($reportDoc, $fragmentNodes);
        $this->renumberBookmarkIds($templateDoc, $fragmentNodes);

        // Loaded once and mutated in place by mergeMedia()/mergeStylesAndNumbering() (which may
        // itself need to register a brand-new word/numbering.xml part) rather than each reloading
        // and rewriting these two parts independently: ZipArchive::getFromName() never reflects an
        // uncommitted addFromString() from earlier in the same session (verified empirically), so a
        // second independent load+write of the same part would silently discard the first one's
        // changes.
        $outputRels = $this->loadXmlEntry($output, 'word/_rels/document.xml.rels');
        $contentTypes = $this->loadXmlEntry($output, '[Content_Types].xml');

        $ridMap = $this->mergeMedia($output, $report, $fragmentNodes, $outputRels, $contentTypes);
        $this->remapImageEmbeds($fragmentNodes, $ridMap);
        $this->mergeHyperlinks($report, $fragmentNodes, $outputRels);

        $this->mergeStylesAndNumbering($output, $report, $fragmentNodes, $outputRels, $contentTypes);

        $output->addFromString('word/_rels/document.xml.rels', $outputRels->saveXML());
        $output->addFromString('[Content_Types].xml', $contentTypes->saveXML());

        $parent = $targetParagraph->parentNode;
        foreach ($fragmentNodes as $node) {
            $imported = $templateDoc->importNode($node, true);
            $parent->insertBefore($imported, $targetParagraph);
        }
        $parent->removeChild($targetParagraph);

        $output->addFromString('word/document.xml', $templateDoc->saveXML());

        $this->applyUpdateFields($output);
        $this->updateCoreProperties($output, $now);
    }

    private function loadXmlEntry(ZipArchive $zip, string $name): DOMDocument
    {
        $xml = $zip->getFromName($name);

        if ($xml === false) {
            throw new RuntimeException("Missing {$name} in archive.");
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException("Malformed XML in {$name}.");
        }

        return $dom;
    }

    private function ensureNamespaceDeclared(DOMDocument $dom, string $prefix, string $uri): void
    {
        $root = $dom->documentElement;

        if ($root->lookupNamespaceURI($prefix) === $uri) {
            return;
        }

        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:'.$prefix, $uri);
    }

    /**
     * Finds the paragraph whose concatenated <w:t> text contains ":content:". Mirrors
     * TemplateValidatorService's detection so a tag fragmented across multiple <w:r> runs is
     * found without any separate "run normalization" pass.
     */
    private function findContentTagParagraph(DOMDocument $dom): ?DOMElement
    {
        foreach ($dom->getElementsByTagNameNS(self::WORD_NS, 'p') as $paragraph) {
            $text = '';
            foreach ($paragraph->getElementsByTagNameNS(self::WORD_NS, 't') as $textNode) {
                $text .= $textNode->textContent;
            }

            if (str_contains($text, TemplateValidatorService::CONTENT_TAG)) {
                return $paragraph;
            }
        }

        return null;
    }

    /**
     * Substitutes :timestamp: and :version: wherever they appear in the template's own
     * word/document.xml (already loaded, mutated in place, written by the caller) and in every
     * word/header*.xml / word/footer*.xml part of the package — those are separate documents, each
     * with their own <w:p> paragraphs, not reachable from $templateDoc.
     */
    private function substituteTemplateTags(ZipArchive $output, DOMDocument $templateDoc, Carbon $now): void
    {
        $replacements = [
            self::TIMESTAMP_TAG => $now->format(self::TIMESTAMP_FORMAT),
            self::VERSION_TAG => (string) app('mercator.version'),
        ];

        $this->replaceTagsThroughoutDocument($templateDoc, $replacements);

        for ($i = 0; $i < $output->numFiles; $i++) {
            $name = $output->getNameIndex($i);
            if (! preg_match('#^word/(header|footer)\d*\.xml$#', $name)) {
                continue;
            }

            $part = $this->loadXmlEntry($output, $name);
            $this->replaceTagsThroughoutDocument($part, $replacements);
            $output->addFromString($name, $part->saveXML());
        }
    }

    /**
     * @param  array<string, string>  $replacements  tag => literal replacement text
     */
    private function replaceTagsThroughoutDocument(DOMDocument $dom, array $replacements): void
    {
        foreach ($dom->getElementsByTagNameNS(self::WORD_NS, 'p') as $paragraph) {
            foreach ($replacements as $tag => $value) {
                while ($this->replaceFirstTagOccurrence($paragraph, $tag, $value)) {
                    // Paragraph text is rebuilt after each replacement (node boundaries shift), so
                    // re-scanning from scratch is required to find any further occurrence of $tag.
                }
            }
        }
    }

    /**
     * Finds and replaces the first occurrence of $tag within $paragraph's <w:t> descendants,
     * splicing the literal replacement text into whichever single node contains the tag's start
     * byte and blanking/truncating every other node the tag spans -- so a tag Word has fragmented
     * across multiple runs (e.g. after a spell-check pass) is still found and replaced, the same
     * way findContentTagParagraph() finds a fragmented ":content:" via concatenation, except here
     * the surrounding text in the paragraph must survive (only the tag itself is replaced, not the
     * whole paragraph). Byte offsets are used throughout (not mb_* / character counts): since PHP
     * strings are plain byte sequences and no individual <w:t> node's text is ever a broken UTF-8
     * sequence on its own, consistently using byte lengths on both sides of the offset math lines
     * up correctly regardless of multi-byte characters elsewhere in the surrounding text.
     */
    private function replaceFirstTagOccurrence(DOMElement $paragraph, string $tag, string $value): bool
    {
        $textNodes = [];
        $concatenated = '';
        foreach ($paragraph->getElementsByTagNameNS(self::WORD_NS, 't') as $textNode) {
            $textNodes[] = $textNode;
            $concatenated .= $textNode->textContent;
        }

        $tagStart = strpos($concatenated, $tag);
        if ($tagStart === false) {
            return false;
        }
        $tagEnd = $tagStart + strlen($tag);

        $cursor = 0;
        $inserted = false;
        foreach ($textNodes as $textNode) {
            $nodeText = $textNode->textContent;
            $nodeStart = $cursor;
            $nodeEnd = $cursor + strlen($nodeText);

            $overlapStart = max($tagStart, $nodeStart);
            $overlapEnd = min($tagEnd, $nodeEnd);

            if ($overlapStart < $overlapEnd) {
                $before = substr($nodeText, 0, $overlapStart - $nodeStart);
                $after = substr($nodeText, $overlapEnd - $nodeStart);
                $insertion = (! $inserted && $overlapStart === $tagStart) ? $value : '';
                $textNode->textContent = $before.$insertion.$after;
                $inserted = $inserted || $overlapStart === $tagStart;
            }

            $cursor = $nodeEnd;
        }

        return true;
    }

    /**
     * Returns the report's <w:body> children, excluding its own trailing <w:sectPr> (the final
     * section properties, always the last direct child of <w:body>). Any *intermediate* section
     * break is represented as a <w:sectPr> nested inside a paragraph's <w:pPr> rather than as a
     * direct child of <w:body>, so this check naturally leaves those untouched — no separate
     * "preserve intermediate sectPr" logic is needed.
     *
     * @return array<int, DOMNode>
     */
    private function extractBodyContentNodes(DOMDocument $reportDoc): array
    {
        $body = $reportDoc->getElementsByTagNameNS(self::WORD_NS, 'body')->item(0);

        if (! $body instanceof DOMElement) {
            throw new RuntimeException('Generated report has no <w:body>.');
        }

        $nodes = [];
        foreach ($body->childNodes as $child) {
            $nodes[] = $child;
        }

        $last = end($nodes);
        if ($last instanceof DOMElement && $last->namespaceURI === self::WORD_NS && $last->localName === 'sectPr') {
            array_pop($nodes);
        }

        return $nodes;
    }

    /**
     * PhpWord 1.4's Word2007 writer emits every <w:tbl> with <w:tblGrid> before <w:tblPr>
     * (Writer\Word2007\Element\Table::write() writes the columns, then the style), but CT_Tbl's
     * schema sequence requires tblPr before tblGrid. Swaps the two elements back into schema order
     * for every <w:tbl> in the fragment, at any nesting depth, before it's merged into the
     * template. See also fixTablePropertyChildOrder(), which fixes a second, independent ordering
     * defect inside <w:tblPr> itself.
     *
     * @param  array<int, DOMNode>  $fragmentNodes
     */
    private function fixTablePropertyOrder(array $fragmentNodes): void
    {
        foreach ($this->collectElementsByTagNS($fragmentNodes, self::WORD_NS, 'tbl') as $tbl) {
            $tblPr = null;
            $tblGrid = null;
            foreach ($tbl->childNodes as $child) {
                if (! $child instanceof DOMElement || $child->namespaceURI !== self::WORD_NS) {
                    continue;
                }
                if ($child->localName === 'tblPr') {
                    $tblPr = $child;
                } elseif ($child->localName === 'tblGrid') {
                    $tblGrid = $child;
                }
            }

            if ($tblPr !== null && $tblGrid !== null) {
                $tbl->insertBefore($tblPr, $tblGrid);
            }
        }
    }

    /**
     * CT_TblPrBase's schema sequence is (in order): tblStyle, tblpPr, tblOverlap, bidiVisual,
     * tblStyleRowBandSize, tblStyleColBandSize, tblW, jc, tblCellSpacing, tblInd, tblBorders, shd,
     * tblLayout, tblCellMar, tblLook (confirmed against the published OOXML schema documentation).
     * PhpWord 1.4's Writer\Word2007\Style\Table::writeStyle() instead writes, in this fixed order:
     * jc, tblW, tblCellSpacing, tblInd, tblLayout, tblpPr (position), bidiVisual, tblCellMar,
     * tblBorders -- e.g. tblLayout ends up before tblCellMar/tblBorders instead of after, and jc
     * before tblW instead of after. Word's reader is strict about this content-model sequence and
     * removes an out-of-order <w:tblPr> wholesale ("Repaired Records: Table properties from
     * /word/document.xml part"); LibreOffice is not. Reorders every <w:tblPr>'s direct children
     * into schema order, at any nesting depth, before the fragment is merged into the template.
     *
     * @param  array<int, DOMNode>  $fragmentNodes
     */
    private function fixTablePropertyChildOrder(array $fragmentNodes): void
    {
        $rank = array_flip(self::TBL_PR_CHILD_ORDER);

        foreach ($this->collectElementsByTagNS($fragmentNodes, self::WORD_NS, 'tblPr') as $tblPr) {
            $children = [];
            foreach ($tblPr->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $children[] = $child;
                }
            }

            usort($children, fn (DOMElement $a, DOMElement $b) => ($rank[$a->localName] ?? PHP_INT_MAX) <=> ($rank[$b->localName] ?? PHP_INT_MAX));

            foreach ($children as $child) {
                $tblPr->appendChild($child);
            }
        }
    }

    /**
     * PhpWord 1.4's Writer\Word2007\Element\TextBreak::write() writes a bare <w:br/> (no <w:r>
     * wrapper) whenever the break is written "withoutP" — i.e. straight into an already-open
     * run-level container such as a TextRun or, critically, a list item (ListItemRun): our own
     * WordHelper::addHtmlSafely() feeds report HTML (e.g. an entity's rich-text "Description")
     * through PhpWord's HTML parser, and a `<li>...<br>...</li>` in that source reliably hits this
     * path. A <w:br/> directly under <w:p> (sibling of <w:r>, not inside one) is well-formed XML
     * but violates CT_R's content model -- Word's reader rejects it ("found unreadable content"),
     * while LibreOffice's more forgiving one renders it anyway, which is why this only ever showed
     * up for report fields built from user-entered rich text. Wraps every such orphaned <w:br/> in
     * a <w:r> in place.
     *
     * @param  array<int, DOMNode>  $fragmentNodes
     */
    private function fixUnwrappedLineBreaks(DOMDocument $reportDoc, array $fragmentNodes): void
    {
        foreach ($this->collectElementsByTagNS($fragmentNodes, self::WORD_NS, 'br') as $br) {
            $parent = $br->parentNode;
            if (! $parent instanceof DOMElement || $parent->namespaceURI !== self::WORD_NS || $parent->localName !== 'p') {
                continue;
            }

            $run = $reportDoc->createElementNS(self::WORD_NS, 'w:r');
            $parent->replaceChild($run, $br);
            $run->appendChild($br);
        }
    }

    /**
     * @param  array<int, DOMNode>  $roots
     * @return Generator<DOMElement>
     */
    private function walkElements(array $roots): Generator
    {
        foreach ($roots as $root) {
            if (! $root instanceof DOMElement) {
                continue;
            }

            yield $root;

            foreach ($root->childNodes as $child) {
                yield from $this->walkElements([$child]);
            }
        }
    }

    /**
     * @param  array<int, DOMNode>  $roots
     * @return array<int, DOMElement>
     */
    private function collectElementsByTagNS(array $roots, string $ns, string $localName): array
    {
        $matches = [];
        foreach ($this->walkElements($roots) as $element) {
            if ($element->namespaceURI === $ns && $element->localName === $localName) {
                $matches[] = $element;
            }
        }

        return $matches;
    }

    /**
     * Renumbers the fragment's <w:bookmarkStart>/<w:bookmarkEnd> numeric w:id attributes so they
     * cannot collide with bookmarks already present in the template, starting right after the
     * template's own highest existing id. Bookmark *names* (getUID()-based, e.g. "ENTITY_1", or
     * PhpWord's own "_Toc1") are left untouched — only the numeric id, which has no meaning
     * outside pairing a start with its end, is renumbered.
     *
     * @param  array<int, DOMNode>  $fragmentNodes
     */
    private function renumberBookmarkIds(DOMDocument $templateDoc, array $fragmentNodes): void
    {
        $maxId = -1;
        foreach ($this->collectElementsByTagNS([$templateDoc->documentElement], self::WORD_NS, 'bookmarkStart') as $start) {
            $maxId = max($maxId, (int) $start->getAttributeNS(self::WORD_NS, 'id'));
        }

        $nextId = $maxId + 1;
        $idMap = [];

        foreach ($this->collectElementsByTagNS($fragmentNodes, self::WORD_NS, 'bookmarkStart') as $start) {
            $oldId = $start->getAttributeNS(self::WORD_NS, 'id');
            if (! isset($idMap[$oldId])) {
                $idMap[$oldId] = (string) $nextId++;
            }
            $start->getAttributeNodeNS(self::WORD_NS, 'id')->value = $idMap[$oldId];
        }

        foreach ($this->collectElementsByTagNS($fragmentNodes, self::WORD_NS, 'bookmarkEnd') as $end) {
            $oldId = $end->getAttributeNS(self::WORD_NS, 'id');
            if (isset($idMap[$oldId])) {
                $end->getAttributeNodeNS(self::WORD_NS, 'id')->value = $idMap[$oldId];
            }
        }
    }

    /**
     * Copies every word/media/* file actually referenced (via a <v:imagedata r:id="..."> inside
     * the surviving fragment nodes) from the report into the template, renaming on filename
     * collision and assigning fresh relationship ids, then registers the new relationships and,
     * if needed, new content-type defaults. Only relationships the fragment actually uses are
     * touched — the report's own cover page/TOC images (already stripped out with the rest of the
     * report's own wrapper) are never dragged in.
     *
     * @param  array<int, DOMNode>  $fragmentNodes
     * @return array<string, string> old report rId => new template rId
     */
    private function mergeMedia(ZipArchive $output, ZipArchive $report, array $fragmentNodes, DOMDocument $outputRels, DOMDocument $contentTypes): array
    {
        $usedRids = [];
        foreach ($this->collectElementsByTagNS($fragmentNodes, self::VML_NS, 'imagedata') as $element) {
            if ($element->hasAttributeNS(self::RELS_NS, 'id')) {
                $usedRids[$element->getAttributeNS(self::RELS_NS, 'id')] = true;
            }
        }

        if ($usedRids === []) {
            return [];
        }

        $reportRels = $this->loadXmlEntry($report, 'word/_rels/document.xml.rels');
        $reportTargets = [];
        foreach ($reportRels->getElementsByTagNameNS(self::PACKAGE_RELS_NS, 'Relationship') as $rel) {
            $id = $rel->getAttribute('Id');
            if (isset($usedRids[$id])) {
                $reportTargets[$id] = $rel->getAttribute('Target');
            }
        }

        $nextRid = 1;
        foreach ($outputRels->getElementsByTagNameNS(self::PACKAGE_RELS_NS, 'Relationship') as $rel) {
            if (preg_match('/^rId(\d+)$/', $rel->getAttribute('Id'), $m)) {
                $nextRid = max($nextRid, (int) $m[1] + 1);
            }
        }

        $existingMediaNames = [];
        for ($i = 0; $i < $output->numFiles; $i++) {
            $name = $output->getNameIndex($i);
            if (str_starts_with($name, 'word/media/')) {
                $existingMediaNames[basename($name)] = true;
            }
        }

        $ridMap = [];
        $newExtensions = [];

        foreach ($reportTargets as $oldRid => $target) {
            $bytes = $report->getFromName('word/'.$target);
            if ($bytes === false) {
                continue;
            }

            $basename = basename($target);
            $newBasename = $this->uniqueMediaName($basename, $existingMediaNames);
            $existingMediaNames[$newBasename] = true;

            $output->addFromString('word/media/'.$newBasename, $bytes);

            $newRid = 'rId'.$nextRid++;
            $ridMap[$oldRid] = $newRid;

            $relationship = $outputRels->createElementNS(
                self::PACKAGE_RELS_NS,
                'Relationship'
            );
            $relationship->setAttribute('Id', $newRid);
            $relationship->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
            $relationship->setAttribute('Target', 'media/'.$newBasename);
            $outputRels->documentElement->appendChild($relationship);

            $extension = strtolower(pathinfo($newBasename, PATHINFO_EXTENSION));
            if ($extension !== '') {
                $newExtensions[$extension] = true;
            }
        }

        $this->ensureContentTypeDefaults($contentTypes, $newExtensions);

        return $ridMap;
    }

    /**
     * @param  array<string, true>  $existingNames
     */
    private function uniqueMediaName(string $basename, array $existingNames): string
    {
        if (! isset($existingNames[$basename])) {
            return $basename;
        }

        $extension = pathinfo($basename, PATHINFO_EXTENSION);
        $stem = pathinfo($basename, PATHINFO_FILENAME);

        $suffix = 1;
        do {
            $candidate = $extension !== '' ? "{$stem}_{$suffix}.{$extension}" : "{$stem}_{$suffix}";
            $suffix++;
        } while (isset($existingNames[$candidate]));

        return $candidate;
    }

    /**
     * @param  array<string, true>  $extensions
     */
    private function ensureContentTypeDefaults(DOMDocument $contentTypes, array $extensions): void
    {
        if ($extensions === []) {
            return;
        }

        $declared = [];
        foreach ($contentTypes->getElementsByTagName('Default') as $default) {
            $declared[strtolower($default->getAttribute('Extension'))] = true;
        }

        foreach (array_keys($extensions) as $extension) {
            if (isset($declared[$extension])) {
                continue;
            }

            $default = $contentTypes->createElement('Default');
            $default->setAttribute('Extension', $extension);
            $default->setAttribute('ContentType', self::IMAGE_EXTENSION_MIME_MAP[$extension] ?? 'application/octet-stream');
            $contentTypes->documentElement->appendChild($default);
        }
    }

    /**
     * @param  array<int, DOMNode>  $fragmentNodes
     * @param  array<string, string>  $ridMap
     */
    private function remapImageEmbeds(array $fragmentNodes, array $ridMap): void
    {
        if ($ridMap === []) {
            return;
        }

        foreach ($this->collectElementsByTagNS($fragmentNodes, self::VML_NS, 'imagedata') as $element) {
            if (! $element->hasAttributeNS(self::RELS_NS, 'id')) {
                continue;
            }

            $oldRid = $element->getAttributeNS(self::RELS_NS, 'id');
            if (isset($ridMap[$oldRid])) {
                $element->getAttributeNodeNS(self::RELS_NS, 'id')->value = $ridMap[$oldRid];
            }
        }
    }

    /**
     * Copies every external hyperlink relationship the fragment references (via a <w:hyperlink
     * r:id="..."> -- an *internal*, same-document link instead carries its target as a plain
     * w:anchor attribute and needs no relationship at all) into the template, assigning fresh
     * relationship ids and remapping the fragment's r:id attributes to match. Unlike mergeMedia(),
     * there's no file to copy: an external hyperlink relationship is just Type + Target +
     * TargetMode="External" (e.g. a "mailto:" link from a report field's rich-text HTML, or an
     * addLink() call with $internal=false). Without this, every such link keeps referencing the
     * *report's own* relationship id, which doesn't exist in the merged package's rels -- an
     * unresolvable r:id that Word's reader can't recover from, and which was reliably reported as
     * "unreadable content" ("Table Properties") for every entity with a hyperlinked contact point.
     *
     * @param  array<int, DOMNode>  $fragmentNodes
     */
    private function mergeHyperlinks(ZipArchive $report, array $fragmentNodes, DOMDocument $outputRels): void
    {
        $usedRids = [];
        foreach ($this->collectElementsByTagNS($fragmentNodes, self::WORD_NS, 'hyperlink') as $element) {
            if ($element->hasAttributeNS(self::RELS_NS, 'id')) {
                $usedRids[$element->getAttributeNS(self::RELS_NS, 'id')] = true;
            }
        }

        if ($usedRids === []) {
            return;
        }

        $reportRels = $this->loadXmlEntry($report, 'word/_rels/document.xml.rels');
        $reportHyperlinks = [];
        foreach ($reportRels->getElementsByTagNameNS(self::PACKAGE_RELS_NS, 'Relationship') as $rel) {
            $id = $rel->getAttribute('Id');
            if (isset($usedRids[$id]) && $rel->getAttribute('Type') === self::HYPERLINK_REL_TYPE) {
                $reportHyperlinks[$id] = $rel;
            }
        }

        if ($reportHyperlinks === []) {
            return;
        }

        $nextRid = 1;
        foreach ($outputRels->getElementsByTagNameNS(self::PACKAGE_RELS_NS, 'Relationship') as $rel) {
            if (preg_match('/^rId(\d+)$/', $rel->getAttribute('Id'), $m)) {
                $nextRid = max($nextRid, (int) $m[1] + 1);
            }
        }

        $ridMap = [];
        foreach ($reportHyperlinks as $oldRid => $reportRel) {
            $newRid = 'rId'.$nextRid++;
            $ridMap[$oldRid] = $newRid;

            $relationship = $outputRels->createElementNS(self::PACKAGE_RELS_NS, 'Relationship');
            $relationship->setAttribute('Id', $newRid);
            $relationship->setAttribute('Type', self::HYPERLINK_REL_TYPE);
            $relationship->setAttribute('Target', $reportRel->getAttribute('Target'));
            if ($reportRel->getAttribute('TargetMode') !== '') {
                $relationship->setAttribute('TargetMode', $reportRel->getAttribute('TargetMode'));
            }
            $outputRels->documentElement->appendChild($relationship);
        }

        foreach ($this->collectElementsByTagNS($fragmentNodes, self::WORD_NS, 'hyperlink') as $element) {
            if (! $element->hasAttributeNS(self::RELS_NS, 'id')) {
                continue;
            }

            $oldRid = $element->getAttributeNS(self::RELS_NS, 'id');
            if (isset($ridMap[$oldRid])) {
                $element->getAttributeNodeNS(self::RELS_NS, 'id')->value = $ridMap[$oldRid];
            }
        }
    }

    /**
     * Copies every named style used by the fragment (or its copied styles) that the template does
     * not already define — the template's own style wins on a styleId conflict, per spec. Any
     * w:numId referenced by a copied style (or directly by the fragment body) is renumbered and
     * carried over from the report's numbering.xml, so heading auto-numbering keeps working; a
     * numId that only appears in a style the template already owns is left behind entirely, since
     * that style (and its numbering) has no effect on the merged document.
     *
     * @param  array<int, DOMNode>  $fragmentNodes
     */
    private function mergeStylesAndNumbering(ZipArchive $output, ZipArchive $report, array $fragmentNodes, DOMDocument $outputRels, DOMDocument $contentTypes): void
    {
        $reportStyles = $this->loadXmlEntry($report, 'word/styles.xml');
        $templateStyles = $this->loadXmlEntry($output, 'word/styles.xml');

        $templateStyleIds = [];
        foreach ($this->collectElementsByTagNS([$templateStyles->documentElement], self::WORD_NS, 'style') as $style) {
            $templateStyleIds[$style->getAttributeNS(self::WORD_NS, 'styleId')] = true;
        }

        $stylesToCopy = [];
        foreach ($this->collectElementsByTagNS([$reportStyles->documentElement], self::WORD_NS, 'style') as $style) {
            $styleId = $style->getAttributeNS(self::WORD_NS, 'styleId');
            if ($styleId !== '' && ! isset($templateStyleIds[$styleId])) {
                $stylesToCopy[] = $style;
            }
        }

        $neededNumIds = [];
        foreach ($this->collectElementsByTagNS($stylesToCopy, self::WORD_NS, 'numId') as $numId) {
            $neededNumIds[$numId->getAttributeNS(self::WORD_NS, 'val')] = true;
        }
        $fragmentNumIdElements = $this->collectElementsByTagNS($fragmentNodes, self::WORD_NS, 'numId');
        foreach ($fragmentNumIdElements as $numId) {
            $neededNumIds[$numId->getAttributeNS(self::WORD_NS, 'val')] = true;
        }

        $numIdMap = $neededNumIds === []
            ? []
            : $this->mergeNumbering($output, $report, array_keys($neededNumIds), $outputRels, $contentTypes);

        foreach ($this->collectElementsByTagNS($stylesToCopy, self::WORD_NS, 'numId') as $numId) {
            $oldVal = $numId->getAttributeNS(self::WORD_NS, 'val');
            if (isset($numIdMap[$oldVal])) {
                $numId->getAttributeNodeNS(self::WORD_NS, 'val')->value = $numIdMap[$oldVal];
            }
        }
        foreach ($fragmentNumIdElements as $numId) {
            $oldVal = $numId->getAttributeNS(self::WORD_NS, 'val');
            if (isset($numIdMap[$oldVal])) {
                $numId->getAttributeNodeNS(self::WORD_NS, 'val')->value = $numIdMap[$oldVal];
            }
        }

        foreach ($stylesToCopy as $style) {
            $imported = $templateStyles->importNode($style, true);
            $templateStyles->documentElement->appendChild($imported);
        }

        if ($stylesToCopy !== []) {
            $output->addFromString('word/styles.xml', $templateStyles->saveXML());
        }
    }

    /**
     * Imports exactly the requested <w:num> definitions (and their <w:abstractNum>) from the
     * report's numbering.xml into the template's, renumbering both ids to sit right after the
     * template's own highest existing ids. If the template has no word/numbering.xml part at all —
     * true of every template that never defines its own numbered list, e.g. LibreOffice-authored
     * documents, which omit the part entirely rather than shipping an empty one — a fresh part is
     * created and registered (relationship + content-type override) rather than silently skipping
     * the merge, which would otherwise leave the copied Heading1/2/3 styles pointing at a w:numId
     * that doesn't exist anywhere in the package (Word drops the numbering silently in that case).
     *
     * @param  array<int, string>  $oldNumIds
     * @return array<string, string> old numId => new numId
     */
    private function mergeNumbering(ZipArchive $output, ZipArchive $report, array $oldNumIds, DOMDocument $outputRels, DOMDocument $contentTypes): array
    {
        if (! $this->zipHasEntry($report, 'word/numbering.xml')) {
            return [];
        }

        $reportNumbering = $this->loadXmlEntry($report, 'word/numbering.xml');
        $isNewPart = ! $this->zipHasEntry($output, 'word/numbering.xml');
        $templateNumbering = $isNewPart
            ? $this->createNumberingPart($outputRels, $contentTypes)
            : $this->loadXmlEntry($output, 'word/numbering.xml');

        $nextAbstractId = 0;
        foreach ($this->collectElementsByTagNS([$templateNumbering->documentElement], self::WORD_NS, 'abstractNum') as $abstractNum) {
            $nextAbstractId = max($nextAbstractId, (int) $abstractNum->getAttributeNS(self::WORD_NS, 'abstractNumId') + 1);
        }

        // w:numId="0" is a reserved OOXML sentinel meaning "no numbering" -- Word silently ignores
        // any paragraph/style referencing it, so the allocator must never hand out 0, even when the
        // template's own numbering.xml has no <w:num> elements to compute a "next id" from.
        $nextNumId = 1;
        foreach ($this->collectElementsByTagNS([$templateNumbering->documentElement], self::WORD_NS, 'num') as $num) {
            $nextNumId = max($nextNumId, (int) $num->getAttributeNS(self::WORD_NS, 'numId') + 1);
        }

        $reportNums = [];
        foreach ($this->collectElementsByTagNS([$reportNumbering->documentElement], self::WORD_NS, 'num') as $num) {
            $reportNums[$num->getAttributeNS(self::WORD_NS, 'numId')] = $num;
        }

        $reportAbstractNums = [];
        foreach ($this->collectElementsByTagNS([$reportNumbering->documentElement], self::WORD_NS, 'abstractNum') as $abstractNum) {
            $reportAbstractNums[$abstractNum->getAttributeNS(self::WORD_NS, 'abstractNumId')] = $abstractNum;
        }

        // CT_Numbering's schema requires every <w:abstractNum> to precede every <w:num> (and both
        // to precede a trailing <w:numIdMacAtCleanup>, if present). Word validates this element
        // order strictly and silently misnumbers/misrenders headings when it's violated; LibreOffice
        // tolerates any order, which is why a broken merge only shows up in Word. A plain
        // appendChild() per numId would interleave newly-copied abstractNum/num pairs (abstract1,
        // num1, abstract2, num2, ...), so insertion anchors are resolved once up front from the
        // template's original content and every abstractNum is inserted before every num via two
        // separate passes below.
        $cleanup = $this->collectElementsByTagNS([$templateNumbering->documentElement], self::WORD_NS, 'numIdMacAtCleanup')[0] ?? null;
        $firstNum = $this->collectElementsByTagNS([$templateNumbering->documentElement], self::WORD_NS, 'num')[0] ?? null;
        $abstractAnchor = $firstNum ?? $cleanup;
        $numAnchor = $cleanup;

        $pendingNums = [];
        foreach ($oldNumIds as $oldNumId) {
            if (isset($reportNums[$oldNumId])) {
                $pendingNums[$oldNumId] = $reportNums[$oldNumId];
            }
        }

        $numIdMap = [];
        $abstractIdMap = [];
        $changed = false;

        foreach ($pendingNums as $num) {
            $abstractRefs = $this->collectElementsByTagNS([$num], self::WORD_NS, 'abstractNumId');
            $oldAbstractId = $abstractRefs === [] ? null : $abstractRefs[0]->getAttributeNS(self::WORD_NS, 'val');

            if ($oldAbstractId !== null && isset($reportAbstractNums[$oldAbstractId]) && ! isset($abstractIdMap[$oldAbstractId])) {
                $newAbstractId = (string) $nextAbstractId++;
                $abstractIdMap[$oldAbstractId] = $newAbstractId;

                $importedAbstract = $this->importElement($templateNumbering, $reportAbstractNums[$oldAbstractId]);
                $importedAbstract->getAttributeNodeNS(self::WORD_NS, 'abstractNumId')->value = $newAbstractId;
                if ($abstractAnchor !== null) {
                    $templateNumbering->documentElement->insertBefore($importedAbstract, $abstractAnchor);
                } else {
                    $templateNumbering->documentElement->appendChild($importedAbstract);
                }
                $changed = true;
            }
        }

        foreach ($pendingNums as $oldNumId => $num) {
            $abstractRefs = $this->collectElementsByTagNS([$num], self::WORD_NS, 'abstractNumId');
            $oldAbstractId = $abstractRefs === [] ? null : $abstractRefs[0]->getAttributeNS(self::WORD_NS, 'val');

            $newNumId = (string) $nextNumId++;
            $numIdMap[$oldNumId] = $newNumId;

            $importedNum = $this->importElement($templateNumbering, $num);
            $importedNum->getAttributeNodeNS(self::WORD_NS, 'numId')->value = $newNumId;
            if ($oldAbstractId !== null && isset($abstractIdMap[$oldAbstractId])) {
                $this->collectElementsByTagNS([$importedNum], self::WORD_NS, 'abstractNumId')[0]
                    ->getAttributeNodeNS(self::WORD_NS, 'val')->value = $abstractIdMap[$oldAbstractId];
            }
            if ($numAnchor !== null) {
                $templateNumbering->documentElement->insertBefore($importedNum, $numAnchor);
            } else {
                $templateNumbering->documentElement->appendChild($importedNum);
            }
            $changed = true;
        }

        // If the part was just created (registered in rels/content-types by createNumberingPart()),
        // it must be written even if $changed stayed false, otherwise the freshly-added relationship
        // would point at a zip entry that was never actually written.
        if ($changed || $isNewPart) {
            $output->addFromString('word/numbering.xml', $templateNumbering->saveXML());
        }

        return $numIdMap;
    }

    /**
     * Creates an empty word/numbering.xml part and registers it in the template's own
     * word/_rels/document.xml.rels and [Content_Types].xml so it becomes a valid, referenceable
     * member of the package. Both documents are mutated in place; the caller is responsible for
     * writing them back to the zip exactly once (see performMerge()).
     */
    private function createNumberingPart(DOMDocument $outputRels, DOMDocument $contentTypes): DOMDocument
    {
        $numbering = new DOMDocument('1.0', 'UTF-8');
        $numbering->xmlStandalone = true;
        $root = $numbering->createElementNS(self::WORD_NS, 'w:numbering');
        $numbering->appendChild($root);

        $nextRid = 1;
        foreach ($outputRels->getElementsByTagNameNS(self::PACKAGE_RELS_NS, 'Relationship') as $rel) {
            if (preg_match('/^rId(\d+)$/', $rel->getAttribute('Id'), $m)) {
                $nextRid = max($nextRid, (int) $m[1] + 1);
            }
        }

        $relationship = $outputRels->createElementNS(self::PACKAGE_RELS_NS, 'Relationship');
        $relationship->setAttribute('Id', 'rId'.$nextRid);
        $relationship->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering');
        $relationship->setAttribute('Target', 'numbering.xml');
        $outputRels->documentElement->appendChild($relationship);

        $hasOverride = false;
        foreach ($contentTypes->getElementsByTagName('Override') as $override) {
            if ($override->getAttribute('PartName') === '/word/numbering.xml') {
                $hasOverride = true;
                break;
            }
        }
        if (! $hasOverride) {
            $override = $contentTypes->createElement('Override');
            $override->setAttribute('PartName', '/word/numbering.xml');
            $override->setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml');
            $contentTypes->documentElement->appendChild($override);
        }

        return $numbering;
    }

    private function zipHasEntry(ZipArchive $zip, string $name): bool
    {
        return $zip->locateName($name) !== false;
    }

    private function importElement(DOMDocument $doc, DOMElement $node): DOMElement
    {
        $imported = $doc->importNode($node, true);

        if (! $imported instanceof DOMElement) {
            throw new RuntimeException('Failed to import an OOXML element during template merge.');
        }

        return $imported;
    }

    private function applyUpdateFields(ZipArchive $output): void
    {
        $settings = $this->loadXmlEntry($output, 'word/settings.xml');

        $updateFields = $settings->getElementsByTagNameNS(self::WORD_NS, 'updateFields')->item(0);

        if ($updateFields instanceof DOMElement) {
            $updateFields->setAttributeNS(self::WORD_NS, 'w:val', 'true');
        } else {
            $updateFields = $settings->createElementNS(self::WORD_NS, 'w:updateFields');
            $updateFields->setAttributeNS(self::WORD_NS, 'w:val', 'true');
            $settings->documentElement->insertBefore($updateFields, $settings->documentElement->firstChild);
        }

        $output->addFromString('word/settings.xml', $settings->saveXML());
    }

    private function updateCoreProperties(ZipArchive $output, Carbon $now): void
    {
        $core = $this->loadXmlEntry($output, 'docProps/core.xml');
        $formattedNow = $now->format(\DateTimeInterface::ATOM);

        $modified = $core->getElementsByTagNameNS(self::DCTERMS_NS, 'modified')->item(0);
        if ($modified instanceof DOMElement) {
            $modified->textContent = $formattedNow;
        } else {
            $modified = $this->createDcTermsElement($core, 'modified', $formattedNow);
            $core->documentElement->appendChild($modified);
        }

        $created = $core->getElementsByTagNameNS(self::DCTERMS_NS, 'created')->item(0);
        if (! $created instanceof DOMElement || trim($created->textContent) === '') {
            if ($created instanceof DOMElement) {
                $created->textContent = $formattedNow;
            } else {
                $core->documentElement->appendChild($this->createDcTermsElement($core, 'created', $formattedNow));
            }
        }

        $output->addFromString('docProps/core.xml', $core->saveXML());
    }

    private function createDcTermsElement(DOMDocument $dom, string $localName, string $value): DOMElement
    {
        $element = $dom->createElementNS(self::DCTERMS_NS, 'dcterms:'.$localName, $value);
        $element->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:type', 'dcterms:W3CDTF');

        return $element;
    }
}
