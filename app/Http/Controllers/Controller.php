<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Resolve the page size for a server-side paginated list, remembering the
     * user's choice in session (per route) so it survives future visits.
     */
    protected function resolvePerPage(int $default = 50, int $min = 10, int $max = 500): int
    {
        $key = $this->perPageSessionKey();

        if (request()->has('per_page')) {
            $perPage = min(max((int) request('per_page'), $min), $max);
            session()->put($key, $perPage);

            return $perPage;
        }

        return (int) session($key, $default);
    }

    protected function perPageSessionKey(): string
    {
        return 'per_page.'.(request()->route()?->getName() ?? request()->path());
    }
}
