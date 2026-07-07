<?php

namespace App\Services\Report;

use PhpOffice\PhpWord\Element\Section;

interface ReportSection
{
    /**
     * Adds the complete section (title, graph(s), then one block per object type)
     * to the PhpWord document being built.
     *
     * @param  array<int, string>  $selectedVues  Normalized list of selected `vues[]` ids (never empty — the caller resolves "none selected" to "all").
     */
    public function build(Section $section, WordHelper $helper, array $selectedVues): void;
}
