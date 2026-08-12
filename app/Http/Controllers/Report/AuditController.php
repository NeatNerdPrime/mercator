<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Admin\HomeController;
use App\Support\ModelRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// PhpOffice
// see : https://phpspreadsheet.readthedocs.io/en/latest/topics/recipes/

class AuditController extends HomeController
{
    public function maturity()
    {
        $levels = $this->computeMaturity();

        $path = storage_path('app/levels-'.Carbon::today()->format('Ymd').'.xlsx');

        $header = [
            'Object',
            'Count 1',
            'Total 1',
            'Maturity 1',
            'Count 2',
            'Total 2',
            'Maturity 2',
            'Count 3',
            'Total 3',
            'Maturity 3',
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$header], null, 'A1');

        // bold title
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F77BE');

        // column size
        $sheet->getColumnDimension('A')->setAutoSize(true); // Objet
        $sheet->getColumnDimension('B')->setAutoSize(true); // Count 1
        $sheet->getColumnDimension('C')->setAutoSize(true); // Total 1
        $sheet->getColumnDimension('D')->setAutoSize(true); // % 1
        $sheet->getColumnDimension('E')->setAutoSize(true); // Count 2
        $sheet->getColumnDimension('F')->setAutoSize(true); // total 2
        $sheet->getColumnDimension('G')->setAutoSize(true); // % 2
        $sheet->getColumnDimension('H')->setAutoSize(true); // Count 3
        $sheet->getColumnDimension('I')->setAutoSize(true); // Total 3
        $sheet->getColumnDimension('J')->setAutoSize(true); // % 3

        // center cells
        $sheet->getStyle('B:J')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // percentage
        $sheet->getStyle('D')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle('G')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle('J')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

        // Initialise row count
        $row = 2;

        // ============
        // Ecosystem
        // ============
        $sheet->setCellValue("A{$row}", trans('cruds.menu.ecosystem.title_short'));
        $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAEC7E8');

        // L1
        $sheet->setCellValue("B{$row}", '=sum(B'.($row + 1).':B'.($row + 2).')');
        $sheet->setCellValue("C{$row}", '=sum(C'.($row + 1).':C'.($row + 2).')');
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");

        // L2
        $sheet->setCellValue("E{$row}", '=sum(E'.($row + 1).':E'.($row + 2).')');
        $sheet->setCellValue("F{$row}", '=sum(F'.($row + 1).':F'.($row + 2).')');
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");

        // L3
        $sheet->setCellValue("H{$row}", '=sum(H'.($row + 1).':H'.($row + 2).')');
        $sheet->setCellValue("I{$row}", '=sum(I'.($row + 1).':I'.($row + 2).')');
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Entities
        $sheet->setCellValue("A{$row}", trans('cruds.entity.title'));
        $sheet->setCellValue("B{$row}", $levels['entities_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['entities']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['entities_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['entities']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['entities_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['entities']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Relations
        $sheet->setCellValue("A{$row}", trans('cruds.relation.title'));
        $sheet->setCellValue("B{$row}", $levels['relations_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['relations']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['relations_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['relations']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['relations_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['relations']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // ============
        // Metier
        // ============
        $sheet->setCellValue("A{$row}", trans('cruds.menu.metier.title_short'));
        $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAEC7E8');

        // L1
        $sheet->setCellValue("B{$row}", '=sum(B'.($row + 1).':B'.($row + 7).')');
        $sheet->setCellValue("C{$row}", '=sum(C'.($row + 1).':C'.($row + 7).')');
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");

        // L2
        $sheet->setCellValue("E{$row}", '=sum(E'.($row + 1).':E'.($row + 7).')');
        $sheet->setCellValue("F{$row}", '=sum(F'.($row + 1).':F'.($row + 7).')');
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");

        // L3
        $sheet->setCellValue("H{$row}", '=sum(H'.($row + 1).':H'.($row + 7).')');
        $sheet->setCellValue("I{$row}", '=sum(I'.($row + 1).':I'.($row + 7).')');
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // MacroProcessus
        $sheet->setCellValue("A{$row}", trans('cruds.macroProcessus.title'));
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", '');
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", $levels['macroProcessuses_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['macroProcessuses']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['macroProcessuses_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['macroProcessuses']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Process
        $sheet->setCellValue("A{$row}", trans('cruds.process.title'));
        $sheet->setCellValue("B{$row}", $levels['processes_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['processes']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['processes_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['processes']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['processes_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['processes']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Activity
        $sheet->setCellValue("A{$row}", trans('cruds.activity.title'));
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", '');
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", $levels['activities_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['activities']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['activities_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['activities']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Operation
        $sheet->setCellValue("A{$row}", trans('cruds.operation.title'));
        $sheet->setCellValue("B{$row}", $levels['operations_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['operations']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['operations_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['operations']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['operations_lvl3']);
        $sheet->setCellValue("I{$row}", $levels['operations']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Tâche
        $sheet->setCellValue("A{$row}", trans('cruds.task.title'));
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", '');
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", '');
        $sheet->setCellValue("F{$row}", '');
        $sheet->setCellValue("G{$row}", '');
        $sheet->setCellValue("H{$row}", $levels['tasks_lvl3']);
        $sheet->setCellValue("I{$row}", $levels['tasks']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Acteur
        $sheet->setCellValue("A{$row}", trans('cruds.actor.title'));
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", '');
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", $levels['actors_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['actors']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['actors_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['actors']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Information
        $sheet->setCellValue("A{$row}", trans('cruds.information.title'));
        $sheet->setCellValue("B{$row}", $levels['informations_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['informations']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['informations_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['informations']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['informations_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['informations']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // ============
        // Application
        // ============
        $sheet->setCellValue("A{$row}", trans('cruds.menu.application.title_short'));
        $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAEC7E8');

        // L1
        $sheet->setCellValue("B{$row}", '=sum(B'.($row + 1).':B'.($row + 6).')');
        $sheet->setCellValue("C{$row}", '=sum(C'.($row + 1).':C'.($row + 6).')');
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");

        // L2
        $sheet->setCellValue("E{$row}", '=sum(E'.($row + 1).':E'.($row + 6).')');
        $sheet->setCellValue("F{$row}", '=sum(F'.($row + 1).':F'.($row + 6).')');
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");

        // L3
        $sheet->setCellValue("H{$row}", '=sum(H'.($row + 1).':H'.($row + 6).')');
        $sheet->setCellValue("I{$row}", '=sum(I'.($row + 1).':I'.($row + 6).')');
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Block applicatif
        $sheet->setCellValue("A{$row}", trans('cruds.applicationBlock.title'));
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", '');
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", $levels['applicationBlocks_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['applicationBlocks']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['applicationBlocks_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['applicationBlocks']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Applications
        $sheet->setCellValue("A{$row}", trans('cruds.application.title'));
        $sheet->setCellValue("B{$row}", $levels['applications_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['applications']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['applications_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['applications']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['applications_lvl3']);
        $sheet->setCellValue("I{$row}", $levels['applications']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // applicationService
        $sheet->setCellValue("A{$row}", trans('cruds.applicationService.title'));
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", '');
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", $levels['applicationServices_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['applicationServices']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['applicationServices_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['applicationServices']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // applicationModule
        $sheet->setCellValue("A{$row}", trans('cruds.applicationModule.title'));
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", '');
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", $levels['applicationModules_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['applicationModules']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['applicationModules_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['applicationModules']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // database
        $sheet->setCellValue("A{$row}", trans('cruds.database.title'));
        $sheet->setCellValue("B{$row}", $levels['databases_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['databases']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['databases_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['databases']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['databases_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['databases']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // flux
        $sheet->setCellValue("A{$row}", trans('cruds.applicationFlow.title'));
        $sheet->setCellValue("B{$row}", $levels['flows_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['flows']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['flows_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['flows']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['flows_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['flows']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // ===============
        // Administration
        // ===============
        $sheet->setCellValue("A{$row}", trans('cruds.menu.administration.title_short'));
        $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAEC7E8');

        // L1
        $sheet->setCellValue("B{$row}", '=sum(B'.($row + 1).':B'.($row + 4).')');
        $sheet->setCellValue("C{$row}", '=sum(C'.($row + 1).':C'.($row + 4).')');
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");

        // L2
        $sheet->setCellValue("E{$row}", '=sum(E'.($row + 1).':E'.($row + 4).')');
        $sheet->setCellValue("F{$row}", '=sum(F'.($row + 1).':F'.($row + 4).')');
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");

        // L3
        $sheet->setCellValue("H{$row}", '=sum(H'.($row + 1).':H'.($row + 4).')');
        $sheet->setCellValue("I{$row}", '=sum(I'.($row + 1).':I'.($row + 4).')');
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Zone
        $sheet->setCellValue("A{$row}", trans('cruds.zoneAdmin.title'));
        $sheet->setCellValue("B{$row}", $levels['zones_ad_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['zones_ad']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['zones_ad_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['zones_ad']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['zones_ad_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['zones_ad']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Annuaire
        $sheet->setCellValue("A{$row}", trans('cruds.annuaire.title'));
        $sheet->setCellValue("B{$row}", $levels['annuaires_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['annuaires']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['annuaires_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['annuaires']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['annuaires_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['annuaires']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Forest
        $sheet->setCellValue("A{$row}", trans('cruds.forestAd.title'));
        $sheet->setCellValue("B{$row}", $levels['forests_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['forests']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['forests_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['forests']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['forests_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['forests']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Domaines
        $sheet->setCellValue("A{$row}", trans('cruds.domain.title'));
        $sheet->setCellValue("B{$row}", $levels['domaines_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['domains']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['domaines_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['domains']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['domaines_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['domains']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // ======================
        // Infrastructure logique
        // ======================
        $sheet->setCellValue("A{$row}", trans('cruds.menu.logical_infrastructure.title_short'));
        $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAEC7E8');

        // L1
        $sheet->setCellValue("B{$row}", '=sum(B'.($row + 1).':B'.($row + 11).')');
        $sheet->setCellValue("C{$row}", '=sum(C'.($row + 1).':C'.($row + 11).')');
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");

        // L2
        $sheet->setCellValue("E{$row}", '=sum(E'.($row + 1).':E'.($row + 11).')');
        $sheet->setCellValue("F{$row}", '=sum(F'.($row + 1).':F'.($row + 11).')');
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");

        // L3
        $sheet->setCellValue("H{$row}", '=sum(H'.($row + 1).':H'.($row + 11).')');
        $sheet->setCellValue("I{$row}", '=sum(I'.($row + 1).':I'.($row + 11).')');
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Network
        $sheet->setCellValue("A{$row}", trans('cruds.network.title'));
        $sheet->setCellValue("B{$row}", $levels['networks_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['networks']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['networks_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['networks']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['networks_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['networks']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // SubNetwork
        $sheet->setCellValue("A{$row}", trans('cruds.subnetwork.title'));
        $sheet->setCellValue("B{$row}", $levels['subnetworks_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['subnetworks']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['subnetworks_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['subnetworks']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['subnetworks_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['subnetworks']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Gateway
        $sheet->setCellValue("A{$row}", trans('cruds.gateway.title'));
        $sheet->setCellValue("B{$row}", $levels['gateways_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['gateways']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['gateways_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['gateways']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['gateways_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['gateways']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // ExternalConnectedEntity
        $sheet->setCellValue("A{$row}", trans('cruds.externalConnectedEntity.title'));
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", '');
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", $levels['externalConnectedEntities_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['externalConnectedEntities']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['externalConnectedEntities_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['externalConnectedEntities']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // NetworkSwitch
        $sheet->setCellValue("A{$row}", trans('cruds.networkSwitch.title'));
        $sheet->setCellValue("B{$row}", $levels['switches_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['switches']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['switches_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['switches']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['switches_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['switches']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Router
        $sheet->setCellValue("A{$row}", trans('cruds.router.title'));
        $sheet->setCellValue("B{$row}", $levels['routers_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['routers']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['routers_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['routers']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['routers_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['routers']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // SecurityDevice
        $sheet->setCellValue("A{$row}", trans('cruds.securityDevice.title'));
        $sheet->setCellValue("B{$row}", $levels['securityDevices_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['securityDevices']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['securityDevices_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['securityDevices']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['securityDevices_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['securityDevices']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // DHCPServer
        $sheet->setCellValue("A{$row}", trans('cruds.dhcpServer.title'));
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", '');
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", $levels['DHCPServers_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['DHCPServers']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['DHCPServers_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['DHCPServers']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Clusters
        $sheet->setCellValue("A{$row}", trans('cruds.cluster.title'));
        $sheet->setCellValue("B{$row}", $levels['clusters_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['clusters']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['clusters_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['clusters']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['clusters_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['clusters']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // LogicalServer
        $sheet->setCellValue("A{$row}", trans('cruds.logicalServer.title'));
        $sheet->setCellValue("B{$row}", $levels['logicalServers_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['logicalServers']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['logicalServers_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['logicalServers']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['logicalServers_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['logicalServers']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Containers
        $sheet->setCellValue("A{$row}", trans('cruds.container.title'));
        $sheet->setCellValue("B{$row}", $levels['containers_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['containers']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['containers_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['containers']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['containers_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['containers']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // VLAN
        $sheet->setCellValue("A{$row}", trans('cruds.vlan.title'));
        $sheet->setCellValue("B{$row}", $levels['vlans_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['vlans']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['vlans_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['vlans']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['vlans_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['vlans']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");

        // certificates
        $sheet->setCellValue("A{$row}", trans('cruds.certificate.title'));
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", '');
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", $levels['certificates_lvl2']);
        $sheet->setCellValue("F{$row}", $levels['certificates']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['certificates_lvl2']);
        $sheet->setCellValue("I{$row}", $levels['certificates']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // =========================
        // Infrastructure physique
        // =========================
        $sheet->setCellValue("A{$row}", trans('cruds.menu.physical_infrastructure.title_short'));
        $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAEC7E8');

        // L1
        $sheet->setCellValue("B{$row}", '=sum(B'.($row + 1).':B'.($row + 11).')');
        $sheet->setCellValue("C{$row}", '=sum(C'.($row + 1).':C'.($row + 11).')');
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");

        // L2
        $sheet->setCellValue("E{$row}", '=sum(E'.($row + 1).':E'.($row + 11).')');
        $sheet->setCellValue("F{$row}", '=sum(F'.($row + 1).':F'.($row + 11).')');
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");

        // L3
        $sheet->setCellValue("H{$row}", '=sum(H'.($row + 1).':H'.($row + 11).')');
        $sheet->setCellValue("I{$row}", '=sum(I'.($row + 1).':I'.($row + 11).')');
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Site
        $sheet->setCellValue("A{$row}", trans('cruds.site.title'));
        $sheet->setCellValue("B{$row}", $levels['sites_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['sites']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['sites_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['sites']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['sites_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['sites']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Building
        $sheet->setCellValue("A{$row}", trans('cruds.building.title'));
        $sheet->setCellValue("B{$row}", $levels['buildings_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['buildings']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['buildings_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['buildings']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['buildings_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['buildings']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Bay
        $sheet->setCellValue("A{$row}", trans('cruds.bay.title'));
        $sheet->setCellValue("B{$row}", $levels['bays_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['bays']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['bays_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['bays']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['bays_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['bays']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // PhysicalServer
        $sheet->setCellValue("A{$row}", trans('cruds.physicalServer.title'));
        $sheet->setCellValue("B{$row}", $levels['physicalServers_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['physicalServers']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['physicalServers_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['physicalServers']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['physicalServers_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['physicalServers']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // Phone
        $sheet->setCellValue("A{$row}", trans('cruds.phone.title'));
        $sheet->setCellValue("B{$row}", $levels['phones_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['phones']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['phones_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['phones']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['phones_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['phones']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // PhysicalRouter
        $sheet->setCellValue("A{$row}", trans('cruds.physicalRouter.title'));
        $sheet->setCellValue("B{$row}", $levels['physicalRouters_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['physicalRouters']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['physicalRouters_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['physicalRouters']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['physicalRouters_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['physicalRouters']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // PhysicalSwitch
        $sheet->setCellValue("A{$row}", trans('cruds.physicalSwitch.title'));
        $sheet->setCellValue("B{$row}", $levels['physicalSwitchs_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['physicalSwitchs']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['physicalSwitchs_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['physicalSwitchs']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['physicalSwitchs_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['physicalSwitchs']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // PhysicalSecurityDevice
        $sheet->setCellValue("A{$row}", trans('cruds.physicalSecurityDevice.title'));
        $sheet->setCellValue("B{$row}", $levels['physicalSecurityDevices_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['physicalSecurityDevices']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['physicalSecurityDevices_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['physicalSecurityDevices']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['physicalSecurityDevices_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['physicalSecurityDevices']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // WAN
        $sheet->setCellValue("A{$row}", trans('cruds.wan.title'));
        $sheet->setCellValue("B{$row}", $levels['wans_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['wans']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['wans_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['wans']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['wans_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['wans']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // MAN
        $sheet->setCellValue("A{$row}", trans('cruds.man.title'));
        $sheet->setCellValue("B{$row}", $levels['mans_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['mans']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['mans_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['mans']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['mans_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['mans']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // LAN
        $sheet->setCellValue("A{$row}", trans('cruds.lan.title'));
        $sheet->setCellValue("B{$row}", $levels['lans_lvl1']);
        $sheet->setCellValue("C{$row}", $levels['lans']);
        $sheet->setCellValue("D{$row}", "=B{$row}/C{$row}");
        $sheet->setCellValue("E{$row}", $levels['lans_lvl1']);
        $sheet->setCellValue("F{$row}", $levels['lans']);
        $sheet->setCellValue("G{$row}", "=E{$row}/F{$row}");
        $sheet->setCellValue("H{$row}", $levels['lans_lvl1']);
        $sheet->setCellValue("I{$row}", $levels['lans']);
        $sheet->setCellValue("J{$row}", "=H{$row}/I{$row}");
        $row++;

        // =============================================================
        // Save sheet
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        // Return
        return response()->download($path)->deleteFileAfterSend();
    }

    public function changes()
    {
        $path = storage_path('app/changes-'.Carbon::today()->format('Ymd').'.xlsx');

        /*
        select subject_type, description, EXTRACT(YEAR FROM created_at) as yr, EXTRACT(MONTH FROM created_at) as mth, count(*) as count
        from audit_logs
        where created_at >= now() - INTERVAL 12 month
        group by subject_type, description, EXTRACT(YEAR FROM created_at), EXTRACT(MONTH FROM created_at);
        */

        $auditLogs = DB::table('audit_logs')
            ->select(DB::raw('subject_type, description, EXTRACT(YEAR FROM created_at) AS yr, EXTRACT(MONTH FROM created_at) AS mth, count(*) as count'))
            ->where('created_at', '>=', Carbon::now()->startOfMonth()->addMonths(-12))
            ->groupBy('subject_type', 'description', DB::raw('EXTRACT(YEAR FROM created_at)'), DB::raw('EXTRACT(MONTH FROM created_at)'))
            ->get();

        $header = [
            trans('Objet'),
            trans('Action'),
            Carbon::now()->startOfMonth()->addMonths(-12)->format('m/Y'),
            Carbon::now()->startOfMonth()->addMonths(-11)->format('m/Y'),
            Carbon::now()->startOfMonth()->addMonths(-10)->format('m/Y'),
            Carbon::now()->startOfMonth()->addMonths(-9)->format('m/Y'),
            Carbon::now()->startOfMonth()->addMonths(-8)->format('m/Y'),
            Carbon::now()->startOfMonth()->addMonths(-7)->format('m/Y'),
            Carbon::now()->startOfMonth()->addMonths(-6)->format('m/Y'),
            Carbon::now()->startOfMonth()->addMonths(-5)->format('m/Y'),
            Carbon::now()->startOfMonth()->addMonths(-4)->format('m/Y'),
            Carbon::now()->startOfMonth()->addMonths(-3)->format('m/Y'),
            Carbon::now()->startOfMonth()->addMonths(-2)->format('m/Y'),
            Carbon::now()->startOfMonth()->addMonths(-1)->format('m/Y'),
            Carbon::now()->startOfMonth()->format('m/Y'),
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$header], null, 'A1');
        // freeze top rows
        $sheet->freezePane('C2');

        // bold title
        $sheet->getStyle('1')->getFont()->setBold(true);
        // white font
        $sheet->getStyle('1')->getFont()->getColor()->setRGB('FFFFFF');
        // background color
        $sheet->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F77BE');

        // column size and border
        for ($i = 0; $i <= 14; $i++) {
            $col = chr(ord('A') + $i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle("{$col}1")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("{$col}2")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
        }

        // Center
        $sheet->getStyle('B1:O141')->getAlignment()->setHorizontal('center');

        // Total months
        $tMonths = Carbon::now()->year * 12 + Carbon::now()->month;

        // App\xxx -> index, title
        $rows = [
            'GDPR' => ['index' => 2, 'title' => trans('cruds.menu.gdpr.title_short')],
            'App\\Models\\DataProcessing' => ['index' => 3, 'title' => ModelRegistry::title('App\\Models\\DataProcessing')],
            'App\\Models\\SecurityControl' => ['index' => 6, 'title' => ModelRegistry::title('App\\Models\\SecurityControl')],

            'Ecosystem' => ['index' => 9, 'title' => trans('cruds.menu.ecosystem.title_short')],
            'App\\Models\\Entity' => ['index' => 10, 'title' => ModelRegistry::title('App\\Models\\Entity')],
            'App\\Models\\Relation' => ['index' => 13, 'title' => ModelRegistry::title('App\\Models\\Relation')],

            'Metier' => ['index' => 16, 'title' => trans('cruds.menu.metier.title_short')],
            'App\\Models\\MacroProcessus' => ['index' => 17, 'title' => ModelRegistry::title('App\\Models\\MacroProcessus')],
            'App\\Models\\Process' => ['index' => 20, 'title' => ModelRegistry::title('App\\Models\\Process')],
            'App\\Models\\Activity' => ['index' => 23, 'title' => ModelRegistry::title('App\\Models\\Activity')],
            'App\\Models\\Operation' => ['index' => 26, 'title' => ModelRegistry::title('App\\Models\\Operation')],
            'App\\Models\\Task' => ['index' => 29, 'title' => ModelRegistry::title('App\\Models\\Task')],
            'App\\Models\\Actor' => ['index' => 32, 'title' => ModelRegistry::title('App\\Models\\Actor')],
            'App\\Models\\Information' => ['index' => 35, 'title' => ModelRegistry::title('App\\Models\\Information')],

            'Applications' => ['index' => 38, 'title' => trans('cruds.menu.application.title_short')],
            'App\\Models\\ApplicationBlock' => ['index' => 39, 'title' => ModelRegistry::title('App\\Models\\ApplicationBlock')],
            'App\\Models\\Application' => ['index' => 42, 'title' => ModelRegistry::title('App\\Models\\Application')],
            'App\\Models\\ApplicationService' => ['index' => 45, 'title' => ModelRegistry::title('App\\Models\\ApplicationService')],
            'App\\Models\\ApplicationModule' => ['index' => 48, 'title' => ModelRegistry::title('App\\Models\\ApplicationModule')],
            'App\\Models\\Database' => ['index' => 51, 'title' => ModelRegistry::title('App\\Models\\Database')],
            'App\\Models\\ApplicationFlow' => ['index' => 54, 'title' => ModelRegistry::title('App\\Models\\ApplicationFlow')],

            'Administration' => ['index' => 57, 'title' => trans('cruds.menu.administration.title_short')],
            'App\\Models\\ZoneAdmin' => ['index' => 58, 'title' => ModelRegistry::title('App\\Models\\ZoneAdmin')],
            'App\\Models\\Annuaire' => ['index' => 61, 'title' => ModelRegistry::title('App\\Models\\Annuaire')],
            'App\\Models\\ForestAd' => ['index' => 64, 'title' => ModelRegistry::title('App\\Models\\ForestAd')],
            'App\\Models\\Domain' => ['index' => 67, 'title' => ModelRegistry::title('App\\Models\\Domain')],

            'LogicalInfrastructure' => ['index' => 70, 'title' => trans('cruds.menu.logical_infrastructure.title_short')],
            'App\\Models\\Network' => ['index' => 71, 'title' => ModelRegistry::title('App\\Models\\Network')],
            'App\\Models\\Subnetwork' => ['index' => 74, 'title' => ModelRegistry::title('App\\Models\\Subnetwork')],
            'App\\Models\\Gateway' => ['index' => 77, 'title' => ModelRegistry::title('App\\Models\\Gateway')],
            'App\\Models\\ExternalConnectedEntity' => ['index' => 80, 'title' => ModelRegistry::title('App\\Models\\ExternalConnectedEntity')],
            'App\\Models\\NetworkSwitch' => ['index' => 83, 'title' => ModelRegistry::title('App\\Models\\NetworkSwitch')],
            'App\\Models\\Router' => ['index' => 86, 'title' => ModelRegistry::title('App\\Models\\Router')],
            'App\\Models\\SecurityDevice' => ['index' => 89, 'title' => ModelRegistry::title('App\\Models\\SecurityDevice')],
            'App\\Models\\DhcpServer' => ['index' => 92, 'title' => ModelRegistry::title('App\\Models\\DhcpServer')],
            'App\\Models\\LogicalServer' => ['index' => 95, 'title' => ModelRegistry::title('App\\Models\\LogicalServer')],
            'App\\Models\\Certificate' => ['index' => 98, 'title' => ModelRegistry::title('App\\Models\\Certificate')],

            'PhysicalInfrastructure' => ['index' => 101, 'title' => trans('cruds.menu.physical_infrastructure.title_short')],
            'App\\Models\\Site' => ['index' => 102, 'title' => ModelRegistry::title('App\\Models\\Site')],
            'App\\Models\\Building' => ['index' => 105, 'title' => ModelRegistry::title('App\\Models\\Building')],
            'App\\Models\\Bay' => ['index' => 108, 'title' => ModelRegistry::title('App\\Models\\Bay')],
            'App\\Models\\PhysicalServer' => ['index' => 111, 'title' => ModelRegistry::title('App\\Models\\PhysicalServer')],
            'App\\Models\\Workstation' => ['index' => 114, 'title' => ModelRegistry::title('App\\Models\\Workstation')],
            'App\\Models\\StorageDevice' => ['index' => 117, 'title' => ModelRegistry::title('App\\Models\\StorageDevice')],
            'App\\Models\\Peripheral' => ['index' => 120, 'title' => ModelRegistry::title('App\\Models\\Peripheral')],
            'App\\Models\\Phone' => ['index' => 123, 'title' => ModelRegistry::title('App\\Models\\Phone')],
            'App\\Models\\PhysicalRouter' => ['index' => 126, 'title' => ModelRegistry::title('App\\Models\\PhysicalRouter')],
            'App\\Models\\PhysicalSwitch' => ['index' => 129, 'title' => ModelRegistry::title('App\\Models\\PhysicalSwitch')],
            'App\\Models\\WifiTerminal' => ['index' => 132, 'title' => ModelRegistry::title('App\\Models\\WifiTerminal')],
            'App\\Models\\PhysicalSecurityDevice' => ['index' => 135, 'title' => ModelRegistry::title('App\\Models\\PhysicalSecurityDevice')],
            'App\\Models\\Wan' => ['index' => 138, 'title' => ModelRegistry::title('App\\Models\\Wan')],
            'App\\Models\\Man' => ['index' => 141, 'title' => ModelRegistry::title('App\\Models\\Man')],
            'App\\Models\\Lan' => ['index' => 144, 'title' => ModelRegistry::title('App\\Models\\Lan')],
            'App\\Models\\Vlan' => ['index' => 147, 'title' => ModelRegistry::title('App\\Models\\Vlan')],
        ];

        // Fill sheet
        $idx = 2;
        foreach ($rows as $key => $row) {
            // $idx = $row['index'];
            $sheet->setCellValue("A{$idx}", $row['title']);
            if (str_starts_with($key, 'App\\')) {
                $sheet->setCellValue("B{$idx}", 'created');
                $sheet->getStyle("B{$idx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF59A14F');
                $sheet->getStyle("A{$idx}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("B{$idx}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$idx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAEC7E8');
                $idx++;
                $sheet->setCellValue("B{$idx}", 'updated');
                $sheet->getStyle("B{$idx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF28E2B');
                $sheet->getStyle("A{$idx}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("B{$idx}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$idx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAEC7E8');
                $idx++;
                $sheet->setCellValue("B{$idx}", 'deleted');
                $sheet->getStyle("B{$idx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE15759');
                $sheet->getStyle("A{$idx}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("B{$idx}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$idx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFAEC7E8');
                $idx++;
            } else {
                $sheet->getStyle("A{$idx}")->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$idx}")->getFont()->setBold(true);
                $sheet->getStyle("A{$idx}:O{$idx}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F77BE');
                $idx++;
            }
        }

        // Populate the Timesheet
        foreach ($auditLogs as $auditLog) {
            if (isset($rows[$auditLog->subject_type])) {
                // get row
                $row = $rows[$auditLog->subject_type]['index'];

                // add action index
                if ($auditLog->description === 'updated') {
                    $row += 1;
                } elseif ($auditLog->description === 'deleted') {
                    $row += 2;
                }

                // get year / month
                $year = (int) $auditLog->yr;
                $month = (int) $auditLog->mth;

                // compute column
                $delta = 14 - ($tMonths - ($year * 12 + $month));
                $column = chr(ord('A') + $delta);

                // Place value
                $sheet->setCellValue("{$column}{$row}", $auditLog->count);
                $sheet->getStyle("{$column}{$row}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);

                // set the color of the cell
                if ($auditLog->description === 'updated') {
                    $sheet->getStyle("{$column}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF28E2B');
                } elseif ($auditLog->description === 'deleted') {
                    $sheet->getStyle("{$column}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE15759');
                } else {
                    $sheet->getStyle("{$column}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF59A14F');
                }

                // \Log::info("{$column}{$row} ->". $auditLog->subject_type. ', ' . $year . ', ' . $month . ', ' . $auditLog->count);
            }
        }

        // Write speansheet
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        // Return
        return response()->download($path);
    }
}
