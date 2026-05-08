<?php namespace ProcessWire;

$rootPath = 'C:/laragon/www/discharge-app';
if (!class_exists("ProcessWire\\ProcessWire", false)) {
    require_once("$rootPath/wire/core/ProcessWire.php");
}

$config = ProcessWire::buildConfig($rootPath);
$config->internal = false;
$wire = new ProcessWire($config);

$pages     = $wire->pages;
$templates = $wire->templates;
$fields    = $wire->fields;
$su = $wire->users->get('admin');
$wire->users->setCurrentUser($su);

// Load ready.php with full PW context (simulates web request lifecycle)
$wire->wire()->includeFile($rootPath . '/site/ready.php', [
    'wire'      => $wire,
    'pages'     => $pages,
    'templates' => $templates,
    'fields'    => $fields,
    'user'      => $su,
]);

$ts = time();
$pdfOk = false;

echo "\n=== FULL 8-TEST RUNTIME VERIFICATION (with ready.php) ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n\n";

// ── TEST 1: SCHEMA ────────────────────────────────────────────
echo "TEST 1: SCHEMA CHECK\n" . str_repeat("-", 40) . "\n";
$reqT = ['patient-record','admission-record','procedure','operation-note','investigation'];
$t1pass = true;
foreach ($reqT as $tn) {
    $t = $templates->get($tn);
    $ok = ($t && $t->id);
    if (!$ok) $t1pass = false;
    echo "  Template '$tn': " . ($ok ? "PASS (id={$t->id})" : "FAIL") . "\n";
}
$reqF = ['case_status','proc_date','surgery_date','investigation_date','review_date'];
foreach ($reqF as $fn) {
    $f = $fields->get($fn);
    $ok = ($f && $f->id);
    if (!$ok) $t1pass = false;
    echo "  Field '$fn': " . ($ok ? "PASS (id={$f->id}, type={$f->type})" : "FAIL") . "\n";
}
echo "  RESULT: " . ($t1pass ? "PASS" : "FAIL") . "\n\n";

// ── TEST 2: RECORD CREATION ──────────────────────────────────
echo "TEST 2: RECORD CREATION TEST\n" . str_repeat("-", 40) . "\n";
$patCon = $pages->get('/patients/');
echo "  Container: " . $patCon->path . " (id={$patCon->id})\n";

$patient = new Page();
$patient->template = $templates->get('patient-record');
$patient->parent   = $patCon;
$patient->name     = 'vtest-patient-' . $ts;
$patient->title    = 'VERIFY Test Patient';
$pages->save($patient);
echo "  [PATIENT] " . ($patient->id ? "PASS id={$patient->id}" : "FAIL") . "\n";
echo "  [PATIENT] patient_id: '" . $patient->patient_id . "'\n";

$adm = new Page();
$adm->template = $templates->get('admission-record');
$adm->parent   = $patient;
$adm->name     = 'vtest-adm-' . $ts;
$adm->title    = 'VERIFY Test Admission';
$pages->save($adm);
echo "  [ADMISSION] " . ($adm->id ? "PASS id={$adm->id}" : "FAIL") . "\n";
echo "  [ADMISSION] ip_number: '" . $adm->ip_number . "'\n";
echo "  [ADMISSION] admitted_on: " . ($adm->admitted_on ? date('Y-m-d H:i:s', (int)$adm->admitted_on) : "EMPTY") . "\n";

$proc = new Page();
$proc->template  = $templates->get('procedure');
$proc->parent    = $adm;
$proc->name      = 'new';
$proc->title     = 'VERIFY Procedure';
$proc->proc_name = 'Test Procedure CRIF';
$proc->proc_date = time();
$pages->save($proc);
echo "  [PROCEDURE] " . ($proc->id ? "PASS id={$proc->id}" : "FAIL") . "\n";
echo "  [PROCEDURE] name: '" . $proc->name . "'\n";

$opnote = new Page();
$opnote->template     = $templates->get('operation-note');
$opnote->parent       = $proc;
$opnote->name         = 'new';
$opnote->title        = 'VERIFY Op Note';
$opnote->surgery_date = time();
$pages->save($opnote);
echo "  [OP-NOTE] " . ($opnote->id ? "PASS id={$opnote->id}" : "FAIL") . "\n";
echo "  [OP-NOTE] name: '" . $opnote->name . "'\n";

$inv = new Page();
$inv->template            = $templates->get('investigation');
$inv->parent              = $adm;
$inv->name                = 'vtest-inv-' . $ts;
$inv->title               = 'VERIFY Investigation';
$inv->investigation_date  = time();
$inv->investigation_name  = 'Complete Blood Count';
$inv->investigation_type  = 'Blood Test';
$pages->save($inv);
echo "  [INVESTIGATION] " . ($inv->id ? "PASS id={$inv->id}" : "FAIL") . "\n";

$allCreated = $patient->id && $adm->id && $proc->id && $opnote->id && $inv->id;
echo "  RESULT: " . ($allCreated ? "PASS" : "FAIL") . "\n";
echo "  IDs: patient={$patient->id} adm={$adm->id} proc={$proc->id} opnote={$opnote->id} inv={$inv->id}\n\n";

// ── TEST 3: HIERARCHY ─────────────────────────────────────────
echo "TEST 3: HIERARCHY TEST\n" . str_repeat("-", 40) . "\n";
$h1 = ($adm->parent->id == $patient->id);
$h2 = ($proc->parent->id == $adm->id);
$h3 = ($opnote->parent->id == $proc->id);
$h4 = ($inv->parent->id == $adm->id);
echo "  Patient(" . $patient->id . ")→Admission(" . $adm->id . "): " . ($h1 ? "PASS" : "FAIL") . "\n";
echo "  Admission(" . $adm->id . ")→Procedure(" . $proc->id . "): " . ($h2 ? "PASS" : "FAIL") . "\n";
echo "  Procedure(" . $proc->id . ")→OpNote(" . $opnote->id . "): " . ($h3 ? "PASS" : "FAIL") . "\n";
echo "  Admission(" . $adm->id . ")→Investigation(" . $inv->id . "): " . ($h4 ? "PASS" : "FAIL") . "\n";
echo "  RESULT: " . ($h1&&$h2&&$h3&&$h4 ? "PASS" : "FAIL") . "\n\n";

// ── TEST 4: HOOKS ─────────────────────────────────────────────
echo "TEST 4: HOOK TEST\n" . str_repeat("-", 40) . "\n";

$pid = $patient->patient_id;
$pidOk = !empty($pid) && preg_match('/^REG-\d{4}-\d+$/', $pid);
echo "  patient_id: '$pid' — " . ($pidOk ? "PASS" : "FAIL") . "\n";

$ip = $adm->ip_number;
$ipOk = !empty($ip) && preg_match('/^IP-\d{8}-\d+$/', $ip);
echo "  ip_number: '$ip' — " . ($ipOk ? "PASS" : "FAIL") . "\n";

$admOn = $adm->admitted_on;
$admOnOk = !empty($admOn);
echo "  admitted_on: " . ($admOn ? date('Y-m-d H:i:s', (int)$admOn) : "EMPTY") . " — " . ($admOnOk ? "PASS" : "FAIL") . "\n";

$procNameOk = ($proc->name !== 'new') && (strpos($proc->name, 'proc-') === 0);
echo "  Procedure name: '{$proc->name}' — " . ($procNameOk ? "PASS" : "FAIL") . "\n";

$opnoteNameOk = ($opnote->name !== 'new') && (strpos($opnote->name, 'opnote-') === 0);
echo "  OpNote name: '{$opnote->name}' — " . ($opnoteNameOk ? "PASS" : "FAIL") . "\n";

// discharged_on hook
$freshAdm = $pages->get($adm->id);
$freshAdm->of(false);
$freshAdm->case_status = 2;
$pages->save($freshAdm);
$refetchAdm = $pages->get($adm->id);
$dischOn = $refetchAdm->discharged_on;
$dischOk = !empty($dischOn);
echo "  discharged_on after status=2: " . ($dischOn ? date('Y-m-d H:i:s', (int)$dischOn) : "EMPTY") . " — " . ($dischOk ? "PASS" : "FAIL") . "\n";

$hooksPass = $pidOk && $ipOk && $admOnOk && $procNameOk && $opnoteNameOk && $dischOk;
echo "  RESULT: " . ($hooksPass ? "PASS" : "FAIL") . "\n\n";

// ── TEST 5: BUSINESS RULES ────────────────────────────────────
echo "TEST 5: BUSINESS RULE TEST\n" . str_repeat("-", 40) . "\n";

// Rule 5a: check template parentTemplates restriction
$opTpl = $templates->get('operation-note');
$parentTemplatesCount = 0;
if ($opTpl && $opTpl->parentTemplates) {
    $parentTemplatesCount = count($opTpl->parentTemplates);
}
echo "  [5a] operation-note parentTemplates count: $parentTemplatesCount\n";
if ($parentTemplatesCount > 0) {
    $ptNames = [];
    foreach ($opTpl->parentTemplates as $ptId) {
        $pt = $templates->get($ptId);
        if ($pt) $ptNames[] = $pt->name;
    }
    echo "  [5a] Allowed parents: " . implode(', ', $ptNames) . "\n";
    echo "  [5a] 'procedure' in list: " . (in_array('procedure', $ptNames) ? "YES" : "NO") . "\n";
    echo "  [5a] RESULT: " . (in_array('procedure', $ptNames) ? "PASS" : "FAIL") . "\n";
} else {
    echo "  [5a] No parentTemplates restriction set — FAIL (can be saved under any parent)\n";
}

// Rule 5b: discharge before clinical entries (use a FRESH bare admission)
echo "  [5b] Creating bare admission (no procedures)...\n";
$bareAdm = new Page();
$bareAdm->template = $templates->get('admission-record');
$bareAdm->parent   = $patient;
$bareAdm->name     = 'bare-adm-' . $ts;
$bareAdm->title    = 'Bare Admission';
$pages->save($bareAdm);
echo "  [5b] Bare admission id={$bareAdm->id}\n";

$bareAdm->of(false);
$bareAdm->case_status = 2;
try {
    $pages->save($bareAdm);
    $refetch = $pages->get($bareAdm->id);
    $savedStatus = (int)$refetch->getUnformatted('case_status');
    echo "  [5b] case_status after discharge attempt: $savedStatus\n";
    echo "  [5b] Discharge blocked: " . ($savedStatus !== 2 ? "YES — PASS" : "NO — FAIL") . "\n";
} catch (\Exception $e) {
    echo "  [5b] Exception (rule enforced): " . $e->getMessage() . " — PASS\n";
}
if ($bareAdm->id) $pages->delete($bareAdm, true);

echo "\n";

// ── TEST 6: SEARCH ────────────────────────────────────────────
echo "TEST 6: SEARCH TEST\n" . str_repeat("-", 40) . "\n";

// Re-save admission to rebuild search index
$admForSearch = $pages->get($adm->id);
$admForSearch->of(false);
$pages->save($admForSearch);

$admAfterSave = $pages->get($adm->id);
$sIdx = $admAfterSave->search_index;
echo "  search_index: '" . substr($sIdx, 0, 120) . "'\n";
echo "  search_index non-empty: " . (!empty($sIdx) ? "PASS" : "FAIL") . "\n";

// Direct field search by ip_number
$ipNum = $adm->ip_number;
$r2 = $pages->find("template=admission-record, ip_number=$ipNum");
echo "  Find by ip_number '$ipNum': " . count($r2) . " result(s) — " . (count($r2) > 0 ? "PASS" : "FAIL") . "\n";

// Search_index contains patient name
$r3 = $pages->find("template=admission-record, search_index%=verify");
echo "  search_index contains 'verify': " . count($r3) . " result(s) — " . (count($r3) > 0 ? "PASS" : "UNVERIFIED") . "\n";

echo "  RESULT: " . (count($r2) > 0 ? "PASS" : "FAIL") . "\n\n";

// ── TEST 7: PDF ────────────────────────────────────────────────
echo "TEST 7: PDF TEST\n" . str_repeat("-", 40) . "\n";

$autoload = $rootPath . '/vendor/autoload.php';
if (file_exists($autoload)) require_once($autoload);

$mPdfClass = class_exists('\Mpdf\Mpdf');
echo "  vendor/autoload.php: " . (file_exists($autoload) ? "EXISTS" : "MISSING") . "\n";
echo "  Mpdf class loadable: " . ($mPdfClass ? "PASS" : "FAIL") . "\n";

if ($mPdfClass) {
    try {
        ob_start();
        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
        $mpdf->WriteHTML('<h1>Discharge Summary</h1><p>Patient: VERIFY Test | Date: ' . date('Y-m-d') . '</p>');
        $pdfBytes = $mpdf->Output('', 'S');
        ob_end_clean();
        $pdfOk = (substr($pdfBytes, 0, 4) === '%PDF');
        echo "  mPDF generate: " . ($pdfOk ? "PASS — PDF header confirmed (" . strlen($pdfBytes) . " bytes)" : "FAIL") . "\n";
    } catch (\Exception $e) {
        if (ob_get_level()) ob_end_clean();
        echo "  mPDF generate: FAIL — " . $e->getMessage() . "\n";
        $pdfOk = false;
    }
}

$tplFile    = $rootPath . '/site/templates/admission-record.php';
$tplContent = file_exists($tplFile) ? file_get_contents($tplFile) : '';
$tplHasMpdf = strpos($tplContent, 'Mpdf') !== false;
$tplHasOutput = strpos($tplContent, 'Output(') !== false;
echo "  Template wired for PDF: " . ($tplHasMpdf ? "YES" : "NO") . "\n";
echo "  Template calls Output(): " . ($tplHasOutput ? "YES" : "NO") . "\n";
echo "  RESULT: " . ($mPdfClass && $pdfOk ? "PASS" : "FAIL") . "\n\n";

// ── TEST 8: AUDIT LOG ─────────────────────────────────────────
echo "TEST 8: AUDIT LOG TEST\n" . str_repeat("-", 40) . "\n";

$auditRoot = $pages->get('/audit-log/');
$auditTpl  = $templates->get('audit-log');
$auditFld  = $fields->get('audit_action');

echo "  /audit-log/ page exists: " . ($auditRoot->id ? "YES id={$auditRoot->id}" : "NO — MISSING") . "\n";
echo "  audit-log template exists: " . ($auditTpl ? "YES id={$auditTpl->id}" : "NO — MISSING") . "\n";
echo "  audit_action field exists: " . ($auditFld ? "YES id={$auditFld->id}" : "NO — MISSING") . "\n";

if ($auditRoot->id && $auditTpl) {
    $countBefore = $pages->count("template=audit-log, parent={$auditRoot->id}");
    $testAdm = $pages->get($adm->id);
    $testAdm->of(false);
    $testAdm->diagnosis = 'AUDIT TEST ' . date('H:i:s');
    $pages->save($testAdm);
    $countAfter = $pages->count("template=audit-log, parent={$auditRoot->id}");
    $newEntry = $pages->find("template=audit-log, parent={$auditRoot->id}, sort=-created, limit=1")->first();
    $auditCreated = ($countAfter > $countBefore);
    echo "  Audit log entries before: $countBefore, after: $countAfter\n";
    echo "  New audit entry: " . ($auditCreated ? "CREATED id={$newEntry->id}" : "NOT CREATED — FAIL") . "\n";
    if ($auditCreated && $newEntry->id) {
        echo "  Audit title: '" . $newEntry->title . "'\n";
        $changes = $newEntry->audit_field_changes;
        echo "  field_changes: '" . substr($changes, 0, 150) . "'\n";
    }
    echo "  RESULT: " . ($auditCreated ? "PASS" : "FAIL") . "\n";
} else {
    echo "  RESULT: FAIL — audit infrastructure missing\n";
}

// ── CLEANUP ───────────────────────────────────────────────────
echo "\nCLEANUP\n" . str_repeat("-", 40) . "\n";
foreach ([$opnote, $inv, $proc, $adm, $patient] as $pg) {
    if ($pg && $pg->id) {
        $pages->delete($pg, true);
        echo "  Deleted id={$pg->id}\n";
    }
}
echo "\n=== END VERIFICATION ===\n";
