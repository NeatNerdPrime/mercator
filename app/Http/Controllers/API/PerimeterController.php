<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\StorePerimeterRequest;
use App\Http\Requests\UpdatePerimeterRequest;
use App\Models\Perimeter;
use Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gestion des périmètres. Comme dans l'interface d'administration
 * (Admin\PerimeterController), tout est protégé par la permission `configure`.
 */
class PerimeterController extends APIController
{
    protected string $modelClass = Perimeter::class;

    /**
     * Les périmètres ne sont ni cartographiables ni scopés : pas de filtre
     * cartographe (qui exigerait une permission `perimeter_access` inexistante),
     * l'accès est entièrement décidé par la permission `configure`.
     */
    protected function newQuery(): Builder
    {
        return Perimeter::query();
    }

    public function index(Request $request)
    {
        abort_if(Gate::denies('configure'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return $this->indexResource($request);
    }

    public function store(StorePerimeterRequest $request): JsonResponse
    {
        $perimeter = Perimeter::query()->create($request->validated());

        return response()->json($perimeter, Response::HTTP_CREATED);
    }

    public function show(Perimeter $perimeter): JsonResource
    {
        abort_if(Gate::denies('configure'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $perimeter['roles'] = $perimeter->roles()->pluck('id');

        return new JsonResource($perimeter);
    }

    public function update(UpdatePerimeterRequest $request, Perimeter $perimeter): JsonResponse
    {
        $perimeter->update($request->validated());

        return response()->json();
    }

    public function destroy(Perimeter $perimeter): JsonResponse
    {
        abort_if(Gate::denies('configure'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($perimeter->isDefault()) {
            return response()->json(
                ['message' => trans('cruds.perimeter.errors.default_not_deletable')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($perimeter->isInUse()) {
            return response()->json(
                ['message' => trans('cruds.perimeter.errors.in_use')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $perimeter->delete();

        return response()->json();
    }
}
