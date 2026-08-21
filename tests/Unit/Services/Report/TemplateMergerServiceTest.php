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

function mergeIntoTempFile(string $templateFixture = 'merge-template.docx', string $bodyFixture = 'merge-body.docx'): array
{
    $output = mergeOutputPath();

    (new TemplateMergerService)->merge(
        mergeFixture($templateFixture),
        mergeFixture($bodyFixture),
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

test('merge keeps every abstractNum before every num when multiple numIds are merged at once', function () {
    // Regression: mergeNumbering() used to appendChild() each abstractNum/num pair in turn, so
    // merging 2+ numIds in one pass produced [abstract1, num1, abstract2, num2, ...] -- CT_Numbering
    // requires every <w:abstractNum> to precede every <w:num>. Word enforces that schema sequence
    // strictly and silently misnumbers/misrenders heading numbering when it's violated (e.g. chapter
    // numbers displaying incorrectly), while LibreOffice tolerates any element order, which is why
    // the bug only showed up in Word. This fixture's report body references two numIds at once: the
    // Heading2 style's own numbering, and a directly-numbered bullet paragraph in the body.
    [$output, $zip] = mergeIntoTempFile('merge-template.docx', 'merge-body-multi-numbering.docx');

    try {
        $zip->open($output);

        $numberingXml = $zip->getFromName('word/numbering.xml');

        $lastAbstractPos = strrpos($numberingXml, '<w:abstractNum ');
        $firstNumPos = strpos($numberingXml, '<w:num ');

        expect($lastAbstractPos)->not->toBeFalse();
        expect($firstNumPos)->not->toBeFalse();
        expect($lastAbstractPos)->toBeLessThan($firstNumPos);

        // Both numIds were actually merged (two <w:num> elements), not just the first one found.
        expect(substr_count($numberingXml, '<w:num '))->toBe(2);
    } finally {
        $zip->close();
        unlink($output);
    }
});

test('merge reorders tblPr before tblGrid in every table, including ones nested inside a cell', function () {
    // Regression: PhpWord 1.4's Word2007 writer emits every <w:tbl> as [tblGrid, tblPr] (it writes
    // the columns before the style), but CT_Tbl's schema sequence requires tblPr before tblGrid.
    // Word enforces that sequence more strictly for a table nested inside a table cell than for one
    // that's a direct child of the body (LibreOffice tolerates either order everywhere), which is
    // why only report fields built from a nested table (the icon table in
    // addDescriptionCellWithIcon, the value table in addNestedTableRow -- "Termes du contrat" in
    // the UI) triggered "Word found unreadable content", never the report's own top-level tables.
    // This fixture's report body has both: an outer table and, nested inside its single cell,
    // another table -- both written in PhpWord's actual (backwards) order.
    [$output, $zip] = mergeIntoTempFile('merge-template.docx', 'merge-body-table-order.docx');

    try {
        $zip->open($output);
        $documentXml = $zip->getFromName('word/document.xml');

        preg_match_all('/<w:tbl>(<w:tblGrid>.*?<\/w:tblGrid>|<w:tblPr>.*?<\/w:tblPr>)/', $documentXml, $matches);
        expect($matches[1])->toHaveCount(2);
        foreach ($matches[1] as $firstChild) {
            expect($firstChild)->toStartWith('<w:tblPr>');
        }
    } finally {
        $zip->close();
        unlink($output);
    }
});

test('merge reorders tblPr\'s own children into CT_TblPrBase schema order', function () {
    // Regression: PhpWord 1.4's Writer\Word2007\Style\Table::writeStyle() writes <w:tblPr>'s
    // children as jc, tblW, tblCellSpacing, tblInd, tblLayout, tblpPr, bidiVisual, tblCellMar,
    // tblBorders -- but CT_TblPrBase's schema sequence requires tblW before jc, and tblBorders
    // before tblLayout before tblCellMar. This is a second, independent ordering defect from the
    // tblGrid-vs-tblPr one covered above (both trigger the same "Table Properties" repair
    // notification, which is why fixing only the first one left the errors unchanged). This
    // fixture's table already has tblPr correctly placed before tblGrid, isolating the
    // within-tblPr child order.
    [$output, $zip] = mergeIntoTempFile('merge-template.docx', 'merge-body-tblpr-order.docx');

    try {
        $zip->open($output);
        $documentXml = $zip->getFromName('word/document.xml');

        preg_match('/<w:tblPr>(.*?)<\/w:tblPr>/', $documentXml, $tblPrMatch);
        expect($tblPrMatch)->not->toBeEmpty();

        $order = ['tblW', 'jc', 'tblCellSpacing', 'tblInd', 'tblBorders', 'shd', 'tblLayout', 'tblCellMar'];
        preg_match_all('/<w:(\w+)/', $tblPrMatch[1], $childMatches);
        $topLevelChildren = array_values(array_intersect($childMatches[1], $order));

        $ranks = array_map(fn ($name) => array_search($name, $order), $topLevelChildren);
        expect($ranks)->toBe(collect($ranks)->sort()->values()->all());
    } finally {
        $zip->close();
        unlink($output);
    }
});

test('merge copies external hyperlink relationships and remaps their r:id', function () {
    // Regression: mergeMedia() only ever copied <v:imagedata r:id="..."> (image) relationships --
    // a <w:hyperlink r:id="..."> (an external link, e.g. a "mailto:" address from a report field's
    // rich-text HTML, or an addLink() call with $internal=false) kept referencing the *report's
    // own* relationship id, which doesn't exist anywhere in the merged package. Confirmed against
    // a real generated report: every entity with a hyperlinked "Point de contact" left a dangling
    // r:id in the merged word/document.xml, and Word's reader can't recover from an unresolvable
    // relationship reference ("found unreadable content").
    [$output, $zip] = mergeIntoTempFile('merge-template.docx', 'merge-body-hyperlink.docx');

    try {
        $zip->open($output);
        $documentXml = $zip->getFromName('word/document.xml');
        $rels = $zip->getFromName('word/_rels/document.xml.rels');

        preg_match('/<w:hyperlink r:id="([^"]+)"/', $documentXml, $hyperlinkMatch);
        expect($hyperlinkMatch)->not->toBeEmpty();
        $newRid = $hyperlinkMatch[1];

        preg_match_all('/r:id="([^"]+)"/', $documentXml, $allRids);
        $declaredRids = [];
        preg_match_all('/<Relationship Id="([^"]+)"/', $rels, $declaredMatch);
        $declaredRids = $declaredMatch[1];

        foreach ($allRids[1] as $usedRid) {
            expect($declaredRids)->toContain($usedRid);
        }

        preg_match('/<Relationship Id="'.$newRid.'"[^>]*\/>/', $rels, $relMatch);
        expect($relMatch)->not->toBeEmpty();
        expect($relMatch[0])
            ->toContain('Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink"')
            ->toContain('Target="mailto:support@example.com"')
            ->toContain('TargetMode="External"');
    } finally {
        $zip->close();
        unlink($output);
    }
});

test('merge wraps every orphaned <w:br/> in its own <w:r>, including one inside a list item', function () {
    // Regression: PhpWord 1.4's Writer\Word2007\Element\TextBreak::write() writes a bare <w:br/>
    // (no <w:r> wrapper) whenever it's written "withoutP" -- i.e. straight into an already-open
    // run-level container, which covers both $run->addTextBreak() (EcosystemSection's relation
    // list) and, critically, PhpWord's own HTML parser turning a `<li>...<br>...</li>` in a report
    // field's rich-text "Description" into a line break inside a list item (a ListItemRun). A
    // <w:br/> as a direct sibling of <w:r> inside <w:p> is well-formed XML but violates CT_R's
    // content model -- Word's reader rejects it ("found unreadable content"), LibreOffice's more
    // forgiving one renders it anyway. This fixture's report body has both shapes.
    [$output, $zip] = mergeIntoTempFile('merge-template.docx', 'merge-body-unwrapped-break.docx');

    try {
        $zip->open($output);
        $documentXml = $zip->getFromName('word/document.xml');

        preg_match_all('/<w:br\s*\/>/', $documentXml, $matches);
        expect($matches[0])->toHaveCount(2);

        $dom = new DOMDocument;
        expect($dom->loadXML($documentXml))->toBeTrue();
        $breaks = $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'br');
        expect($breaks->length)->toBe(2);
        foreach ($breaks as $break) {
            expect($break->parentNode->localName)->toBe('r');
        }
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
