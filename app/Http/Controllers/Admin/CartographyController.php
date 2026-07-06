<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Report\ReportBuilder;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CartographyController extends Controller
{
    public function __construct(private readonly ReportBuilder $reportBuilder) {}

    public function cartography(Request $request)
    {
        abort_if(Gate::denies('reports_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vues = $request->input('vues', []);
        $withGraphs = $request->boolean('graph');

        $filepath = $this->reportBuilder->build($vues, $withGraphs);

        return response()->download($filepath)->deleteFileAfterSend(true);
    }
}
