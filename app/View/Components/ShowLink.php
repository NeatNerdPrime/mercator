<?php

namespace App\View\Components;

use App\Support\ModelRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;
use Illuminate\View\View;

class ShowLink extends Component
{
    public string $label;

    public ?string $url;

    public function __construct(Model $model, ?string $label = null)
    {
        $this->label = $label ?? ($model->name ?? '');

        $routeName = in_array(get_class($model), ModelRegistry::LINKABLE_MODELS, true)
            ? ModelRegistry::routeName(get_class($model))
            : null;

        $this->url = ($routeName && Gate::allows('show-object', $model))
            ? route($routeName, $model->getKey())
            : null;
    }

    public function render(): View
    {
        return view('components.show-link');
    }
}
