<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perimeter;
use App\Support\PerimeterSettings;
use Illuminate\Contracts\View\View;

class InfoController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();
        $roles = $user->roles()->orderBy('title')->pluck('title')->toArray();

        $cartographerTypes = [];
        if (session('is_cartographer')) {
            $perms = session('cartographer_permissions', []);
            $cartographerTypes = array_map(
                fn ($fqcn) => class_basename($fqcn),
                array_keys(array_filter($perms, fn ($ids) => ! empty($ids)))
            );
            sort($cartographerTypes);
        }

        $perimetersEnabled = PerimeterSettings::isEnabled();
        $perimeters = $perimetersEnabled
            ? Perimeter::query()->whereIn('id', $user->perimeterIds())->orderBy('name')->pluck('name')->toArray()
            : [];

        $memLimitRaw = ini_get('memory_limit');
        $memUsedBytes = memory_get_usage(true);
        $memLimitBytes = $this->parseMemoryLimit($memLimitRaw);

        $memUsedMb = round($memUsedBytes / 1048576, 1);
        $memFreeMb = $memLimitBytes > 0 ? round(($memLimitBytes - $memUsedBytes) / 1048576, 1) : null;

        return view('doc.info', [
            'mercatorVersion' => app('mercator.version'),
            'appEnv' => config('app.env'),
            'appTimezone' => config('app.timezone'),
            'appLocale' => config('app.locale'),
            'dbDriver' => config('database.default'),
            'memLimit' => $memLimitRaw,
            'memUsedMb' => $memUsedMb,
            'memFreeMb' => $memFreeMb,
            'userName' => $user->name,
            'userEmail' => $user->email,
            'userLogin' => $user->login,
            'roles' => $roles,
            'isCartographer' => session('is_cartographer', false),
            'cartographerTypes' => $cartographerTypes,
            'perimetersEnabled' => $perimetersEnabled,
            'perimeters' => $perimeters,
        ]);
    }

    private function parseMemoryLimit(string $val): int
    {
        $unit = strtoupper(substr(trim($val), -1));
        $num = (int) $val;

        return match ($unit) {
            'G' => $num * 1073741824,
            'M' => $num * 1048576,
            'K' => $num * 1024,
            default => $num,
        };
    }
}
