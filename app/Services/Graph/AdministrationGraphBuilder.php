<?php

namespace App\Services\Graph;

use App\Models\AdminUser;
use App\Models\Annuaire;
use App\Models\Application;
use App\Models\Cartographer;
use App\Models\Domain;
use App\Models\ForestAd;
use App\Models\ZoneAdmin;
use Illuminate\Support\Collection;

class AdministrationGraphBuilder
{
    /**
     * @param  Collection<int, ZoneAdmin>  $zones
     * @param  Collection<int, Annuaire>  $annuaires
     * @param  Collection<int, ForestAd>  $forests
     * @param  Collection<int, Domain>  $domains
     * @param  Collection<int, AdminUser>  $adminUsers
     * @param  array{withHref?: bool, iconPathResolver?: callable(string): string}  $options
     */
    public function buildDot(
        Collection $zones,
        Collection $annuaires,
        Collection $forests,
        Collection $domains,
        Collection $adminUsers,
        array $options = []
    ): string {
        $withHref = $options['withHref'] ?? true;
        $iconPath = $options['iconPathResolver'] ?? fn (string $webPath) => $webPath;

        $lines = ['digraph  {'];

        foreach ($zones as $zone) {
            $lines[] = DotNode::withImage('Z'.$zone->id, $iconPath('/images/zoneadmin.png'), [e($zone->name)], $this->href($zone, $withHref));

            foreach ($zone->annuaires as $annuaire) {
                if ($annuaires->contains('id', $annuaire->id)) {
                    $lines[] = 'Z'.$zone->id.' -> A'.$annuaire->id;
                }
            }

            foreach ($zone->forestAds as $forest) {
                if ($forests->contains('id', $forest->id)) {
                    $lines[] = 'Z'.$zone->id.' -> F'.$forest->id;
                }
            }
        }

        foreach ($annuaires as $annuaire) {
            $lines[] = DotNode::withImage('A'.$annuaire->id, $iconPath('/images/annuaire.png'), [e($annuaire->name)], $this->href($annuaire, $withHref));
        }

        if (Cartographer::canAccess(Application::class)) {
            $applications = $annuaires->pluck('application')->filter()->unique('id');

            foreach ($applications as $application) {
                $lines[] = DotNode::withImage('AP'.$application->id, $iconPath('/images/application.png'), [e($application->name)], $this->href($application, $withHref));
            }

            foreach ($annuaires as $annuaire) {
                if ($annuaire->application !== null) {
                    $lines[] = 'AP'.$annuaire->application->id.' -> A'.$annuaire->id;
                }
            }
        }

        foreach ($forests as $forest) {
            $lines[] = DotNode::withImage('F'.$forest->id, $iconPath('/images/ldap.png'), [e($forest->name)], $this->href($forest, $withHref));

            foreach ($forest->domains as $domain) {
                if ($domains->contains('id', $domain->id)) {
                    $lines[] = 'F'.$forest->id.' -> D'.$domain->id;
                }
            }
        }

        foreach ($domains as $domain) {
            $lines[] = DotNode::withImage('D'.$domain->id, $iconPath('/images/domain.png'), [e($domain->name)], $this->href($domain, $withHref));
        }

        if (Cartographer::canAccess(AdminUser::class)) {
            foreach ($adminUsers as $user) {
                if ($user->domain_id !== null && $domains->contains('id', $user->domain_id)) {
                    $lines[] = DotNode::withImage('U'.$user->id, $iconPath('/images/user.png'), [e($user->user_id)], $this->href($user, $withHref));
                    $lines[] = 'D'.$user->domain_id.' -> U'.$user->id;
                }
            }
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array{path: string, width: string, height: string}>
     */
    public function imageManifest(): array
    {
        return [
            ['path' => '/images/zoneadmin.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/annuaire.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/application.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/ldap.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/domain.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/user.png', 'width' => '64px', 'height' => '64px'],
        ];
    }

    private function href(ZoneAdmin|Annuaire|Application|ForestAd|Domain|AdminUser $model, bool $withHref): string
    {
        return $withHref ? ' href="#'.$model->getUID().'"' : '';
    }
}
