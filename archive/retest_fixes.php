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

// Load ready.php (simulates web request lifecycle)
$wire->wire()->includeFile($rootPath . '/site/ready.php', [
    'wire'      => $wire,
    'pages'     => $pages,
    'templates' => $templates,
    'fields'    => $fields,
    'user'      => $su,
]);

$ts = time();
echo "\n=== RETEST: HOOK, BUSINESS RULE, AUDIT LOG ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n\n";

// ── Setup: create patient + admission for tests ───────────────────────────────
$patCon  = $pages->get('/patients/');
$patient = new Page();
$patient->template = $templates->get('patient-record');
$patient->parent   = $patCon;
$patient->name     = 'retest-patient-' . $ts;
$patient->title    = 'Retest Patient';
$pages->save($patient);

$adm = new Page();
$adm->template = $templates->get('admission-record');
$adm->parent   = $patient;
$adm->name     = 'retest-adm-' . $ts;
$adm->title    = 'Retest Admission';
$pages->save($adm);

$proc = new Page();
$proc->template  = $templates->get('procedure');
$proc->parent    = $adm;
$proc->name      = 'new';
$proc->title     = 'Retest Procedure';
$proc->proc_name = 'Appendectomy';
$proc->proc_date = time();
$pages->save($proc);

echo "Setup: patient={$patient->id}, adm={$adm->id}, proc={$proc->id}\n\n";

// ══════════════════════════════════════════════════════════════
// TEST 4 (RETEST): HOOK TEST — discharged_on only
// ══════════════════════════════════════════════════════════════
echo "TEST 4 (RETEST): HOOK TEST — discharged_on auto-set\n" . str_repeat("-", 40) . "\n";

// Set case_status = 2 (Discharged) on the admission
$freshAdm = $pages->get($adm->id);
$freshAdm->of(false);
$freshAdm->case_status = 2;
$pages->save($freshAdm);

// Wait a tick for hookAfter to complete, then re-read from DB
$refetch = $pages->get($adm->id);
$dischOn = $refetch->discharged_on;
$dischOk = !empty($dischOn);

echo "  case_status set to 2 on admission id={$adm->id}\n";
echo "  discharged_on after save: " . ($dischOn ? date('Y-m-d H:i:s', (int)$dischOn) : "EMPTY") . "\n";
echo "  RESULT: " . ($dischOk ? "PASS" : "FAIL") . "\n\n";

// ══════════════════════════════════════════════════════════════
// TEST 5 (RETEST): BUSINESS RULE TEST
// ══════════════════════════════════════════════════════════════
echo "TEST 5 (RETEST): BUSINESS RULE TEST\n" . str_repeat("-", 40) . "\n";

// ── Rule 5a: operation-note parent restriction ────────────────
echo "  [5a] Checking operation-note parentTemplates restriction...\n";
$opTpl = $templates->get('operation-note');
$parentTemplates = $opTpl->parentTemplates;
$ptNames = [];
foreach ($parentTemplates as $ptId) {
    $pt = $templates->get((int)$ptId);
    if ($pt) $ptNames[] = $pt->name;
}
echo "  [5a] parentTemplates: " . (count($ptNames) ? implode(', ', $ptNames) : "NONE") . "\n";
$rule5aPass = (count($ptNames) === 1 && in_array('procedure', $ptNames));
echo "  [5a] RESULT: " . ($rule5aPass ? "PASS — restricted to 'procedure' only" : "FAIL") . "\n\n";

// ── Rule 5b: bare discharge blocked ──────────────────────────
echo "  [5b] Discharge block: bare admission (no clinical entries)...\n";
$bareAdm = new Page();
$bareAdm->template = $templates->get('admission-record');
$bareAdm->parent   = $patient;
$bareAdm->name     = 'bare-retest-' . $ts;
$bareAdm->title    = 'Bare Retest Admission';
$pages->save($bareAdm);
echo "  [5b] Bare admission created id={$bareAdm->id}\n";
echo "  [5b] Confirming zero clinical entries under it: " . $pages->count("template=procedure|investigation, parent={$bareAdm->id}") . "\n";

$bareAdm->of(false);
$bareAdm->case_status = 2; // attempt discharge
$pages->save($bareAdm);

// Re-read from DB to get actual saved value
$refetchBare = $pages->get($bareAdm->id);
$savedStatusRaw = $refetchBare->getUnformatted('case_status');
$savedStatus    = is_object($savedStatusRaw) ? (int)$savedStatusRaw->first()->id : (int)$savedStatusRaw;
echo "  [5b] case_status in DB after discharge attempt: $savedStatus\n";
$rule5bPass = ($savedStatus !== 2);
echo "  [5b] Discharge blocked: " . ($rule5bPass ? "YES — PASS" : "NO — FAIL (status was saved as discharged)") . "\n\n";

$pages->delete($bareAdm, true);

// ── Rule 5b confirmed: with procedures, discharge SHOULD go through ────────────
echo "  [5b-confirm] Discharge with procedures SHOULD succeed...\n";
// The adm already has a procedure (proc). Try to set it to discharged.
$admWithProc = $pages->get($adm->id);
$admWithProc->of(false);
$admWithProc->case_status = 2;
$pages->save($admWithProc);
$refetchAdmWithProc = $pages->get($adm->id);
$statusWithProcRaw = $refetchAdmWithProc->getUnformatted('case_status');
$statusWithProc    = is_object($statusWithProcRaw) ? (int)$statusWithProcRaw->first()->id : (int)$statusWithProcRaw;
echo "  [5b-confirm] case_status after discharge with proc: $statusWithProc (expected 2)\n";
echo "  [5b-confirm] RESULT: " . ($statusWithProc === 2 ? "PASS — discharge allowed when procedures exist" : "FAIL") . "\n\n";

// ══════════════════════════════════════════════════════════════
// TEST 8 (RETEST): AUDIT LOG TEST
// ══════════════════════════════════════════════════════════════
echo "TEST 8 (RETEST): AUDIT LOG TEST\n" . str_repeat("-", 40) . "\n";

$auditRoot = $pages->get('/audit-log/');
$auditTpl  = $templates->get('audit-log');
echo "  /audit-log/ page: " . ($auditRoot->id ? "PASS id={$auditRoot->id}" : "FAIL") . "\n";
echo "  audit-log template: " . ($auditTpl ? "PASS id={$auditTpl->id}" : "FAIL") . "\n";

// Verify all audit fields
$auditFieldNames = [
    'audit_entity_id','audit_entity_template','audit_entity_title',
    'audit_action','audit_user_id','audit_user_name',
    'audit_timestamp','audit_ip_address','audit_field_changes',
];
$allFieldsOk = true;
foreach ($auditFieldNames as $fn) {
    $f = $fields->get($fn);
    if (!$f || !$f->id) { $allFieldsOk = false; echo "  Field '$fn': MISSING\n"; }
}
echo "  All 9 audit fields: " . ($allFieldsOk ? "PASS" : "FAIL") . "\n";

// Test: modify admission (already discharged), check audit entry created
$countBefore = $pages->count("template=audit-log, parent={$auditRoot->id}");

// Save a clinical modification — change diagnosis field on the admission
$testAdm = $pages->get($adm->id);
$testAdm->of(false);
$testAdm->diagnosis = 'AUDIT RETEST ' . date('H:i:s');
$pages->save($testAdm);

$countAfter = $pages->count("template=audit-log, parent={$auditRoot->id}");
$newEntry = $pages->find("template=audit-log, parent={$auditRoot->id}, sort=-created, limit=1")->first();
$auditCreated = ($countAfter > $countBefore);

echo "  Audit entries before modification: $countBefore\n";
echo "  Audit entries after modification: $countAfter\n";
echo "  New audit entry created: " . ($auditCreated ? "YES id={$newEntry->id}" : "NO — FAIL") . "\n";

if ($auditCreated && $newEntry && $newEntry->id) {
    echo "  audit_entity_id: " . $newEntry->audit_entity_id . "\n";
    echo "  audit_entity_template: " . $newEntry->audit_entity_template . "\n";
    echo "  audit_action: " . $newEntry->audit_action . " (2=Updated)\n";
    echo "  audit_user_name: " . $newEntry->audit_user_name . "\n";
    echo "  audit_field_changes: '" . substr($newEntry->audit_field_changes, 0, 200) . "'\n";
}
echo "  RESULT: " . ($auditCreated ? "PASS" : "FAIL") . "\n\n";

// ── Test audit on NEW record creation ─────────────────────────────────────────
echo "  [8b] Audit on NEW record creation...\n";
$countBefore2 = $pages->count("template=audit-log, parent={$auditRoot->id}");
$newProc = new Page();
$newProc->template  = $templates->get('procedure');
$newProc->parent    = $adm;
$newProc->name      = 'new';
$newProc->title     = 'Audit Test Procedure';
$newProc->proc_name = 'Hernia Repair';
$newProc->proc_date = time();
$pages->save($newProc);

$countAfter2 = $pages->count("template=audit-log, parent={$auditRoot->id}");
$auditNew = ($countAfter2 > $countBefore2);
echo "  Audit entries after new proc creation: $countAfter2 (was $countBefore2)\n";
echo "  [8b] RESULT: " . ($auditNew ? "PASS — audit logged for new record" : "FAIL") . "\n\n";

// ══════════════════════════════════════════════════════════════
// SUMMARY
// ══════════════════════════════════════════════════════════════
echo str_repeat("=", 60) . "\n";
echo "SUMMARY\n" . str_repeat("=", 60) . "\n";
echo "  TEST 4 (discharged_on): " . ($dischOk ? "PASS" : "FAIL") . "\n";
echo "  TEST 5a (opnote parent): " . ($rule5aPass ? "PASS" : "FAIL") . "\n";
echo "  TEST 5b (discharge block): " . ($rule5bPass ? "PASS" : "FAIL") . "\n";
echo "  TEST 8 (audit log write): " . ($auditCreated ? "PASS" : "FAIL") . "\n";
echo "  TEST 8b (audit on create): " . ($auditNew ? "PASS" : "FAIL") . "\n";

$allPass = $dischOk && $rule5aPass && $rule5bPass && $auditCreated && $auditNew;
echo "\n  FINAL: " . ($allPass ? "ALL RETESTED ITEMS PASS" : "FAILURES REMAIN") . "\n\n";

// ── Cleanup ────────────────────────────────────────────────────────────────────
echo "CLEANUP\n" . str_repeat("-", 20) . "\n";
foreach ([$newProc, $proc, $adm, $patient] as $pg) {
    if ($pg && $pg->id) {
        $pages->delete($pg, true);
        echo "  Deleted id={$pg->id}\n";
    }
}
