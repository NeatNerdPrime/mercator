<?php

namespace App\Services\Report;

use Carbon\Carbon;
use PhpOffice\PhpWord\IOFactory;

class ReportBuilder
{
    /**
     * Vue ids in document rendering order (GDPR first, per user decision).
     *
     * @var array<int, string>
     */
    private const VUE_ORDER = ['7', '1', '2', '3', '4', '5', '6'];

    /**
     * @var array<int, class-string<ReportSection>>
     */
    private const VUE_SECTION_CLASSES = [
        1 => EcosystemSection::class,
        2 => InformationSystemSection::class,
        3 => ApplicationSection::class,
        4 => AdministrationSection::class,
        5 => LogicalInfrastructureSection::class,
        6 => PhysicalInfrastructureSection::class,
        7 => GdprSection::class,
    ];

    public function __construct(private readonly WordHelper $wordHelper = new WordHelper) {}

    /**
     * @param  array<int, string>  $selectedVues
     */
    public function build(array $selectedVues, bool $withGraphs = false): string
    {
        $vues = $this->normalizeSelectedVues($selectedVues);
        $this->wordHelper->setIncludeGraphs($withGraphs);

        $phpWord = $this->wordHelper->newDocument();

        $title = trans('cruds.report.cartography.title');
        $generatedOn = trans('cruds.report.cartography.generated_on').' : '.Carbon::now()->format('d/m/Y H:i');
        $mercatorVersion = trans('cruds.report.cartography.version').' : '.app('mercator.version');

        $section = $this->wordHelper->addCoverPageAndToc($phpWord, $title, $generatedOn, $mercatorVersion);

        foreach (self::VUE_ORDER as $vueId) {
            if (! in_array($vueId, $vues, true)) {
                continue;
            }

            $sectionClass = self::VUE_SECTION_CLASSES[$vueId];
            (new $sectionClass)->build($section, $this->wordHelper, $vues);

            $section->addPageBreak();
        }

        $filepath = storage_path('app/reports/cartographie-'.Carbon::today()->format('Ymd').'-'.uniqid().'.docx');

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        try {
            $objWriter->save($filepath);
        } finally {
            $this->wordHelper->cleanupTempFiles();
        }

        return $filepath;
    }

    /**
     * @param  array<int, string>  $selectedVues
     * @return array<int, string>
     */
    private function normalizeSelectedVues(array $selectedVues): array
    {
        if (count($selectedVues) === 0) {
            return self::VUE_ORDER;
        }

        return $selectedVues;
    }
}
