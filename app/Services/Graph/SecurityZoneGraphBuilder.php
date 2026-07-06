<?php

namespace App\Services\Graph;

use App\Models\AdminUser;
use App\Models\Building;
use App\Models\Zone;
use Illuminate\Support\Collection;

class SecurityZoneGraphBuilder
{
    /**
     * @param  Collection<int, Zone>  $zones
     * @param  Collection<int, Building>  $buildings
     * @param  Collection<int, AdminUser>  $adminUsers
     * @param  array{withHref?: bool, iconResolver?: callable(?int, string): string}  $options
     */
    public function buildDot(Collection $zones, Collection $buildings, Collection $adminUsers, array $options = []): string
    {
        $withHref = $options['withHref'] ?? true;
        $iconResolver = $options['iconResolver'] ?? fn (?int $iconId, string $fallback) => $iconId === null
            ? $fallback
            : route('admin.documents.show', $iconId);

        $lines = [
            'digraph {',
            'graph [rankdir=TB fontname="FreeSans"]',
            'node  [fontname="FreeSans"]',
            'edge  [fontname="FreeSans"]',
        ];

        foreach ($zones as $zone) {
            $lines[] = 'ZONE'.$zone->id.' [label="'.$this->escapeLabel($zone->name).'" shape=none labelloc="b" width=1 height=1.1 image="'.$iconResolver(null, '/images/zone.png').'"'.$this->href($zone, $withHref).']';
        }

        foreach ($buildings as $building) {
            $lines[] = 'BUILD'.$building->id.' [label="'.$this->escapeLabel($building->name).'" shape=none labelloc="b" width=1 height=1.1 image="'.$iconResolver(null, '/images/building.png').'"'.$this->href($building, $withHref).']';
        }

        foreach ($adminUsers as $adminUser) {
            $lines[] = 'AU'.$adminUser->id.' [label="'.$this->escapeLabel($adminUser->user_id).'" shape=none labelloc="b" width=1 height=1.1 image="'.$iconResolver(null, '/images/user.png').'"'.$this->href($adminUser, $withHref).']';
        }

        foreach ($zones as $zone) {
            foreach ($zone->childZones as $child) {
                if ($zones->contains('id', $child->id)) {
                    $lines[] = 'ZONE'.$zone->id.' -> ZONE'.$child->id;
                }
            }
            foreach ($zone->buildings as $building) {
                if ($buildings->contains('id', $building->id)) {
                    $lines[] = 'ZONE'.$zone->id.' -> BUILD'.$building->id;
                }
            }
            foreach ($zone->adminUsers as $adminUser) {
                if ($adminUsers->contains('id', $adminUser->id)) {
                    $lines[] = 'ZONE'.$zone->id.' -> AU'.$adminUser->id;
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
            ['path' => '/images/zone.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/building.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/user.png', 'width' => '64px', 'height' => '64px'],
        ];
    }

    private function escapeLabel(?string $value): string
    {
        return e(addslashes($value ?? ''));
    }

    private function href(mixed $model, bool $withHref): string
    {
        return $withHref ? ' href="#'.$model->getUID().'"' : '';
    }
}
