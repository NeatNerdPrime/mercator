<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\ApplicationModule;
use App\Models\ApplicationService;
use App\Models\Cartographer;
use App\Models\Database;
use App\Models\Entity;
use App\Services\Cairn\CairnApplicationDiagramService;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoints de rendu du diagramme Cairn (vue `application`). Le CRUD/liste resourceful
 * (index/create/store/edit/update/destroy) est fourni séparément ; ce contrôleur n'ajoute que
 * `search` (Select2 #2) et `generate` (résolution sélection -> DSL, sans rendu SVG serveur).
 */
class CairnController extends Controller
{
    /** @var array<string, class-string> */
    private const SEARCHABLE_TYPES = [
        'entity' => Entity::class,
        'application' => Application::class,
        'service' => ApplicationService::class,
        'module' => ApplicationModule::class,
        'database' => Database::class,
        'flux' => ApplicationFlow::class,
    ];

    /**
     * TEMPORAIRE — le CRUD resourceful (index/create/store/edit/update/destroy) doit venir
     * du prompt "sauvegarde". Cette méthode ne sert qu'à exposer le canevas pour vérification
     * manuelle en Phase 3 ; à retirer/fusionner quand le contrôleur resourceful sera en place.
     */
    public function create(): View
    {
        abort_if(Gate::denies('cairn_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.cairn.create');
    }

    public function search(Request $request): JsonResponse
    {
        abort_if(Gate::denies('cairn_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $modelClass = self::SEARCHABLE_TYPES[(string) $request->string('type')] ?? null;

        if ($modelClass === null) {
            return response()->json([]);
        }

        $term = mb_strtolower(trim((string) $request->string('search')));
        $driver = DB::connection()->getDriverName();

        $results = Cartographer::scopedQueryByClass($modelClass)
            ->select(['id', 'name'])
            ->when($term !== '', function ($query) use ($term, $driver) {
                $sql = 'LOWER(name) LIKE ?';
                if ($driver === 'sqlite') {
                    $sql .= " ESCAPE '\\'";
                }
                $query->whereRaw($sql, ['%'.$this->escapeLike($term).'%']);
            })
            ->orderByRaw('LOWER(name)')
            ->limit(50)
            ->get();

        return response()->json(
            $results->map(fn ($item) => ['id' => $item->getAttribute('id'), 'text' => $item->getAttribute('name')])->values()
        );
    }

    public function generate(Request $request, CairnApplicationDiagramService $service): JsonResponse
    {
        abort_if(Gate::denies('cairn_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $validated = $request->validate([
            'selection' => ['array'],
            'selection.*.type' => ['required_with:selection', 'string', 'in:'.implode(',', array_keys(self::SEARCHABLE_TYPES))],
            'selection.*.id' => ['required_with:selection', 'integer'],
        ]);

        $dsl = $service->build($validated['selection'] ?? []);

        if ($dsl === null) {
            return response()->json(['empty' => true]);
        }

        return response()->json(['dsl' => $dsl]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['=', '%', '_'], ['==', '=%', '=_'], $value);
    }
}
