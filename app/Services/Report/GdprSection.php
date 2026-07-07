<?php

namespace App\Services\Report;

use App\Models\Cartographer;
use App\Models\DataProcessing;
use App\Models\MacroProcessus;
use App\Models\SecurityControl;
use App\Services\Graph\GdprGraphBuilder;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpWord\Element\Section;

class GdprSection implements ReportSection
{
    public function build(Section $section, WordHelper $helper, array $selectedVues): void
    {
        $section->addTitle(trans('cruds.menu.gdpr.title'), 1);

        $macroProcessuses = Cartographer::scopedQuery(MacroProcessus::query())->get();
        $dataProcessings = Cartographer::scopedQuery(DataProcessing::query())
            ->with(['processes', 'applications', 'informations', 'documents'])
            ->get()
            ->sortBy(fn (DataProcessing $item) => mb_strtolower((string) $item->name));
        $securityControls = Cartographer::scopedQuery(SecurityControl::query())->get()
            ->sortBy(fn (SecurityControl $item) => mb_strtolower((string) $item->name));

        $this->addDataProcessing($section, $helper, $dataProcessings, $macroProcessuses, $selectedVues);
        $this->addSecurityControls($section, $helper, $securityControls);
    }

    /**
     * @param  Collection<int, DataProcessing>  $dataProcessings
     * @param  Collection<int, MacroProcessus>  $macroProcessuses
     * @param  array<int, string>  $selectedVues
     */
    private function addDataProcessing(Section $section, WordHelper $helper, Collection $dataProcessings, Collection $macroProcessuses, array $selectedVues): void
    {
        if ($dataProcessings->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.dataProcessing.title'), 2);

        $graphBuilder = new GdprGraphBuilder;

        foreach ($dataProcessings as $dataProcessing) {
            $helper->addBookmarkedTitle($section, $dataProcessing->getUID(), (string) $dataProcessing->name, 3);
            $this->addDataProcessingGraph($section, $helper, $graphBuilder, $dataProcessing, $macroProcessuses);

            $table = $helper->addTable($section, (string) $dataProcessing->name);

            $helper->addTextRow($table, trans('cruds.dataProcessing.fields.legal_basis'), $dataProcessing->legal_basis);
            $helper->addHTMLRow($table, trans('cruds.dataProcessing.fields.description'), $dataProcessing->description);
            $helper->addHTMLRow($table, trans('cruds.dataProcessing.fields.responsible'), $dataProcessing->responsible);
            $helper->addHTMLRow($table, trans('cruds.dataProcessing.fields.purpose'), $dataProcessing->purpose);

            $lawfulnessBases = array_filter([
                $dataProcessing->lawfulness_consent ? trans('cruds.dataProcessing.fields.lawfulness_consent') : null,
                $dataProcessing->lawfulness_contract ? trans('cruds.dataProcessing.fields.lawfulness_contract') : null,
                $dataProcessing->lawfulness_legal_obligation ? trans('cruds.dataProcessing.fields.lawfulness_legal_obligation') : null,
                $dataProcessing->lawfulness_vital_interest ? trans('cruds.dataProcessing.fields.lawfulness_vital_interest') : null,
                $dataProcessing->lawfulness_public_interest ? trans('cruds.dataProcessing.fields.lawfulness_public_interest') : null,
                $dataProcessing->lawfulness_legitimate_interest ? trans('cruds.dataProcessing.fields.lawfulness_legitimate_interest') : null,
            ]);
            $helper->addTextRow($table, trans('cruds.dataProcessing.fields.lawfulness'), implode(', ', $lawfulnessBases));
            $helper->addHTMLRow($table, '', $dataProcessing->lawfulness);

            $helper->addHTMLRow($table, trans('cruds.dataProcessing.fields.categories'), $dataProcessing->categories);
            $helper->addHTMLRow($table, trans('cruds.dataProcessing.fields.data_source'), $dataProcessing->data_source);
            $helper->addHTMLRow($table, trans('cruds.dataProcessing.fields.data_collection_obligation'), $dataProcessing->data_collection_obligation);
            $helper->addHTMLRow($table, trans('cruds.dataProcessing.fields.recipients'), $dataProcessing->recipients);
            $helper->addHTMLRow($table, trans('cruds.dataProcessing.fields.transfert'), $dataProcessing->transfert);
            $helper->addHTMLRow($table, trans('cruds.dataProcessing.fields.automated_decision_making'), $dataProcessing->automated_decision_making);
            $helper->addHTMLRow($table, trans('cruds.dataProcessing.fields.retention'), $dataProcessing->retention);
            $helper->addHTMLRow($table, trans('cruds.dataProcessing.fields.data_subject_rights'), $dataProcessing->data_subject_rights);

            if ($dataProcessing->update_date !== null) {
                $helper->addTextRow($table, trans('cruds.dataProcessing.fields.update_date'), $dataProcessing->update_date->format('d-m-Y'));
            }

            if ($dataProcessing->processes->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.dataProcessing.fields.processes'), $dataProcessing->processes, $selectedVues);
            }

            if ($dataProcessing->applications->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.dataProcessing.fields.applications'), $dataProcessing->applications, $selectedVues);
            }

            if ($dataProcessing->informations->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.dataProcessing.fields.information'), $dataProcessing->informations, $selectedVues);
            }

            if ($dataProcessing->documents->isNotEmpty()) {
                $helper->addDocumentLinksRow($table, trans('cruds.dataProcessing.fields.documents'), $dataProcessing->documents);
            }
        }
    }

    /**
     * Per-registre subgraph: this DataProcessing plus its direct MacroProcessus/Process/Application
     * links, reusing GdprGraphBuilder::buildDot()'s existing structure filtered down to just this
     * one DataProcessing. Skipped when it has neither processes nor applications (an isolated node
     * adds nothing beyond its own table row).
     *
     * @param  Collection<int, MacroProcessus>  $macroProcessuses
     */
    private function addDataProcessingGraph(Section $section, WordHelper $helper, GdprGraphBuilder $graphBuilder, DataProcessing $dataProcessing, Collection $macroProcessuses): void
    {
        $processes = $dataProcessing->processes;
        $applications = $dataProcessing->applications;

        if ($processes->isEmpty() && $applications->isEmpty()) {
            return;
        }

        $macroProcessIds = $processes->pluck('macroprocess_id')->filter()->unique();
        $ownMacroProcessuses = $macroProcessuses->whereIn('id', $macroProcessIds);

        $dot = $graphBuilder->buildDot($ownMacroProcessuses, $processes, new Collection([$dataProcessing]), $applications, [
            'withHref' => false,
            'iconPathResolver' => fn (string $webPath) => public_path(ltrim($webPath, '/')),
        ]);
        $helper->insertGraph($section, $dot);
    }

    /**
     * SecurityControl deliberately shows only name/description, faithful to its current
     * show.blade.php — decision confirmed by the user 2026-07-05 (§1.1 Vue 7 of the analysis doc):
     * its inverse relations (Application::securityControls(), Process::securityControls()) are not
     * rendered anywhere in the app today, including from Application/Process themselves.
     *
     * @param  Collection<int, SecurityControl>  $securityControls
     */
    private function addSecurityControls(Section $section, WordHelper $helper, Collection $securityControls): void
    {
        if ($securityControls->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.securityControl.title'), 2);

        foreach ($securityControls as $securityControl) {
            $bookmark = 'SECURITY_CONTROL_'.$securityControl->id;
            $helper->addBookmarkedTitle($section, $bookmark, (string) $securityControl->name, 3);
            $table = $helper->addTable($section, (string) $securityControl->name);

            $helper->addHTMLRow($table, trans('cruds.securityControl.fields.description'), $securityControl->description);
        }
    }
}
