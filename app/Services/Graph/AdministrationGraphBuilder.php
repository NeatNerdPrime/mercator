<?php

namespace App\Services\Graph;

use App\Models\AdminUser;
use App\Models\Annuaire;
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
            $lines[] = 'Z'.$zone->id.' [label="'.e($zone->name).'" shape=none labelloc="b"  width=1 height=1.1 image="'.$iconPath('/images/zoneadmin.png').'"'.$this->href($zone, $withHref).']';

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
            $lines[] = 'A'.$annuaire->id.' [label="'.e($annuaire->name).'" shape=none labelloc="b"  width=1 height=1.1 image="'.$iconPath('/images/annuaire.png').'"'.$this->href($annuaire, $withHref).']';
        }

        foreach ($forests as $forest) {
            $lines[] = 'F'.$forest->id.' [label="'.e($forest->name).'" shape=none labelloc="b"  width=1 height=1.1 image="'.$iconPath('/images/ldap.png').'"'.$this->href($forest, $withHref).']';

            foreach ($forest->domains as $domain) {
                if ($domains->contains('id', $domain->id)) {
                    $lines[] = 'F'.$forest->id.' -> D'.$domain->id;
                }
            }
        }

        foreach ($domains as $domain) {
            $lines[] = 'D'.$domain->id.' [label="'.e($domain->name).'" shape=none labelloc="b"  width=1 height=1.1 image="'.$iconPath('/images/domain.png').'"'.$this->href($domain, $withHref).']';
        }

        if (Cartographer::canAccess(AdminUser::class)) {
            foreach ($adminUsers as $user) {
                if ($user->domain_id !== null && $domains->contains('id', $user->domain_id)) {
                    $lines[] = 'U'.$user->id.' [label="'.e($user->user_id).'" shape=none labelloc="b"  width=1 height=1.1 image="'.$iconPath('/images/user.png').'"'.$this->href($user, $withHref).']';
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
            ['path' => '/images/ldap.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/domain.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/user.png', 'width' => '64px', 'height' => '64px'],
        ];
    }

    private function href(ZoneAdmin|Annuaire|ForestAd|Domain|AdminUser $model, bool $withHref): string
    {
        return $withHref ? ' href="#'.$model->getUID().'"' : '';
    }
}
