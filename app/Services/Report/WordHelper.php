<?php

namespace App\Services\Report;

use App\Contracts\HasUniqueIdentifierContract;
use App\Models\Activity;
use App\Models\Actor;
use App\Models\AdminUser;
use App\Models\Annuaire;
use App\Models\Application;
use App\Models\ApplicationBlock;
use App\Models\ApplicationFlow;
use App\Models\ApplicationModule;
use App\Models\ApplicationService;
use App\Models\Backup;
use App\Models\Bay;
use App\Models\Building;
use App\Models\Certificate;
use App\Models\Cluster;
use App\Models\Container;
use App\Models\Database;
use App\Models\DataProcessing;
use App\Models\DhcpServer;
use App\Models\Dnsserver;
use App\Models\Document;
use App\Models\Domain;
use App\Models\Entity;
use App\Models\ExternalConnectedEntity;
use App\Models\ForestAd;
use App\Models\Gateway;
use App\Models\Information;
use App\Models\Lan;
use App\Models\LogicalFlow;
use App\Models\LogicalServer;
use App\Models\MacroProcessus;
use App\Models\Man;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\Operation;
use App\Models\Peripheral;
use App\Models\Phone;
use App\Models\PhysicalLink;
use App\Models\PhysicalRouter;
use App\Models\PhysicalSecurityDevice;
use App\Models\PhysicalServer;
use App\Models\PhysicalSwitch;
use App\Models\Process;
use App\Models\Relation;
use App\Models\Router;
use App\Models\SecurityControl;
use App\Models\SecurityDevice;
use App\Models\Site;
use App\Models\StorageDevice;
use App\Models\Subnetwork;
use App\Models\Task;
use App\Models\Vlan;
use App\Models\Wan;
use App\Models\WifiTerminal;
use App\Models\Workstation;
use App\Models\Zone;
use App\Models\ZoneAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

class WordHelper
{
    public const FANCY_TABLE_TITLE_STYLE = ['bold' => true, 'color' => '006699'];

    public const FANCY_LEFT_TABLE_CELL_STYLE = ['bold' => true, 'color' => '000000'];

    public const FANCY_RIGHT_TABLE_CELL_STYLE = ['bold' => false, 'color' => '000000'];

    public const FANCY_LINK_STYLE = ['color' => '006699'];

    public const NO_SPACE = [
        'spaceBefore' => 30,
        'spaceAfter' => 30,
    ];

    /**
     * DPI used to rasterize DOT graphs with Graphviz (must match the `-Gdpi=` flag passed to
     * `dot` in generateGraphImage()) — needed to convert the resulting PNG's pixel dimensions
     * back to points for Word insertion.
     */
    private const GRAPH_DPI = 300;

    /**
     * Maximum width (in points) a graph is displayed at in the report; the usable page width.
     */
    private const MAX_GRAPH_WIDTH_PT = 450;

    /**
     * Maps each cartographied model to the `vues[]` id of the report section it belongs to.
     * Derived directly from docs/analysis-cartography-report.md §1.3.4.
     *
     * @var array<class-string<Model>, string>
     */
    public const MODEL_VUE_MAP = [
        Entity::class => '1',
        Relation::class => '1',
        MacroProcessus::class => '2',
        Process::class => '2',
        Activity::class => '2',
        Operation::class => '2',
        Task::class => '2',
        Actor::class => '2',
        Information::class => '2',
        ApplicationBlock::class => '3',
        Application::class => '3',
        ApplicationService::class => '3',
        ApplicationModule::class => '3',
        Database::class => '3',
        ApplicationFlow::class => '3',
        ZoneAdmin::class => '4',
        Annuaire::class => '4',
        ForestAd::class => '4',
        Domain::class => '4',
        AdminUser::class => '4',
        Network::class => '5',
        Subnetwork::class => '5',
        Gateway::class => '5',
        ExternalConnectedEntity::class => '5',
        Router::class => '5',
        NetworkSwitch::class => '5',
        SecurityDevice::class => '5',
        DhcpServer::class => '5',
        Dnsserver::class => '5',
        LogicalServer::class => '5',
        Cluster::class => '5',
        Backup::class => '5',
        Container::class => '5',
        LogicalFlow::class => '5',
        Vlan::class => '5',
        Certificate::class => '5',
        Site::class => '6',
        Building::class => '6',
        Bay::class => '6',
        Zone::class => '6',
        PhysicalServer::class => '6',
        Workstation::class => '6',
        StorageDevice::class => '6',
        Peripheral::class => '6',
        Phone::class => '6',
        PhysicalSwitch::class => '6',
        PhysicalRouter::class => '6',
        WifiTerminal::class => '6',
        PhysicalSecurityDevice::class => '6',
        PhysicalLink::class => '6',
        Wan::class => '6',
        Man::class => '6',
        Lan::class => '6',
        DataProcessing::class => '7',
        SecurityControl::class => '7',
    ];

    public function newDocument(): PhpWord
    {
        $phpWord = new PhpWord;
        Settings::setOutputEscapingEnabled(true);
        $phpWord->getSettings()->setHideGrammaticalErrors(true);
        $phpWord->getSettings()->setHideSpellingErrors(true);

        $phpWord->addNumberingStyle(
            'hNum',
            ['type' => 'multilevel', 'levels' => [
                ['pStyle' => 'Heading1', 'format' => 'decimal', 'text' => '%1.'],
                ['pStyle' => 'Heading2', 'format' => 'decimal', 'text' => '%1.%2.'],
                ['pStyle' => 'Heading3', 'format' => 'decimal', 'text' => '%1.%2.%3.'],
            ],
            ]
        );
        $phpWord->addTitleStyle(
            0,
            ['size' => 28, 'bold' => true],
            ['align' => 'center']
        );
        $phpWord->addTitleStyle(
            1,
            ['size' => 16, 'bold' => true],
            ['numStyle' => 'hNum', 'numLevel' => 0]
        );
        $phpWord->addTitleStyle(
            2,
            ['size' => 14, 'bold' => true],
            ['numStyle' => 'hNum', 'numLevel' => 1]
        );
        $phpWord->addTitleStyle(
            3,
            ['size' => 12, 'bold' => true],
            ['numStyle' => 'hNum', 'numLevel' => 2]
        );

        return $phpWord;
    }

    public function addCoverPageAndToc(PhpWord $phpWord, string $title, string $generatedOn, string $mercatorVersion): Section
    {
        $section = $phpWord->addSection();

        $header = $section->addHeader();
        $header->addText($title, ['size' => 9, 'italic' => true], ['alignment' => Jc::CENTER]);
        $header->addText($generatedOn, ['size' => 9, 'italic' => true], ['alignment' => Jc::CENTER]);

        $footer = $section->addFooter();
        $footer->addPreserveText('{PAGE} / {NUMPAGES}', ['size' => 8], ['alignment' => Jc::CENTER]);

        $section->addTitle($title, 0);
        $section->addTextBreak(1);
        $section->addText($generatedOn, ['size' => 10], ['alignment' => Jc::CENTER]);
        $section->addText($mercatorVersion, ['size' => 10], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(2);

        $toc = $section->addTOC(['spaceAfter' => 50, 'size' => 10]);
        $toc->setMinDepth(1);
        $toc->setMaxDepth(2);
        $section->addTextBreak(1);

        $section->addPageBreak();

        return $section;
    }

    public function addBookmarkedTitle(Section $section, string $uid, string $title, int $depth): void
    {
        $section->addBookmark($uid);
        $section->addTitle($title, $depth);
    }

    /**
     * Renders $target as an internal hyperlink to its bookmark when its vue is among $selectedVues,
     * otherwise falls back to plain text (mirrors the @canAccess-gated behaviour of the interactive screens).
     *
     * @param  Model&HasUniqueIdentifierContract  $target
     */
    public function linkOrText(TextRun $run, Model $target, array $selectedVues, ?string $label = null): void
    {
        $label ??= (string) ($target->name ?? '');
        $vue = self::MODEL_VUE_MAP[$target::class] ?? null;

        if ($vue !== null && in_array($vue, $selectedVues, true)) {
            $run->addLink($target->getUID(), $label, self::FANCY_LINK_STYLE, null, true);
        } else {
            $run->addText($label);
        }
    }

    /**
     * Adds a table row whose value cell is a comma-separated list of internal links (or plain text
     * for targets outside the selected vues), one per $items entry.
     *
     * @param  iterable<int, Model&HasUniqueIdentifierContract>  $items
     * @param  (callable(Model&HasUniqueIdentifierContract): string)|null  $labelResolver
     */
    public function addLinkListRow(Table $table, string $title, iterable $items, array $selectedVues, ?callable $labelResolver = null): void
    {
        $items = $items instanceof \Traversable ? iterator_to_array($items) : (array) $items;

        $table->addRow();
        $table->addCell(2000, self::NO_SPACE)->addText($title, self::FANCY_LEFT_TABLE_CELL_STYLE, self::NO_SPACE);
        $run = $table->addCell(6000)->addTextRun(self::NO_SPACE);

        $count = count($items);
        $i = 0;
        foreach ($items as $item) {
            $label = $labelResolver !== null ? $labelResolver($item) : (string) ($item->name ?? '');
            $this->linkOrText($run, $item, $selectedVues, $label);
            if (++$i < $count) {
                $run->addText(', ');
            }
        }
    }

    /**
     * Resolves the local filesystem path for an object's icon: the uploaded Document behind
     * $iconId if it exists, otherwise the static fallback image shipped under public/.
     */
    public function resolveIconPath(?int $iconId, string $fallbackImage): string
    {
        if ($iconId !== null) {
            $documentPath = storage_path('docs/'.$iconId);
            if (is_file($documentPath)) {
                return $documentPath;
            }
        }

        return public_path($fallbackImage);
    }

    public function addImageRow(Table $table, string $title, string $imagePath, int $width = 60, int $height = 60): void
    {
        $table->addRow();
        $table->addCell(2000, self::NO_SPACE)->addText($title, self::FANCY_LEFT_TABLE_CELL_STYLE, self::NO_SPACE);
        $cell = $table->addCell(6000);

        if (is_file($imagePath)) {
            $cell->addImage($imagePath, ['width' => $width, 'height' => $height]);
        }
    }

    /**
     * Adds a row whose value cell holds a small nested table (header row + one row per $rows entry).
     * Used for the "nested child collection" fields identified in the content inventory
     * (RelationValue under Relation, ActivityImpact under Activity, etc.).
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, string>>  $rows  Each entry holds one value per header, in order.
     */
    public function addNestedTableRow(Table $table, string $title, array $headers, iterable $rows): void
    {
        $table->addRow();
        $table->addCell(2000, self::NO_SPACE)->addText($title, self::FANCY_LEFT_TABLE_CELL_STYLE, self::NO_SPACE);
        $cell = $table->addCell(6000);

        $nested = $cell->addTable(['borderSize' => 1, 'borderColor' => '999999', 'cellMargin' => 50]);
        $nested->addRow();
        foreach ($headers as $header) {
            $nested->addCell(2000)->addText($header, self::FANCY_LEFT_TABLE_CELL_STYLE, self::NO_SPACE);
        }
        foreach ($rows as $row) {
            $nested->addRow();
            foreach ($row as $value) {
                $nested->addCell(2000)->addText($value, self::FANCY_RIGHT_TABLE_CELL_STYLE, self::NO_SPACE);
            }
        }
    }

    /**
     * Adds a row whose value cell is a comma-separated list of external hyperlinks to attached
     * Document files (the generic BelongsToMany "attachment" pattern found on Relation,
     * ExternalConnectedEntity and DataProcessing).
     *
     * @param  iterable<int, Document>  $documents
     */
    public function addDocumentLinksRow(Table $table, string $title, iterable $documents): void
    {
        $documents = $documents instanceof \Traversable ? iterator_to_array($documents) : (array) $documents;

        $table->addRow();
        $table->addCell(2000, self::NO_SPACE)->addText($title, self::FANCY_LEFT_TABLE_CELL_STYLE, self::NO_SPACE);
        $run = $table->addCell(6000)->addTextRun(self::NO_SPACE);

        $count = count($documents);
        $i = 0;
        foreach ($documents as $document) {
            $run->addLink(route('admin.documents.show', $document->id), $document->filename, self::FANCY_LINK_STYLE, null, false);
            if (++$i < $count) {
                $run->addText(', ');
            }
        }
    }

    /**
     * Renders the standard Confidentiality/Integrity/Availability/Traceability(+Authenticity)
     * "security_need" badges found on many cartographied objects (MacroProcessus, Process,
     * Network, Application, Database, ...). Authenticity is only shown when the
     * `mercator.parameters.security_need_auth` config flag is enabled, matching every existing
     * _details.blade.php that gates it correctly.
     */
    public function addSecurityNeedRow(Table $table, string $title, ?int $c, ?int $i, ?int $a, ?int $t, ?int $auth = null): void
    {
        $parts = [
            trans('global.confidentiality').' : '.$this->riskLabel($c),
            trans('global.integrity').' : '.$this->riskLabel($i),
            trans('global.availability').' : '.$this->riskLabel($a),
            trans('global.tracability').' : '.$this->riskLabel($t),
        ];

        if (config('mercator.parameters.security_need_auth')) {
            $parts[] = trans('global.authenticity').' : '.$this->riskLabel($auth);
        }

        $this->addTextRow($table, $title, implode(' | ', $parts));
    }

    /**
     * Translates the 0-4 risk/severity scale shared by every "security_need_*" field and by
     * ActivityImpact.severity (none/low/medium/strong/very_strong).
     */
    public function riskLabel(?int $value): string
    {
        return match ($value) {
            0 => trans('global.none'),
            1 => trans('global.low'),
            2 => trans('global.medium'),
            3 => trans('global.strong'),
            4 => trans('global.very_strong'),
            default => '',
        };
    }

    /**
     * Formats a duration stored in minutes as "a days b hours c minutes" (each part omitted when
     * zero). Shared by every BIA/continuity field expressed in minutes (Activity's
     * maximum_tolerable_downtime/recovery_time_objective/..., Application's rto/rpo).
     */
    public function formatMinutesDuration(?int $minutes): string
    {
        if ($minutes === null) {
            return '';
        }

        $days = intdiv($minutes, 60 * 24);
        $hours = intdiv($minutes, 60) % 24;
        $mins = $minutes % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days.' '.trans($days > 1 ? 'global.days' : 'global.day');
        }
        if ($hours > 0) {
            $parts[] = $hours.' '.trans($hours > 1 ? 'global.hours' : 'global.hour');
        }
        if ($mins > 0) {
            $parts[] = $mins.' '.trans($mins > 1 ? 'global.minutes' : 'global.minute');
        }

        return implode(' ', $parts);
    }

    private bool $includeGraphs = true;

    /**
     * Controls whether insertGraph() actually rasterizes and embeds graphs, per the report form's
     * "with schemas" checkbox (unchecked by default — dot rasterization is the slow part of report
     * generation, so plain-text reports skip it entirely).
     */
    public function setIncludeGraphs(bool $includeGraphs): void
    {
        $this->includeGraphs = $includeGraphs;
    }

    /**
     * @var array<int, string>
     */
    private array $tempImagePaths = [];

    /**
     * Rasterizes a DOT graph and inserts it into the section. No-op when graphs are disabled (see
     * setIncludeGraphs()).
     *
     * The temp PNG is deliberately NOT deleted here: PhpWord's Element\Image only registers the
     * source path, it doesn't read the file until the writer's save() call bundles the media into
     * the .docx zip — which happens well after this method returns, once every section has been
     * built. Deleting the file immediately left every embedded graph as a blank placeholder (the
     * relationship was registered but the referenced media was already gone). Call
     * cleanupTempFiles() once the document has actually been saved.
     */
    public function insertGraph(Section $section, string $dot): void
    {
        if (! $this->includeGraphs) {
            return;
        }

        if ($this->countGraphNodes($dot) < 2) {
            return;
        }

        $imagePath = $this->generateGraphImage($this->applyGraphFontSize($dot));

        if (is_file($imagePath) && filesize($imagePath) > 0) {
            $section->addImage($imagePath, [
                'width' => $this->graphDisplayWidth($imagePath),
                'wrappingStyle' => 'inline',
                'alignment' => Jc::CENTER,
            ]);
            $this->tempImagePaths[] = $imagePath;
        } else {
            @unlink($imagePath);
        }
    }

    /**
     * Counts the distinct node declarations in a DOT source: lines shaped "ID [attrs]", excluding
     * the "graph"/"node"/"edge"/"digraph"/"subgraph"/"strict" default-attribute keywords and edges
     * (which always contain "->" in every graph builder in this codebase). A graph with fewer than
     * 2 nodes (a single icon, no relation to show) isn't worth embedding in the report.
     */
    private function countGraphNodes(string $dot): int
    {
        $reserved = ['graph', 'node', 'edge', 'digraph', 'subgraph', 'strict'];
        $nodeIds = [];

        foreach (explode("\n", $dot) as $line) {
            $line = trim($line);
            if ($line === '' || str_contains($line, '->')) {
                continue;
            }
            if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*\[/', $line, $matches)) {
                continue;
            }
            if (in_array(mb_strtolower($matches[1]), $reserved, true)) {
                continue;
            }
            $nodeIds[$matches[1]] = true;
        }

        return count($nodeIds);
    }

    /**
     * Injects a graph/node/edge fontsize default (1pt below the document's own default font size)
     * right after the digraph's opening brace, so node/edge labels in the rasterized PNG stay
     * visually consistent with the surrounding Word text. Inserted first so a builder's own
     * attribute statements (e.g. SecurityZoneGraphBuilder's "node [fontname=...]") still apply on
     * top of it — Graphviz default-attribute statements are cumulative, not a full reset.
     */
    private function applyGraphFontSize(string $dot): string
    {
        $fontSize = max(1, Settings::getDefaultFontSize() - 1);
        $attrs = sprintf('graph [fontsize=%1$d];node [fontsize=%1$d];edge [fontsize=%1$d];', $fontSize);

        $result = preg_replace('/^(\s*(?:strict\s+)?digraph\s*\w*\s*\{)/i', '$1'.$attrs, $dot, 1);

        return $result ?? $dot;
    }

    /**
     * Display width (in points) for a rasterized graph PNG: proportionally capped at
     * MAX_GRAPH_WIDTH_PT, never enlarged beyond the image's own natural size. PhpWord's Image
     * element auto-computes a proportional height from this width alone
     * (Element\Image::setProportionalSize()), so only the width needs resolving here.
     */
    private function graphDisplayWidth(string $imagePath): float
    {
        $imageSize = @getimagesize($imagePath);
        if ($imageSize === false || $imageSize[0] <= 0) {
            return self::MAX_GRAPH_WIDTH_PT;
        }

        return self::capWidthToPt($imageSize[0], self::GRAPH_DPI);
    }

    /**
     * Pure conversion: a pixel width rasterized at $dpi, converted to points and capped at
     * MAX_GRAPH_WIDTH_PT (the report's usable page width) — never enlarged past its natural size.
     */
    private static function capWidthToPt(int $widthPx, int $dpi): float
    {
        $naturalWidthPt = $widthPx * 72 / $dpi;

        return min($naturalWidthPt, self::MAX_GRAPH_WIDTH_PT);
    }

    /**
     * Deletes the temp graph images accumulated by insertGraph(). Must be called after the document
     * has been written to disk (PhpWord reads these files lazily at save() time).
     */
    public function cleanupTempFiles(): void
    {
        foreach ($this->tempImagePaths as $path) {
            @unlink($path);
        }
        $this->tempImagePaths = [];
    }

    public function addTable(Section $section, ?string $title = null): Table
    {
        $table = $section->addTable(
            [
                'borderSize' => 2,
                'borderColor' => '006699',
                'cellMargin' => 80,
                'alignment' => JcTable::CENTER,
            ]
        );
        $table->addRow();
        $table->addCell(8000, ['gridSpan' => 2])
            ->addText(
                $title,
                self::FANCY_TABLE_TITLE_STYLE,
                self::NO_SPACE
            );

        return $table;
    }

    public function addTextRow(Table $table, string $title, ?string $value = null): void
    {
        $table->addRow();
        $table->addCell(2000, self::NO_SPACE)->addText($title, self::FANCY_LEFT_TABLE_CELL_STYLE, self::NO_SPACE);
        $table->addCell(6000, self::NO_SPACE)->addText($value, self::FANCY_RIGHT_TABLE_CELL_STYLE, self::NO_SPACE);
    }

    public function addHTMLRow(Table $table, string $title, ?string $value = null): void
    {
        $table->addRow();
        $table->addCell(2000)->addText($title, self::FANCY_LEFT_TABLE_CELL_STYLE, self::NO_SPACE);
        try {
            Html::addHtml($table->addCell(6000), str_replace('<br>', '<br/>', $value ?? ''));
        } catch (\Exception $e) {
            Log::error('WordHelper - Invalid HTML '.$value);
        }
    }

    public function addTextRunRow(Table $table, string $title)
    {
        $table->addRow();
        $table->addCell(2000)->addText($title, self::FANCY_LEFT_TABLE_CELL_STYLE, self::NO_SPACE);
        $cell = $table->addCell(6000);

        return $cell->addTextRun(self::FANCY_RIGHT_TABLE_CELL_STYLE);
    }

    public function dotImage(string $imagePath, string $name): string
    {
        return '[shape=none label=<'.
              '<TABLE border="0" cellborder="0" cellspacing="0">'.
              '<TR><TD><IMG SRC="'.public_path($imagePath).'"/></TD></TR>'.
              '<TR><TD>'.$name.'</TD></TR>'.
              '</TABLE> >]';
    }

    public function generateGraphImage(string $graph): string
    {
        $dotPath = tempnam(sys_get_temp_dir(), 'dot');

        try {
            $dotFile = fopen($dotPath, 'w');
            fwrite($dotFile, $graph);
            fclose($dotFile);

            $imagePath = tempnam(sys_get_temp_dir(), 'png');

            // add "unset SERVER_NAME;" due to Apache2
            // see: https://github.com/glejeune/Ruby-Graphviz/issues/69
            shell_exec('unset SERVER_NAME; /usr/bin/dot -Tpng -Gdpi='.self::GRAPH_DPI." {$dotPath} -o{$imagePath}");
        } finally {
            @unlink($dotPath);
        }

        return $imagePath;
    }
}
