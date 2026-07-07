<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Report\ReportBuilder;
use App\Services\Report\TemplateValidatorService;
use App\Support\ReportTemplateSettings;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class CartographyController extends Controller
{
    public function __construct(
        private readonly ReportBuilder $reportBuilder,
        private readonly TemplateValidatorService $templateValidator = new TemplateValidatorService
    ) {}

    public function cartography(Request $request)
    {
        abort_if(Gate::denies('reports_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $vues = $request->input('vues', []);
        $withGraphs = $request->boolean('graph');

        $filepath = $this->reportBuilder->build($vues, $withGraphs);

        return response()->download($filepath)->deleteFileAfterSend(true);
    }

    public function downloadDefaultTemplate()
    {
        abort_if(Gate::denies('reports_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return response()->download(ReportTemplateSettings::defaultTemplatePath(), 'default-template.docx');
    }

    public function downloadCurrentTemplate()
    {
        abort_if(Gate::denies('reports_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $current = ReportTemplateSettings::load();
        abort_if($current === null || ! is_file(ReportTemplateSettings::storagePath()), Response::HTTP_NOT_FOUND, '404 Not Found');

        return response()->download(ReportTemplateSettings::storagePath(), $current['original_name']);
    }

    public function uploadTemplate(Request $request)
    {
        abort_if(Gate::denies('configure'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $request->validate([
            'template' => ['required', 'file', 'mimes:docx', 'max:10240'],
        ]);

        $file = $request->file('template');

        $errors = $this->templateValidator->validate($file->getRealPath());

        if ($errors !== []) {
            return back()->withErrors(['template' => trans($errors[0])]);
        }

        ReportTemplateSettings::ensureStorageDirectoryExists();
        $targetPath = ReportTemplateSettings::storagePath();
        $file->move(dirname($targetPath), basename($targetPath));

        ReportTemplateSettings::save($file->getClientOriginalName(), Carbon::now());

        return back()->with('status', trans('report_template.upload_success'));
    }
}
