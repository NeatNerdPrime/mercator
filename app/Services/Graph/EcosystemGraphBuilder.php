<?php

namespace App\Services\Graph;

use App\Models\Entity;
use App\Models\Relation;
use Illuminate\Support\Collection;

class EcosystemGraphBuilder
{
    /**
     * @param  Collection<int, Entity>  $entities
     * @param  Collection<int, Relation>  $relations
     * @param  array{withHref?: bool, iconResolver?: callable}  $options
     */
    public function buildDot(Collection $entities, Collection $relations, array $options = []): string
    {
        $withHref = $options['withHref'] ?? true;
        $iconResolver = $options['iconResolver'] ?? fn (Entity $entity) => $entity->icon_id === null
            ? '/images/entity.png'
            : route('admin.documents.show', $entity->icon_id);

        $lines = [];
        $lines[] = 'digraph  {';
        $lines[] = 'node [shape=none labelloc="b"  width=1 height=1.1]';

        foreach ($entities as $entity) {
            $href = $withHref ? ' href="#'.$entity->getUID().'"' : '';
            $lines[] = 'E'.$entity->id.' [label="'.$this->escapeLabel($entity->name).'" image="'.$iconResolver($entity).'"'.$href.']';

            if ($entity->parentEntity !== null && $entities->contains('id', $entity->parentEntity->id)) {
                $lines[] = 'E'.$entity->parentEntity->id.' -> E'.$entity->id;
            }
        }

        foreach ($relations as $relation) {
            if ($entities->contains('id', $relation->source_id) && $entities->contains('id', $relation->destination_id)) {
                $href = $withHref ? ' href="#'.$relation->getUID().'"' : '';
                $lines[] = 'E'.$relation->source_id.' -> E'.$relation->destination_id.' [label="'.$this->escapeLabel($relation->name).'"'.$href.']';
            }
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Entity>  $entities
     * @return array<int, array{path: string, width: string, height: string}>
     */
    public function imageManifest(Collection $entities): array
    {
        $manifest = [
            ['path' => '/images/entity.png', 'width' => '64px', 'height' => '64px'],
        ];

        foreach ($entities as $entity) {
            if ($entity->icon_id !== null) {
                $manifest[] = [
                    'path' => route('admin.documents.show', $entity->icon_id),
                    'width' => '64px',
                    'height' => '64px',
                ];
            }
        }

        return $manifest;
    }

    private function escapeLabel(?string $value): string
    {
        return e($value ?? '');
    }
}
