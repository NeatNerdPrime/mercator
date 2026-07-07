<?php

namespace App\Support;

use App\Models\Parameter;
use DateTimeInterface;

/**
 * Tracks the single, global, admin-uploaded Word template used to render the cartography report
 * (see App\Services\Report\TemplateMergerService), mirroring MercatorSettings's pattern of storing
 * structured metadata as one JSON-encoded Parameter value instead of a dedicated table.
 */
class ReportTemplateSettings
{
    private const PARAM_NAME = 'report_template';

    public static function storagePath(): string
    {
        return storage_path('app/report-templates/template.docx');
    }

    public static function ensureStorageDirectoryExists(): void
    {
        $directory = dirname(self::storagePath());

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    public static function defaultTemplatePath(): string
    {
        return resource_path('reports/default-template.docx');
    }

    /**
     * The template to merge the report body into: the uploaded one if metadata for it is recorded
     * and the file is actually present, otherwise Mercator's own default template.
     */
    public static function currentTemplatePath(): string
    {
        if (self::load() !== null && is_file(self::storagePath())) {
            return self::storagePath();
        }

        return self::defaultTemplatePath();
    }

    /**
     * @return array{original_name: string, uploaded_at: string}|null
     */
    public static function load(): ?array
    {
        $stored = Parameter::getValue(self::PARAM_NAME);

        if ($stored === null) {
            return null;
        }

        return json_decode($stored, true);
    }

    public static function save(string $originalName, DateTimeInterface $uploadedAt): void
    {
        Parameter::setValue(self::PARAM_NAME, json_encode([
            'original_name' => $originalName,
            'uploaded_at' => $uploadedAt->format(DateTimeInterface::ATOM),
        ]));
    }
}
