<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perimeter;
use App\Support\PerimeterSettings;
use Gate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PerimeterController extends Controller
{
    private const REDIRECT_TAB = 'perimeters';

    public function activation(Request $request): RedirectResponse
    {
        abort_if(Gate::denies('configure'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        PerimeterSettings::setEnabled($request->boolean('perimeters_enabled'));

        return $this->backToTab(trans('cruds.configuration.saved'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(Gate::denies('configure'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'nom' => ['required', 'string', 'min:2', 'max:32', 'unique:perimeters,nom'],
        ]);

        Perimeter::query()->create($data);

        return $this->backToTab(trans('cruds.configuration.saved'));
    }

    public function update(Request $request, Perimeter $perimeter): RedirectResponse
    {
        abort_if(Gate::denies('configure'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'nom' => [
                'required',
                'string',
                'min:2',
                'max:32',
                Rule::unique('perimeters', 'nom')->ignore($perimeter->id),
            ],
        ]);

        $perimeter->update($data);

        return $this->backToTab(trans('cruds.configuration.saved'));
    }

    public function destroy(Perimeter $perimeter): RedirectResponse
    {
        abort_if(Gate::denies('configure'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($perimeter->isDefault()) {
            return $this->backToTab(trans('cruds.perimeter.errors.default_not_deletable'), false);
        }

        if ($perimeter->isInUse()) {
            return $this->backToTab(trans('cruds.perimeter.errors.in_use'), false);
        }

        $perimeter->delete();

        return $this->backToTab(trans('cruds.configuration.saved'));
    }

    private function backToTab(string $message, bool $success = true): RedirectResponse
    {
        return redirect(route('admin.config.parameters').'?tab='.self::REDIRECT_TAB)
            ->with($success ? 'success' : 'error', $message);
    }
}
