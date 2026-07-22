<?php

use App\Services\Report\TemplateMergerService;
use Illuminate\Support\Carbon;

function mergeFixture(string $name): string
{
    return base_path('tests/fixtures/templates/'.$name);
}

function concatenatedText(string $xml): string
{
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches);

    return implode('', $matches[1]);
}

function mergeOutputPath(): string
{
    return sys_get_temp_dir().'/mercator-merged-'.bin2hex(random_bytes(8)).'.docx';
}

function mergeIntoTempFile(string $templateFixture = 'merge-template.docx'): array
{
    $output = mergeOutputPath();

    (new TemplateMergerService)->merge(
        mergeFixture($templateFixture),
        mergeFixture('merge-body.docx'),
        $output
    );

    return [$output, new ZipArchive];
}

test('merge replaces the :content: paragraph with the report body and keeps the template sectPr', function () {
    [$output, $zip] = mergeIntoTempFile();

    try {
        expect($zip->open($output))->toBe(true);

        $documentXml = $zip->getFromName('word/document.xml');

        $dom = new DOMDocument;
        expect($dom->loadXML($documentXml))->toBeTrue();

        expect($documentXml)
            ->not->toContain(':content:')
            ->toContain('Custom Cover Title')
            ->toContain('Body Heading')
            ->toContain('Body Subheading')
            // Margins come from the template's own sectPr, not the report body's.
            ->toContain('w:top="2160"')
            ->not->toContain('w:top="1440"');
    } finally {
        $zip->close();
        unlink($output);
    }
});

test('merge renumbers fragment bookmark ids past the template\'s own highest id, without touching names', function () {
    [$output, $zip] = mergeIntoTempFile();

    try {
        $zip->open($output);
        $documentXml = $zip->getFromName('word/document.xml');

        preg_match_all('/<w:bookmarkStart w:id="(\d+)" w:name="([^"]+)"\/>/', $documentXml, $matches, PREG_SET_ORDER);
        $byName = [];
        foreach ($matches as $match) {
            $byName[$match[2]][] = (int) $match[1];
        }

        // The template's own bookmarks (ids 0 and 5) are untouched.
        expect($byName['EXISTING_BOOKMARK'] ?? null)->toBe([5]);
        expect($byName['_Toc0'] ?? [])->toContain(0);

        // All fragment-derived bookmarks (ENTITY_1, and the fragment's own "_Toc0"/"_Toc1") are
        // renumbered to ids strictly after the template's highest existing id (5).
        expect($byName['ENTITY_1'] ?? null)->not->toBeNull();
        foreach ($byName['ENTITY_1'] as $id) {
            expect($id)->toBeGreaterThan(5);
        }

        $fragmentToc0Ids = array_filter($byName['_Toc0'] ?? [], fn ($id) => $id !== 0);
        expect($fragmentToc0Ids)->not->toBeEmpty();
        foreach ($fragmentToc0Ids as $id) {
            expect($id)->toBeGreaterThan(5);
        }

        // Every bookmark id in the merged document is unique (valid start/end pairing).
        $allIds = array_merge(...array_values($byName));
        expect(array_unique($allIds))->toHaveCount(count($allIds));
    } finally {
        $zip->close();
        unlink($output);
    }
});

test('merge copies the media file referenced by the report body and remaps its relationship id', function () {
    [$output, $zip] = mergeIntoTempFile();

    try {
        $zip->open($output);

        $mediaNames = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'word/media/')) {
                $mediaNames[] = $name;
            }
        }
        expect($mediaNames)->toHaveCount(1);
        expect($mediaNames[0])->toEndWith('.png');

        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        expect($rels)->toContain('Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"');
        preg_match('/<Relationship Id="([^"]+)" Type="[^"]*\/image" Target="media\/([^"]+)"\/>/', $rels, $relMatch);
        expect($relMatch)->not->toBeEmpty();
        [, $newRid, $newBasename] = $relMatch;
        expect('word/media/'.$newBasename)->toBe($mediaNames[0]);

        $documentXml = $zip->getFromName('word/document.xml');
        expect($documentXml)->toContain('<v:imagedata r:id="'.$newRid.'"');

        $contentTypes = $zip->getFromName('[Content_Types].xml');
        expect($contentTypes)->toContain('<Default Extension="png"');
    } finally {
        $zip->close();
        unlink($output);
    }
});

test('merge keeps the template\'s own style on a styleId conflict and copies a style the template lacks, along with its numbering', function () {
    [$output, $zip] = mergeIntoTempFile();

    try {
        $zip->open($output);
        $stylesXml = $zip->getFromName('word/styles.xml');

        // Conflicting styleId "Heading1": template's own version (CC0000, size 40, italic) wins;
        // the report body's version (0000CC, size 32) must not appear.
        expect($stylesXml)
            ->toContain('w:styleId="Heading1"')
            ->toContain('CC0000')
            ->not->toContain('0000CC');

        // "Heading2" only exists in the report body, so it is copied over verbatim.
        expect($stylesXml)->toContain('w:styleId="Heading2"');

        preg_match('/<w:style w:type="paragraph" w:styleId="Heading2">.*?<\/w:style>/s', $stylesXml, $styleMatch);
        expect($styleMatch)->not->toBeEmpty();
        preg_match('/<w:numId w:val="(\d+)"\/>/', $styleMatch[0], $numIdMatch);
        expect($numIdMatch)->not->toBeEmpty();

        // w:numId="0" is a reserved OOXML sentinel meaning "no numbering" -- Word silently drops
        // any numbering that ends up referenced this way. The merge-template fixture starts with
        // an empty numbering.xml (no <w:num> to compute a "next id" from), which is exactly the
        // condition that previously made the id allocator hand out 0 here.
        expect($numIdMatch[1])->not->toBe('0');

        $numberingXml = $zip->getFromName('word/numbering.xml');
        $newNumId = $numIdMatch[1];
        expect($numberingXml)->toContain('<w:num w:numId="'.$newNumId.'">');

        preg_match('/<w:num w:numId="'.$newNumId.'"><w:abstractNumId w:val="(\d+)"\/><\/w:num>/', $numberingXml, $abstractMatch);
        expect($abstractMatch)->not->toBeEmpty();
        expect($numberingXml)->toContain('w:abstractNumId="'.$abstractMatch[1].'"');
    } finally {
        $zip->close();
        unlink($output);
    }
});

test('merge enables updateFields and bumps the modified date while preserving the template\'s created date', function () {
    [$output, $zip] = mergeIntoTempFile();

    try {
        $zip->open($output);

        $settingsXml = $zip->getFromName('word/settings.xml');
        expect($settingsXml)->toContain('<w:updateFields w:val="true"/>');

        $coreXml = $zip->getFromName('docProps/core.xml');
        expect($coreXml)->toContain('<dcterms:created xsi:type="dcterms:W3CDTF">2020-01-01T00:00:00+00:00</dcterms:created>');

        preg_match('/<dcterms:modified[^>]*>([^<]+)<\/dcterms:modified>/', $coreXml, $modifiedMatch);
        expect($modifiedMatch)->not->toBeEmpty();
        expect($modifiedMatch[1])->not->toBe('2020-01-01T00:00:00+00:00');
        expect($modifiedMatch[1])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
    } finally {
        $zip->close();
        unlink($output);
    }
});

test('merge throws and leaves no output file when the template has no :content: tag', function () {
    $output = mergeOutputPath();

    expect(fn () => (new TemplateMergerService)->merge(
        mergeFixture('missing-tag.docx'),
        mergeFixture('merge-body.docx'),
        $output
    ))->toThrow(RuntimeException::class);

    expect(is_file($output))->toBeFalse();
});

test('merge creates and registers word/numbering.xml when the template has no numbering part at all', function () {
    // Regression: a template that never defines its own numbered list (e.g. LibreOffice-authored
    // documents, which omit word/numbering.xml entirely rather than shipping an empty one) used to
    // make mergeNumbering() bail out silently, leaving the copied Heading2 style's <w:numId>
    // pointing at a numbering definition that didn't exist anywhere in the merged package -- Word
    // drops such numbering silently instead of erroring, so the bug was invisible except visually.
    [$output, $zip] = mergeIntoTempFile('merge-template-no-numbering.docx');

    try {
        $zip->open($output);

        $numberingXml = $zip->getFromName('word/numbering.xml');
        expect($numberingXml)->not->toBeFalse();
        expect($numberingXml)->toContain('<w:num w:numId="1">');
        expect($numberingXml)->not->toContain('<w:num w:numId="0">');

        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        expect($rels)->toContain('Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering"');
        expect($rels)->toContain('Target="numbering.xml"');

        $contentTypes = $zip->getFromName('[Content_Types].xml');
        expect($contentTypes)->toContain('<Override PartName="/word/numbering.xml"');

        $stylesXml = $zip->getFromName('word/styles.xml');
        preg_match('/<w:style w:type="paragraph" w:styleId="Heading2">.*?<\/w:style>/s', $stylesXml, $styleMatch);
        preg_match('/<w:numId w:val="(\d+)"\/>/', $styleMatch[0], $numIdMatch);
        expect($numIdMatch[1])->toBe('1');
    } finally {
        $zip->close();
        unlink($output);
    }
});

test('merge substitutes :timestamp: and :version: in the body, header and footer, including a fragmented occurrence', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-04 09:15:00'));

    try {
        [$output, $zip] = mergeIntoTempFile('merge-template-with-tags.docx');

        try {
            $zip->open($output);

            $version = (string) app('mercator.version');

            $documentXml = $zip->getFromName('word/document.xml');
            // The tag was split across two <w:r> runs in the fixture, so the replaced text is too
            // (":times" ends the first run, "tamp: value" starts the second) -- concatenating every
            // <w:t> in document order, the same way the production code detects/locates tags,
            // mirrors what a reader (or Word itself) actually sees.
            expect(concatenatedText($documentXml))->toContain('Body 04/03/2026 09:15 value');
            expect($documentXml)
                ->not->toContain(':times')
                ->not->toContain(':content:');

            $headerXml = $zip->getFromName('word/header1.xml');
            expect($headerXml)
                ->toContain('Version '.$version)
                ->not->toContain(':version:');

            $footerXml = $zip->getFromName('word/footer1.xml');
            // Both occurrences in "Generated: :timestamp: / :timestamp:" must be replaced, not
            // just the first one found in the paragraph.
            expect($footerXml)
                ->toContain('Generated: 04/03/2026 09:15 / 04/03/2026 09:15')
                ->not->toContain(':timestamp:');
        } finally {
            $zip->close();
            unlink($output);
        }
    } finally {
        Carbon::setTestNow();
    }
});

test('merge leaves :timestamp:/:version: untouched when a template does not use them', function () {
    [$output, $zip] = mergeIntoTempFile();

    try {
        $zip->open($output);
        $documentXml = $zip->getFromName('word/document.xml');
        expect($documentXml)
            ->not->toContain(':timestamp:')
            ->not->toContain(':version:');
    } finally {
        $zip->close();
        unlink($output);
    }
});
