<?php namespace ProcessWire;

/**
 * Phase 18 migration
 * - Makes operation-note a direct child of admission-record
 * - Adds optional procedure_ref_id for legacy linkage/prefill
 * - Adds field_key to admin_discharge_templates and backfills common mappings
 */

if (!$user->isSuperuser()) {
    throw new WirePermissionException('Superuser required');
}

$messages = [];
$fieldsApi = wire('fields');
$templatesApi = wire('templates');
$pagesApi = wire('pages');
$database = wire('database');
$modulesApi = wire('modules');

$log = function (string $message) use (&$messages): void {
    $messages[] = $message;
};

if (!$fieldsApi->get('procedure_ref_id')) {
    $field = new Field();
    $field->type = $modulesApi->get('FieldtypePage');
    $field->name = 'procedure_ref_id';
    $field->label = 'Procedure Reference';
    $field->inputfieldClass = 'InputfieldSelect';
    $field->findPagesSelector = 'template=procedure';
    $fieldsApi->save($field);
    $log('Created field: procedure_ref_id');
}

$operationNoteTemplate = $templatesApi->get('operation-note');
$admissionTemplate = $templatesApi->get('admission-record');
$procedureTemplate = $templatesApi->get('procedure');

if ($operationNoteTemplate && $fieldsApi->get('procedure_ref_id') && !$operationNoteTemplate->fieldgroup->hasField('procedure_ref_id')) {
    $operationNoteTemplate->fieldgroup->add($fieldsApi->get('procedure_ref_id'));
    $templatesApi->save($operationNoteTemplate);
    $log('Attached procedure_ref_id to operation-note template');
}

if ($operationNoteTemplate && $admissionTemplate) {
    $operationNoteTemplate->parentTemplates = [$admissionTemplate->id];
    $templatesApi->save($operationNoteTemplate);
    $log('Restricted operation-note parent template to admission-record');
}

if ($admissionTemplate && $operationNoteTemplate) {
    $childTemplates = $admissionTemplate->childTemplates ?: [];
    if (!in_array($operationNoteTemplate->id, $childTemplates, true)) {
        $childTemplates[] = $operationNoteTemplate->id;
        $admissionTemplate->childTemplates = array_values(array_unique($childTemplates));
        $templatesApi->save($admissionTemplate);
        $log('Allowed operation-note as a child of admission-record');
    }
}

if ($procedureTemplate && $operationNoteTemplate) {
    $childTemplates = array_values(array_filter((array) $procedureTemplate->childTemplates, function ($templateId) use ($operationNoteTemplate) {
        return (int) $templateId !== (int) $operationNoteTemplate->id;
    }));
    $procedureTemplate->childTemplates = $childTemplates;
    $templatesApi->save($procedureTemplate);
    $log('Removed operation-note from procedure child templates');
}

$legacyNotes = $pagesApi->find("template=operation-note, parent.template=procedure, include=all");
foreach ($legacyNotes as $legacyNote) {
    $procedurePage = $legacyNote->parent;
    $casePage = $procedurePage instanceof Page ? $procedurePage->parent : null;
    if (!$casePage || !$casePage->id || $casePage->template->name !== 'admission-record') {
        continue;
    }

    $legacyNote->of(false);
    if ($fieldsApi->get('procedure_ref_id')) {
        $legacyNote->set('procedure_ref_id', $procedurePage);
    }
    $legacyNote->parent = $casePage;
    $pagesApi->save($legacyNote);
    $log('Moved operation-note #' . $legacyNote->id . ' to case #' . $casePage->id . ' with procedure_ref_id=' . $procedurePage->id);
}

try {
    $database->exec("ALTER TABLE `admin_discharge_templates` ADD COLUMN `field_key` varchar(100) DEFAULT NULL AFTER `type`");
    $log('Added admin_discharge_templates.field_key');
} catch (\Throwable $e) {
    $log('admin_discharge_templates.field_key already exists');
}

$database->exec("UPDATE `admin_discharge_templates`
    SET `field_key` = CASE
        WHEN `field_key` IS NOT NULL AND `field_key` <> '' THEN `field_key`
        WHEN REPLACE(LOWER(`type`), '_', '-') = 'history' THEN 'history_present_illness'
        WHEN REPLACE(LOWER(`type`), '_', '-') = 'examination' THEN 'examination_findings'
        WHEN REPLACE(LOWER(`type`), '_', '-') = 'advice' THEN 'follow_up_instructions'
        WHEN REPLACE(LOWER(`type`), '_', '-') = 'operation-note' THEN 'surgical_approach'
        ELSE NULL
    END");
$log('Backfilled common field_key values for existing templates');

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'messages' => $messages,
], JSON_PRETTY_PRINT);
