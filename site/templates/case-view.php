<?php namespace ProcessWire;

$case = $page;
if ($page->template->name === 'case-view') {
    $requestedCaseId = (int) $input->get->id;
    $requestedCase = $requestedCaseId ? $pages->get("id={$requestedCaseId}, template=admission-record") : null;
    if (!$requestedCase || !$requestedCase->id) {
        $session->redirect('/patients/');
    }
    $case = $requestedCase;
    $page->of(false);
    $page->title = $case->title ?: ('Case ' . $case->id);
}
$case->of(false);

$patient = ($case && $case->parent instanceof Page) ? $case->parent : null;
$caseUrl = $page->template->name === 'case-view'
    ? ('/case-view/?id=' . (int) $case->id)
    : ($case && $case->url ? $case->url : $page->url);
$fieldsApi = wire('fields');

$setOptionField = function (Page $pageToUpdate, string $fieldName, $value) use ($fieldsApi, $pages): void {
    if (!$fieldsApi->get($fieldName)) {
        return;
    }
    if ($value === '' || $value === null) {
        $pageToUpdate->set($fieldName, null);
        return;
    }
    $field = $fieldsApi->get($fieldName);
    $fieldType = $field ? $field->type : null;
    $fieldClassName = $fieldType ? get_class($fieldType) : '';
    if (is_array($value) && ($fieldClassName === 'FieldtypePage' || strpos($fieldClassName, 'Page') !== false)) {
        $pageIds = [];
        foreach ($value as $item) {
            if (is_object($item) && $item instanceof Page) {
                $pageIds[] = $item;
            } elseif (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $pageIds[] = (int) $item;
            } elseif (is_string($item) && $item !== '') {
                $found = $pages->get("title=$item, include=all");
                if ($found && $found->id) {
                    $pageIds[] = $found;
                }
            }
        }
        try {
            $pageToUpdate->set($fieldName, $pageIds);
        } catch (\Throwable $e) {
            wire('log')->save('case-view-errors', "setOptionField(Page) failed for {$fieldName}: " . $e->getMessage());
        }
        return;
    }
    try {
        $pageToUpdate->set($fieldName, $value);
    } catch (\Throwable $e) {
        wire('log')->save('case-view-errors', "setOptionField failed for {$fieldName}: " . $e->getMessage());
    }
};

$saveFieldIfExists = function (Page $pageToUpdate, string $fieldName, $value) use ($fieldsApi): void {
    if ($fieldsApi->get($fieldName)) {
        try {
            $pageToUpdate->set($fieldName, $value);
        } catch (\Throwable $e) {
            wire('log')->save('case-view-errors', "saveFieldIfExists failed for {$fieldName}: " . $e->getMessage());
        }
    }
};

$getOptionId = function ($rawValue): int {
    if (is_object($rawValue) && method_exists($rawValue, 'first')) {
        $first = $rawValue->first();
        return $first ? (int) $first->id : 0;
    }
    if (is_object($rawValue) && isset($rawValue->id)) {
        return (int) $rawValue->id;
    }
    return (int) $rawValue;
};

$getOptionTitle = function ($rawValue): string {
    if (is_object($rawValue) && method_exists($rawValue, 'first')) {
        $first = $rawValue->first();
        return $first && isset($first->title) ? (string) $first->title : '';
    }
    if (is_object($rawValue) && isset($rawValue->title)) {
        return (string) $rawValue->title;
    }
    return trim((string) $rawValue);
};

$normalizeTemplateType = function ($value): string {
    $normalized = strtolower(trim((string) $value));
    $normalized = str_replace('_', '-', $normalized);
    if ($normalized === 'operation') {
        $normalized = 'operation-note';
    }
    return $normalized;
};

$buildCaseUrl = function (array $params = [], string $fragment = '') use ($caseUrl): string {
    $query = http_build_query(array_filter($params, function ($value) {
        return $value !== null && $value !== '';
    }));
    $sep = (strpos($caseUrl, '?') !== false) ? '&' : '?';
    return $caseUrl . ($query ? $sep . $query : '') . ($fragment ? '#' . ltrim($fragment, '#') : '');
};

$getOptionTitles = function ($rawValue): array {
    $titles = [];
    if (is_iterable($rawValue)) {
        foreach ($rawValue as $item) {
            if (is_object($item) && isset($item->title)) {
                $title = trim((string) $item->title);
            } else {
                $title = trim((string) $item);
            }
            if ($title !== '') {
                $titles[] = $title;
            }
        }
    } elseif ($rawValue !== null && $rawValue !== '') {
        $titles[] = trim((string) $rawValue);
    }
    return array_values(array_unique(array_filter($titles)));
};

$knownComorbidityFlags = ['DM', 'HTN', 'CKD', 'Asthma', 'IHD'];

$normalizeComorbidityFlag = function (string $condition): string {
    $normalized = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $condition)));
    $map = [
        'dm' => 'DM',
        'diabetes' => 'DM',
        'diabetes mellitus' => 'DM',
        'diabetic mellitus' => 'DM',
        't2dm' => 'DM',
        'type 2 diabetes' => 'DM',
        'type ii diabetes' => 'DM',
        'type 2 dm' => 'DM',
        'htn' => 'HTN',
        'hypertension' => 'HTN',
        'high blood pressure' => 'HTN',
        'ckd' => 'CKD',
        'chronic kidney disease' => 'CKD',
        'chronic renal disease' => 'CKD',
        'chronic renal failure' => 'CKD',
        'asthma' => 'Asthma',
        'bronchial asthma' => 'Asthma',
        'ihd' => 'IHD',
        'ischemic heart disease' => 'IHD',
        'ischaemic heart disease' => 'IHD',
        'coronary artery disease' => 'IHD',
        'cad' => 'IHD',
    ];
    return $map[$normalized] ?? '';
};

$splitComorbidityTreatment = function (string $treatment): array {
    $treatment = trim($treatment);
    if ($treatment === '') {
        return ['', ''];
    }

    if (preg_match('/^(.+?)(\s+(?:\d+(?:\.\d+)?\s*(?:mg|mcg|g|ml|iu|units?)\b.*))$/i', $treatment, $matches)) {
        return [trim($matches[1]), trim($matches[2])];
    }

    if (preg_match('/^(.+?)(\s+(?:od|bd|tid|qid|hs|stat|prn)\b.*)$/i', $treatment, $matches)) {
        return [trim($matches[1]), trim($matches[2])];
    }

    return [$treatment, ''];
};

$deleteComorbidityDrugPages = function (Page $casePage) use ($pages): void {
    try {
        $drugPages = $pages->find("template=comorbidity-drug, parent={$casePage->id}, include=all");
        foreach ($drugPages as $drugPage) {
            $drugPage->delete();
        }
    } catch (\Throwable $e) {
        wire('log')->save('case-view-errors', 'deleteComorbidityDrugPages failed for case ' . $casePage->id . ': ' . $e->getMessage());
    }
};

$deleteDrugHistoryPages = function (Page $casePage) use ($pages): void {
    try {
        $drugPages = $pages->find("template=drug-history-entry, parent={$casePage->id}, include=all");
        foreach ($drugPages as $drugPage) {
            $drugPage->delete();
        }
    } catch (\Throwable $e) {
        wire('log')->save('case-view-errors', 'deleteDrugHistoryPages failed for case ' . $casePage->id . ': ' . $e->getMessage());
    }
};

$procedureHasFilledSurgeryPlan = function (Page $procedurePage) use ($fieldsApi, $getOptionTitle): bool {
    $procName = trim((string) ($procedurePage->proc_name ?: $procedurePage->title));
    $procDate = (int) $procedurePage->getUnformatted('proc_date');
    $anesthesia = trim((string) ($getOptionTitle($procedurePage->anesthesia_type) ?: $procedurePage->anesthesia_type));

    return $procName !== ''
        && $procDate > 0
        && $anesthesia !== '';
};

$hasText = function ($value): bool {
    return trim(strip_tags((string) $value)) !== '';
};

$extractEmbeddedMeta = function ($value): array {
    $rawValue = (string) $value;
    $meta = [];
    if (preg_match('/<!--DCMETA:(.+?)-->$/s', $rawValue, $matches)) {
        $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
        $rawValue = preg_replace('/\s*<!--DCMETA:.+?-->$/s', '', $rawValue);
    }
    return [
        'text' => rtrim((string) $rawValue),
        'meta' => $meta,
    ];
};

$mergeEmbeddedMeta = function ($text, array $meta = []): string {
    $cleanText = rtrim((string) $text);
    $cleanMeta = array_filter($meta, function ($value) {
        if (is_array($value)) {
            return count(array_filter($value, function ($innerValue) {
                return $innerValue !== null && $innerValue !== '' && $innerValue !== [];
            })) > 0;
        }
        return $value !== null && $value !== '';
    });

    if (!$cleanMeta) {
        return $cleanText;
    }

    $payload = json_encode($cleanMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return trim($cleanText . "\n\n<!--DCMETA:" . $payload . '-->');
};

$formatOperationNoteName = function (Page $operationNotePage): string {
    $rawTitle = trim((string) ($operationNotePage->title ?: ''));
    if ($rawTitle !== '') {
        $rawTitle = preg_replace('/\s+note$/i', '', $rawTitle);
        if (trim((string) $rawTitle) !== '') {
            return trim((string) $rawTitle);
        }
    }
    return 'Operation Note';
};

$workflowDefinitions = include $config->paths->templates . 'includes/workflow-definitions.php';
$workflowHasFieldsConfig = false;
try {
    $workflowHasFieldsConfig = (bool) $database->query("SHOW COLUMNS FROM admin_workflow_config LIKE 'fields_config'")->fetch(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $workflowHasFieldsConfig = false;
}

try {
    $existingWorkflowRows = $database->query("SELECT module_name FROM admin_workflow_config")->fetchAll(\PDO::FETCH_COLUMN);
    $insertWorkflowRow = $database->prepare("INSERT INTO admin_workflow_config (module_name, label, is_enabled, is_mandatory, sort_order" . ($workflowHasFieldsConfig ? ", fields_config" : "") . ") VALUES (?,?,?,?,?" . ($workflowHasFieldsConfig ? ",?" : "") . ")");
    foreach ($workflowDefinitions as $moduleName => $moduleDefinition) {
        if (in_array($moduleName, $existingWorkflowRows, true)) {
            continue;
        }
        $params = [
            $moduleName,
            $moduleDefinition['label'],
            (int) ($moduleDefinition['default_enabled'] ?? 1),
            (int) ($moduleDefinition['default_mandatory'] ?? 0),
            (int) ($moduleDefinition['default_sort_order'] ?? 0),
        ];
        if ($workflowHasFieldsConfig) {
            $params[] = json_encode(['fields' => [], 'actions' => []]);
        }
        $insertWorkflowRow->execute($params);
    }
    $preopWorkflowRow = $database->query("SELECT id, sort_order FROM admin_workflow_config WHERE module_name='preop-print' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    $operationWorkflowRow = $database->query("SELECT id, sort_order FROM admin_workflow_config WHERE module_name='operation-note' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    if ($preopWorkflowRow && $operationWorkflowRow && (int) $preopWorkflowRow['sort_order'] > (int) $operationWorkflowRow['sort_order']) {
        $updateWorkflowSort = $database->prepare("UPDATE admin_workflow_config SET sort_order=? WHERE id=?");
        $updateWorkflowSort->execute([(int) $operationWorkflowRow['sort_order'], (int) $preopWorkflowRow['id']]);
        $updateWorkflowSort->execute([(int) $preopWorkflowRow['sort_order'], (int) $operationWorkflowRow['id']]);
    }
} catch (\Throwable $e) {
    // If workflow bootstrap fails, the page will fall back to local defaults below.
}

$normalizeWorkflowConfig = function ($rawConfig): array {
    $decoded = is_array($rawConfig) ? $rawConfig : json_decode((string) $rawConfig, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }
    return [
        'fields' => isset($decoded['fields']) && is_array($decoded['fields']) ? $decoded['fields'] : [],
        'actions' => isset($decoded['actions']) && is_array($decoded['actions']) ? $decoded['actions'] : [],
    ];
};

$workflow = [];
try {
    $workflow = $database->query("SELECT * FROM admin_workflow_config WHERE is_enabled=1 ORDER BY sort_order ASC")->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $workflow = [];
}
if (!$workflow) {
    foreach ($workflowDefinitions as $moduleName => $moduleDefinition) {
        if (empty($moduleDefinition['default_enabled'])) {
            continue;
        }
        $workflow[] = [
            'id' => 0,
            'module_name' => $moduleName,
            'label' => $moduleDefinition['label'],
            'is_enabled' => (int) ($moduleDefinition['default_enabled'] ?? 1),
            'is_mandatory' => (int) ($moduleDefinition['default_mandatory'] ?? 0),
            'sort_order' => (int) ($moduleDefinition['default_sort_order'] ?? 0),
            'fields_config' => json_encode(['fields' => [], 'actions' => []]),
        ];
    }
}

$workflowByModuleName = [];
foreach ($workflow as $workflowIndex => $workflowRow) {
    $workflowRow['fields_config_normalized'] = $normalizeWorkflowConfig($workflowRow['fields_config'] ?? null);
    $workflowByModuleName[$workflowRow['module_name']] = $workflowRow;
    $workflow[$workflowIndex] = $workflowRow;
}

$getWorkflowAnchor = function (string $moduleName) use ($workflowDefinitions): string {
    return $workflowDefinitions[$moduleName]['anchor'] ?? ('module-' . $moduleName);
};

$isMandatory = function (string $moduleName) use ($workflowByModuleName): bool {
    return !empty($workflowByModuleName[$moduleName]['is_mandatory']);
};

$getFieldConfig = function (string $moduleName, string $fieldKey) use ($workflowByModuleName): array {
    $defaults = ['visible' => true, 'editable' => true, 'mandatory' => false];
    $moduleConfig = $workflowByModuleName[$moduleName]['fields_config_normalized']['fields'][$fieldKey] ?? null;
    if (!is_array($moduleConfig)) {
        return $defaults;
    }
    return [
        'visible' => array_key_exists('visible', $moduleConfig) ? (bool) $moduleConfig['visible'] : $defaults['visible'],
        'editable' => array_key_exists('editable', $moduleConfig) ? (bool) $moduleConfig['editable'] : $defaults['editable'],
        'mandatory' => array_key_exists('mandatory', $moduleConfig) ? (bool) $moduleConfig['mandatory'] : $defaults['mandatory'],
    ];
};

$getActionConfig = function (string $moduleName, string $actionKey) use ($workflowByModuleName): array {
    $defaults = ['visible' => true, 'enabled' => true];
    $moduleConfig = $workflowByModuleName[$moduleName]['fields_config_normalized']['actions'][$actionKey] ?? null;
    if (!is_array($moduleConfig)) {
        return $defaults;
    }
    return [
        'visible' => array_key_exists('visible', $moduleConfig) ? (bool) $moduleConfig['visible'] : $defaults['visible'],
        'enabled' => array_key_exists('enabled', $moduleConfig) ? (bool) $moduleConfig['enabled'] : $defaults['enabled'],
    ];
};

$getPostWorkflowFieldIssues = function (string $moduleName) use ($workflowDefinitions, $getFieldConfig, $input): array {
    $issues = [];
    $moduleDefinition = $workflowDefinitions[$moduleName] ?? ['fields' => []];
    foreach (($moduleDefinition['fields'] ?? []) as $fieldKey => $fieldLabel) {
        $fieldConfig = $getFieldConfig($moduleName, $fieldKey);
        if (empty($fieldConfig['mandatory']) || empty($fieldConfig['visible'])) {
            continue;
        }
        $rawValue = $input->post->$fieldKey;
        $hasValue = false;
        if (is_array($rawValue)) {
            foreach ($rawValue as $item) {
                if (trim((string) $item) !== '') {
                    $hasValue = true;
                    break;
                }
            }
        } else {
            $hasValue = trim((string) $rawValue) !== '';
        }
        if (!$hasValue) {
            $issues[] = $fieldLabel . ' is required';
        }
    }
    return $issues;
};

$workflowRenderContext = [];
$renderWorkflowModule = function (array $workflowStep, int $displayIndex) use ($workflowDefinitions, $config, $caseUrl, &$workflowRenderContext): string {
    $moduleName = $workflowStep['module_name'];
    $moduleDefinition = $workflowDefinitions[$moduleName] ?? null;
    if (!$moduleDefinition || empty($moduleDefinition['file'])) {
        return '';
    }
    $moduleFile = $config->paths->templates . $moduleDefinition['file'];
    if (!file_exists($moduleFile)) {
        return '';
    }

    ob_start();
    if ($workflowRenderContext) {
        extract($workflowRenderContext, EXTR_SKIP);
    }
    include $moduleFile;
    $markup = ob_get_clean();

    $anchor = $moduleDefinition['anchor'] ?? ('module-' . $moduleName);
    $originalAnchor = null;
    if (preg_match('/<section class="card case-module[^"]*" id="([^"]+)"/', $markup, $anchorMatch)) {
        $originalAnchor = $anchorMatch[1];
    }
    $markup = preg_replace(
        '/<section class="card case-module([^"]*)" id="[^"]+"(?: data-module-step="[^"]+")?/',
        '<section class="card case-module$1" id="' . $anchor . '" data-module-step="' . $displayIndex . '" data-module-name="' . $moduleName . '"',
        $markup,
        1
    );
    $mandatoryHtml = !empty($workflowStep['is_mandatory'])
        ? ' <span class="case-module__required-star" aria-label="Required step" title="This module is mandatory">★</span>'
        : '';
    $markup = preg_replace(
        '/<span class="badge([^"]*)">[^<]*<\/span>/',
        '<span class="badge$1">Step ' . $displayIndex . $mandatoryHtml . '</span>',
        $markup,
        1
    );
    $markup = preg_replace_callback(
        '/<(input|textarea|select)\b(?![^>]*\bdata-field=)([^>]*\bname=(["\'])([^"\']+)\3[^>]*)>/i',
        function ($matches) {
            $fieldKey = preg_replace('/\[\]$/', '', (string) $matches[4]);
            return '<' . $matches[1] . $matches[2] . ' data-field="' . $fieldKey . '">';
        },
        $markup
    );
    $markup = preg_replace(
        '/<button\b(?![^>]*\bdata-action=)([^>]*type="submit"[^>]*)>/i',
        '<button$1 data-action="save_' . str_replace('-', '_', $moduleName) . '">',
        $markup
    );
    $markup = preg_replace(
        '/<form\b(?![^>]*\baction=)([^>]*)>/i',
        '<form action="' . $caseUrl . '"$1>',
        $markup
    );
    if ($originalAnchor && $originalAnchor !== $anchor) {
        $markup = str_replace('#' . $originalAnchor, '#' . $anchor, $markup);
    }

    $collapseIcon = '<span class="case-module__collapse-icon"><i data-lucide="chevron-down" aria-hidden="true" style="width:18px;height:18px"></i></span>';
    if (preg_match('/<div class="card__body[\s"]/', $markup, $bodyMatch, PREG_OFFSET_CAPTURE)) {
        $headerEndPos = $bodyMatch[0][1];
        $headerFragment = substr($markup, 0, $headerEndPos);
        if (strpos($headerFragment, 'card__action') !== false) {
            $lastDivClosePos = strrpos($headerFragment, '</div>');
            if ($lastDivClosePos !== false) {
                $beforeLast = substr($headerFragment, 0, $lastDivClosePos);
                $secondLastDivClosePos = strrpos($beforeLast, '</div>');
                if ($secondLastDivClosePos !== false) {
                    $markup = substr_replace($markup, $collapseIcon, $secondLastDivClosePos, 0);
                } else {
                    $markup = substr_replace($markup, $collapseIcon, $lastDivClosePos, 0);
                }
            }
        } else {
            $lastDivClosePos = strrpos($headerFragment, '</div>');
            if ($lastDivClosePos !== false) {
                $markup = substr_replace($markup, $collapseIcon, $lastDivClosePos, 0);
            }
        }
    }

    return $markup;
};

$writeAuditLog = function (Page $entityPage, int $actionId, array $details = []) use ($pages, $user): void {
    $auditRoot = $pages->get('/audit-log/');
    $auditTemplate = wire('templates')->get('audit-log');
    if (!$auditRoot || !$auditRoot->id || !$auditTemplate || !$auditTemplate->id) {
        return;
    }

    $log = new Page();
    $log->template = $auditTemplate;
    $log->parent = $auditRoot;
    $log->name = 'audit-' . $entityPage->id . '-' . time() . '-' . rand(100, 999);
    $log->title = strtoupper($entityPage->template->name) . ' #' . $entityPage->id . ' - ' . date('Y-m-d H:i:s');
    $log->of(false);
    $log->audit_entity_id = $entityPage->id;
    $log->audit_entity_template = $entityPage->template->name;
    $log->audit_entity_title = $entityPage->title ?: ('Page #' . $entityPage->id);
    try {
        $log->audit_action = $actionId;
    } catch (\Throwable $e) {
        $log->audit_action = 2;
    }
    $log->audit_user_id = $user->id;
    $log->audit_user_name = $user->name;
    $log->audit_timestamp = time();
    $log->audit_ip_address = (php_sapi_name() === 'cli') ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '');
    $log->audit_field_changes = $details ? json_encode($details, JSON_PRETTY_PRINT) : '';
    $pages->save($log, ['noHooks' => false, 'quiet' => true]);
};

$buildMedicationAutoFillRows = function (Page $casePage) use ($pages): array {
    $rows = [];

    $comorbidityDrugPages = wire('templates')->get('comorbidity-drug')
        ? $pages->find("template=comorbidity-drug, parent={$casePage->id}, sort=sort")
        : new PageArray();
    foreach ($comorbidityDrugPages as $drugPage) {
        $drugName = trim((string) $drugPage->get('drug_name'));
        if ($drugName === '') {
            continue;
        }
        $rows[] = [
            'drug' => $drugName,
            'dose' => trim((string) $drugPage->get('drug_dose')),
            'frequency' => '',
            'duration' => '',
            'notes' => 'Comorbidity: ' . trim((string) $drugPage->get('comorb_condition_flag')),
            'source' => 'comorbidity',
            'continue_previous' => 1,
            'is_duplicate' => false,
        ];
    }

    $drugHistoryPages = wire('templates')->get('drug-history-entry')
        ? $pages->find("template=drug-history-entry, parent={$casePage->id}, sort=sort")
        : new PageArray();
    foreach ($drugHistoryPages as $drugPage) {
        $drugName = trim((string) $drugPage->get('drug_name'));
        if ($drugName === '') {
            continue;
        }
        $doseParts = array_values(array_filter([
            trim((string) $drugPage->get('drug_dose')),
        ]));
        $rows[] = [
            'drug' => $drugName,
            'dose' => implode(' ', $doseParts),
            'frequency' => trim((string) $drugPage->get('drug_frequency')),
            'duration' => '',
            'notes' => 'Ongoing medication',
            'source' => 'drug_history',
            'continue_previous' => 1,
            'is_duplicate' => false,
        ];
    }

    $drugNameCounts = [];
    foreach ($rows as $row) {
        $key = strtolower(trim((string) $row['drug']));
        if ($key === '') {
            continue;
        }
        $drugNameCounts[$key] = ($drugNameCounts[$key] ?? 0) + 1;
    }
    foreach ($rows as $index => $row) {
        $key = strtolower(trim((string) $row['drug']));
        $rows[$index]['is_duplicate'] = $key !== '' && ($drugNameCounts[$key] ?? 0) > 1;
    }

    return $rows;
};

$loadKeyValueSettings = function (string $tableName) use ($database): array {
    $settings = [];
    try {
        $rows = $database->query("SELECT setting_key, setting_value FROM {$tableName}")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (\Throwable $e) {
        // Ignore missing optional admin tables on case view render.
    }
    return $settings;
};

if ((int) $input->get->medication_refill === 1 && $case && $case->id) {
    header('Content-Type: application/json');
    if (!$user->isLoggedin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'rows' => []]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'rows' => $buildMedicationAutoFillRows($case),
    ]);
    exit;
}

$getDischargeBlockers = function (Page $casePage, ?Page $patientPage) use (
    $pages,
    $fieldsApi,
    $getOptionTitle,
    $getOptionTitles,
    $hasText,
    $extractEmbeddedMeta,
    $workflow,
    $isMandatory
): array {
    $issues = [];
    $patientName = $patientPage ? trim((string) $patientPage->title) : '';
    if ($patientName === '') {
        $issues[] = 'Patient name is required';
    }

    if (!(int) $casePage->getUnformatted('discharged_on')) {
        $issues[] = 'Discharge date must be set';
    }

    $diagnosisLabel = $casePage->primary_diagnosis_ref && $casePage->primary_diagnosis_ref->id
        ? trim((string) $casePage->primary_diagnosis_ref->title)
        : trim((string) $casePage->diagnosis);
    if ($diagnosisLabel === '') {
        $issues[] = 'Primary diagnosis is required';
    }

    $comorbidityNone = $fieldsApi->get('comorbidity_none') ? (bool) $casePage->getUnformatted('comorbidity_none') : false;
    $comorbidityFlagsForCheck = $fieldsApi->get('comorbidity_flags') ? $getOptionTitles($casePage->getUnformatted('comorbidity_flags')) : [];
    $comorbidityDrugCount = wire('templates')->get('comorbidity-drug')
        ? (int) $pages->count("template=comorbidity-drug, parent={$casePage->id}")
        : 0;
    $comorbidityAddressed = $comorbidityNone || count($comorbidityFlagsForCheck) > 0 || $comorbidityDrugCount > 0;
    if (!$comorbidityAddressed) {
        $issues[] = "Comorbidity status must be explicitly recorded (select 'None' or add conditions)";
    }

    $investigationCount = (int) $pages->count("template=investigation, parent={$casePage->id}");
    $procedures = $pages->find("template=procedure, parent={$casePage->id}, sort=proc_date");
    $caseOperationNotes = $pages->find("template=operation-note, parent={$casePage->id}, sort=sort, sort=created");
    $operationNotesByProcedureId = [];
    foreach ($caseOperationNotes as $caseOperationNote) {
        $procedureRef = $fieldsApi->get('procedure_ref_id') ? $caseOperationNote->getUnformatted('procedure_ref_id') : null;
        $procedureRefId = $procedureRef instanceof Page ? (int) $procedureRef->id : (int) $procedureRef;
        if ($procedureRefId > 0 && !isset($operationNotesByProcedureId[$procedureRefId])) {
            $operationNotesByProcedureId[$procedureRefId] = $caseOperationNote;
        }
    }
    if ($procedures && count($procedures)) {
        foreach ($procedures as $procedurePage) {
            $procedureName = trim((string) ($procedurePage->proc_name ?: $procedurePage->title ?: 'Procedure'));
            $operationNote = $operationNotesByProcedureId[$procedurePage->id] ?? null;
            if (!$operationNote || !$operationNote->id) {
                $operationNote = $pages->get("template=operation-note, parent={$procedurePage}");
            }
            $hasProcDate = (int) $procedurePage->getUnformatted('proc_date') > 0;
            $anesthesia = trim((string) ($getOptionTitle($procedurePage->anesthesia_type) ?: $procedurePage->anesthesia_type));
            if (!$hasProcDate || $anesthesia === '') {
                $issues[] = 'Procedure plan is incomplete for: ' . $procedureName;
            }
        }
    }

    $generalConditionLabel = $getOptionTitle($casePage->general_condition) ?: trim((string) $casePage->condition_at_discharge);
    if (!$hasText($generalConditionLabel)) {
        $issues[] = 'Condition at discharge must be recorded';
    }

    $medicationRows = [];
    $rawMedications = trim((string) $casePage->medications_on_discharge);
    if ($rawMedications !== '') {
        $decodedMeds = json_decode($rawMedications, true);
        if (is_array($decodedMeds)) {
            foreach ($decodedMeds as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $drug = trim((string) ($row['drug'] ?? ''));
                if ($drug !== '') {
                    $medicationRows[] = $drug;
                }
            }
        } else {
            foreach (preg_split('/\r\n|\r|\n/', $rawMedications) as $line) {
                if (trim((string) $line) !== '') {
                    $medicationRows[] = trim((string) $line);
                }
            }
        }
    }
    if (!count($medicationRows ?? [])) {
        $issues[] = 'Medications on discharge must be provided';
    }

    $followUpPayload = $extractEmbeddedMeta((string) $casePage->follow_up_instructions);
    $followUpMeta = $followUpPayload['meta'];
    if (!$hasText($followUpPayload['text'])) {
        $issues[] = 'Advice on discharge must be filled';
    }
    if (trim((string) ($followUpMeta['subsequent_plan'] ?? '')) === '') {
        $issues[] = 'Subsequent treatment plan must be filled';
    }

    $mandatoryWorkflowChecks = [
        'admission' => [
            'complete' => $patientName !== '' && $hasText($casePage->room_bed),
            'message' => 'Admission module is incomplete',
        ],
        'diagnosis' => [
            'complete' => ($casePage->primary_diagnosis_ref && $casePage->primary_diagnosis_ref->id) || $hasText($casePage->diagnosis),
            'message' => 'Diagnosis module is incomplete',
        ],
        'history' => [
            'complete' => $hasText($casePage->chief_complaint) && $comorbidityAddressed,
            'message' => 'History module is incomplete',
        ],
        'examination' => [
            'complete' => $hasText($casePage->inspection),
            'message' => 'Examination module is incomplete',
        ],
        'investigations' => [
            'complete' => $investigationCount > 0,
            'message' => 'Investigations module is incomplete',
        ],
        'ot-plan' => [
            'complete' => ($procedures ? count($procedures) : 0) === 0 || !count(array_filter($issues, function ($item) {
                return strpos($item, 'Procedure plan is incomplete for: ') === 0;
            })),
            'message' => 'OT Plan module is incomplete',
        ],
        'preop-print' => [
            'complete' => true,
            'message' => 'Pre-op Print module is incomplete',
        ],
        'operation-note' => [
            'complete' => ($procedures ? count($procedures) : 0) === 0 || !count(array_filter($issues, function ($item) {
                return strpos($item, 'Operation Note is incomplete for: ') === 0;
            })),
            'message' => 'Operation Note module is incomplete',
        ],
        'hospital-course' => [
            'complete' => (int) $pages->count("template=hospital-course-entry, parent={$casePage->id}") > 0,
            'message' => 'Hospital Course module is incomplete',
        ],
        'condition' => [
            'complete' => $hasText($generalConditionLabel),
            'message' => 'Condition module is incomplete',
        ],
        'medications' => [
            'complete' => count($medicationRows ?? []) > 0,
            'message' => 'Medications module is incomplete',
        ],
        'advice' => [
            'complete' => $hasText($followUpPayload['text']) && trim((string) ($followUpMeta['subsequent_plan'] ?? '')) !== '',
            'message' => 'Advice & Follow-up module is incomplete',
        ],
        'discharge-engine' => [
            'complete' => !count($issues),
            'message' => 'Final Output module is incomplete',
        ],
    ];

    foreach ($workflow as $workflowStep) {
        $moduleName = (string) ($workflowStep['module_name'] ?? '');
        if (!$isMandatory($moduleName)) {
            continue;
        }
        $moduleCheck = $mandatoryWorkflowChecks[$moduleName] ?? null;
        if ($moduleCheck && empty($moduleCheck['complete'])) {
            $issues[] = $moduleCheck['message'];
        }
    }

    return array_values(array_unique($issues));
};

// ── Rule Engine: evaluate active rules against this case ──────────────────────
$triggeredRules = [];
if ($case && $case->id) {
    try {
        $allRules = wire('database')->query("SELECT * FROM admin_rules WHERE is_active=1")
            ->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($allRules as $rule) {
            $actual = null;
            switch ($rule['condition_field']) {
                case 'age':
                    $actual = (int) ($case->patient_age ?? 0);
                    break;
                case 'gender':
                    $actual = strtolower($patient ? ($getOptionTitle($patient->gender) ?: '') : '');
                    break;
                case 'diagnosis':
                    $diagTitle = ($case->primary_diagnosis_ref && $case->primary_diagnosis_ref->id)
                        ? (string) $case->primary_diagnosis_ref->title
                        : strip_tags((string) ($case->diagnosis ?? ''));
                    $actual = strtolower(trim($diagTitle));
                    break;
                case 'days_admitted':
                    $admittedTs = (int) $case->getUnformatted('admitted_on');
                    $actual = $admittedTs ? max(0, (int) floor((time() - $admittedTs) / 86400)) : 0;
                    break;
                case 'case_status':
                    $actual = (string) ((int) $case->getUnformatted('case_status'));
                    break;
                case 'consultant':
                    $actual = strtolower(
                        ($case->consultant_ref && $case->consultant_ref->id)
                            ? (string) $case->consultant_ref->title
                            : ''
                    );
                    break;
                case 'operation_done':
                    $hasSurgery = (bool) $pages->count("template=procedure, parent={$case->id}");
                    $actual = $hasSurgery ? 'yes' : 'no';
                    break;
                default:
                    continue 2;
            }
            $cv = $rule['condition_value'];
            $hit = false;
            switch ($rule['operator']) {
                case 'equals':       $hit = strcasecmp((string) $actual, (string) $cv) === 0; break;
                case 'not_equals':   $hit = strcasecmp((string) $actual, (string) $cv) !== 0; break;
                case 'contains':     $hit = stripos((string) $actual, (string) $cv) !== false; break;
                case 'greater_than': $hit = is_numeric($actual) && (float) $actual > (float) $cv; break;
                case 'less_than':    $hit = is_numeric($actual) && (float) $actual < (float) $cv; break;
            }
            if ($hit) {
                $triggeredRules[] = $rule;
            }
        }
    } catch (\Throwable $e) {
        // rules table may not exist yet — silently skip
    }
}

// ── Role-based module permissions ─────────────────────────────────────────────
// $userCaseModulePerms['module'] = ['can_view'=>1,'can_edit'=>0,'can_delete'=>0,'can_approve'=>0]
// Superusers bypass all checks. If no record exists for a module, full access is assumed.
$userCaseModulePerms = [];
if (!$user->isSuperuser()) {
    $firstRoleName = null;
    foreach ($user->roles as $_r) {
        if ($_r->name !== 'guest' && $_r->name !== 'superuser') {
            $firstRoleName = $_r->name;
            break;
        }
    }
    if ($firstRoleName) {
        try {
            $_pq = wire('database')->prepare(
                "SELECT module, can_view, can_edit, can_delete, can_approve
                 FROM admin_role_permissions WHERE role_name=?"
            );
            $_pq->execute([$firstRoleName]);
            foreach ($_pq->fetchAll(\PDO::FETCH_ASSOC) as $_pr) {
                $userCaseModulePerms[$_pr['module']] = $_pr;
            }
        } catch (\Throwable $e) {}
    }
}

// Clinical roles should always be able to edit case workflow modules.
// This prevents stale rows in admin_role_permissions from forcing view-only mode.
$forceEditRoles = ['physician-assistant', 'consultant', 'medical-officer'];
$hasForcedEditRole = false;
foreach ($forceEditRoles as $forceRole) {
    if ($user->hasRole($forceRole)) {
        $hasForcedEditRole = true;
        break;
    }
}
if ($hasForcedEditRole) {
    foreach (array_keys($workflowDefinitions) as $moduleName) {
        $existing = $userCaseModulePerms[$moduleName] ?? [];
        $userCaseModulePerms[$moduleName] = [
            'module' => $moduleName,
            'can_view' => 1,
            'can_edit' => 1,
            'can_delete' => (int) ($existing['can_delete'] ?? 0),
            'can_approve' => (int) ($existing['can_approve'] ?? 0),
        ];
    }
}

if ((int) $input->get->pdf === 1 && $case && $case->id) {
    // ── Simple discharge PDF ──────────────────────────────────────────────────
    $pConsultant = $case->consultant_ref && $case->consultant_ref->id
        ? (string) $case->consultant_ref->title
        : trim((string) ($case->discharge_consultant ?: $case->admitting_unit ?: ''));
    $pPatient   = $patient ? trim((string) $patient->title) : '';
    $pId        = $patient ? trim((string) $patient->patient_id) : '';
    $pAge       = trim((string) $case->patient_age);
    $pAgeUnit   = $getOptionTitle($case->age_unit) ?: 'Years';
    $pGender    = $patient ? ($getOptionTitle($patient->gender) ?: '') : '';
    $pAgeGender = trim(($pAge !== '' ? $pAge . ' ' . $pAgeUnit : '') . ($pGender !== '' ? ' / ' . $pGender : ''));
    $pDiag      = $case->primary_diagnosis_ref && $case->primary_diagnosis_ref->id
        ? trim((string) $case->primary_diagnosis_ref->title)
        : trim(strip_tags((string) $case->diagnosis));
    $pSide      = $case->diagnosis_side ? (string) $case->diagnosis_side->title : '';
    $pIp        = trim((string) $case->ip_number);
    $pBed       = trim((string) ($case->room_bed ?: $case->ward_room));
    $pAdmitted  = $case->getUnformatted('admitted_on') ? date('d/m/Y', (int)$case->getUnformatted('admitted_on')) : '';
    $pDischarged= $case->getUnformatted('discharged_on') ? date('d/m/Y', (int)$case->getUnformatted('discharged_on')) : '';
    $pGuardian  = $patient ? trim((string) $patient->guardian_name) : '';
    $pPhone     = $patient ? trim((string) $patient->phone) : '';
    $pProcs     = $pages->find("template=procedure, parent={$case->id}, sort=proc_date");
    $pCondition = $case->general_condition
        ? (string) $case->general_condition->title
        : trim(strip_tags((string) $case->condition_at_discharge));
    $pMeds      = trim((string) $case->medications_on_discharge);
    $pFollowup  = trim((string) $case->follow_up_instructions);
    $pReview    = $case->getUnformatted('review_date') ? date('d/m/Y', (int)$case->getUnformatted('review_date')) : '';

    while (ob_get_level()) { ob_end_clean(); }

    $pdfHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;font-size:10.5pt;line-height:1.5;color:#1a1a1a}
.lh{width:100%;border-bottom:2px solid #1e3a8a;padding-bottom:10px;margin-bottom:16px}
.logo{width:60px;height:60px;background:#1e3a8a;color:#fff;border-radius:6px;text-align:center;line-height:60px;font-weight:bold;font-size:20px}
.hi{text-align:right;font-size:9pt;color:#444}
.hi h2{color:#1e3a8a;margin:0;font-size:15pt}
.doc-title{text-align:center;font-size:13pt;font-weight:700;margin:10px 0 16px;text-transform:uppercase;color:#1e3a8a}
table.pi{width:100%;border-collapse:collapse;margin-bottom:16px;font-size:9.5pt;border:1px solid #cbd5e1}
table.pi th,table.pi td{padding:6px 10px;border:1px solid #cbd5e1;vertical-align:top}
table.pi th{background:#f1f5f9;color:#475569;font-weight:600;text-transform:uppercase;font-size:8.5pt;width:18%}
.sec{margin-top:14px;margin-bottom:6px;font-weight:700;font-size:10.5pt;color:#1e3a8a;border-bottom:1px solid #e2e8f0;padding-bottom:3px;text-transform:uppercase}
.content{font-size:10pt;margin-bottom:12px}
.proc-item{padding:5px 0 5px 12px;border-left:3px solid #94a3b8;margin-bottom:8px}
.sig{width:100%;margin-top:50px}
.sig td{width:50%;text-align:center;font-size:9pt}
.footer{position:fixed;bottom:0;width:100%;text-align:center;font-size:8pt;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:4px}
</style></head><body>';

    // Letterhead
    $pdfHtml .= '<table class="lh"><tr><td><div class="logo">GR</div></td><td class="hi">
        <h2>Ganga Hospital</h2><p>Department of Hand Surgery &amp; Microsurgery<br>Structured Clinical Documentation System</p>
    </td></tr></table>';
    $pdfHtml .= '<div class="doc-title">Discharge Summary</div>';

    // Patient info
    $pdfHtml .= '<table class="pi">';
    $pdfHtml .= '<tr><th>Patient Name</th><td>' . htmlspecialchars($pPatient) . '</td><th>Patient ID</th><td>' . htmlspecialchars($pId) . '</td></tr>';
    $pdfHtml .= '<tr><th>Age / Gender</th><td>' . htmlspecialchars($pAgeGender) . '</td><th>IP Number</th><td>' . htmlspecialchars($pIp) . '</td></tr>';
    $pdfHtml .= '<tr><th>Guardian</th><td>' . htmlspecialchars($pGuardian) . '</td><th>Phone</th><td>' . htmlspecialchars($pPhone) . '</td></tr>';
    $pdfHtml .= '<tr><th>Ward / Bed</th><td>' . htmlspecialchars($pBed) . '</td><th>Consultant</th><td>' . htmlspecialchars($pConsultant) . '</td></tr>';
    $pdfHtml .= '<tr><th>Admitted</th><td>' . htmlspecialchars($pAdmitted) . '</td><th>Discharged</th><td>' . htmlspecialchars($pDischarged) . '</td></tr>';
    $pdfHtml .= '</table>';

    // Diagnosis
    if ($pDiag !== '') {
        $pdfHtml .= '<div class="sec">Diagnosis</div>';
        $pdfHtml .= '<div class="content"><strong>' . htmlspecialchars($pDiag) . '</strong>' . ($pSide !== '' ? ' (' . htmlspecialchars($pSide) . ')' : '') . '</div>';
    }

    // Procedures
    if (count($pProcs)) {
        $pdfHtml .= '<div class="sec">Procedures</div><div class="content">';
        foreach ($pProcs as $proc) {
            $procDate = $proc->getUnformatted('proc_date') ? date('d-m-Y', (int)$proc->getUnformatted('proc_date')) : '';
            $procName = htmlspecialchars(trim((string)($proc->proc_name ?: $proc->title)));
            $pdfHtml .= '<div class="proc-item">' . ($procDate ? '<strong>' . $procDate . '</strong> : ' : '') . $procName . '</div>';
        }
        $pdfHtml .= '</div>';
    }

    // Condition at discharge
    if ($pCondition !== '') {
        $pdfHtml .= '<div class="sec">Condition at Discharge</div>';
        $pdfHtml .= '<div class="content">' . htmlspecialchars($pCondition) . '</div>';
    }

    // Medications
    if ($pMeds !== '') {
        $pdfHtml .= '<div class="sec">Medications on Discharge</div>';
        $decoded = json_decode($pMeds, true);
        if (is_array($decoded) && count($decoded)) {
            $pdfHtml .= '<table style="width:100%;border-collapse:collapse;font-size:9.5pt"><tr style="background:#f1f5f9"><th style="padding:5px 8px;border:1px solid #cbd5e1;text-align:left">Drug</th><th style="padding:5px 8px;border:1px solid #cbd5e1">Dose</th><th style="padding:5px 8px;border:1px solid #cbd5e1">Duration</th><th style="padding:5px 8px;border:1px solid #cbd5e1">Frequency</th></tr>';
            foreach ($decoded as $med) {
                $pdfHtml .= '<tr><td style="padding:5px 8px;border:1px solid #cbd5e1">' . htmlspecialchars((string)($med['drug'] ?? '')) . '</td>'
                    . '<td style="padding:5px 8px;border:1px solid #cbd5e1;text-align:center">' . htmlspecialchars((string)($med['dose'] ?? '')) . '</td>'
                    . '<td style="padding:5px 8px;border:1px solid #cbd5e1;text-align:center">' . htmlspecialchars((string)($med['duration'] ?? '')) . '</td>'
                    . '<td style="padding:5px 8px;border:1px solid #cbd5e1;text-align:center">' . htmlspecialchars((string)($med['frequency'] ?? '')) . '</td></tr>';
            }
            $pdfHtml .= '</table>';
        } else {
            $pdfHtml .= '<div class="content">' . nl2br(htmlspecialchars($pMeds)) . '</div>';
        }
    }

    // Follow-up
    if ($pFollowup !== '' || $pReview !== '') {
        $pdfHtml .= '<div class="sec">Follow-up</div><div class="content">';
        if ($pFollowup !== '') $pdfHtml .= nl2br(htmlspecialchars($pFollowup)) . '<br>';
        if ($pReview !== '') $pdfHtml .= '<strong>Review Date:</strong> ' . htmlspecialchars($pReview);
        $pdfHtml .= '</div>';
    }

    // Signature
    $pdfHtml .= '<table class="sig"><tr><td><br><br><br><strong>' . htmlspecialchars($pConsultant) . '</strong><br>Consultant</td>'
        . '<td><br><br><br><strong>Prepared by</strong><br>Medical Officer</td></tr></table>';
    $pdfHtml .= '<div class="footer">GangaReg Clinical Registry · Printed: ' . date('d/m/Y H:i') . ' · IP: ' . htmlspecialchars($pIp) . '</div>';
    $pdfHtml .= '</body></html>';

    $vendorAutoload = $config->paths->root . 'vendor/autoload.php';
    if (!file_exists($vendorAutoload)) { exit('mPDF not found'); }
    require_once $vendorAutoload;
    $mpdf = new \Mpdf\Mpdf([
        'margin_left' => 15, 'margin_right' => 15,
        'margin_top' => 20, 'margin_bottom' => 25,
        'default_font' => 'arial',
    ]);
    $mpdf->WriteHTML($pdfHtml);
    $mpdf->Output('Discharge_' . ($pIp ?: $case->id) . '_' . date('Ymd') . '.pdf', 'I');
    exit;
}

// dead code removed — replaced by simple GET handler above
if (false) {
    $validationIssues = $getDischargeBlockers($case, $patient);
    if ($validationIssues) {
        $session->error('Cannot generate PDF: incomplete case.');
        $session->redirect($buildCaseUrl([
            'saved' => 'pdf-missing',
            'pdf_missing' => implode('|', $validationIssues),
        ], $getWorkflowAnchor('discharge-engine')));
    }

    $consultantNameForPdf = '';
    if ($case->consultant_ref && $case->consultant_ref->id) {
        $consultantNameForPdf = $case->consultant_ref->title;
    } elseif (trim((string) $case->discharge_consultant) !== '') {
        $consultantNameForPdf = trim((string) $case->discharge_consultant);
    } elseif (trim((string) $case->admitting_unit) !== '') {
        $consultantNameForPdf = trim((string) $case->admitting_unit);
    } else {
        $consultantNameForPdf = trim((string) $case->doctor_name);
    }

    $patientNameForPdf = $patient ? trim((string) $patient->title) : 'Clinical Case';
    $patientIdForPdf = $patient ? trim((string) $patient->patient_id) : '';
    $ageValueForPdf = trim((string) $case->patient_age);
    $ageUnitForPdf = $getOptionTitle($case->age_unit) ?: 'Years';
    $genderForPdf = $patient ? ($getOptionTitle($patient->gender) ?: '') : '';
    $ageGenderForPdf = trim($ageValueForPdf !== '' ? ($ageValueForPdf . ' ' . $ageUnitForPdf . ($genderForPdf !== '' ? ' / ' . $genderForPdf : '')) : $genderForPdf);
    $bedForPdf = trim((string) ($case->room_bed ?: $case->ward_room));
    $diagnosisForPdf = $case->primary_diagnosis_ref && $case->primary_diagnosis_ref->id
        ? trim((string) $case->primary_diagnosis_ref->title)
        : trim((string) $case->diagnosis);
    $associatedConditionsForPdf = trim((string) $case->associated_conditions);
    $historyOfPresentIllnessForPdf = '';
    if ($fieldsApi->get('hpi') && trim((string) $case->hpi) !== '') {
        $historyOfPresentIllnessForPdf = trim((string) $case->hpi);
    } elseif ($fieldsApi->get('history_complaints')) {
        $historyOfPresentIllnessForPdf = trim((string) $case->history_complaints);
    }
    $pastMedicalHistoryForPdf = $fieldsApi->get('past_medical_history') ? trim((string) $case->past_medical_history) : '';
    $pastSurgicalHistoryForPdf = $fieldsApi->get('past_surgical_history') ? trim((string) $case->past_surgical_history) : '';

    $proceduresForPdf = $pages->find("template=procedure, parent={$case->id}, sort=proc_date, sort=sort");
    $operationNotesForPdfPages = $pages->find("template=operation-note, parent={$case->id}, sort=sort, sort=created");
    $operationNotesForPdfByProcedure = [];
    $standaloneOperationNotesForPdf = [];
    foreach ($operationNotesForPdfPages as $operationNotePage) {
        $procedureRef = $fieldsApi->get('procedure_ref_id') ? $operationNotePage->getUnformatted('procedure_ref_id') : null;
        $procedureRefId = $procedureRef instanceof Page ? (int) $procedureRef->id : (int) $procedureRef;
        if ($procedureRefId > 0) {
            $linkedProcedure = $pages->get($procedureRefId);
            if ($linkedProcedure && $linkedProcedure->id && $linkedProcedure->template->name === 'procedure' && (int) $linkedProcedure->parent_id === (int) $case->id) {
                $operationNotesForPdfByProcedure[$procedureRefId] = $operationNotePage;
                continue;
            }
        }
        $standaloneOperationNotesForPdf[] = $operationNotePage;
    }
    $primaryProcedureForPdf = ($proceduresForPdf && count($proceduresForPdf)) ? $proceduresForPdf->first() : null;
    $operationNotesForPdf = [];
    foreach ($proceduresForPdf as $procedurePage) {
        $operationNote = $operationNotesForPdfByProcedure[$procedurePage->id] ?? null;
        if (!$operationNote || !$operationNote->id) {
            $operationNote = $pages->get("template=operation-note, parent={$procedurePage}");
        }
        if ($operationNote && $operationNote->id) {
            $operationNotesForPdf[] = ['procedure' => $procedurePage, 'note' => $operationNote];
        }
    }
    foreach ($standaloneOperationNotesForPdf as $standaloneOperationNote) {
        $operationNotesForPdf[] = ['procedure' => null, 'note' => $standaloneOperationNote];
    }

    $comorbidityNoneForPdf = $fieldsApi->get('comorbidity_none') ? (bool) $case->getUnformatted('comorbidity_none') : false;
    $comorbidityFlagsForPdf = $fieldsApi->get('comorbidity_flags') ? $getOptionTitles($case->getUnformatted('comorbidity_flags')) : [];
    $comorbidityCustomConditionsForPdf = [];
    $comorbidityDrugPagesForPdf = wire('templates')->get('comorbidity-drug')
        ? $pages->find("template=comorbidity-drug, parent={$case->id}, sort=sort")
        : new PageArray();
    $comorbidityDrugRowsForPdf = [];
    foreach ($comorbidityDrugPagesForPdf as $drugPage) {
        $condition = trim((string) $drugPage->get('comorb_condition_flag'));
        $drugName = trim((string) $drugPage->get('drug_name'));
        $drugDose = trim((string) $drugPage->get('drug_dose'));
        if ($condition !== '' && !in_array($condition, $knownComorbidityFlags, true)) {
            $comorbidityCustomConditionsForPdf[] = $condition;
        }
        if ($drugName !== '') {
            $comorbidityDrugRowsForPdf[] = [
                'condition' => $condition,
                'drug_name' => $drugName,
                'drug_dose' => $drugDose,
            ];
        }
    }

    $legacyComorbidityTextForPdf = $fieldsApi->get('comorbidities') ? trim((string) $case->comorbidities) : '';
    if (!$comorbidityNoneForPdf && !$comorbidityFlagsForPdf && !$comorbidityDrugRowsForPdf && $legacyComorbidityTextForPdf !== '') {
        $legacyComorbidityRowsForPdf = json_decode($legacyComorbidityTextForPdf, true);
        if (is_array($legacyComorbidityRowsForPdf)) {
            foreach ($legacyComorbidityRowsForPdf as $legacyRow) {
                if (!is_array($legacyRow)) {
                    continue;
                }
                $condition = trim((string) ($legacyRow['condition'] ?? ''));
                $treatment = trim((string) ($legacyRow['treatment'] ?? ''));
                if ($condition !== '') {
                    $normalizedFlag = $normalizeComorbidityFlag($condition);
                    if ($normalizedFlag !== '' && !in_array($normalizedFlag, $comorbidityFlagsForPdf, true)) {
                        $comorbidityFlagsForPdf[] = $normalizedFlag;
                    } elseif ($normalizedFlag === '' && !in_array($condition, $comorbidityCustomConditionsForPdf, true)) {
                        $comorbidityCustomConditionsForPdf[] = $condition;
                    }
                }
                if ($treatment !== '') {
                    [$drugName, $drugDose] = $splitComorbidityTreatment($treatment);
                    if ($drugName !== '') {
                        $comorbidityDrugRowsForPdf[] = [
                            'condition' => $condition,
                            'drug_name' => $drugName,
                            'drug_dose' => $drugDose,
                        ];
                    }
                }
            }
        }
    }
    $comorbidityFlagsForPdf = array_values(array_unique(array_filter($comorbidityFlagsForPdf)));
    $comorbidityCustomConditionsForPdf = array_values(array_unique(array_filter($comorbidityCustomConditionsForPdf)));
    $comorbidityLinesForPdf = [];
    if ($comorbidityNoneForPdf) {
        $comorbidityLinesForPdf[] = 'No known comorbidities';
    } else {
        foreach ($knownComorbidityFlags as $flag) {
            if (in_array($flag, $comorbidityFlagsForPdf, true)) {
                $comorbidityLinesForPdf[] = $flag;
            }
        }
        foreach ($comorbidityCustomConditionsForPdf as $customCondition) {
            if (!in_array($customCondition, $comorbidityLinesForPdf, true)) {
                $comorbidityLinesForPdf[] = $customCondition;
            }
        }
        if (!$comorbidityLinesForPdf && $legacyComorbidityTextForPdf !== '') {
            $comorbidityLinesForPdf[] = $legacyComorbidityTextForPdf;
        }
    }

    $drugHistoryEntriesForPdf = [];
    $drugHistoryPagesForPdf = wire('templates')->get('drug-history-entry')
        ? $pages->find("template=drug-history-entry, parent={$case->id}, sort=sort")
        : new PageArray();
    foreach ($drugHistoryPagesForPdf as $drugHistoryPage) {
        $drugName = trim((string) $drugHistoryPage->get('drug_name'));
        $drugDose = trim((string) $drugHistoryPage->get('drug_dose'));
        $drugFrequency = trim((string) $drugHistoryPage->get('drug_frequency'));
        if ($drugName !== '') {
            $drugHistoryEntriesForPdf[] = [
                'drug_name' => $drugName,
                'drug_dose' => $drugDose,
                'drug_frequency' => $drugFrequency,
            ];
        }
    }
    if (!$drugHistoryEntriesForPdf) {
        $legacyDrugHistoryTextForPdf = $fieldsApi->get('drug_history') ? trim((string) $case->drug_history) : '';
        if ($legacyDrugHistoryTextForPdf !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $legacyDrugHistoryTextForPdf) as $legacyLine) {
                $legacyLine = trim((string) $legacyLine);
                if ($legacyLine === '') {
                    continue;
                }
                $parts = array_map('trim', explode(' - ', $legacyLine));
                $drugHistoryEntriesForPdf[] = [
                    'drug_name' => $parts[0] ?? $legacyLine,
                    'drug_dose' => $parts[1] ?? '',
                    'drug_frequency' => $parts[2] ?? '',
                ];
            }
        }
    }

    $investigationSelector = "template=investigation, parent={$case->id}, sort=investigation_date, sort=sort";
    if ($fieldsApi->get('include_in_discharge')) {
        $investigationSelector .= ", include_in_discharge=1";
    }
    $investigationsForPdf = $pages->find($investigationSelector);

    $dischargeEntriesForPdf = $pages->find("template=hospital-course-entry, parent={$case->id}, hce_type.title=Discharge, sort=hce_date, sort=created");
    $generalConditionForPdf = $getOptionTitle($case->general_condition) ?: trim((string) $case->condition_at_discharge);

    $conditionPayloadForPdf = $extractEmbeddedMeta((string) $case->pain_status);
    $conditionMetaForPdf = $conditionPayloadForPdf['meta'];
    $followUpPayloadForPdf = $extractEmbeddedMeta((string) $case->follow_up_instructions);
    $followUpMetaForPdf = $followUpPayloadForPdf['meta'];

    $rawMedicationsForPdf = trim((string) $case->medications_on_discharge);
    $medicationRowsForPdf = [];
    if ($rawMedicationsForPdf !== '') {
        $decodedMedicationsForPdf = json_decode($rawMedicationsForPdf, true);
        if (is_array($decodedMedicationsForPdf)) {
            foreach ($decodedMedicationsForPdf as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $drug = trim((string) ($row['drug'] ?? ''));
                if ($drug === '') {
                    continue;
                }
                $medicationRowsForPdf[] = [
                    'drug' => $drug,
                    'dose' => trim((string) ($row['dose'] ?? '')),
                    'frequency' => trim((string) ($row['frequency'] ?? '')),
                    'duration' => trim((string) ($row['duration'] ?? '')),
                ];
            }
        } else {
            foreach (preg_split('/\r\n|\r|\n/', $rawMedicationsForPdf) as $line) {
                $line = trim((string) $line);
                if ($line !== '') {
                    $medicationRowsForPdf[] = ['drug' => $line, 'dose' => '', 'frequency' => '', 'duration' => ''];
                }
            }
        }
    }

    $hospitalName = $templateSettings['pdf_header'];
    $hospitalSubtitle = 'Department of Hand Surgery & Microsurgery';
    $headerHtml = '
        <div style="border-bottom:1px solid #cbd5e1; padding-bottom:8px; font-family:Arial, sans-serif; font-size:9pt; color:#334155;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-size:12pt; font-weight:bold; color:#1e3a8a;">' . $sanitizer->entities($hospitalName) . '</td>
                    <td style="text-align:right;">
                        <strong>' . $sanitizer->entities($patientNameForPdf) . '</strong>
                        ' . ($case->ip_number ? ' | IP ' . $sanitizer->entities((string) $case->ip_number) : '') . '
                    </td>
                </tr>
                <tr>
                    <td>' . $sanitizer->entities($hospitalSubtitle) . '</td>
                    <td style="text-align:right;">Discharge Summary</td>
                </tr>
            </table>
        </div>';
    $footerHtml = '<div style="font-family:Arial, sans-serif; font-size:8.5pt; color:#64748b; border-top:1px solid #e2e8f0; padding-top:4px; text-align:center;">' . $sanitizer->entities($templateSettings['pdf_footer']) . ' | Page {PAGENO} of {nbpg}</div>';

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; font-size: <?= (int) $templateSettings['pdf_font_size'] ?>pt; line-height: 1.45; color: #1f2937; }
    .letterhead { width: 100%; border-bottom: 2px solid #1e3a8a; padding-bottom: 12px; margin-bottom: 18px; }
    .letterhead td { vertical-align: middle; }
    .logo-box { width: 58px; height: 58px; border-radius: 8px; background: #1e3a8a; color: #fff; text-align: center; line-height: 58px; font-size: 22px; font-weight: bold; }
    .hospital-name { font-size: 17pt; font-weight: bold; color: #1e3a8a; margin: 0; }
    .hospital-subtitle { font-size: 9pt; color: #475569; margin-top: 4px; }
    .doc-title { text-align: center; font-size: 13pt; font-weight: bold; margin: 12px 0 16px; text-transform: uppercase; color: #1e3a8a; letter-spacing: 0.4px; }
    .section-title { margin: 16px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #cbd5e1; font-size: 10.5pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; }
    .meta-table, .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .meta-table th, .meta-table td, .data-table th, .data-table td { border: 1px solid #cbd5e1; padding: 7px 9px; vertical-align: top; }
    .meta-table th, .data-table th { background: #f8fafc; color: #334155; font-weight: bold; text-align: left; }
    .meta-table th { width: 20%; }
    .text-block { margin-bottom: 10px; }
    .muted { color: #64748b; }
    .pill-list { margin: 0 0 10px; padding: 0; list-style: none; }
    .pill-list li { display: inline-block; margin: 0 8px 6px 0; padding: 3px 8px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 9pt; }
    .procedure-card { border-left: 3px solid #94a3b8; padding-left: 10px; margin-bottom: 12px; }
    .procedure-title { font-weight: bold; color: #0f172a; }
  </style>
</head>
<body>
  <table class="letterhead">
    <tr>
      <td width="72"><div class="logo-box">GH</div></td>
      <td>
        <div class="hospital-name"><?= $sanitizer->entities($hospitalName) ?></div>
        <div class="hospital-subtitle"><?= $sanitizer->entities($hospitalSubtitle) ?><br>Structured Clinical Documentation System</div>
      </td>
    </tr>
  </table>

  <div class="doc-title">Discharge Summary</div>

  <?php if ($templateSettings['show_admission'] === '1'): ?>
  <div class="section-title">Admission Information</div>
  <table class="meta-table">
    <tr>
      <th>Patient Name</th>
      <td><?= $sanitizer->entities($patientNameForPdf) ?></td>
      <th>Patient ID</th>
      <td><?= $patientIdForPdf !== '' ? $sanitizer->entities($patientIdForPdf) : 'Not recorded' ?></td>
    </tr>
    <tr>
      <th>Age / Sex</th>
      <td><?= $ageGenderForPdf !== '' ? $sanitizer->entities($ageGenderForPdf) : 'Not recorded' ?></td>
      <th>Consultant</th>
      <td><?= $consultantNameForPdf !== '' ? $sanitizer->entities($consultantNameForPdf) : 'Not assigned' ?></td>
    </tr>
    <tr>
      <th>Bed</th>
      <td><?= $sanitizer->entities($bedForPdf !== '' ? $bedForPdf : 'Not assigned') ?></td>
      <th>IP Number</th>
      <td><?= $case->ip_number ? $sanitizer->entities((string) $case->ip_number) : 'Not recorded' ?></td>
    </tr>
    <tr>
      <th>Admission Date</th>
      <td><?= $case->getUnformatted('admitted_on') ? date('d M Y', $case->getUnformatted('admitted_on')) : 'Not recorded' ?></td>
      <th>Discharge Date</th>
      <td><?= $case->getUnformatted('discharged_on') ? date('d M Y', $case->getUnformatted('discharged_on')) : 'Not recorded' ?></td>
    </tr>
  </table>
  <?php endif; ?>

  <?php if ($templateSettings['show_diagnosis'] === '1'): ?>
  <div class="section-title">Diagnosis</div>
  <div class="text-block"><strong><?= $sanitizer->entities($diagnosisForPdf) ?></strong></div>
  <?php if ($associatedConditionsForPdf !== ''): ?>
  <div class="text-block"><strong>Associated Conditions:</strong><br><?= nl2br($sanitizer->entities($associatedConditionsForPdf)) ?></div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($templateSettings['show_ot_plan'] === '1' && $primaryProcedureForPdf): ?>
  <?php if ($primaryProcedureForPdf): ?>
  <div class="section-title">Procedure Date and Name</div>
  <table class="meta-table">
    <tr>
      <th>Procedure</th>
      <td><?= $sanitizer->entities($primaryProcedureForPdf->proc_name ?: $primaryProcedureForPdf->title) ?></td>
      <th>Date</th>
      <td><?= $primaryProcedureForPdf->getUnformatted('proc_date') ? date('d M Y', $primaryProcedureForPdf->getUnformatted('proc_date')) : 'Not recorded' ?></td>
    </tr>
  </table>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($templateSettings['show_history'] === '1'): ?>
  <div class="section-title">History and Presenting Illness</div>
  <div class="text-block"><strong>Chief Complaint:</strong><br><?= $hasText($case->chief_complaint) ? nl2br($sanitizer->entities($case->chief_complaint)) : 'Not recorded' ?></div>
  <div class="text-block"><strong>History of Present Illness:</strong><br><?= $historyOfPresentIllnessForPdf !== '' ? nl2br($sanitizer->entities($historyOfPresentIllnessForPdf)) : 'Not recorded' ?></div>
  <?php if ($pastMedicalHistoryForPdf !== ''): ?>
  <div class="text-block"><strong>Past Medical History:</strong><br><?= nl2br($sanitizer->entities($pastMedicalHistoryForPdf)) ?></div>
  <?php endif; ?>
  <?php if ($pastSurgicalHistoryForPdf !== ''): ?>
  <div class="text-block"><strong>Past Surgical History:</strong><br><?= nl2br($sanitizer->entities($pastSurgicalHistoryForPdf)) ?></div>
  <?php endif; ?>

  <div class="section-title">Comorbidities and Drug History</div>
  <?php if ($comorbidityLinesForPdf): ?>
  <ul class="pill-list">
    <?php foreach ($comorbidityLinesForPdf as $line): ?>
    <li><?= $sanitizer->entities($line) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <div class="text-block">No comorbidities recorded.</div>
  <?php endif; ?>

  <?php if ($comorbidityDrugRowsForPdf): ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Condition</th>
        <th>Drug</th>
        <th>Dose</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($comorbidityDrugRowsForPdf as $row): ?>
      <tr>
        <td><?= $sanitizer->entities($row['condition'] !== '' ? $row['condition'] : 'Unspecified') ?></td>
        <td><?= $sanitizer->entities($row['drug_name']) ?></td>
        <td><?= $sanitizer->entities($row['drug_dose']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if ($drugHistoryEntriesForPdf): ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Drug</th>
        <th>Dose</th>
        <th>Frequency</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($drugHistoryEntriesForPdf as $row): ?>
      <tr>
        <td><?= $sanitizer->entities($row['drug_name']) ?></td>
        <td><?= $sanitizer->entities($row['drug_dose']) ?></td>
        <td><?= $sanitizer->entities($row['drug_frequency']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="text-block">No ongoing medications recorded.</div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($templateSettings['show_examination'] === '1'): ?>
  <div class="section-title">Examination</div>
  <div class="text-block"><strong>General Examination:</strong><br><?= $hasText($case->inspection) ? nl2br($sanitizer->entities($case->inspection)) : 'Not recorded' ?></div>
  <div class="text-block"><strong>Local Examination:</strong><br><?= $hasText($case->examination_findings) ? nl2br($sanitizer->entities($case->examination_findings)) : 'Not recorded' ?></div>
  <?php endif; ?>

  <?php if ($templateSettings['show_investigations'] === '1'): ?>
  <div class="section-title">Investigations</div>
  <?php if (count($investigationsForPdf ?? [])): ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Date</th>
        <th>Type</th>
        <th>Investigation</th>
        <th>Findings</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($investigationsForPdf as $investigation): ?>
      <?php
        $investigationType = $investigation->investigation_type && $investigation->investigation_type->title
          ? $investigation->investigation_type->title
          : 'Other';
      ?>
      <tr>
        <td><?= $investigation->getUnformatted('investigation_date') ? date('d M Y', $investigation->getUnformatted('investigation_date')) : 'Not recorded' ?></td>
        <td><?= $sanitizer->entities($investigationType) ?></td>
        <td><?= $sanitizer->entities($investigation->investigation_name ?: $investigation->title) ?></td>
        <td><?= $hasText($investigation->investigation_findings) ? nl2br($sanitizer->entities($investigation->investigation_findings)) : 'Not recorded' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="text-block">No discharge investigations selected.</div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($templateSettings['show_operation_note'] === '1'): ?>
  <div class="section-title">Operation Notes</div>
  <?php if ($operationNotesForPdf): ?>
    <?php foreach ($operationNotesForPdf as $bundle): ?>
    <?php
      $procedurePage = $bundle['procedure'];
      $operationNote = $bundle['note'];
      $closurePayloadForPdf = $extractEmbeddedMeta((string) $operationNote->closure_details);
      $procedureLabelForPdf = $procedurePage
        ? trim((string) ($procedurePage->proc_name ?: $procedurePage->title))
        : $formatOperationNoteName($operationNote);
    ?>
    <div class="procedure-card">
      <div class="procedure-title"><?= $sanitizer->entities($procedureLabelForPdf) ?></div>
      <?php if ($hasText($operationNote->procedure_steps)): ?>
      <div class="text-block"><strong>Procedure Steps:</strong><br><?= nl2br($sanitizer->entities($operationNote->procedure_steps)) ?></div>
      <?php endif; ?>
      <?php if ($hasText($operationNote->implants_used)): ?>
      <div class="text-block"><strong>Implants:</strong><br><?= nl2br($sanitizer->entities($operationNote->implants_used)) ?></div>
      <?php endif; ?>
      <?php if ($hasText($closurePayloadForPdf['text'])): ?>
      <div class="text-block"><strong>Closure:</strong><br><?= nl2br($sanitizer->entities($closurePayloadForPdf['text'])) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
  <div class="text-block">No operation notes recorded.</div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($templateSettings['show_hospital_course'] === '1'): ?>
  <div class="section-title">Hospital Course</div>
  <?php foreach ($dischargeEntriesForPdf as $entry): ?>
  <div class="text-block">
    <strong><?= $entry->getUnformatted('hce_date') ? date('d M Y', $entry->getUnformatted('hce_date')) : 'Date pending' ?>:</strong>
    <?= nl2br($sanitizer->entities((string) $entry->hce_note)) ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($templateSettings['show_condition'] === '1'): ?>
  <div class="section-title">Condition at Discharge</div>
  <table class="meta-table">
    <tr>
      <th>Status of Patient During Discharge</th>
      <td><?= $hasText($generalConditionForPdf) ? $sanitizer->entities($generalConditionForPdf) : 'Not recorded' ?></td>
      <th>Condition Summary</th>
      <td><?= $hasText($conditionPayloadForPdf['text']) ? $sanitizer->entities($conditionPayloadForPdf['text']) : 'Not recorded' ?></td>
    </tr>
    <tr>
      <th>Pain Scale (0-10)</th>
      <td><?= $fieldsApi->get('pain_scale') ? (int) $case->getUnformatted('pain_scale') : 'Not recorded' ?></td>
      <th></th>
      <td></td>
    </tr>
  </table>
  <?php endif; ?>

  <?php if ($templateSettings['show_medications'] === '1'): ?>
  <div class="section-title">Medications on Discharge</div>
  <table class="data-table">
    <thead>
      <tr>
        <th>Drug</th>
        <th>Strength</th>
        <th>Frequency</th>
        <th>Duration</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($medicationRowsForPdf as $row): ?>
      <tr>
        <td><?= $sanitizer->entities($row['drug']) ?></td>
        <td><?= $sanitizer->entities($row['dose']) ?></td>
        <td><?= $sanitizer->entities($row['frequency']) ?></td>
        <td><?= $sanitizer->entities($row['duration']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if ($templateSettings['show_advice'] === '1'): ?>
  <div class="section-title">Advice and Follow-up</div>
  <div class="text-block"><strong>Instructions:</strong><br><?= $hasText($followUpPayloadForPdf['text']) ? nl2br($sanitizer->entities($followUpPayloadForPdf['text'])) : 'Not recorded' ?></div>
  <div class="text-block"><strong>Subsequent Treatment Plan:</strong><br><?= trim((string) ($followUpMetaForPdf['subsequent_plan'] ?? '')) !== '' ? nl2br($sanitizer->entities((string) $followUpMetaForPdf['subsequent_plan'])) : 'Not recorded' ?></div>
  <div class="text-block"><strong>Follow-up Date:</strong> <?= $case->getUnformatted('review_date') ? date('d M Y', $case->getUnformatted('review_date')) : 'Not recorded' ?></div>
  <?php endif; ?>
</body>
</html>
    <?php
    $pdfHtml = ob_get_clean();
    while (ob_get_level()) { ob_end_clean(); }

    $vendorAutoload = $config->paths->root . 'vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        header('Content-Type: text/plain');
        exit('mPDF vendor not found');
    }

    require_once $vendorAutoload;

    $filename = 'discharge-' . ($case->ip_number ?: $case->id) . '-' . date('Ymd') . '.pdf';
    $writeAuditLog($case, 6, [
        'event' => 'Generated PDF',
        'filename' => $filename,
        'generated_at' => date('c'),
    ]);

    $mpdf = new \Mpdf\Mpdf([
        'margin_left' => (int) $templateSettings['pdf_margin'],
        'margin_right' => (int) $templateSettings['pdf_margin'],
        'margin_top' => 30,
        'margin_bottom' => (int) $templateSettings['pdf_margin'],
        'default_font' => 'arial',
    ]);
    $mpdf->SetTitle('Discharge Summary - ' . ($case->ip_number ?: $case->id));
    $mpdf->SetHTMLHeader($headerHtml);
    $mpdf->SetHTMLFooter($footerHtml);
    $mpdf->WriteHTML($pdfHtml);
    $mpdf->Output($filename, 'D');
    exit;
}

$config->appendTemplateFile = '_main.php';
$config->useMarkupRegions = true;

$postAction = $sanitizer->text($input->post->action);
$saveModule = $sanitizer->text($input->post->save_module);
$activePostAction = $saveModule !== '' ? $saveModule : $postAction;

if ($input->requestMethod('POST') && $case && $case->id && $activePostAction !== '') {
    $case = $pages->getFresh($case);
    $case->of(false);

    if (!$session->CSRF->validate()) {
        wire('log')->save('case-view-errors', 'CSRF validation failed for action=' . $activePostAction . ' on case ' . ($case ? $case->id : 'null'));
        $session->error('Session expired. Please try again.');
        $session->redirect($buildCaseUrl([], $getWorkflowAnchor($workflowSaveModule ?: 'admission')));
    }

    $saveActionModuleMap = [
        'admission' => 'admission',
        'diagnosis' => 'diagnosis',
        'history' => 'history',
        'examination' => 'examination',
        'investigation' => 'investigations',
        'update_investigation' => 'investigations',
        'procedure_plan' => 'ot-plan',
        'surgery_plan' => 'ot-plan',
        'procedure' => 'ot-plan',
        'operation_note' => 'operation-note',
        'hospital_course' => 'hospital-course',
        'condition' => 'condition',
        'medications' => 'medications',
        'advice' => 'advice',
    ];
    $workflowSaveModule = $saveActionModuleMap[$activePostAction] ?? null;

    // Block save if user has no view or edit permission for this module
    if ($workflowSaveModule && !$user->isSuperuser() && isset($userCaseModulePerms[$workflowSaveModule])) {
        if (!(int)($userCaseModulePerms[$workflowSaveModule]['can_view'] ?? 1)) {
            $session->error('You do not have permission to access the ' . ucfirst(str_replace('-', ' ', $workflowSaveModule)) . ' module.');
            $session->redirect($buildCaseUrl(['saved' => 'error'], $getWorkflowAnchor($workflowSaveModule)));
        }
        if (!(int)($userCaseModulePerms[$workflowSaveModule]['can_edit'] ?? 1)) {
            $session->error('You do not have permission to edit the ' . ucfirst(str_replace('-', ' ', $workflowSaveModule)) . ' module.');
            $session->redirect($buildCaseUrl(['saved' => 'error'], $getWorkflowAnchor($workflowSaveModule)));
        }
    }

    if ($workflowSaveModule) {
        $workflowFieldIssues = $getPostWorkflowFieldIssues($workflowSaveModule);
        if ($workflowFieldIssues) {
            $session->error(implode(', ', $workflowFieldIssues));
            $session->redirect($buildCaseUrl([
                'saved' => 'error',
                'pdf_missing' => implode('|', $workflowFieldIssues),
            ], $getWorkflowAnchor($workflowSaveModule)));
        }
    }

    try {
        switch ($activePostAction) {
            case 'draft':
                $case->of(false);
                $case->save();
                $session->redirect($buildCaseUrl(['saved' => 'draft']));
                break;

            case 'lock_case':
                $roleFlags = wire('authRoleFlags') ?: [];
                if (empty($roleFlags['is_consultant']) && empty($roleFlags['is_admin'])) {
                    $session->error('Only consultants can lock the case.');
                    $session->redirect($buildCaseUrl([], $getWorkflowAnchor('discharge-engine')));
                    break;
                }
                $lockBlockers = $getDischargeBlockers($case, $patient);
                if (count($lockBlockers ?? [])) {
                    $session->error('Cannot lock: ' . implode(', ', $lockBlockers));
                    $session->redirect($buildCaseUrl([
                        'saved' => 'lock-blocked',
                        'pdf_missing' => implode('|', $lockBlockers),
                    ], $getWorkflowAnchor('discharge-engine')));
                    break;
                }
                // Rule engine: enforce require_field rules at lock time
                $ruleViolations = [];
                foreach ($triggeredRules as $ruleItem) {
                    if ($ruleItem['action_type'] !== 'require_field') continue;
                    $reqField = trim($ruleItem['action_value']);
                    if (!$reqField) continue;
                    $fieldVal = $case->get($reqField);
                    $isEmpty = ($fieldVal === null || $fieldVal === '' || $fieldVal === 0
                        || ($fieldVal instanceof PageArray && !$fieldVal->count())
                        || (is_array($fieldVal) && empty($fieldVal)));
                    if ($isEmpty) {
                        $ruleViolations[] = '"' . $reqField . '" is required (condition: ' . $ruleItem['condition_field'] . ' ' . $ruleItem['operator'] . ' ' . $ruleItem['condition_value'] . ')';
                    }
                }
                if ($ruleViolations) {
                    $session->error('Cannot lock — rule enforcement: ' . implode('; ', $ruleViolations));
                    $session->redirect($buildCaseUrl(['saved' => 'lock-blocked'], $getWorkflowAnchor('discharge-engine')));
                    break;
                }
                $case->of(false);
                $setOptionField($case, 'case_status', 2);
                if (!$case->getUnformatted('discharged_on')) {
                    $case->discharged_on = time();
                }
                $case->save();
                $session->redirect($buildCaseUrl(['saved' => 'lock']));
                break;

            case 'delete_proc':
                $procId = (int) $sanitizer->int($input->post->proc_id);
                $procedure = $procId ? $pages->get($procId) : null;
                if ($procedure && $procedure->id && $procedure->template->name === 'procedure' && (int) $procedure->parent_id === (int) $case->id) {
                    $procedure->delete();
                }
                $session->redirect($buildCaseUrl(['saved' => 'procedure-deleted'], $getWorkflowAnchor('ot-plan')));
                break;

            case 'delete_hce':
                $hceId = (int) $sanitizer->int($input->post->hce_id);
                $hceEntry = $hceId ? $pages->get($hceId) : null;
                if ($hceEntry && $hceEntry->id
                    && $hceEntry->template->name === 'hospital-course-entry'
                    && (int) $hceEntry->parent_id === (int) $case->id) {
                    $hceEntry->of(false);
                    $hceEntry->delete();
                }
                $session->redirect($buildCaseUrl(['saved' => 'hce-deleted'], $getWorkflowAnchor('hospital-course')));
                break;

            case 'delete_attachment':
                $fieldMap = [
                    'clinical-photos' => 'clinical_images',
                    'investigation-reports' => 'investigation_files',
                ];
                $zone = $sanitizer->text($input->post->zone);
                $fileName = basename($sanitizer->text($input->post->file_name));
                $fieldName = $fieldMap[$zone] ?? '';
                if ($fieldName && $fieldsApi->get($fieldName) && $fileName !== '') {
                    $case->of(false);
                    foreach ($case->$fieldName as $file) {
                        if ($file->basename === $fileName || $file->name === $fileName) {
                            $case->$fieldName->delete($file);
                            break;
                        }
                    }
                    $case->save($fieldName);
                }
                $session->redirect($buildCaseUrl(['saved' => 'attachment'], 'content'));
                break;

            case 'admission':
                if ($patient && $patient->id) {
                    $patient->of(false);
                    $patientNameInput = trim((string) $sanitizer->text($input->post->patient_name));
                    if ($patientNameInput !== '') {
                        $patient->title = $patientNameInput;
                    }
                    $patient->phone = $sanitizer->text($input->post->phone);
                    $saveFieldIfExists($patient, 'secondary_phone', $sanitizer->text($input->post->phone_secondary));
                    $patient->guardian_name = $sanitizer->text($input->post->guardian);
                    $patient->address = $sanitizer->textarea($input->post->address);
                    $setOptionField($patient, 'gender', $sanitizer->text($input->post->gender));
                    $patient->save();
                }

                $consultantLabel = $sanitizer->text($input->post->consultant_label) ?: 'Dr. Md. Tawfiq Alam Siddique';
                $admissionDate = $sanitizer->date($input->post->admission_date, 'Y-m-d');
                $dischargeDate = $sanitizer->date($input->post->discharge_date, 'Y-m-d');

                $case->of(false);
                $case->patient_age = (int) $sanitizer->int($input->post->patient_age);
                $setOptionField($case, 'age_unit', $sanitizer->text($input->post->age_unit));
                $case->room_bed = $sanitizer->text($input->post->room_bed);
                $case->ward_room = '';
                $case->consultant_ref = null;
                $case->discharge_consultant = $consultantLabel;

                if ($admissionDate) {
                    $case->admitted_on = strtotime($admissionDate);
                }
                $case->discharged_on = $dischargeDate ? strtotime($dischargeDate) : null;
                $case->save();
                $session->redirect($buildCaseUrl(['saved' => 'admission'], $getWorkflowAnchor('admission')));
                break;

            case 'diagnosis':
                $diagnosisText = $sanitizer->textarea($input->post->primary_diagnosis_text);
                if (trim((string) $diagnosisText) === '') {
                    $session->redirect($buildCaseUrl(['saved' => 'error'], $getWorkflowAnchor('diagnosis')));
                    break;
                }
                $case->of(false);
                if ($fieldsApi->get('primary_diagnosis_ref')) {
                    $case->primary_diagnosis_ref = null;
                }
                $case->diagnosis = $diagnosisText;
                $saveFieldIfExists($case, 'secondary_diagnosis', '');
                $case->save();
                $session->redirect($buildCaseUrl(['saved' => 'diagnosis'], $getWorkflowAnchor('diagnosis')));
                break;

            case 'history':
                $chiefComplaintText = $sanitizer->textarea($input->post->chief_complaint);
                if (trim((string) $chiefComplaintText) === '') {
                    $session->error('Chief Complaint is required.');
                    $session->redirect($buildCaseUrl(['saved' => 'error'], $getWorkflowAnchor('history')));
                    break;
                }

                $case->of(false);
                $case->chief_complaint = $chiefComplaintText;
                $historyText = $sanitizer->textarea($input->post->history_present_illness);
                if ($fieldsApi->get('hpi')) {
                    $case->hpi = $historyText;
                }
                if ($fieldsApi->get('history_complaints')) {
                    $case->history_complaints = $historyText;
                }
                $saveFieldIfExists($case, 'past_medical_history', $sanitizer->textarea($input->post->past_medical_history));
                $saveFieldIfExists($case, 'past_surgical_history', $sanitizer->textarea($input->post->past_surgical_history));
                $saveFieldIfExists($case, 'drug_history_reviewed', 1);

                $comorbidityNone = (int) $sanitizer->int($input->post->comorbidity_none) === 1;
                $submittedFlags = array_values(array_unique(array_filter(array_map(function ($value) use ($sanitizer) {
                    return $sanitizer->text($value);
                }, (array) $input->post->comorbidity_flags))));
                $submittedCustomConditions = array_values(array_unique(array_filter(array_map(function ($value) use ($sanitizer) {
                    return $sanitizer->text($value);
                }, (array) $input->post->comorb_custom_condition))));
                $drugConditions = (array) $input->post->comorb_drug_condition;
                $drugNames = (array) $input->post->comorb_drug_name;
                $drugDoses = (array) $input->post->comorb_drug_dose;
                $drugHistoryNames = (array) $input->post->drug_hist_name;
                $drugHistoryDoses = (array) $input->post->drug_hist_dose;
                $drugHistoryFrequencies = (array) $input->post->drug_hist_freq;

                $submittedFlags = array_values(array_filter($submittedFlags, function ($flag) use ($knownComorbidityFlags) {
                    return in_array($flag, $knownComorbidityFlags, true) || $flag === 'Custom';
                }));
                if ($submittedCustomConditions && !in_array('Custom', $submittedFlags, true)) {
                    $submittedFlags[] = 'Custom';
                }

                if ($comorbidityNone) {
                    $saveFieldIfExists($case, 'comorbidity_none', 1);
                    $saveFieldIfExists($case, 'comorbidity_flags', []);
                    $deleteComorbidityDrugPages($case);
                    $saveFieldIfExists($case, 'comorbidities', '');
                } else {
                    $setOptionField($case, 'comorbidity_none', 0);
                    $setOptionField($case, 'comorbidity_flags', $submittedFlags);
                    $deleteComorbidityDrugPages($case);

                    $comorbidityDrugTemplate = wire('templates')->get('comorbidity-drug');
                    $legacyComorbidityRows = [];

                    foreach ($submittedFlags as $flag) {
                        if ($flag === 'Custom') {
                            continue;
                        }
                        $legacyComorbidityRows[$flag] = ['condition' => $flag, 'since' => '', 'treatment' => ''];
                    }
                    foreach ($submittedCustomConditions as $customCondition) {
                        $legacyComorbidityRows[$customCondition] = ['condition' => $customCondition, 'since' => '', 'treatment' => ''];
                    }

                    foreach ($drugConditions as $index => $conditionValue) {
                        $condition = $sanitizer->text($conditionValue);
                        $drugName = $sanitizer->text($drugNames[$index] ?? '');
                        $drugDose = $sanitizer->text($drugDoses[$index] ?? '');
                        if ($condition === '' && $drugName === '' && $drugDose === '') {
                            continue;
                        }

                        if ($condition !== '' && !in_array($condition, $knownComorbidityFlags, true) && !in_array($condition, $submittedCustomConditions, true)) {
                            $submittedCustomConditions[] = $condition;
                            if (!in_array('Custom', $submittedFlags, true)) {
                                $submittedFlags[] = 'Custom';
                            }
                            $legacyComorbidityRows[$condition] = ['condition' => $condition, 'since' => '', 'treatment' => ''];
                        }

                        if ($drugName === '') {
                            continue;
                        }

                        if ($comorbidityDrugTemplate && $comorbidityDrugTemplate->id) {
                            try {
                                $drugPage = new Page();
                                $drugPage->template = 'comorbidity-drug';
                                $drugPage->parent = $case;
                                $drugPage->name = $sanitizer->pageName(($condition ?: 'comorbidity') . '-drug-' . ($drugName ?: time()), true) ?: ('comorbidity-drug-' . time() . '-' . $index);
                                $drugPage->title = trim(($condition ?: 'Comorbidity') . ' Drug - ' . $drugName);
                                $drugPage->of(false);
                                $saveFieldIfExists($drugPage, 'comorb_condition_flag', $condition);
                                $saveFieldIfExists($drugPage, 'drug_name', $drugName);
                                $saveFieldIfExists($drugPage, 'drug_dose', $drugDose);
                                $drugPage->save();
                            } catch (\Throwable $e) {
                                wire('log')->save('case-view-errors', 'comorbidity-drug save failed: ' . $e->getMessage());
                            }
                        }

                        $legacyTreatment = trim($drugName . ($drugDose !== '' ? ' ' . $drugDose : ''));
                        if ($condition !== '') {
                            if (!isset($legacyComorbidityRows[$condition])) {
                                $legacyComorbidityRows[$condition] = ['condition' => $condition, 'since' => '', 'treatment' => ''];
                            }
                            $existingTreatment = $legacyComorbidityRows[$condition]['treatment'];
                            $legacyComorbidityRows[$condition]['treatment'] = trim($existingTreatment !== '' ? ($existingTreatment . '; ' . $legacyTreatment) : $legacyTreatment);
                        }
                    }

                    $submittedCustomConditions = array_values(array_unique(array_filter($submittedCustomConditions)));
                    if ($submittedCustomConditions && !in_array('Custom', $submittedFlags, true)) {
                        $submittedFlags[] = 'Custom';
                    }
                    $saveFieldIfExists($case, 'comorbidity_flags', $submittedFlags);
                    $saveFieldIfExists($case, 'comorbidities', $legacyComorbidityRows ? json_encode(array_values($legacyComorbidityRows)) : '');
                }

                $deleteDrugHistoryPages($case);
                $drugHistoryTemplate = wire('templates')->get('drug-history-entry');
                $legacyDrugHistoryRows = [];

                foreach ($drugHistoryNames as $index => $nameValue) {
                    $drugName = $sanitizer->text($nameValue);
                    $drugDose = $sanitizer->text($drugHistoryDoses[$index] ?? '');
                    $drugFrequency = $sanitizer->text($drugHistoryFrequencies[$index] ?? '');

                    if ($drugName === '' && $drugDose === '' && $drugFrequency === '') {
                        continue;
                    }

                    if ($drugName === '') {
                        continue;
                    }

                    if ($drugHistoryTemplate && $drugHistoryTemplate->id) {
                        try {
                            $drugPage = new Page();
                            $drugPage->template = 'drug-history-entry';
                            $drugPage->parent = $case;
                            $drugPage->name = $sanitizer->pageName(($drugName ?: 'drug-history') . '-history-' . $index, true) ?: ('drug-history-' . time() . '-' . $index);
                            $drugPage->title = trim($drugName . ' - Drug History');
                            $drugPage->of(false);
                            $saveFieldIfExists($drugPage, 'drug_name', $drugName);
                            $saveFieldIfExists($drugPage, 'drug_dose', $drugDose);
                            $saveFieldIfExists($drugPage, 'drug_frequency', $drugFrequency);
                            $drugPage->save();
                        } catch (\Throwable $e) {
                            wire('log')->save('case-view-errors', 'drug-history-entry save failed: ' . $e->getMessage());
                        }
                    }

                    $legacyDrugHistoryRows[] = trim($drugName . ($drugDose !== '' ? ' - ' . $drugDose : '') . ($drugFrequency !== '' ? ' - ' . $drugFrequency : ''));
                }

                $saveFieldIfExists($case, 'drug_history', implode("\n", $legacyDrugHistoryRows));
                try {
                    $case->save();
                } catch (\Throwable $e) {
                    wire('log')->save('case-view-errors', 'case->save() failed in history for case ' . $case->id . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                    throw $e;
                }
                $session->redirect($buildCaseUrl(['saved' => 'history'], $getWorkflowAnchor('history')));
                break;

            case 'examination':
                $case->of(false);
                $case->inspection = $sanitizer->textarea($input->post->inspection);
                $case->examination_findings = $sanitizer->textarea($input->post->examination_findings);
$case->save();
                $session->redirect($buildCaseUrl(['saved' => 'examination'], $getWorkflowAnchor('examination')));
                break;

            case 'investigation':
                $invDate = $sanitizer->date($input->post->investigation_date, 'Y-m-d');
                $invName = $sanitizer->text($input->post->investigation_name);
                $invFindings = $sanitizer->textarea($input->post->investigation_findings);
                $invInclude = (int) $sanitizer->int($input->post->include_in_discharge) === 1 ? 1 : 0;

                if (!$invDate || trim((string) $invName) === '' || trim((string) $invFindings) === '') {
                    $session->redirect($buildCaseUrl(['saved' => 'error'], $getWorkflowAnchor('investigations')));
                    break;
                }

                $investigation = new Page();
                $investigation->template = 'investigation';
                $investigation->parent = $case;
                $investigation->name = $sanitizer->pageName($invName ?: ('investigation-' . time()), true) ?: ('investigation-' . time());
                $investigation->title = $invName ?: 'Investigation';
                $investigation->of(false);
                $saveFieldIfExists($investigation, 'investigation_name', $invName);
                if ($invDate) {
                    $saveFieldIfExists($investigation, 'investigation_date', strtotime($invDate));
                }
                $saveFieldIfExists($investigation, 'investigation_findings', $invFindings);
                $saveFieldIfExists($investigation, 'include_in_discharge', $invInclude);
                $investigation->save();
                $session->redirect($buildCaseUrl(['saved' => 'investigations'], $getWorkflowAnchor('investigations')));
                break;

            case 'update_investigation':
                $investigationId = (int) $sanitizer->int($input->post->investigation_id);
                $investigation = $investigationId ? $pages->get($investigationId) : null;
                if (!$investigation || !$investigation->id || $investigation->template->name !== 'investigation' || (int) $investigation->parent_id !== (int) $case->id) {
                    $session->redirect($buildCaseUrl(['saved' => 'error'], $getWorkflowAnchor('investigations')));
                    break;
                }

                $invDate = $sanitizer->date($input->post->investigation_date, 'Y-m-d');
                $invName = $sanitizer->text($input->post->investigation_name);
                $invFindings = $sanitizer->textarea($input->post->investigation_findings);
                $invInclude = (int) $sanitizer->int($input->post->include_in_discharge) === 1 ? 1 : 0;

                if (!$invDate || trim((string) $invName) === '' || trim((string) $invFindings) === '') {
                    $session->redirect($buildCaseUrl(['saved' => 'error', 'edit_inv' => $investigationId], $getWorkflowAnchor('investigations')));
                    break;
                }

                $investigation->of(false);
                $investigation->title = $invName ?: 'Investigation';
                $saveFieldIfExists($investigation, 'investigation_name', $invName);
                $saveFieldIfExists($investigation, 'investigation_date', strtotime($invDate));
                $saveFieldIfExists($investigation, 'investigation_findings', $invFindings);
                $saveFieldIfExists($investigation, 'include_in_discharge', $invInclude);
                $investigation->save();
                $session->redirect($buildCaseUrl(['saved' => 'investigations'], $getWorkflowAnchor('investigations')));
                break;

            case 'delete_investigation':
                $investigationId = (int) $sanitizer->int($input->post->investigation_id);
                $investigation = $investigationId ? $pages->get($investigationId) : null;
                if ($investigation && $investigation->id && $investigation->template->name === 'investigation' && (int) $investigation->parent_id === (int) $case->id) {
                    $investigation->delete();
                }
                $session->redirect($buildCaseUrl(['saved' => 'investigations'], $getWorkflowAnchor('investigations')));
                break;

            case 'procedure_plan':
            case 'surgery_plan':
                $procId = (int) $sanitizer->int($input->post->proc_id);
                $procedure = $procId ? $pages->get($procId) : null;
                if (!$procedure || !$procedure->id || $procedure->template->name !== 'procedure' || (int) $procedure->parent_id !== (int) $case->id) {
                    $session->redirect($buildCaseUrl(['saved' => 'error'], $getWorkflowAnchor('ot-plan')));
                    break;
                }

                $procDate = $sanitizer->date($input->post->proc_date, 'Y-m-d');
                $procedure->of(false);
                $procedureName = trim((string) $sanitizer->text($input->post->proc_name));
                if ($procedureName !== '') {
                    $procedure->title = $procedureName;
                    $saveFieldIfExists($procedure, 'proc_name', $procedureName);
                }
                if ($procDate) {
                    $saveFieldIfExists($procedure, 'proc_date', strtotime($procDate));
                }
                $saveFieldIfExists($procedure, 'proc_time', $sanitizer->text($input->post->proc_time));
                $setOptionField($procedure, 'anesthesia_type', $sanitizer->text($input->post->anesthesia_type));
                $setOptionField($procedure, 'c_arm_required', $sanitizer->text($input->post->c_arm_required));
                $setOptionField($procedure, 'microscope_required', $sanitizer->text($input->post->microscope_required));
                $saveFieldIfExists($procedure, 'implant_details', $sanitizer->text($input->post->implant_details));
                $saveFieldIfExists($procedure, 'anesthesiologist_name', $sanitizer->text($input->post->anesthesiologist_name));
                $saveFieldIfExists($procedure, 'surgeon_name', $sanitizer->text($input->post->surgeon_name));
                $procedure->save();
                $session->redirect($buildCaseUrl(['saved' => 'procedure_plan'], $getWorkflowAnchor('ot-plan')));
                break;

            case 'delete_ot_plan':
                $procId = (int) $sanitizer->int($input->post->proc_id);
                $procedure = $procId ? $pages->get($procId) : null;
                if ($procedure && $procedure->id && $procedure->template->name === 'procedure' && (int) $procedure->parent_id === (int) $case->id) {
                    $procedure->delete();
                }
                $session->redirect($buildCaseUrl(['saved' => 'procedure-deleted'], $getWorkflowAnchor('ot-plan')));
                break;

            case 'procedure':
                $procId = (int) $sanitizer->int($input->post->proc_id);
                $procName = $sanitizer->text($input->post->proc_name);
                $procDate = $sanitizer->date($input->post->proc_date, 'Y-m-d');
                $surgeonName = $sanitizer->text($input->post->surgeon_name);

                $procedure = $procId ? $pages->get($procId) : new Page();
                if (!$procedure || !$procedure->id) {
                    $procedure = new Page();
                    $procedure->template = 'procedure';
                    $procedure->parent = $case;
                }
                $procedure->of(false);
                $procedure->title = $procName ?: 'Procedure';
                $procedure->name = $sanitizer->pageName(($procName ?: 'procedure') . '-' . ($procDate ?: date('Ymd')), true) ?: ('procedure-' . time());
                $saveFieldIfExists($procedure, 'proc_name', $procName);
                if ($procDate) {
                    $saveFieldIfExists($procedure, 'proc_date', strtotime($procDate));
                }
                $saveFieldIfExists($procedure, 'surgeon_name', $surgeonName);
                if ($procId) {
                    $procSide = $sanitizer->text($input->post->proc_side);
                    $anaesthesiaType = $sanitizer->text($input->post->anesthesia_type);
                    $cArmRequired = $sanitizer->text($input->post->c_arm_required);
                    $microscopeRequired = $sanitizer->text($input->post->microscope_required);
                    $implantDetails = $sanitizer->textarea($input->post->implant_details);
                    $saveFieldIfExists($procedure, 'procedure_side', $procSide);
                    $saveFieldIfExists($procedure, 'proc_side', $procSide);
                    $setOptionField($procedure, 'anesthesia_type', $anaesthesiaType);
                    $setOptionField($procedure, 'c_arm_required', $cArmRequired);
                    $setOptionField($procedure, 'microscope_required', $microscopeRequired);
                    $saveFieldIfExists($procedure, 'implant_details', $implantDetails);
                }
                $procedure->save();
                $session->redirect($buildCaseUrl(['saved' => 'procedure'], $getWorkflowAnchor('ot-plan')));
                break;

            case 'operation_note':
                $opNoteId = (int) $sanitizer->int($input->post->opnote_id);
                $procedureId = (int) $sanitizer->int($input->post->procedure_id);
                $procedure = $procedureId ? $pages->get($procedureId) : null;
                if (!$procedure || !$procedure->id || $procedure->template->name !== 'procedure' || (int) $procedure->parent_id !== (int) $case->id) {
                    $procedure = null;
                }
                $procedureName = $sanitizer->text($input->post->procedure_name);
                $opNote = $opNoteId ? $pages->get($opNoteId) : null;
                if (!$opNote || !$opNote->id || $opNote->template->name !== 'operation-note' || (int) $opNote->parent_id !== (int) $case->id) {
                    $opNote = null;
                }
                if (!$opNote && $procedure && $procedure->id && $fieldsApi->get('procedure_ref_id')) {
                    $opNote = $pages->get("template=operation-note, parent={$case->id}, procedure_ref_id={$procedure->id}");
                }
                if (!$opNote || !$opNote->id) {
                    $opNote = new Page();
                    $opNote->template = 'operation-note';
                    $opNote->parent = $case;
                    $opNote->name = 'new';
                }
                $stepBlocksRaw = (array) $input->post->procedure_steps_block;
                $stepBlocks = array_values(array_filter(array_map(function ($value) use ($sanitizer) {
                    return $sanitizer->textarea($value);
                }, $stepBlocksRaw)));
                $surgeryDate = $sanitizer->date($input->post->surgery_date, 'Y-m-d');
                $closureDetailsText = $sanitizer->textarea($input->post->closure_details);
                $operationNoteMeta = [
                    'surgeon_name' => $sanitizer->text($input->post->surgeon_name),
                    'assistant_name' => $sanitizer->text($input->post->assistant_name),
                    'start_time' => $sanitizer->text($input->post->start_time),
                    'end_time' => $sanitizer->text($input->post->end_time),
                    'incision' => $sanitizer->text($input->post->incision),
                    'tourniquet_time' => $sanitizer->text($input->post->tourniquet_time),
                    'template_name' => $sanitizer->text($input->post->opnote_template_name),
                ];

                $opNote->of(false);
                $resolvedOperationName = trim((string) ($procedureName !== '' ? $procedureName : ($procedure && $procedure->id ? ($procedure->proc_name ?: $procedure->title) : $formatOperationNoteName($opNote))));
                $opNote->title = trim(($resolvedOperationName !== '' ? $resolvedOperationName : 'Operation Note') . ' Note');
                $saveFieldIfExists($opNote, 'procedure_ref_id', $procedure && $procedure->id ? $procedure : null);
                if ($surgeryDate) {
                    $saveFieldIfExists($opNote, 'surgery_date', strtotime($surgeryDate));
                }
                $setOptionField($opNote, 'anesthesia_type', $sanitizer->text($input->post->opnote_anesthesia_type));
                $saveFieldIfExists($opNote, 'anesthesiologist_name', $sanitizer->text($input->post->anesthesiologist_name));
                $saveFieldIfExists($opNote, 'patient_position', $sanitizer->text($input->post->patient_position));
                $saveFieldIfExists($opNote, 'surgical_approach', $sanitizer->textarea($input->post->surgical_approach));
                if (!empty($stepBlocksRaw)) {
                    $saveFieldIfExists($opNote, 'procedure_steps', implode("\n\n", $stepBlocks));
                }
                $saveFieldIfExists($opNote, 'implants_used', $sanitizer->text($input->post->implants_used));
                $saveFieldIfExists($opNote, 'closure_details', $mergeEmbeddedMeta($closureDetailsText, $operationNoteMeta));
                $opNote->save();
                $session->redirect($buildCaseUrl(['saved' => 'operation-note'], $getWorkflowAnchor('operation-note')));
                break;

            case 'preop_print':
                $printPatientName = $patient ? $patient->title : 'Clinical Case';
                $printDiagnosis = $case->primary_diagnosis_ref && $case->primary_diagnosis_ref->id
                    ? $case->primary_diagnosis_ref->title
                    : trim((string) $case->diagnosis);
                $printProcedures = $pages->find("template=procedure, parent=$case, sort=proc_date");
                $printComorbidityNone = $fieldsApi->get('comorbidity_none') ? (bool) $case->getUnformatted('comorbidity_none') : false;
                $printComorbidityStructuredCount = wire('templates')->get('comorbidity-drug')
                    ? (int) $pages->count("template=comorbidity-drug, parent={$case->id}")
                    : 0;
                $printComorbidityLegacy = trim((string) $case->comorbidities) !== '';
                $printComorbidityHasFlags = false;
                if (!$printComorbidityNone && $fieldsApi->get('comorbidity_flags') && is_iterable($case->getUnformatted('comorbidity_flags'))) {
                    foreach ($case->getUnformatted('comorbidity_flags') as $printFlagItem) {
                        $printFlagTitle = is_object($printFlagItem) && isset($printFlagItem->title) ? trim((string) $printFlagItem->title) : trim((string) $printFlagItem);
                        if ($printFlagTitle !== '') {
                            $printComorbidityHasFlags = true;
                            break;
                        }
                    }
                }
                $printComorbidityAddressed = $printComorbidityNone || $printComorbidityStructuredCount > 0 || $printComorbidityHasFlags || $printComorbidityLegacy;

                $printReadyProcedures = [];
                foreach ($printProcedures as $printProcedure) {
                    if ($procedureHasFilledSurgeryPlan($printProcedure)) {
                        $printReadyProcedures[] = $printProcedure;
                    }
                }

                $preopMissing = [];
                if (trim((string) $printPatientName) === '') {
                    $preopMissing[] = 'Patient name';
                }
                if (trim((string) $printDiagnosis) === '') {
                    $preopMissing[] = 'Diagnosis';
                }
                if (!$printComorbidityAddressed) {
                    $preopMissing[] = 'Comorbidity status';
                }
                if (!count($printReadyProcedures ?? [])) {
                    $preopMissing[] = 'At least one procedure with a completed procedure plan';
                }

                if ($preopMissing) {
                    $session->redirect($buildCaseUrl(['saved' => 'preop-missing'], $getWorkflowAnchor('preop-print')));
                    break;
                }

                $printConsultantName = $case->consultant_ref && $case->consultant_ref->id
                    ? $case->consultant_ref->title
                    : trim((string) ($case->discharge_consultant ?: $case->admitting_unit ?: $case->doctor_name));
                $printDrugHistoryPages = wire('templates')->get('drug-history-entry')
                    ? $pages->find("template=drug-history-entry, parent={$case->id}, sort=sort")
                    : [];
                $printComorbidityDrugPages = wire('templates')->get('comorbidity-drug')
                    ? $pages->find("template=comorbidity-drug, parent={$case->id}, sort=sort")
                    : [];

                $printComorbidityLines = [];
                if ($printComorbidityNone) {
                    $printComorbidityLines[] = 'None';
                } else {
                    if ($fieldsApi->get('comorbidity_flags') && is_iterable($case->getUnformatted('comorbidity_flags'))) {
                        foreach ($case->getUnformatted('comorbidity_flags') as $flagItem) {
                            $flagTitle = is_object($flagItem) && isset($flagItem->title) ? trim((string) $flagItem->title) : trim((string) $flagItem);
                            if ($flagTitle !== '' && !in_array($flagTitle, $printComorbidityLines, true)) {
                                $printComorbidityLines[] = $flagTitle;
                            }
                        }
                    }
                    if (!$printComorbidityLines && $printComorbidityLegacy) {
                        $printComorbidityLines[] = trim((string) $case->comorbidities);
                    }
                }

                ob_start();
                ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Pre-operative Plan</title>
  <style>
    body { font-family: Arial, sans-serif; color: #111827; font-size: 12px; }
    h1 { font-size: 22px; margin: 0 0 8px; }
    h2 { font-size: 14px; margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #d1d5db; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; vertical-align: top; }
    .meta td { width: 50%; }
    .muted { color: #6b7280; }
  </style>
</head>
<body>
  <h1>Pre-operative Plan</h1>
  <div class="muted">Printed <?= date('d M Y, g:i A') ?></div>

  <h2>Admission Information</h2>
  <table class="meta">
    <tr>
      <td><strong>Patient</strong><br><?= $sanitizer->entities($printPatientName) ?></td>
      <td><strong>IP Number</strong><br><?= $sanitizer->entities((string) $case->ip_number) ?></td>
    </tr>
    <tr>
      <td><strong>Consultant</strong><br><?= $sanitizer->entities($printConsultantName ?: 'Not assigned') ?></td>
      <td><strong>Admission Date</strong><br><?= $case->getUnformatted('admitted_on') ? date('d M Y', $case->getUnformatted('admitted_on')) : 'Not recorded' ?></td>
    </tr>
  </table>

  <h2>Diagnosis</h2>
  <p><strong><?= $sanitizer->entities($printDiagnosis) ?></strong></p>
  <?php if ($hasText($case->associated_conditions)): ?>
  <p><?= nl2br($sanitizer->entities($case->associated_conditions)) ?></p>
  <?php endif; ?>

  <h2>History Snapshot</h2>
  <p><strong>Comorbidity:</strong> <?= $sanitizer->entities(implode(', ', $printComorbidityLines)) ?></p>
  <?php if (count($printComorbidityDrugPages ?? [])): ?>
  <p><strong>Comorbidity Drugs:</strong><br>
    <?php foreach ($printComorbidityDrugPages as $index => $drugPage): ?>
      <?= $index ? '<br>' : '' ?><?= $sanitizer->entities(trim(($drugPage->comorb_condition_flag ? $drugPage->comorb_condition_flag . ': ' : '') . $drugPage->drug_name . ($drugPage->drug_dose ? ' ' . $drugPage->drug_dose : ''))) ?>
    <?php endforeach; ?>
  </p>
  <?php endif; ?>
  <?php if (count($printDrugHistoryPages ?? [])): ?>
  <p><strong>Drug History:</strong><br>
    <?php foreach ($printDrugHistoryPages as $index => $drugPage): ?>
      <?= $index ? '<br>' : '' ?><?= $sanitizer->entities(trim($drugPage->drug_name . ($drugPage->drug_dose ? ' ' . $drugPage->drug_dose : '') . ($drugPage->drug_frequency ? ' ' . $drugPage->drug_frequency : ''))) ?>
    <?php endforeach; ?>
  </p>
  <?php endif; ?>

  <h2>OT Plan</h2>
  <table>
    <thead>
      <tr>
        <th>Procedure</th>
        <th>Date / Time</th>
        <th>Anesthesia</th>
        <th>C-Arm</th>
        <th>Microscope</th>
        <th>Implant Details</th>
        <th>Anesthesiologist</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($printReadyProcedures as $printProcedure): ?>
      <tr>
        <td><?= $sanitizer->entities($printProcedure->proc_name ?: $printProcedure->title) ?></td>
        <td>
          <?= $printProcedure->getUnformatted('proc_date') ? date('d M Y', $printProcedure->getUnformatted('proc_date')) : 'Not recorded' ?>
          <?php if ($fieldsApi->get('proc_time') && trim((string) $printProcedure->proc_time) !== ''): ?>
            <br><?= $sanitizer->entities($printProcedure->proc_time) ?>
          <?php endif; ?>
        </td>
        <td><?= $sanitizer->entities($getOptionTitle($printProcedure->anesthesia_type) ?: (string) $printProcedure->anesthesia_type) ?></td>
        <td><?= $sanitizer->entities($getOptionTitle($printProcedure->c_arm_required) ?: (string) $printProcedure->c_arm_required) ?></td>
        <td><?= $sanitizer->entities($getOptionTitle($printProcedure->microscope_required) ?: (string) $printProcedure->microscope_required) ?></td>
        <td><?= $sanitizer->entities((string) $printProcedure->implant_details) ?></td>
        <td><?= $sanitizer->entities((string) $printProcedure->anesthesiologist_name ?: 'Not assigned') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

</body>
</html>
                <?php
                $preopHtml = ob_get_clean();
                while (ob_get_level()) { ob_end_clean(); }
                $vendorAutoload = $config->paths->root . 'vendor/autoload.php';
                if (file_exists($vendorAutoload)) {
                    require_once $vendorAutoload;
                    $mpdf = new \Mpdf\Mpdf([
                        'margin_left' => 15, 'margin_right' => 15,
                        'margin_top' => 18, 'margin_bottom' => 18,
                        'default_font' => 'arial',
                    ]);
                    $mpdf->WriteHTML($preopHtml);
                    $mpdf->Output('PreOp_' . ($case->ip_number ?: $case->id) . '_' . date('Ymd') . '.pdf', 'I');
                    exit;
                }
                echo $preopHtml;
                exit;

            case 'hospital_course':
                $hceEditId   = (int) $sanitizer->int($input->post->course_entry_id);
                $hceDateRaw  = $sanitizer->date($input->post->course_entry_date, 'Y-m-d');
                $hceType     = $sanitizer->text($input->post->course_entry_type);
                $hceNote     = $sanitizer->textarea($input->post->course_entry_note);

                if (!$hceDateRaw || trim($hceNote) === '') {
                    $session->redirect($buildCaseUrl(['saved' => 'error'], $getWorkflowAnchor('hospital-course')));
                    break;
                }

                $hceTimestamp = strtotime($hceDateRaw);

                if ($hceEditId) {
                    // Editing an existing entry
                    $hceEntry = $pages->get($hceEditId);
                    if (!$hceEntry || !$hceEntry->id
                        || $hceEntry->template->name !== 'hospital-course-entry'
                        || (int) $hceEntry->parent_id !== (int) $case->id) {
                        $session->redirect($buildCaseUrl(['saved' => 'error'], $getWorkflowAnchor('hospital-course')));
                        break;
                    }
                } else {
                    // Creating a new entry
                    $hceEntry           = new Page();
                    $hceEntry->template = 'hospital-course-entry';
                    $hceEntry->parent   = $case;
                    $hceEntry->name     = 'hce-' . date('Ymd', $hceTimestamp) . '-' . time();
                }

                $hceEntry->of(false);
                $hceEntry->title = '[' . date('d M Y', $hceTimestamp) . '] ' . $hceType;
                if ($fieldsApi->get('hce_date')) {
                    $hceEntry->hce_date = $hceTimestamp;
                }
                $setOptionField($hceEntry, 'hce_type', $hceType);
                if ($fieldsApi->get('hce_note')) {
                    $hceEntry->hce_note = $hceNote;
                }
                $hceEntry->save();
                $session->redirect($buildCaseUrl(['saved' => 'hospital-course'], $getWorkflowAnchor('hospital-course')));
                break;

            case 'condition':
                $case->of(false);
                $saveFieldIfExists($case, 'condition_at_discharge', $sanitizer->text($input->post->general_condition));
                $saveFieldIfExists($case, 'pain_scale', max(0, min(10, (int) $sanitizer->int($input->post->pain_scale))));
                $case->save();
                $session->redirect($buildCaseUrl(['saved' => 'condition'], $getWorkflowAnchor('condition')));
                break;

            case 'medications':
                $drugs = (array) $input->post->med_drug;
                $doses = (array) $input->post->med_dose;
                $frequencies = (array) $input->post->med_frequency;
                $durations = (array) $input->post->med_duration;
                $medications = [];
                foreach ($drugs as $index => $drug) {
                    $drugValue = $sanitizer->text($drug);
                    $doseValue = $sanitizer->text($doses[$index] ?? '');
                    $frequencyValue = $sanitizer->text($frequencies[$index] ?? '');
                    $durationValue = $sanitizer->text($durations[$index] ?? '');
                    if ($drugValue === '' && $doseValue === '' && $frequencyValue === '' && $durationValue === '') {
                        continue;
                    }
                    $medications[] = [
                        'drug' => $drugValue,
                        'dose' => $doseValue,
                        'frequency' => $frequencyValue,
                        'duration' => $durationValue,
                        'continue_previous' => 0,
                    ];
                }

                $case->of(false);
                $saveFieldIfExists($case, 'medications_on_discharge', $medications ? json_encode($medications) : '');
                $case->save();
                $session->redirect($buildCaseUrl(['saved' => 'medications'], $getWorkflowAnchor('medications')));
                break;

            case 'advice':
                $adviceSectionsPayload = [
                    'subsequent_plan' => $sanitizer->textarea($input->post->subsequent_treatment_plan),
                ];

                $case->of(false);
                $saveFieldIfExists($case, 'follow_up_instructions', $mergeEmbeddedMeta($sanitizer->textarea($input->post->follow_up_instructions), $adviceSectionsPayload));
                $reviewDate = $sanitizer->date($input->post->review_date, 'Y-m-d');
                $saveFieldIfExists($case, 'review_date', $reviewDate ? strtotime($reviewDate) : null);
                $case->save();
                $session->redirect($buildCaseUrl(['saved' => 'advice'], $getWorkflowAnchor('advice')));
                break;
        }
    } catch (\Throwable $e) {
        wire('log')->save('case-view-errors', 'History save failed for case ' . ($case ? $case->id : 'null') . ' action=' . $activePostAction . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $session->error('Save failed: ' . $e->getMessage());
        $session->redirect($buildCaseUrl(['saved' => 'error'], $getWorkflowAnchor($workflowSaveModule ?: 'admission')));
    }
}

$page->of(false);
$page->title = ($case && $case->ip_number) ? ('Case View - ' . $case->ip_number) : 'Case View';

$procedures = $case && $case->id ? $pages->find("template=procedure, parent=$case, sort=proc_date") : new PageArray();
$operationNotes = $case && $case->id ? $pages->find("template=operation-note, parent=$case, sort=sort, sort=created") : new PageArray();
$investigations = $case && $case->id ? $pages->find("template=investigation, parent=$case, sort=-investigation_date, sort=-created") : new PageArray();
$courseEntries = $case && $case->id ? $pages->find("template=hospital-course-entry, parent=$case, sort=hce_date, sort=created") : new PageArray();
$courseEntryCount = $courseEntries ? count($courseEntries) : 0;
$dischargeTaggedEntries = $case && $case->id ? $pages->find("template=hospital-course-entry, parent=$case, hce_type.title=Discharge, sort=hce_date") : new PageArray();
$admittedOn = $case ? $case->getUnformatted('admitted_on') : null;
$dischargedOn = $case ? $case->getUnformatted('discharged_on') : null;
$savedModule = $sanitizer->text($input->get->saved);
$pdfMissingFields = array_values(array_filter(array_map('trim', preg_split('/\|+/', (string) $input->get->pdf_missing))));
$editModule = $sanitizer->text($input->get->edit);
$editProcedureId = (int) $sanitizer->int($input->get->edit_proc);
$editOperationProcedureId = (int) $sanitizer->int($input->get->edit_op);
$editCourseEntryId = (int) $sanitizer->int($input->get->edit_hce);
$editInvestigationId = (int) $sanitizer->int($input->get->edit_inv);

$statusRaw = $case ? $case->getUnformatted('case_status') : 0;
$statusId = $getOptionId($statusRaw);
$isLocked = $statusId === 2;

$consultantPages = $pages->find("template=consultant, sort=title");
$diagnosisPages = $pages->find("template=diagnosis|diagnosis-taxonomy, sort=title");

$getConsultantName = function () use ($case): string {
    if ($case->consultant_ref && $case->consultant_ref->id) {
        return $case->consultant_ref->title;
    }
    if (trim((string) $case->discharge_consultant) !== '') {
        return $case->discharge_consultant;
    }
    if (trim((string) $case->admitting_unit) !== '') {
        return $case->admitting_unit;
    }
    if (trim((string) $case->doctor_name) !== '') {
        return $case->doctor_name;
    }
    return 'Not assigned';
};

$consultantName = $getConsultantName();
$patientName = $patient ? $patient->title : 'Clinical Case';
$patientId = ($patient && $patient->patient_id) ? $patient->patient_id : '';
$secondaryPhone = ($patient && $fieldsApi->get('secondary_phone')) ? trim((string) $patient->secondary_phone) : '';
$bedLabel = trim((string) $case->room_bed) ?: 'Not assigned';
$ageValue = trim((string) $case->patient_age);
$ageUnitLabel = $getOptionTitle($case->age_unit) ?: 'Years';
$genderLabel = $patient ? ($getOptionTitle($patient->gender) ?: 'Not recorded') : 'Not recorded';
$ageGenderLabel = $ageValue !== '' ? ($ageValue . ' ' . $ageUnitLabel . ' / ' . $genderLabel) : $genderLabel;
$daysIn = $admittedOn ? max(0, (int) floor((time() - $admittedOn) / 86400)) : 0;
$lastSaved = $case && $case->modified ? date('d M Y, g:i A', $case->modified) : 'Not yet saved';
$roleFlags = wire('authRoleFlags') ?: [];
$isMO = !empty($roleFlags['is_medical_officer']) || !empty($roleFlags['is_admin']);
$isConsultant = !empty($roleFlags['is_consultant']) || !empty($roleFlags['is_admin']);

$secondaryDiagnosisValues = [];
if ($fieldsApi->get('secondary_diagnosis')) {
    $secondaryRaw = trim((string) $case->secondary_diagnosis);
    if ($secondaryRaw !== '') {
        $secondaryDiagnosisValues = preg_split('/[\r\n,]+/', $secondaryRaw);
        $secondaryDiagnosisValues = array_values(array_filter(array_map('trim', $secondaryDiagnosisValues)));
    }
}

$historyOfPresentIllness = '';
if ($fieldsApi->get('hpi') && trim((string) $case->hpi) !== '') {
    $historyOfPresentIllness = (string) $case->hpi;
} elseif ($fieldsApi->get('history_complaints')) {
    $historyOfPresentIllness = (string) $case->history_complaints;
}

$pastMedicalHistory = $fieldsApi->get('past_medical_history') ? (string) $case->past_medical_history : '';
$pastSurgicalHistory = $fieldsApi->get('past_surgical_history') ? (string) $case->past_surgical_history : '';
$comorbidityText = $fieldsApi->get('comorbidities') ? (string) $case->comorbidities : '';
$legacyComorbidityRows = [];
if ($comorbidityText !== '') {
    $decoded = json_decode($comorbidityText, true);
    if (is_array($decoded)) {
        foreach ($decoded as $cr) {
            if (is_array($cr)) {
                $legacyComorbidityRows[] = [
                    'condition' => trim((string) ($cr['condition'] ?? '')),
                    'since'     => trim((string) ($cr['since'] ?? '')),
                    'treatment' => trim((string) ($cr['treatment'] ?? '')),
                ];
            }
        }
    }
}

$comorbidityNone = $fieldsApi->get('comorbidity_none') ? (bool) $case->getUnformatted('comorbidity_none') : false;
$comorbidityFlags = $fieldsApi->get('comorbidity_flags') ? $getOptionTitles($case->getUnformatted('comorbidity_flags')) : [];
$comorbidityCustomConditions = [];
$comorbidityDrugRows = [];

$comorbidityDrugPages = wire('templates')->get('comorbidity-drug')
    ? $pages->find("template=comorbidity-drug, parent={$case->id}, sort=sort")
    : new PageArray();

foreach ($comorbidityDrugPages as $drugPage) {
    $condition = trim((string) $drugPage->get('comorb_condition_flag'));
    $drugName = trim((string) $drugPage->get('drug_name'));
    $drugDose = trim((string) $drugPage->get('drug_dose'));

    if ($condition === '' && $drugName === '' && $drugDose === '') {
        continue;
    }

    if ($condition !== '' && !in_array($condition, $knownComorbidityFlags, true)) {
        $comorbidityCustomConditions[] = $condition;
    }

    $comorbidityDrugRows[] = [
        'condition' => $condition,
        'drug_name' => $drugName,
        'drug_dose' => $drugDose,
    ];
}

if (!$comorbidityFlags && !$comorbidityDrugRows && $legacyComorbidityRows) {
    foreach ($legacyComorbidityRows as $legacyRow) {
        $condition = trim((string) $legacyRow['condition']);
        $treatment = trim((string) $legacyRow['treatment']);
        if ($condition === '' && $treatment === '') {
            continue;
        }

        $flag = $normalizeComorbidityFlag($condition);
        $resolvedCondition = $flag !== '' ? $flag : $condition;
        if ($flag !== '') {
            $comorbidityFlags[] = $flag;
        } elseif ($resolvedCondition !== '') {
            $comorbidityFlags[] = 'Custom';
            $comorbidityCustomConditions[] = $resolvedCondition;
        }

        if ($treatment !== '') {
            [$drugName, $drugDose] = $splitComorbidityTreatment($treatment);
            $comorbidityDrugRows[] = [
                'condition' => $resolvedCondition,
                'drug_name' => $drugName,
                'drug_dose' => $drugDose,
            ];
        }
    }
}

$comorbidityFlags = array_values(array_unique(array_filter($comorbidityFlags)));
$comorbidityCustomConditions = array_values(array_unique(array_filter($comorbidityCustomConditions)));

$orderedComorbidityFlags = [];
foreach ($knownComorbidityFlags as $flag) {
    if (in_array($flag, $comorbidityFlags, true)) {
        $orderedComorbidityFlags[] = $flag;
    }
}
if (in_array('Custom', $comorbidityFlags, true) || $comorbidityCustomConditions) {
    $orderedComorbidityFlags[] = 'Custom';
}
$comorbidityFlags = $orderedComorbidityFlags;

$comorbidityDrugCounts = [];
foreach ($comorbidityDrugRows as $drugRow) {
    $condition = trim((string) $drugRow['condition']);
    if ($condition === '' || trim((string) $drugRow['drug_name']) === '') {
        continue;
    }
    $comorbidityDrugCounts[$condition] = ($comorbidityDrugCounts[$condition] ?? 0) + 1;
}

$legacyComorbidityHasData = false;
foreach ($legacyComorbidityRows as $legacyRow) {
    if ($legacyRow['condition'] !== '' || $legacyRow['since'] !== '' || $legacyRow['treatment'] !== '') {
        $legacyComorbidityHasData = true;
        break;
    }
}

$comorbidityStructuredCount = $comorbidityDrugPages ? count($comorbidityDrugPages) : 0;
$comorbidityExplicitNone = $fieldsApi->get('comorbidity_none') && (bool) $case->getUnformatted('comorbidity_none');
$comorbidityAddressed = $comorbidityExplicitNone || $comorbidityStructuredCount > 0 || (bool) $comorbidityFlags;
if (!$comorbidityAddressed && !$fieldsApi->get('comorbidity_none') && $legacyComorbidityHasData) {
    $comorbidityAddressed = true;
}

$legacyDrugHistoryText = $fieldsApi->get('drug_history') ? trim((string) $case->drug_history) : '';
$drugHistoryReviewed = $fieldsApi->get('drug_history_reviewed') ? (bool) $case->getUnformatted('drug_history_reviewed') : false;
$drugHistoryEntries = [];

$drugHistoryPages = wire('templates')->get('drug-history-entry')
    ? $pages->find("template=drug-history-entry, parent={$case->id}, sort=sort")
    : new PageArray();

foreach ($drugHistoryPages as $drugHistoryPage) {
    $drugHistoryEntries[] = [
        'drug_name' => trim((string) $drugHistoryPage->get('drug_name')),
        'drug_dose' => trim((string) $drugHistoryPage->get('drug_dose')),
        'drug_frequency' => trim((string) $drugHistoryPage->get('drug_frequency')),
    ];
}

if (!$drugHistoryEntries && $legacyDrugHistoryText !== '') {
    $legacyLines = preg_split('/\r\n|\r|\n/', $legacyDrugHistoryText);
    foreach ($legacyLines as $legacyLine) {
        $legacyLine = trim((string) $legacyLine);
        if ($legacyLine === '') {
            continue;
        }

        $parts = array_map('trim', explode(' - ', $legacyLine));
        $drugHistoryEntries[] = [
            'drug_name' => $parts[0] ?? $legacyLine,
            'drug_dose' => $parts[1] ?? '',
            'drug_frequency' => $parts[2] ?? '',
        ];
    }
}

if (!$drugHistoryEntries) {
    $drugHistoryEntries[] = ['drug_name' => '', 'drug_dose' => '', 'drug_frequency' => ''];
}

$procedureSideMap = [1 => 'Right', 2 => 'Left', 3 => 'Bilateral', 0 => 'N/A'];
$getProcedureSide = function ($procedure) use ($fieldsApi, $getOptionId, $getOptionTitle, $procedureSideMap): string {
    foreach (['procedure_side', 'proc_side'] as $fieldName) {
        if (!$fieldsApi->get($fieldName)) {
            continue;
        }
        $title = $getOptionTitle($procedure->$fieldName);
        if ($title !== '') {
            return $title;
        }
        $id = $getOptionId($procedure->getUnformatted($fieldName));
        if (isset($procedureSideMap[$id])) {
            return $procedureSideMap[$id];
        }
        $raw = trim((string) $procedure->$fieldName);
        if ($raw !== '') {
            return $raw;
        }
    }
    return 'N/A';
};

$getSurgeonName = function ($procedure) {
    return trim((string) ($procedure->surgeon_name ?: $procedure->doctor_name ?: 'Not recorded'));
};

$operationNotesByProcedure = [];
$standaloneOperationNotes = [];
foreach ($operationNotes as $operationNotePage) {
    $procedureRef = $fieldsApi->get('procedure_ref_id') ? $operationNotePage->getUnformatted('procedure_ref_id') : null;
    $procedureRefId = $procedureRef instanceof Page ? (int) $procedureRef->id : (int) $procedureRef;
    if ($procedureRefId > 0) {
        $linkedProcedure = $pages->get($procedureRefId);
        if ($linkedProcedure && $linkedProcedure->id && $linkedProcedure->template->name === 'procedure' && (int) $linkedProcedure->parent_id === (int) $case->id) {
            $operationNotesByProcedure[$linkedProcedure->id] = $operationNotePage;
            continue;
        }
    }
    $standaloneOperationNotes[] = $operationNotePage;
}
foreach ($procedures as $procedure) {
    if (!isset($operationNotesByProcedure[$procedure->id])) {
        $legacyOperationNote = $pages->get("template=operation-note, parent=$procedure");
        if ($legacyOperationNote && $legacyOperationNote->id) {
            $operationNotesByProcedure[$procedure->id] = $legacyOperationNote;
        }
    }
}

$operationNoteMetaById = [];
foreach ($operationNotesByProcedure as $operationNotePage) {
    $parsedClosurePayload = $extractEmbeddedMeta($operationNotePage && $operationNotePage->id ? (string) $operationNotePage->closure_details : '');
    $operationNoteMetaById[$operationNotePage->id] = $parsedClosurePayload['meta'];
    if ($operationNotePage && $operationNotePage->id) {
        $operationNotePage->set('closure_details', $parsedClosurePayload['text']);
    }
}
foreach ($standaloneOperationNotes as $operationNotePage) {
    $parsedClosurePayload = $extractEmbeddedMeta($operationNotePage && $operationNotePage->id ? (string) $operationNotePage->closure_details : '');
    $operationNoteMetaById[$operationNotePage->id] = $parsedClosurePayload['meta'];
    if ($operationNotePage && $operationNotePage->id) {
        $operationNotePage->set('closure_details', $parsedClosurePayload['text']);
    }
}

$operationNoteEntries = [];
foreach ($procedures as $procedure) {
    $operationNoteEntries[] = [
        'procedure' => $procedure,
        'note' => $operationNotesByProcedure[$procedure->id] ?? null,
    ];
}
foreach ($standaloneOperationNotes as $operationNotePage) {
    $operationNoteEntries[] = [
        'procedure' => null,
        'note' => $operationNotePage,
    ];
}
if (empty($operationNoteEntries) || !count($operationNoteEntries)) {
    $operationNoteEntries[] = ['procedure' => null, 'note' => null];
}

$firstOperationNote = null;
foreach ($operationNoteEntries as $operationNoteEntry) {
    $operationNote = $operationNoteEntry['note'] ?? null;
    if ($operationNote && $operationNote->id) {
        $firstOperationNote = $operationNote;
        break;
    }
}

$investigationCount = $investigations ? count($investigations) : 0;
$generalConditionLabel = $getOptionTitle($case->general_condition) ?: trim((string) $case->condition_at_discharge);
$conditionPayload = $extractEmbeddedMeta((string) $case->pain_status);
$painStatusText = $conditionPayload['text'];
$conditionMeta = $conditionPayload['meta'];
$fitForDischargeLabel = '';
$painScoreValue = $fieldsApi->get('pain_scale') ? (string) ((int) $case->getUnformatted('pain_scale')) : '';

$followUpPayload = $extractEmbeddedMeta((string) $case->follow_up_instructions);
$followUpInstructionsText = $followUpPayload['text'];
$followUpMeta = $followUpPayload['meta'];
$adviceRestrictionsText = '';
$advicePhysiotherapyText = '';
$subsequentTreatmentPlanText = trim((string) ($followUpMeta['subsequent_plan'] ?? ''));

$surgeryPlanReadyProcedureCount = 0;
foreach ($procedures as $procedure) {
    if ($procedureHasFilledSurgeryPlan($procedure)) {
        $surgeryPlanReadyProcedureCount++;
    }
}

$rawMedications = trim((string) $case->medications_on_discharge);
$medicationRows = [];
$medicationsAutoFilled = false;
$medicationDuplicateCount = 0;
if ($rawMedications !== '') {
    $decodedMeds = json_decode($rawMedications, true);
    if (is_array($decodedMeds)) {
        foreach ($decodedMeds as $row) {
            if (!is_array($row)) {
                continue;
            }
            $medicationRows[] = [
                'drug' => trim((string) ($row['drug'] ?? '')),
                'dose' => trim((string) ($row['dose'] ?? '')),
                'frequency' => trim((string) ($row['frequency'] ?? '')),
                'duration' => trim((string) ($row['duration'] ?? '')),
                'source' => trim((string) ($row['source'] ?? 'manual')),
                'is_duplicate' => false,
            ];
        }
    } else {
        foreach (preg_split('/\r\n|\r|\n/', $rawMedications) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $medicationRows[] = ['drug' => $line, 'dose' => '', 'frequency' => '', 'duration' => '', 'source' => 'manual', 'is_duplicate' => false];
            }
        }
    }
} else {
    $medicationRows = $buildMedicationAutoFillRows($case);
    $medicationsAutoFilled = count($medicationRows ?? []) > 0;
}

$medicationNameCounts = [];
foreach ($medicationRows as $row) {
    $key = strtolower(trim((string) ($row['drug'] ?? '')));
    if ($key === '') {
        continue;
    }
    $medicationNameCounts[$key] = ($medicationNameCounts[$key] ?? 0) + 1;
}
foreach ($medicationRows as $index => $row) {
    $key = strtolower(trim((string) ($row['drug'] ?? '')));
    $isDuplicate = $key !== '' && ($medicationNameCounts[$key] ?? 0) > 1;
    $medicationRows[$index]['is_duplicate'] = $isDuplicate;
}
$medicationDuplicateCount = count(array_filter($medicationRows, function ($row) {
    return !empty($row['is_duplicate']);
}));

if (!$medicationRows) {
    $medicationRows[] = ['drug' => '', 'dose' => '', 'frequency' => '', 'duration' => '', 'notes' => '', 'source' => 'manual', 'is_duplicate' => false];
}

$attachmentGroups = [
    'clinical-photos' => [
        'label' => 'Examination / Operation Media',
        'field' => 'clinical_images',
        'files' => ($fieldsApi->get('clinical_images') && $case->clinical_images) ? $case->clinical_images : [],
    ],
    'investigation-reports' => [
        'label' => 'Investigation Reports',
        'field' => 'investigation_files',
        'files' => ($fieldsApi->get('investigation_files') && $case->investigation_files) ? $case->investigation_files : [],
    ],
];
$investigationReportFiles = $attachmentGroups['investigation-reports']['files'] ?? [];

$templateSettings = $loadKeyValueSettings('admin_discharge_settings');
$templateSettings += [
    'show_diagnosis' => '1',
    'show_history' => '1',
    'show_examination' => '1',
    'show_investigations' => '1',
    'show_operation_note' => '1',
    'show_hospital_course' => '1',
    'show_medications' => '1',
    'show_advice' => '1',
    'show_admission' => '1',
    'show_ot_plan' => '1',
    'show_condition' => '1',
    'pdf_header' => 'Ganga Hospital',
    'pdf_footer' => 'Clinical Registry - Confidential',
    'pdf_font_size' => '10',
    'pdf_margin' => '12',
];

$caseTemplateCatalog = [];
try {
    $templateRows = $database->query("SELECT id, type, field_key, title, body, status FROM admin_discharge_templates WHERE status='active' ORDER BY type, field_key, title")->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($templateRows as $templateRow) {
        $caseTemplateCatalog[] = [
            'id' => (int) $templateRow['id'],
            'type' => $normalizeTemplateType($templateRow['type']),
            'field_key' => trim((string) ($templateRow['field_key'] ?? '')),
            'title' => (string) $templateRow['title'],
            'body' => (string) $templateRow['body'],
        ];
    }
} catch (\Throwable $e) {
    $caseTemplateCatalog = [];
}

$statusLabelMap = [
    1 => 'Active',
    2 => 'Discharged',
    3 => 'Follow-up',
    4 => 'Cancelled',
];
$statusClassMap = [
    1 => 'badge badge--case-active',
    2 => 'badge badge--dc-finalized',
    3 => 'badge badge--patient-followup',
    4 => 'badge badge--proc-complication',
];
$statusLabel = $statusLabelMap[$statusId] ?? 'Unknown';
$statusClass = $statusClassMap[$statusId] ?? 'badge badge--dc-draft';

$workflowModuleStatuses = [
    'admission' => ['complete' => $hasText($patientName) && $hasText($case->room_bed), 'issues' => (!$hasText($patientName) || !$hasText($case->room_bed)) ? 1 : 0],
    'diagnosis' => ['complete' => ($case->primary_diagnosis_ref && $case->primary_diagnosis_ref->id) || $hasText($case->diagnosis), 'issues' => (($case->primary_diagnosis_ref && $case->primary_diagnosis_ref->id) || $hasText($case->diagnosis)) ? 0 : 1],
    'history' => ['complete' => $hasText($case->chief_complaint) && $comorbidityAddressed, 'issues' => ($hasText($case->chief_complaint) && $comorbidityAddressed) ? 0 : 1],
    'examination' => ['complete' => $hasText($case->inspection), 'issues' => $hasText($case->inspection) ? 0 : 1],
    'investigations' => ['complete' => $investigationCount > 0, 'issues' => 0],
'ot-plan' => ['complete' => $surgeryPlanReadyProcedureCount > 0, 'issues' => $surgeryPlanReadyProcedureCount > 0 ? 0 : (($procedures && count($procedures)) ? 1 : 0)],
            'operation-note' => ['complete' => (bool) ($firstOperationNote && $firstOperationNote->id), 'issues' => ($procedures && count($procedures)) && !($firstOperationNote && $firstOperationNote->id) ? 1 : 0],
    'hospital-course' => ['complete' => $courseEntryCount > 0, 'issues' => $courseEntryCount > 0 ? 0 : 0],
    'condition' => ['complete' => $hasText($generalConditionLabel), 'issues' => $hasText($generalConditionLabel) ? 0 : 1],
    'medications' => ['complete' => $hasText($case->medications_on_discharge), 'issues' => $hasText($case->medications_on_discharge) ? 0 : 1],
    'advice' => ['complete' => $hasText($subsequentTreatmentPlanText), 'issues' => $hasText($subsequentTreatmentPlanText) ? 0 : 1],
];
$canViewModule = function (string $moduleName) use ($user, $userCaseModulePerms): bool {
    if ($user->isSuperuser()) return true;
    if (!isset($userCaseModulePerms[$moduleName])) return true;
    return (int) ($userCaseModulePerms[$moduleName]['can_view'] ?? 1) === 1;
};

$stepDefinitions = [];
foreach ($workflow as $workflowStep) {
    $moduleName = (string) $workflowStep['module_name'];
    if (!$canViewModule($moduleName)) continue;
    $anchor = $getWorkflowAnchor($moduleName);
    $moduleStatus = $workflowModuleStatuses[$moduleName] ?? ['complete' => false, 'issues' => 0];
    $stepDefinitions[$anchor] = [
        'label' => $workflowStep['label'] ?: ($workflowDefinitions[$moduleName]['label'] ?? ucfirst($moduleName)),
        'complete' => (bool) $moduleStatus['complete'],
        'issues' => (int) $moduleStatus['issues'],
        'module_name' => $moduleName,
    ];
}
$completedSteps = count(array_filter($stepDefinitions, function ($step) {
    return !empty($step['complete']);
}));

$diagnosisLabel = $case->primary_diagnosis_ref && $case->primary_diagnosis_ref->id
    ? $case->primary_diagnosis_ref->title
    : trim((string) $case->diagnosis);
$preopMissingRequirements = [];
if (trim((string) $patientName) === '') {
    $preopMissingRequirements[] = 'Patient name';
}
if (trim((string) $diagnosisLabel) === '') {
    $preopMissingRequirements[] = 'Diagnosis';
}
if (!$comorbidityAddressed) {
    $preopMissingRequirements[] = 'Comorbidity status';
}
if ($surgeryPlanReadyProcedureCount < 1) {
    $preopMissingRequirements[] = 'At least one completed procedure plan';
}
$canGeneratePreopPdf = count($preopMissingRequirements ?? []) === 0;
$dischargeBlockers = $getDischargeBlockers($case, $patient);
$canGeneratePdf = count($dischargeBlockers ?? []) === 0;
$preopPrintAnchor = $getWorkflowAnchor('preop-print');
if (isset($stepDefinitions[$preopPrintAnchor])) {
    $stepDefinitions[$preopPrintAnchor]['complete'] = $canGeneratePreopPdf;
    $stepDefinitions[$preopPrintAnchor]['issues'] = count($preopMissingRequirements ?? []);
}
$dischargeEngineAnchor = $getWorkflowAnchor('discharge-engine');
if (isset($stepDefinitions[$dischargeEngineAnchor])) {
    $stepDefinitions[$dischargeEngineAnchor]['complete'] = $statusId === 2;
    $stepDefinitions[$dischargeEngineAnchor]['issues'] = count($dischargeBlockers ?? []);
}
$completedSteps = count(array_filter($stepDefinitions, function ($step) {
    return !empty($step['complete']);
}));
$dischargeBlockerSet = array_fill_keys($dischargeBlockers, true);
$proceduresRequireDischargeValidation = ($procedures && count($procedures)) > 0;
$dischargeReadinessChecks = [
    [
        'label' => 'Patient name set',
        'ok' => !isset($dischargeBlockerSet['Patient name is required']),
        'message' => 'Patient name is required',
    ],
    [
        'label' => 'Discharge date set',
        'ok' => !isset($dischargeBlockerSet['Discharge date must be set']),
        'message' => 'Discharge date must be set',
    ],
    [
        'label' => 'Diagnosis recorded',
        'ok' => !isset($dischargeBlockerSet['Primary diagnosis is required']),
        'message' => 'Primary diagnosis is required',
    ],
    [
        'label' => 'Comorbidity status recorded',
        'ok' => !isset($dischargeBlockerSet["Comorbidity status must be explicitly recorded (select 'None' or add conditions)"]),
        'message' => "Comorbidity status must be explicitly recorded (select 'None' or add conditions)",
    ],
    [
        'label' => 'Procedure plans complete',
        'ok' => !$proceduresRequireDischargeValidation || !count(array_filter($dischargeBlockers, function ($item) {
            return strpos($item, 'Procedure plan is incomplete for: ') === 0;
        })),
        'message' => $proceduresRequireDischargeValidation ? 'All procedures need procedure date and anesthesia type' : 'No procedures recorded, so procedure plan completion is not required',
    ],
    [
        'label' => 'Medications on discharge set',
        'ok' => !isset($dischargeBlockerSet['Medications on discharge must be provided']),
        'message' => 'Medications on discharge must be provided',
    ],
    [
        'label' => 'Condition at discharge set',
        'ok' => !isset($dischargeBlockerSet['Condition at discharge must be recorded']),
        'message' => 'Condition at discharge must be recorded',
    ],
    [
        'label' => 'Fit for discharge set',
        'ok' => !isset($dischargeBlockerSet['Fit for discharge must be recorded']),
        'message' => 'Fit for discharge must be recorded',
    ],
    [
        'label' => 'Pain score set',
        'ok' => !isset($dischargeBlockerSet['Pain score must be recorded']),
        'message' => 'Pain score must be recorded',
    ],
    [
        'label' => 'Advice on discharge set',
        'ok' => !isset($dischargeBlockerSet['Advice on discharge must be filled']),
        'message' => 'Advice on discharge must be filled',
    ],
    [
        'label' => 'Subsequent treatment plan set',
        'ok' => !isset($dischargeBlockerSet['Subsequent treatment plan must be filled']),
        'message' => 'Subsequent treatment plan must be filled',
    ],
];

$savedToastMap = [
    'admission' => ['type' => 'success', 'title' => 'Admission updated', 'message' => 'Admission details were saved successfully.'],
    'diagnosis' => ['type' => 'success', 'title' => 'Diagnosis updated', 'message' => 'Diagnosis details were saved successfully.'],
    'history' => ['type' => 'success', 'title' => 'History updated', 'message' => 'History details were saved successfully.'],
    'examination' => ['type' => 'success', 'title' => 'Examination updated', 'message' => 'Examination details were saved successfully.'],
    'investigations' => ['type' => 'success', 'title' => 'Investigation added', 'message' => 'The investigation entry was created successfully.'],
    'procedure_plan' => ['type' => 'success', 'title' => 'Procedure plan saved', 'message' => 'Procedure plan details were saved successfully.'],
    'procedure' => ['type' => 'success', 'title' => 'Procedure saved', 'message' => 'Procedure details were saved successfully.'],
    'procedure-deleted' => ['type' => 'success', 'title' => 'Procedure deleted', 'message' => 'The procedure was removed from the case.'],
    'operation-note' => ['type' => 'success', 'title' => 'Operation note saved', 'message' => 'Operation note details were saved successfully.'],
    'hospital-course' => ['type' => 'success', 'title' => 'Course entry saved', 'message' => 'The hospital course entry was saved successfully.'],
    'hce-deleted' => ['type' => 'success', 'title' => 'Entry removed', 'message' => 'The hospital course entry was deleted.'],
    'condition' => ['type' => 'success', 'title' => 'Condition saved', 'message' => 'Discharge condition details were saved successfully.'],
    'medications' => ['type' => 'success', 'title' => 'Medications saved', 'message' => 'Discharge medications were saved successfully.'],
    'advice' => ['type' => 'success', 'title' => 'Advice saved', 'message' => 'Advice and follow-up details were saved successfully.'],
    'attachment' => ['type' => 'success', 'title' => 'Attachment removed', 'message' => 'The attachment was removed successfully.'],
    'draft' => ['type' => 'success', 'title' => 'Draft saved', 'message' => 'The case draft timestamp was updated.'],
    'lock' => ['type' => 'success', 'title' => 'Case locked', 'message' => 'The case has been marked as discharged.'],
    'lock-blocked' => ['type' => 'warning', 'title' => 'Lock blocked', 'message' => 'Resolve the discharge readiness blockers before locking the case.'],
    'preop-missing' => ['type' => 'warning', 'title' => 'Pre-op document blocked', 'message' => 'Complete the required OT Plan and prerequisite fields before generating the pre-op document.'],
    'pdf-missing' => ['type' => 'warning', 'title' => 'Discharge PDF blocked', 'message' => 'Complete the missing sections before generating the discharge PDF.'],
    'error' => ['type' => 'error', 'title' => 'Save failed', 'message' => 'Something went wrong while saving. Please try again.'],
];
?>
<style>
.case-module__required-star{color:#ef4444;font-size:11px;line-height:1;vertical-align:middle;}
.case-progress__required-star{color:#ef4444;font-size:10px;margin-left:4px;vertical-align:middle;}
.field__required{color:#ef4444;margin-left:3px;font-size:12px;}
</style>
<div id="breadcrumb">
  <nav class="app-breadcrumb-strip" aria-label="Breadcrumb">
    <a href="/dashboard/">&larr; Back to Dashboard</a>
    <span aria-hidden="true">&rsaquo;</span>
    <span><?php echo $sanitizer->entities($patientName); ?></span>
  </nav>
</div>

<div id="content">
  <?php if (isset($savedToastMap[$savedModule])): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.AppToast) {
        window.AppToast.show(<?php echo json_encode($savedToastMap[$savedModule]); ?>);
      }
    });
  </script>
  <?php endif; ?>

  <?php
  $ruleAlerts   = array_values(array_filter($triggeredRules, fn($r) => in_array($r['action_type'], ['flag_alert', 'notify_user'], true)));
  $ruleRequired = array_values(array_filter($triggeredRules, fn($r) => $r['action_type'] === 'require_field'));
  if ($ruleAlerts || $ruleRequired): ?>
  <div style="padding:12px 24px 4px;">
    <?php foreach ($ruleAlerts as $ra): ?>
    <div style="display:flex;align-items:flex-start;gap:12px;background:#451a03;border:1px solid #c2410c;border-radius:8px;padding:12px 16px;margin-bottom:8px;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#fb923c" stroke-width="2" style="flex-shrink:0;margin-top:2px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <div>
        <div style="font-weight:600;color:#fb923c;font-size:13px;margin-bottom:2px;">Clinical Alert</div>
        <div style="font-size:13px;color:#fed7aa;"><?= $sanitizer->entities($ra['action_value'] ?: ('Rule triggered: ' . $ra['condition_field'] . ' ' . $ra['operator'] . ' ' . $ra['condition_value'])) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php foreach ($ruleRequired as $rr): ?>
    <div style="display:flex;align-items:flex-start;gap:12px;background:#1c1917;border:1px solid #854d0e;border-radius:8px;padding:12px 16px;margin-bottom:8px;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#facc15" stroke-width="2" style="flex-shrink:0;margin-top:2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div>
        <div style="font-weight:600;color:#facc15;font-size:13px;margin-bottom:2px;">Required Field</div>
        <div style="font-size:13px;color:#fef9c3;"><?php
          $ruleFieldLabel = $rr['action_value'] ?: $rr['condition_field'];
          echo $sanitizer->entities('"' . $ruleFieldLabel . '" must be completed (condition: ' . $rr['condition_field'] . ' ' . $rr['operator'] . ' ' . $rr['condition_value'] . ')');
        ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (in_array($savedModule, ['pdf-missing', 'lock-blocked'], true) && $pdfMissingFields): ?>
  <section class="card case-module">
    <div class="card__header">
      <div class="card__title-group">
        <span class="badge badge--proc-complication"><?= $savedModule === 'lock-blocked' ? 'Lock blocked' : 'PDF blocked' ?></span>
        <h2 class="card__title">Resolve These Discharge Blockers</h2>
      </div>
    </div>
    <div class="card__body">
      <div class="alert-warning">
        <ul>
          <?php foreach ($pdfMissingFields as $missingField): ?>
          <li><?= $sanitizer->entities($missingField) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <form method="post" class="case-save-form" hidden>
    <?= $session->CSRF->renderInput() ?>
    <input type="hidden" name="action" value="draft" />
  </form>

  <form method="post" class="case-confirm-form" hidden>
    <?= $session->CSRF->renderInput() ?>
    <input type="hidden" name="action" value="" />
    <input type="hidden" name="proc_id" value="" />
    <input type="hidden" name="investigation_id" value="" />
    <input type="hidden" name="zone" value="" />
    <input type="hidden" name="file_name" value="" />
  </form>

  <?php
    $headerProcedure = ($procedures && count($procedures)) ? $procedures->first() : null;
    $headerProcedureName = $headerProcedure ? trim((string) ($headerProcedure->proc_name ?: $headerProcedure->title)) : '';
    $headerProcedureDate = $headerProcedure && $headerProcedure->getUnformatted('proc_date') ? date('d M Y', $headerProcedure->getUnformatted('proc_date')) : '';
  ?>
  <div class="case-hero">
    <div class="case-hero__main">
      <div class="case-hero__identity">
        <div class="layout-row layout-row--gap-2 layout-row--align-center">
          <h1 class="t-page-heading"><?php echo $sanitizer->entities($patientName); ?></h1>
          <?php if ($isLocked): ?>
          <span class="badge badge--dc-finalized">Discharged</span>
          <?php endif; ?>
        </div>
        <p class="t-body"><?php echo $patientId ? $sanitizer->entities($patientId) : 'Patient ID pending'; ?> | <?php echo $ageGenderLabel ? $sanitizer->entities($ageGenderLabel) : 'Age / sex pending'; ?></p>
      </div>
      <div class="case-hero__summary-grid">
        <div><strong>Bed</strong><span><?php echo $sanitizer->entities($bedLabel); ?></span></div>
        <div><strong>Consultant</strong><span><?php echo $sanitizer->entities($consultantName); ?></span></div>
        <div><strong>Admitted</strong><span><?php echo $admittedOn ? date('d M Y', $admittedOn) : 'Not recorded'; ?></span></div>
        <div><strong>Diagnosis</strong><span><?php echo $hasText($diagnosisLabel) ? $sanitizer->entities($diagnosisLabel) : 'Auto after diagnosis'; ?></span></div>
        <div><strong>Surgery Date</strong><span><?php echo $headerProcedureDate !== '' ? $sanitizer->entities($headerProcedureDate) : 'Auto after surgery plan'; ?></span></div>
        <div><strong>Surgery Name</strong><span><?php echo $headerProcedureName !== '' ? $sanitizer->entities($headerProcedureName) : 'Auto after surgery plan'; ?></span></div>
      </div>
    </div>
    <div class="case-hero__status">
      <div class="case-hero__actions">
        <?php if ($isLocked): ?>
        <button class="btn btn--neutral" type="button" data-action="generate_final_pdf" onclick="window.open('/case-view/?id=<?php echo (int)$case->id; ?>&pdf=1', '_blank')">Download PDF</button>
        <button class="btn btn--neutral" type="button" onclick="window.print()">Print</button>
        <?php else: ?>
        <button class="btn btn--neutral" type="button" data-draft-save data-action="save_draft">Save Draft</button>
        <button class="btn btn--neutral" type="button" data-mode-toggle data-action="preview_mode">Preview</button>
        <?php if ($canGeneratePdf): ?>
        <button class="btn btn-discharge" type="button" data-action="generate_final_pdf" onclick="window.open('/case-view/?id=<?php echo (int)$case->id; ?>&pdf=1', '_blank')">Generate</button>
        <?php else: ?>
        <button class="btn btn--neutral" type="button" data-action="generate_final_pdf" disabled title="Resolve the discharge readiness blockers in Module 11 to generate discharge">Generate</button>
        <?php endif; ?>
        <?php if ($isConsultant): ?>
        <button class="btn btn--destructive" type="button" data-action="lock_case" data-confirm-action="lock_case" data-confirm-title="Lock case" data-confirm-message="This will mark the case as Discharged. This cannot be undone.">Lock Case</button>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script id="case-template-catalog" type="application/json"><?= json_encode($caseTemplateCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <script id="case-workflow-config" type="application/json"><?php
    $workflowUiConfig = [];
    foreach ($workflow as $workflowStep) {
        $workflowUiConfig[$workflowStep['module_name']] = $workflowStep['fields_config_normalized'] ?? ['fields' => [], 'actions' => []];
    }
    echo json_encode($workflowUiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
  ?></script>
  <script id="case-perm-config" type="application/json"><?= json_encode($userCaseModulePerms, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

  <div class="page-body">
    <?php $workflowRenderContext = get_defined_vars(); ?>
    <div class="case-layout" data-case-id="<?php echo (int) $case->id; ?>">
      <aside class="case-sidebar">
        <section class="card case-progress">
          <div class="card__header card__header--borderless">
            <div class="card__title-group">
              <span class="t-micro-heading">Workflow Tracker</span>
              <p class="card__subtitle"><span data-progress-count><?php echo $completedSteps; ?>/<?php echo count($stepDefinitions); ?> ready</span></p>
            </div>
          </div>
          <div class="card__body">
            <nav aria-label="Workflow progress">
              <ul class="case-progress__list">
                <?php foreach ($stepDefinitions as $stepAnchor => $step): ?>
                <?php $stepIsMandatory = !empty($workflowByModuleName[$step['module_name']]['is_mandatory']); ?>
                <li class="case-progress__item<?php echo $step['complete'] ? ' is-complete' : ($step['issues'] ? ' is-warning' : ''); ?>" data-step="<?php echo $sanitizer->entities($stepAnchor); ?>">
                  <a class="case-progress__link" href="#<?php echo $sanitizer->entities($stepAnchor); ?>">
                    <span class="case-progress__number"><?php echo $step['complete'] ? '✔' : ($step['issues'] ? '!' : '○'); ?></span>
                    <span class="case-progress__content">
                      <span class="case-progress__title"><?php echo $sanitizer->entities($step['label']); ?><?php if ($stepIsMandatory): ?><span class="case-progress__required-star" aria-label="Required" title="Mandatory module">★</span><?php endif; ?></span>
                      <span class="case-progress__indicator"><?php echo $step['issues'] ? '(' . (int) $step['issues'] . ')' : '(0)'; ?></span>
                    </span>
                  </a>
                </li>
                <?php endforeach; ?>
              </ul>
            </nav>
          </div>
        </section>

        <section class="card">
          <div class="card__header card__header--borderless">
            <div class="card__title-group">
              <span class="t-micro-heading">Attachments</span>
              <p class="card__subtitle">Exam, investigation, and treatment files linked to this case.</p>
            </div>
          </div>
          <div class="card__body layout-stack layout-stack--gap-3">
            <?php $hasAttachments = false; ?>
            <?php foreach ($attachmentGroups as $zone => $group): ?>
              <?php $groupFiles = is_countable($group['files']) ? $group['files'] : []; ?>
              <?php if (count($groupFiles ?? [])): $hasAttachments = true; ?>
              <div class="layout-stack layout-stack--gap-2">
                <div class="t-meta"><?php echo $sanitizer->entities($group['label']); ?></div>
                <?php foreach ($groupFiles as $file): ?>
                <div class="case-attachment-row">
                  <div class="case-attachment-row__preview">
                    <?php if (strpos((string) $file->mime, 'image/') === 0): ?>
                    <img src="<?php echo $file->size(64, 64)->url; ?>" alt="" />
                    <?php else: ?>
                    <i data-lucide="file-text" aria-hidden="true"></i>
                    <?php endif; ?>
                  </div>
                  <div class="case-attachment-row__content">
                    <div class="case-attachment-row__title"><?php echo $sanitizer->entities($file->basename); ?></div>
                    <div class="case-attachment-row__meta"><?php echo number_format((float) $file->filesize / 1024, 1); ?> KB</div>
                  </div>
                  <button class="btn btn--icon btn--destructive" type="button" data-confirm-action="delete_attachment" data-confirm-title="Delete attachment" data-confirm-message="Remove this attachment from the case?" data-zone="<?php echo $zone; ?>" data-file-name="<?php echo $sanitizer->entities($file->basename); ?>">
                    <i data-lucide="trash-2" aria-hidden="true"></i>
                  </button>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$hasAttachments): ?>
            <p class="t-meta">No files attached yet.</p>
            <?php endif; ?>
            <button class="btn btn--neutral btn--full" type="button" data-modal-trigger="attachments-modal">Upload files</button>
          </div>
        </section>
      </aside>

      <div class="case-workspace">
        <?php foreach ($workflow as $workflowIndex => $workflowStep): ?>
          <?php
            $mn = (string) $workflowStep['module_name'];
            if (!$canViewModule($mn)) continue;
            $moduleOutput = $renderWorkflowModule($workflowStep, $workflowIndex + 1);
            $moduleAnchor = $getWorkflowAnchor($workflowStep['module_name']);
            if (!empty($stepDefinitions[$moduleAnchor]['complete'])) {
                $moduleOutput = preg_replace(
                    '/(<section class="card case-module)([^"]*")/',
                    '$1 is-complete$2',
                    $moduleOutput,
                    1
                );
            }
            echo $moduleOutput;
          ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="modal modal--md" data-modal="confirm-action-modal" aria-hidden="true">
    <div class="modal__dialog">
      <div class="modal__header">
        <h2 class="modal__title" data-confirm-title>Confirm action</h2>
        <button class="btn btn--icon" type="button" data-modal-close aria-label="Close">
          <i data-lucide="x" aria-hidden="true"></i>
        </button>
      </div>
      <div class="modal__body">
        <p class="t-body" data-confirm-message>Please confirm this action.</p>
      </div>
      <div class="modal__footer">
        <button class="btn btn--neutral" type="button" data-modal-close>Cancel</button>
        <button class="btn btn--destructive" type="button" data-confirm-submit>Confirm</button>
      </div>
    </div>
  </div>

  <div class="modal modal--lg" data-modal="template-picker-modal" aria-hidden="true">
    <div class="modal__dialog">
      <div class="modal__header">
        <h2 class="modal__title" data-template-picker-title>Templates</h2>
        <button class="btn btn--icon" type="button" data-modal-close aria-label="Close">
          <i data-lucide="x" aria-hidden="true"></i>
        </button>
      </div>
      <div class="modal__body layout-stack layout-stack--gap-3">
        <p class="t-meta" data-template-picker-empty hidden>No templates found for this section yet.</p>
        <div class="layout-stack layout-stack--gap-2" data-template-picker-list></div>
      </div>
      <div class="modal__footer">
        <button class="btn btn--neutral" type="button" data-modal-close>Close</button>
      </div>
    </div>
  </div>

  <div class="modal modal--lg" data-modal="attachments-modal" aria-hidden="true">
    <div class="modal__dialog">
      <div class="modal__header">
        <h2 class="modal__title">Upload Attachments</h2>
        <button class="btn btn--icon" type="button" data-modal-close aria-label="Close">
          <i data-lucide="x" aria-hidden="true"></i>
        </button>
      </div>
      <div class="modal__body layout-stack layout-stack--gap-4">
        <?php foreach ($attachmentGroups as $zone => $group): ?>
        <div class="case-upload-zone" data-upload-zone="<?php echo $zone; ?>">
          <i data-lucide="upload-cloud" aria-hidden="true"></i>
          <div class="case-upload-zone__title"><?php echo $sanitizer->entities($group['label']); ?></div>
          <div class="t-meta">Click to upload or drag and drop</div>
          <input type="file" accept="<?php echo $zone === 'clinical-photos' ? 'image/*' : '.pdf,.doc,.docx,image/*'; ?>" multiple />
          <div class="case-upload-zone__results"></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="modal__footer">
        <button class="btn btn--neutral" type="button" data-modal-close>Close</button>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      function normalizeWorkflowKey(value) {
        return String(value || '').replace(/\[\]$/, '');
      }

      function safeQuerySelector(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
          return window.CSS.escape(value);
        }
        return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
      }

      function parseInlineJson(scriptId, fallback) {
        try {
          var node = document.getElementById(scriptId);
          return JSON.parse(node ? node.textContent : '');
        } catch (error) {
          return fallback;
        }
      }

      function findFieldContainer(field) {
        if (!field) return null;
        return field.closest('.field, .field-group, .case-repeat-row, .case-chip-detail, .layout-row, .layout-stack, td, tr') || field;
      }

      function applyFieldState(field, config) {
        if (!field || !config) return;

        var container = findFieldContainer(field);
        var isVisible = config.visible !== false;
        if (container) {
          container.hidden = !isVisible;
        } else {
          field.hidden = !isVisible;
        }
        if (!isVisible) {
          field.disabled = true;
          field.required = false;
          return;
        }

        var isEditable = config.editable !== false;
        field.disabled = false;
        if ('readOnly' in field) {
          field.readOnly = !isEditable;
        }
        if (!('readOnly' in field) || field.tagName === 'SELECT') {
          field.disabled = !isEditable;
        }
        if (config.mandatory) {
          field.required = true;
          field.setAttribute('aria-required', 'true');
        } else {
          field.required = false;
          field.removeAttribute('aria-required');
        }
      }

      function applyActionState(actionNode, config) {
        if (!actionNode || !config) return;
        var isVisible = config.visible !== false;
        actionNode.hidden = !isVisible;
        if (!isVisible) {
          actionNode.disabled = true;
          return;
        }
        var isEnabled = config.enabled !== false;
        actionNode.disabled = !isEnabled;
        if (isEnabled) {
          actionNode.removeAttribute('aria-disabled');
        } else {
          actionNode.setAttribute('aria-disabled', 'true');
        }
      }

      function applyWorkflowControl() {
        var workflowConfig = parseInlineJson('case-workflow-config', {});
        document.querySelectorAll('[data-module-name]').forEach(function (moduleSection) {
          var moduleName = moduleSection.getAttribute('data-module-name') || '';
          var moduleConfig = workflowConfig[moduleName] || {};
          var fieldsConfig = moduleConfig.fields || {};
          var actionsConfig = moduleConfig.actions || {};

          Object.keys(fieldsConfig).forEach(function (fieldKey) {
            var normalizedKey = normalizeWorkflowKey(fieldKey);
            var selector = '[data-field="' + safeQuerySelector(normalizedKey) + '"], [name="' + safeQuerySelector(normalizedKey) + '"], [name="' + safeQuerySelector(normalizedKey + '[]') + '"]';
            moduleSection.querySelectorAll(selector).forEach(function (field) {
              applyFieldState(field, fieldsConfig[fieldKey]);
            });
          });

          var actionScope = moduleName === 'discharge-engine' ? document : moduleSection;
          Object.keys(actionsConfig).forEach(function (actionKey) {
            actionScope.querySelectorAll('[data-action="' + safeQuerySelector(actionKey) + '"]').forEach(function (actionNode) {
              applyActionState(actionNode, actionsConfig[actionKey]);
            });
          });
        });
      }

      applyWorkflowControl();

      // ── Permission enforcement: view-only / hidden modules ────────────────────
      (function applyPermissionControl() {
        var perms = parseInlineJson('case-perm-config', {});
        if (!Object.keys(perms).length) return;

        document.querySelectorAll('[data-module-name]').forEach(function (moduleSection) {
          var moduleName = moduleSection.getAttribute('data-module-name') || '';
          var p = perms[moduleName];
          if (!p) return;

          var canView = parseInt(p.can_view, 10) === 1;
          var canEdit = parseInt(p.can_edit, 10) === 1;

          if (!canView) {
            moduleSection.style.display = 'none';
            var sidebarLink = document.querySelector('[data-step="' + moduleName + '"]') ||
              document.querySelector('a.case-progress__link[href="#' + moduleSection.id + '"]');
            if (sidebarLink) {
              var sidebarItem = sidebarLink.closest('li.case-progress__item');
              if (sidebarItem) sidebarItem.style.display = 'none';
            }
            return;
          }

          if (canEdit) return;

          // Disable all form controls
          moduleSection.querySelectorAll('input, textarea, select').forEach(function (el) {
            if (el.type === 'hidden' || el.type === 'submit') return;
            el.disabled = true;
            el.style.opacity = '0.6';
            el.style.cursor = 'not-allowed';
          });

          // Hide all save buttons
          moduleSection.querySelectorAll('button[type="submit"], button[data-action^="save_"]').forEach(function (btn) {
            btn.style.display = 'none';
          });

          // Show view-only badge in the module header
          var header = moduleSection.querySelector('.card__header');
          if (header && !header.querySelector('.badge--view-only')) {
            var badge = document.createElement('span');
            badge.className = 'badge badge--gray badge--view-only';
            badge.textContent = 'View Only';
            badge.style.cssText = 'margin-left:8px;font-size:10px;';
            var titleEl = header.querySelector('.card__title, h2, h3');
            if (titleEl) titleEl.appendChild(badge);
            else header.appendChild(badge);
          }
        });
      }());

      document.querySelectorAll('[data-secondary-add]').forEach(function (button) {
        button.addEventListener('click', function () {
          var container = document.querySelector(button.getAttribute('data-secondary-add'));
          if (!container) return;
          var template = container.querySelector('[data-secondary-template]');
          if (!template) return;
          var clone = template.cloneNode(true);
          clone.removeAttribute('data-secondary-template');
          clone.querySelectorAll('input').forEach(function (input) {
            input.value = '';
          });
          container.appendChild(clone);
        });
      });

      document.addEventListener('click', function (event) {
        var removeSecondary = event.target.closest('[data-secondary-remove]');
        if (removeSecondary) {
          var row = removeSecondary.closest('.layout-row');
          var list = row ? row.parentElement : null;
          if (row && list && list.children.length > 1) {
            row.remove();
          }
        }
      });

      document.addEventListener('click', function (event) {
        var removeDrugHistory = event.target.closest('[data-drug-history-remove]');
        if (removeDrugHistory) {
          var row = removeDrugHistory.closest('.case-repeat-row');
          var list = row ? row.parentElement : null;
          if (row && list && list.querySelectorAll('.case-repeat-row').length > 1) {
            row.remove();
          } else if (row) {
            row.querySelectorAll('input').forEach(function (input) {
              input.value = '';
            });
          }
        }
      });

      document.querySelectorAll('[data-drug-history-add]').forEach(function (button) {
        button.addEventListener('click', function () {
          var list = document.querySelector(button.getAttribute('data-drug-history-add'));
          if (!list) return;
          var template = list.querySelector('[data-drug-history-template]');
          if (!template) return;
          var clone = template.cloneNode(true);
          clone.removeAttribute('data-drug-history-template');
          clone.querySelectorAll('input').forEach(function (input) {
            input.value = '';
          });
          list.appendChild(clone);
        });
      });

      function escapeHtml(value) {
        return String(value || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      }

      function parseJsonAttribute(node, attributeName, fallback) {
        try {
          return JSON.parse(node.getAttribute(attributeName) || '');
        } catch (error) {
          return fallback;
        }
      }

      document.querySelectorAll('[data-comorbidity-widget]').forEach(function (widget) {
        var knownFlags = parseJsonAttribute(widget, 'data-known-flags', []);
        var initialFlags = parseJsonAttribute(widget, 'data-selected-flags', []);
        var initialCustomConditions = parseJsonAttribute(widget, 'data-custom-conditions', []);
        var initialDrugs = parseJsonAttribute(widget, 'data-drugs', []);
        var chipList = widget.querySelector('[data-comorbidity-chip-list]');
        var customChipList = widget.querySelector('[data-comorbidity-custom-chip-list]');
        var flagsInputs = widget.querySelector('[data-comorbidity-flags-inputs]');
        var customInputs = widget.querySelector('[data-comorbidity-custom-inputs]');
        var noneInput = widget.querySelector('[data-comorbidity-none-input]');
        var detailList = widget.querySelector('[data-comorbidity-detail-list]');
        var customTrigger = widget.querySelector('[data-comorbidity-custom-trigger]');
        var customInputWrap = widget.querySelector('[data-comorbidity-custom-input]');
        var customTextInput = widget.querySelector('[data-comorbidity-custom-text]');
        var customAddButton = widget.querySelector('[data-add-custom-comorbidity]');
        var emptyState = widget.querySelector('[data-comorbidity-empty]');
        var nextRowId = 0;

        function nextId() {
          nextRowId += 1;
          return 'comorb-row-' + nextRowId;
        }

        var state = {
          none: widget.getAttribute('data-none') === '1',
          selectedFlags: knownFlags.filter(function (flag) {
            return initialFlags.indexOf(flag) !== -1;
          }),
          customConditions: initialCustomConditions.filter(Boolean),
          drugRows: initialDrugs.map(function (row) {
            return {
              id: nextId(),
              condition: (row && row.condition ? String(row.condition) : '').trim(),
              drug_name: (row && row.drug_name ? String(row.drug_name) : '').trim(),
              drug_dose: (row && row.drug_dose ? String(row.drug_dose) : '').trim()
            };
          })
        };

        if (!state.none && !state.selectedFlags.length && !state.customConditions.length && !state.drugRows.length) {
          state.none = true;
        }
        if (state.selectedFlags.length || state.customConditions.length || state.drugRows.length) {
          state.none = false;
        }

        function getActiveConditions() {
          return state.selectedFlags.concat(state.customConditions);
        }

        function hasCondition(condition) {
          return getActiveConditions().indexOf(condition) !== -1;
        }

        function drugCountFor(condition) {
          return state.drugRows.filter(function (row) {
            return row.condition === condition && row.drug_name.trim() !== '';
          }).length;
        }

        function ensureAtLeastOneRow(condition) {
          var hasAnyRow = state.drugRows.some(function (row) {
            return row.condition === condition;
          });
          if (!hasAnyRow) {
            state.drugRows.push({
              id: nextId(),
              condition: condition,
              drug_name: '',
              drug_dose: ''
            });
          }
        }

        function removeCondition(condition) {
          state.selectedFlags = state.selectedFlags.filter(function (flag) {
            return flag !== condition;
          });
          state.customConditions = state.customConditions.filter(function (customCondition) {
            return customCondition !== condition;
          });
          state.drugRows = state.drugRows.filter(function (row) {
            return row.condition !== condition;
          });
          if (!getActiveConditions().length) {
            state.none = true;
          }
        }

        function syncHiddenInputs() {
          noneInput.value = state.none ? '1' : '0';
          flagsInputs.innerHTML = '';
          customInputs.innerHTML = '';

          if (state.none) {
            return;
          }

          var submittedFlags = state.selectedFlags.slice();
          if (state.customConditions.length) {
            submittedFlags.push('Custom');
          }

          submittedFlags.forEach(function (flag) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'comorbidity_flags[]';
            input.value = flag;
            flagsInputs.appendChild(input);
          });

          state.customConditions.forEach(function (condition) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'comorb_custom_condition[]';
            input.value = condition;
            customInputs.appendChild(input);
          });
        }

        function renderChips() {
          chipList.querySelectorAll('[data-comorbidity-toggle]').forEach(function (chip) {
            var key = chip.getAttribute('data-comorbidity-toggle');
            var isSelected = key === 'None' ? state.none : (!state.none && state.selectedFlags.indexOf(key) !== -1);
            chip.classList.toggle('is-selected', isSelected);
            if (key !== 'None') {
              var count = drugCountFor(key);
              chip.textContent = count ? key + ' (' + count + ' drugs)' : key;
            }
          });

          if (customTrigger) {
            customTrigger.classList.toggle('is-selected', !state.none && state.customConditions.length > 0);
          }

          customChipList.innerHTML = '';
          state.customConditions.forEach(function (condition) {
            var count = drugCountFor(condition);
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'case-chip is-selected';
            chip.setAttribute('data-comorbidity-custom-chip', condition);
            chip.textContent = count ? condition + ' (' + count + ' drugs)' : condition;
            customChipList.appendChild(chip);
          });
        }

        function renderDrugRows() {
          var activeConditions = getActiveConditions();
          detailList.innerHTML = '';
          emptyState.hidden = !state.none;

          if (state.none || !activeConditions.length) {
            return;
          }

          activeConditions.forEach(function (condition) {
            var count = drugCountFor(condition);
            var conditionRows = state.drugRows.filter(function (row) {
              return row.condition === condition;
            });

            if (!conditionRows.length) {
              ensureAtLeastOneRow(condition);
              conditionRows = state.drugRows.filter(function (row) {
                return row.condition === condition;
              });
            }

            var section = document.createElement('div');
            section.className = 'case-chip-detail';
            section.setAttribute('data-comorbidity-detail', condition);

            var rowsHtml = conditionRows.map(function (row) {
              return '' +
                '<div class="case-comorbidity__drug-row" data-comorbidity-row="' + escapeHtml(row.id) + '">' +
                  '<input type="hidden" name="comorb_drug_condition[]" value="' + escapeHtml(condition) + '">' +
                  '<div class="case-comorbidity__drug-grid">' +
                    '<input class="input" type="text" name="comorb_drug_name[]" value="' + escapeHtml(row.drug_name) + '" placeholder="Drug Name" data-comorbidity-drug-name>' +
                    '<input class="input" type="text" name="comorb_drug_dose[]" value="' + escapeHtml(row.drug_dose) + '" placeholder="Dose" data-comorbidity-drug-dose>' +
                    '<button class="btn btn--icon btn--destructive" type="button" data-comorbidity-remove-row="' + escapeHtml(row.id) + '" aria-label="Remove drug row">' +
                      '<i data-lucide="trash-2" aria-hidden="true"></i>' +
                    '</button>' +
                  '</div>' +
                '</div>';
            }).join('');

            section.innerHTML = '' +
              '<div class="layout-row layout-row--between case-comorbidity__detail-head">' +
                '<div class="field__label">' + escapeHtml(count ? condition + ' (' + count + ' drugs)' : condition) + '</div>' +
                '<button class="btn btn--neutral btn--sm" type="button" data-comorbidity-add-drug="' + escapeHtml(condition) + '">+ Add Another Drug</button>' +
              '</div>' +
              '<div class="layout-stack layout-stack--gap-2">' + rowsHtml + '</div>';

            detailList.appendChild(section);
          });

          if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons({
              attrs: {
                'stroke-width': 1.75
              }
            });
          }
        }

        function rerender() {
          syncHiddenInputs();
          renderChips();
          renderDrugRows();
        }

        chipList.addEventListener('click', function (event) {
          var chip = event.target.closest('[data-comorbidity-toggle]');
          if (!chip) return;

          var key = chip.getAttribute('data-comorbidity-toggle');
          if (key === 'None') {
            state.none = true;
            state.selectedFlags = [];
            state.customConditions = [];
            state.drugRows = [];
            rerender();
            return;
          }

          state.none = false;
          if (state.selectedFlags.indexOf(key) === -1) {
            state.selectedFlags.push(key);
            ensureAtLeastOneRow(key);
          } else {
            removeCondition(key);
          }
          rerender();
        });

        if (customTrigger && customInputWrap) {
          customTrigger.addEventListener('click', function () {
            customInputWrap.hidden = !customInputWrap.hidden;
            if (!customInputWrap.hidden && customTextInput) {
              customTextInput.focus();
            }
          });
        }

        function addCustomCondition() {
          if (!customTextInput) return;
          var value = customTextInput.value.trim();
          if (!value) return;
          if (knownFlags.indexOf(value) !== -1 || hasCondition(value)) {
            customTextInput.value = '';
            customInputWrap.hidden = true;
            return;
          }
          state.none = false;
          state.customConditions.push(value);
          ensureAtLeastOneRow(value);
          customTextInput.value = '';
          customInputWrap.hidden = true;
          rerender();
        }

        if (customAddButton) {
          customAddButton.addEventListener('click', addCustomCondition);
        }

        if (customTextInput) {
          customTextInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
              event.preventDefault();
              addCustomCondition();
            }
          });
        }

        customChipList.addEventListener('click', function (event) {
          var chip = event.target.closest('[data-comorbidity-custom-chip]');
          if (!chip) return;
          removeCondition(chip.getAttribute('data-comorbidity-custom-chip'));
          rerender();
        });

        detailList.addEventListener('click', function (event) {
          var addButton = event.target.closest('[data-comorbidity-add-drug]');
          if (addButton) {
            var condition = addButton.getAttribute('data-comorbidity-add-drug');
            state.drugRows.push({
              id: nextId(),
              condition: condition,
              drug_name: '',
              drug_dose: ''
            });
            rerender();
            return;
          }

          var removeButton = event.target.closest('[data-comorbidity-remove-row]');
          if (removeButton) {
            var rowId = removeButton.getAttribute('data-comorbidity-remove-row');
            var row = state.drugRows.find(function (item) {
              return item.id === rowId;
            });
            if (!row) return;
            var condition = row.condition;
            state.drugRows = state.drugRows.filter(function (item) {
              return item.id !== rowId;
            });
            if (hasCondition(condition) && !state.drugRows.some(function (item) { return item.condition === condition; })) {
              ensureAtLeastOneRow(condition);
            }
            rerender();
          }
        });

        detailList.addEventListener('input', function (event) {
          var rowElement = event.target.closest('[data-comorbidity-row]');
          if (!rowElement) return;
          var rowId = rowElement.getAttribute('data-comorbidity-row');
          var row = state.drugRows.find(function (item) {
            return item.id === rowId;
          });
          if (!row) return;

          if (event.target.hasAttribute('data-comorbidity-drug-name')) {
            row.drug_name = event.target.value;
          }
          if (event.target.hasAttribute('data-comorbidity-drug-dose')) {
            row.drug_dose = event.target.value;
          }
          renderChips();
        });

        rerender();
      });

      document.querySelectorAll('[data-investigation-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
          var detail = document.querySelector(button.getAttribute('data-investigation-toggle'));
          if (!detail) return;
          detail.hidden = !detail.hidden;
        });
      });

      ['investigation', 'procedure'].forEach(function (type) {
        var trigger = document.querySelector('[data-inline-' + type + '-trigger]');
        var form = document.querySelector('[data-inline-' + type + '-form]');
        if (trigger && form) {
          trigger.addEventListener('click', function () {
            form.hidden = !form.hidden;
            if (!form.hidden) {
              var firstInput = form.querySelector('input, select, textarea');
              if (firstInput) firstInput.focus();
            }
          });
        }
      });

      function updateMedicationDuplicateState(list) {
        if (!list) return;
        var counts = {};
        list.querySelectorAll('.case-repeat-row').forEach(function (row) {
          var drugInput = row.querySelector('input[name="med_drug[]"]');
          var key = drugInput ? drugInput.value.trim().toLowerCase() : '';
          if (!key) return;
          counts[key] = (counts[key] || 0) + 1;
        });
        list.querySelectorAll('.case-repeat-row').forEach(function (row) {
          var drugInput = row.querySelector('input[name="med_drug[]"]');
          var key = drugInput ? drugInput.value.trim().toLowerCase() : '';
          var duplicate = !!(key && counts[key] > 1);
          row.classList.toggle('case-repeat-row--duplicate', duplicate);
          row.setAttribute('data-duplicate-row', duplicate ? '1' : '0');
          var note = row.querySelector('.case-duplicate-note');
          if (note) {
            note.hidden = !duplicate;
          }
        });
      }

      function populateMedicationList(list, rows) {
        if (!list) return;
        var template = list.querySelector('[data-medication-template]');
        if (!template) return;

        var normalizedRows = Array.isArray(rows) && rows.length ? rows : [{
          drug: '',
          dose: '',
          frequency: '',
          duration: '',
          notes: '',
          is_duplicate: false
        }];

        Array.from(list.querySelectorAll('.case-repeat-row')).forEach(function (row, index) {
          if (index > 0) {
            row.remove();
          }
        });

        var firstRow = list.querySelector('[data-medication-template]');
        if (!firstRow) {
          firstRow = template.cloneNode(true);
          firstRow.setAttribute('data-medication-template', '');
          list.appendChild(firstRow);
        }

        normalizedRows.forEach(function (rowData, index) {
          var row = index === 0 ? firstRow : template.cloneNode(true);
          if (index > 0) {
            row.removeAttribute('data-medication-template');
          }
          var drugInput = row.querySelector('input[name="med_drug[]"]');
          var doseInput = row.querySelector('input[name="med_dose[]"]');
          var frequencyInput = row.querySelector('input[name="med_frequency[]"]');
          var durationInput = row.querySelector('input[name="med_duration[]"]');
          var notesInput = row.querySelector('input[name="med_notes[]"]');
          if (drugInput) drugInput.value = rowData.drug || '';
          if (doseInput) doseInput.value = rowData.dose || '';
          if (frequencyInput) frequencyInput.value = rowData.frequency || '';
          if (durationInput) durationInput.value = rowData.duration || '';
          if (notesInput) notesInput.value = rowData.notes || '';
          row.classList.toggle('case-repeat-row--duplicate', !!rowData.is_duplicate);
          row.setAttribute('data-duplicate-row', rowData.is_duplicate ? '1' : '0');
          var note = row.querySelector('.case-duplicate-note');
          if (note) {
            note.hidden = !rowData.is_duplicate;
          }
          if (index > 0) {
            list.appendChild(row);
          }
        });

        updateMedicationDuplicateState(list);
      }

      document.querySelectorAll('[data-medication-add]').forEach(function (button) {
        button.addEventListener('click', function () {
          var list = document.querySelector(button.getAttribute('data-medication-add'));
          if (!list) return;
          var template = list.querySelector('[data-medication-template]');
          if (!template) return;
          var clone = template.cloneNode(true);
          clone.removeAttribute('data-medication-template');
          clone.querySelectorAll('input').forEach(function (input) {
            input.value = '';
          });
          list.appendChild(clone);
          updateMedicationDuplicateState(list);
        });
      });

      document.addEventListener('click', function (event) {
        var removeMedication = event.target.closest('[data-medication-remove]');
        if (removeMedication) {
          var row = removeMedication.closest('.case-repeat-row');
          var list = row ? row.parentElement : null;
          if (row && list && list.children.length > 1) {
            row.remove();
            updateMedicationDuplicateState(list);
          }
        }
      });

      document.addEventListener('input', function (event) {
        if (event.target.matches('input[name="med_drug[]"]')) {
          var list = event.target.closest('#medication-list');
          updateMedicationDuplicateState(list);
        }
      });

      document.querySelectorAll('[data-medication-refill]').forEach(function (button) {
        button.addEventListener('click', function () {
          var endpoint = button.getAttribute('data-endpoint');
          var caseId = button.getAttribute('data-case-id');
          var target = document.querySelector(button.getAttribute('data-target'));
          if (!endpoint || !caseId || !target) return;

          button.disabled = true;
          fetch(endpoint + '?case_id=' + encodeURIComponent(caseId), {
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
            .then(function (response) {
              return response.ok ? response.json() : Promise.reject(new Error('Request failed'));
            })
            .then(function (payload) {
              if (!payload || payload.success !== true) {
                throw new Error('Invalid payload');
              }
              populateMedicationList(target, payload.rows || []);
              if (window.AppToast) {
                window.AppToast.show({
                  type: 'success',
                  title: 'Medications refilled',
                  message: 'Medication rows were refreshed from comorbidity drugs and drug history.'
                });
              }
            })
            .catch(function () {
              if (window.AppToast) {
                window.AppToast.show({
                  type: 'error',
                  title: 'Refill failed',
                  message: 'Unable to refresh medication rows right now.'
                });
              }
            })
            .finally(function () {
              button.disabled = false;
            });
        });
      });

      document.querySelectorAll('[data-step-block-add]').forEach(function (button) {
        button.addEventListener('click', function () {
          var list = document.querySelector(button.getAttribute('data-step-block-add'));
          if (!list) return;
          var template = list.querySelector('[data-step-block-template]');
          if (!template) return;
          var clone = template.cloneNode(true);
          clone.removeAttribute('data-step-block-template');
          clone.hidden = false;
          clone.querySelectorAll('textarea').forEach(function (input) {
            input.value = '';
          });
          list.appendChild(clone);
        });
      });

      document.addEventListener('click', function (event) {
        var removeBlock = event.target.closest('[data-step-block-remove]');
        if (removeBlock) {
          var block = removeBlock.closest('.case-step-block');
          var list = block ? block.parentElement : null;
          if (block && list && list.querySelectorAll('.case-step-block').length > 1) {
            block.remove();
          }
        }
      });

      var caseLayout = document.querySelector('.case-layout');
      var modeToggle = document.querySelector('[data-mode-toggle]');
      if (caseLayout && modeToggle) {
        modeToggle.addEventListener('click', function () {
          caseLayout.classList.toggle('preview-mode');
          modeToggle.textContent = caseLayout.classList.contains('preview-mode') ? 'Edit Mode' : 'Preview Mode';
        });
      }

      document.querySelectorAll('[data-preview-toggle]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
          var target = document.querySelector(checkbox.getAttribute('data-preview-toggle'));
          if (!target) return;
          target.hidden = !checkbox.checked;
        });
      });

      var draftButton = document.querySelector('[data-draft-save]');
      var draftForm = document.querySelector('.case-save-form');
      if (draftButton && draftForm) {
        draftButton.addEventListener('click', function () {
          draftForm.submit();
        });
      }

      var confirmForm = document.querySelector('.case-confirm-form');
      var confirmButton = document.querySelector('[data-confirm-submit]');
      document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-confirm-action]');
        if (!trigger || !confirmForm) return;
        confirmForm.querySelector('[name="action"]').value = trigger.getAttribute('data-confirm-action') || '';
        confirmForm.querySelector('[name="proc_id"]').value = trigger.getAttribute('data-proc-id') || '';
        confirmForm.querySelector('[name="investigation_id"]').value = trigger.getAttribute('data-investigation-id') || '';
        confirmForm.querySelector('[name="zone"]').value = trigger.getAttribute('data-zone') || '';
        confirmForm.querySelector('[name="file_name"]').value = trigger.getAttribute('data-file-name') || '';
        var title = document.querySelector('[data-confirm-title]');
        var message = document.querySelector('[data-confirm-message]');
        if (title) title.textContent = trigger.getAttribute('data-confirm-title') || 'Confirm action';
        if (message) message.textContent = trigger.getAttribute('data-confirm-message') || 'Please confirm this action.';
        if (window.AppModal) {
          window.AppModal.open('confirm-action-modal');
        }
      });
      if (confirmButton && confirmForm) {
        confirmButton.addEventListener('click', function () {
          confirmForm.submit();
        });
      }

      document.addEventListener('click', function (event) {
        var templateButton = event.target.closest('[data-template-fill]');
        if (templateButton) {
          var targetName = templateButton.getAttribute('data-template-fill');
          var target = document.querySelector('[name="' + targetName + '"]');
          if (target) {
            target.value = templateButton.getAttribute('data-template-value') || '';
            target.dispatchEvent(new Event('input', { bubbles: true }));
          }
        }

        var opnoteTemplateButton = event.target.closest('[data-opnote-template]');
        if (opnoteTemplateButton) {
          var wrapper = opnoteTemplateButton.closest('form');
          if (!wrapper) return;
          var templateType = opnoteTemplateButton.getAttribute('data-opnote-template');
          var presets = {
            release: {
              surgical_approach: 'Standard sterile preparation and draping done. Appropriate approach taken and layers opened carefully.',
              closure_details: 'Hemostasis secured and wound closed in layers with sterile dressing applied.'
            },
            fixation: {
              surgical_approach: 'Standard sterile preparation and draping done. Fracture site exposed with careful soft tissue handling.',
              closure_details: 'Irrigation done, hemostasis secured, and layered closure completed with dressing applied.'
            }
          };
          var preset = presets[templateType];
          if (!preset) return;
          Object.keys(preset).forEach(function (fieldName) {
            var field = wrapper.querySelector('[name="' + fieldName + '"]');
            if (field) {
              field.value = preset[fieldName];
            }
          });
          var hiddenTemplateName = wrapper.querySelector('[name="opnote_template_name"]');
          if (hiddenTemplateName) {
            hiddenTemplateName.value = templateType;
          }
        }
      });

      // Template picker — open modal filtered by section type
      document.addEventListener('click', function (event) {
        var addBtn = event.target.closest('[data-template-add]');
        if (!addBtn) return;
        var type = addBtn.getAttribute('data-template-add');
        var targetSelector = addBtn.getAttribute('data-template-target') || '';
        var catalog = [];
        try {
          var catalogEl = document.getElementById('case-template-catalog');
          catalog = JSON.parse(catalogEl ? catalogEl.textContent : '[]');
        } catch (e) { catalog = []; }
        var typeList = String(type || '').split(',').map(function (value) {
          return value.trim().replace(/_/g, '-').toLowerCase();
        }).filter(Boolean);
        var fieldKey = addBtn.getAttribute('data-template-field') || '';
        if (!fieldKey) {
          var inferredMatch = targetSelector.match(/\[name=['"]([\w_]+)['"]\]/);
          fieldKey = inferredMatch ? inferredMatch[1] : '';
        }
        var filtered = catalog.filter(function (t) {
          return typeList.indexOf(String(t.type || '').replace(/_/g, '-').toLowerCase()) !== -1
            && String(t.field_key || '') === fieldKey;
        });
        var list = document.querySelector('[data-template-picker-list]');
        var empty = document.querySelector('[data-template-picker-empty]');
        var title = document.querySelector('[data-template-picker-title]');
        if (title) title.textContent = fieldKey ? ('Templates - ' + fieldKey) : 'Templates';
        if (list) {
          list.innerHTML = '';
          filtered.forEach(function (tpl) {
            var btn = document.createElement('button');
            btn.className = 'btn btn--neutral btn--full';
            btn.type = 'button';
            btn.style.textAlign = 'left';
            btn.textContent = tpl.title || 'Untitled';
            btn.setAttribute('data-template-fill', fieldKey);
            btn.setAttribute('data-template-value', tpl.body || '');
            list.appendChild(btn);
          });
        }
        if (empty) empty.hidden = filtered.length > 0;
        if (window.AppModal) window.AppModal.open('template-picker-modal');
      });

      // Template creator — open admin panel in new tab with type pre-selected
      document.addEventListener('click', function (event) {
        var createBtn = event.target.closest('[data-template-create]');
        if (!createBtn) return;
        var type = createBtn.getAttribute('data-template-create');
        var fieldKey = createBtn.getAttribute('data-template-field') || '';
        var url = '/admin-panel/?module=templates&create=1&type=' + encodeURIComponent(type);
        if (fieldKey) {
          url += '&field_key=' + encodeURIComponent(fieldKey);
        }
        window.open(url, '_blank');
      });
    });
  </script>
</div>
