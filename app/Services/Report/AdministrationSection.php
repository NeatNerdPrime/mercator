<?php

namespace App\Services\Report;

use App\Models\AdminUser;
use App\Models\Annuaire;
use App\Models\Cartographer;
use App\Models\Domain;
use App\Models\ForestAd;
use App\Models\ZoneAdmin;
use App\Services\Graph\AdministrationGraphBuilder;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpWord\Element\Section;

class AdministrationSection implements ReportSection
{
    public function build(Section $section, WordHelper $helper, array $selectedVues): void
    {
        $section->addTitle(trans('cruds.menu.administration.title'), 1);

        $zoneAdmins = Cartographer::scopedQuery(ZoneAdmin::query())
            ->with(['annuaires', 'forestAds'])
            ->get()
            ->sortBy(fn (ZoneAdmin $item) => mb_strtolower((string) $item->name));

        $annuaires = Cartographer::scopedQuery(Annuaire::query())
            ->with('zoneAdmin')
            ->get()
            ->sortBy(fn (Annuaire $item) => mb_strtolower((string) $item->name));

        $forestAds = Cartographer::scopedQuery(ForestAd::query())
            ->with(['zoneAdmin', 'domains'])
            ->get()
            ->sortBy(fn (ForestAd $item) => mb_strtolower((string) $item->name));

        $domains = Cartographer::scopedQuery(Domain::query())
            ->with(['forestAds', 'logicalServers'])
            ->get()
            ->sortBy(fn (Domain $item) => mb_strtolower((string) $item->name));

        $adminUsers = Cartographer::scopedQuery(AdminUser::query())
            ->with('domain')
            ->get()
            ->sortBy(fn (AdminUser $item) => mb_strtolower((string) $item->user_id));

        $dot = (new AdministrationGraphBuilder)->buildDot($zoneAdmins, $annuaires, $forestAds, $domains, $adminUsers, [
            'withHref' => false,
            'iconPathResolver' => fn (string $webPath) => public_path(ltrim($webPath, '/')),
        ]);
        $helper->insertGraph($section, $dot);

        $this->addZoneAdmins($section, $helper, $zoneAdmins, $selectedVues);
        $this->addAnnuaires($section, $helper, $annuaires, $selectedVues);
        $this->addForestAds($section, $helper, $forestAds, $selectedVues);
        $this->addDomains($section, $helper, $domains, $selectedVues);
        $this->addAdminUsers($section, $helper, $adminUsers, $selectedVues);
    }

    /**
     * @param  Collection<int, ZoneAdmin>  $zoneAdmins
     * @param  array<int, string>  $selectedVues
     */
    private function addZoneAdmins(Section $section, WordHelper $helper, Collection $zoneAdmins, array $selectedVues): void
    {
        if ($zoneAdmins->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.zoneAdmin.title'), 2);

        foreach ($zoneAdmins as $zoneAdmin) {
            $helper->addBookmarkedTitle($section, $zoneAdmin->getUID(), (string) $zoneAdmin->name, 3);
            $table = $helper->addTable($section, (string) $zoneAdmin->name);

            $helper->addHTMLRow($table, trans('cruds.zoneAdmin.fields.description'), $zoneAdmin->description);

            if ($zoneAdmin->annuaires->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.zoneAdmin.fields.annuaires'), $zoneAdmin->annuaires, $selectedVues);
            }

            if ($zoneAdmin->forestAds->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.zoneAdmin.fields.forests'), $zoneAdmin->forestAds, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, Annuaire>  $annuaires
     * @param  array<int, string>  $selectedVues
     */
    private function addAnnuaires(Section $section, WordHelper $helper, Collection $annuaires, array $selectedVues): void
    {
        if ($annuaires->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.annuaire.title'), 2);

        foreach ($annuaires as $annuaire) {
            $helper->addBookmarkedTitle($section, $annuaire->getUID(), (string) $annuaire->name, 3);
            $table = $helper->addTable($section, (string) $annuaire->name);

            $helper->addHTMLRow($table, trans('cruds.annuaire.fields.description'), $annuaire->description);
            $helper->addTextRow($table, trans('cruds.annuaire.fields.solution'), $annuaire->solution);

            if ($annuaire->zoneAdmin !== null) {
                $run = $helper->addTextRunRow($table, trans('cruds.annuaire.fields.zone_admin'));
                $helper->linkOrText($run, $annuaire->zoneAdmin, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, ForestAd>  $forestAds
     * @param  array<int, string>  $selectedVues
     */
    private function addForestAds(Section $section, WordHelper $helper, Collection $forestAds, array $selectedVues): void
    {
        if ($forestAds->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.forestAd.title'), 2);

        foreach ($forestAds as $forestAd) {
            $helper->addBookmarkedTitle($section, $forestAd->getUID(), (string) $forestAd->name, 3);
            $table = $helper->addTable($section, (string) $forestAd->name);

            $helper->addHTMLRow($table, trans('cruds.forestAd.fields.description'), $forestAd->description);

            if ($forestAd->zoneAdmin !== null) {
                $run = $helper->addTextRunRow($table, trans('cruds.forestAd.fields.zone_admin'));
                $helper->linkOrText($run, $forestAd->zoneAdmin, $selectedVues);
            }

            if ($forestAd->domains->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.forestAd.fields.domains'), $forestAd->domains, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, Domain>  $domains
     * @param  array<int, string>  $selectedVues
     */
    private function addDomains(Section $section, WordHelper $helper, Collection $domains, array $selectedVues): void
    {
        if ($domains->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.domain.title'), 2);

        foreach ($domains as $domain) {
            $helper->addBookmarkedTitle($section, $domain->getUID(), (string) $domain->name, 3);
            $table = $helper->addTable($section, (string) $domain->name);

            $helper->addHTMLRow($table, trans('cruds.domain.fields.description'), $domain->description);
            $helper->addTextRow($table, trans('cruds.domain.fields.domain_ctrl_cnt'), (string) $domain->domain_ctrl_cnt);
            $helper->addTextRow($table, trans('cruds.domain.fields.user_count'), (string) $domain->user_count);
            $helper->addTextRow($table, trans('cruds.domain.fields.machine_count'), (string) $domain->machine_count);
            $helper->addTextRow($table, trans('cruds.domain.fields.relation_inter_domaine'), $domain->relation_inter_domaine);

            if ($domain->forestAds->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.domain.fields.forestAds'), $domain->forestAds, $selectedVues);
            }

            if ($domain->logicalServers->isNotEmpty()) {
                $helper->addLinkListRow($table, trans('cruds.domain.fields.logical_servers'), $domain->logicalServers, $selectedVues);
            }
        }
    }

    /**
     * @param  Collection<int, AdminUser>  $adminUsers
     * @param  array<int, string>  $selectedVues
     */
    private function addAdminUsers(Section $section, WordHelper $helper, Collection $adminUsers, array $selectedVues): void
    {
        if ($adminUsers->isEmpty()) {
            return;
        }

        $section->addTitle(trans('cruds.adminUser.title'), 2);

        foreach ($adminUsers as $adminUser) {
            $helper->addBookmarkedTitle($section, $adminUser->getUID(), (string) $adminUser->user_id, 3);
            $table = $helper->addTable($section, (string) $adminUser->user_id);

            $helper->addTextRow($table, trans('cruds.adminUser.fields.firstname'), $adminUser->firstname);
            $helper->addTextRow($table, trans('cruds.adminUser.fields.lastname'), $adminUser->lastname);
            $helper->addTextRow($table, trans('cruds.adminUser.fields.type'), $adminUser->type);
            $helper->addTextRow($table, trans('cruds.adminUser.fields.attributes'), $this->formatAttributes($adminUser->attributes));

            if ($adminUser->domain !== null) {
                $run = $helper->addTextRunRow($table, trans('cruds.adminUser.fields.domain'));
                $helper->linkOrText($run, $adminUser->domain, $selectedVues);
            }

            $helper->addHTMLRow($table, trans('cruds.adminUser.fields.description'), $adminUser->description);
        }
    }

    private function formatAttributes(?string $attributes): ?string
    {
        if ($attributes === null || trim($attributes) === '') {
            return $attributes;
        }

        return implode(', ', array_filter(explode(' ', $attributes)));
    }
}
