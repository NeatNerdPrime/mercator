<?php

use App\Services\Report\TemplateValidatorService;

function templateFixture(string $name): string
{
    return base_path('tests/fixtures/templates/'.$name);
}

test('validate accepts a docx containing the :content: tag exactly once', function () {
    $service = new TemplateValidatorService;

    expect($service->validate(templateFixture('valid-template.docx')))->toBe([]);
});

test('validate detects the :content: tag even when Word split it across multiple runs', function () {
    $service = new TemplateValidatorService;

    expect($service->validate(templateFixture('fragmented-tag.docx')))->toBe([]);
});

test('validate rejects a docx missing the :content: tag', function () {
    $service = new TemplateValidatorService;

    expect($service->validate(templateFixture('missing-tag.docx')))->toBe(['report_template.errors.tag_missing']);
});

test('validate rejects a docx containing the :content: tag more than once', function () {
    $service = new TemplateValidatorService;

    expect($service->validate(templateFixture('multiple-tags.docx')))->toBe(['report_template.errors.tag_multiple']);
});

test('validate rejects a file that is not a valid zip/docx', function () {
    $service = new TemplateValidatorService;

    expect($service->validate(templateFixture('not-a-docx.docx')))->toBe(['report_template.errors.not_a_docx']);
});

test('validate rejects a docx whose word/document.xml is not well-formed', function () {
    $service = new TemplateValidatorService;

    expect($service->validate(templateFixture('malformed-xml.docx')))->toBe(['report_template.errors.not_a_docx']);
});

test('validate rejects a file that does not exist', function () {
    $service = new TemplateValidatorService;

    expect($service->validate(templateFixture('does-not-exist.docx')))->toBe(['report_template.errors.not_a_docx']);
});

test('validate rejects a docx larger than the maximum allowed size', function () {
    $service = new TemplateValidatorService;

    $oversized = tempnam(sys_get_temp_dir(), 'mercator-oversized-').'.docx';
    file_put_contents($oversized, str_repeat('a', TemplateValidatorService::MAX_SIZE_BYTES + 1));

    try {
        expect($service->validate($oversized))->toBe(['report_template.errors.too_large']);
    } finally {
        unlink($oversized);
    }
});
