<?php namespace ProcessWire;
/**
 * QA Runtime Test Endpoint
 * Access: http://discharge-app.test/qa-test/?qakey=qa2026
 * Tests all 8 verification suites via live HTTP runtime.
 */

if ($input->get('qakey') !== 'qa2026') {
    header('HTTP/1.1 403 Forbidden');
    echo "Forbidden"; exit;
}

$config->appendTemplateFile = '';
$config->useMarkupRegions   = false;
header('Content-Type: text/plain; charset=utf-8');

echo "=== QA RUNTIME VERIFICATION REPORT ===\n";
echo "Run Time: " . date('Y-m-d H:i:s') . "\n";
echo "ProcessWire: " . $config->version . "\n";
echo "Current User: " . $user->name . " (id=" . $user->id . ")\n";

// Use superuser
$su = $users->find('roles=superuser, limit=1')->first();
if ($su && $su->id) $users->setCurrentUser($su);
echo "Elevated User: " . $user->name . " (id=" . $user->id . ")\n";
echo str_repeat("=", 60) . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// TEST 1: SCHEMA CHECK
// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 1: SCHEMA CHECK\n" . str_repeat("-", 40) . "\n";

$reqTemplates = ['patient-record','admission-record','procedure','operation-note','investigation'];
$t1pass = true;
foreach ($reqTemplates as $tn) {
    $t = $templates->get($tn);
    $ok = ($t && $t->id);
    if (!$ok) $t1pass = false;
    echo "  Template '$tn': " . ($ok ? "PASS (id={$t->id})" : "FAIL — NOT FOUND") . "\n";
}

$reqFields = ['case_status','proc_date','surgery_date','investigation_date','review_date'];
foreach ($reqFields as $fn) {
    $f = $fields->get($fn);
    $ok = ($f && $f->id);
    if (!$ok) $t1pass = false;
    echo "  Field '$fn': " . ($ok ? "PASS (id={$f->id}, type={$f->type})" : "FAIL — NOT FOUND") . "\n";
}
echo "  RESULT: " . ($t1pass ? "PASS" : "FAIL") . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// TEST 2 & 3 & 4: RECORD CREATION + HIERARCHY + HOOK TEST
// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 2: RECORD CREATION TEST\n" . str_repeat("-", 40) . "\n";

$createdIds = [];
$ts = time();

// Patient container
$patientContainer = $pages->get('/patients/');
echo "  Patient container: " . $patientContainer->path . " (id={$patientContainer->id})\n";

// 2a. Patient
$patient = new Page();
$patient->template = $templates->get('patient-record');
$patient->parent   = $patientContainer;
$patient->name     = 'qa-patient-' . $ts;
$patient->title    = 'QA Test Patient ' . date('Ymd-His');
$patient->of(false);
$pages->save($patient);
$createdIds['patient'] = $patient->id;
echo "  [PATIENT] PASS — id={$patient->id}\n";
echo "  [PATIENT] patient_id auto-value: '" . $patient->patient_id . "'\n";

// 2b. Admission
$admission = new Page();
$admission->template = $templates->get('admission-record');
$admission->parent   = $patient;
$admission->name     = 'qa-adm-' . $ts;
$admission->title    = 'QA Admission ' . date('Ymd-His');
$admission->of(false);
$admission->chief_complaint = 'QA Test — pain in wrist';
$pages->save($admission);
$createdIds['admission'] = $admission->id;
echo "  [ADMISSION] PASS — id={$admission->id}\n";
echo "  [ADMISSION] ip_number auto-value: '" . $admission->ip_number . "'\n";
$admOn = $admission->getUnformatted('admitted_on');
echo "  [ADMISSION] admitted_on: " . ($admOn ? date('Y-m-d H:i:s', $admOn) . " — SET" : "EMPTY") . "\n";

// 2c. Procedure (child of admission)
$procedure = new Page();
$procedure->template = $templates->get('procedure');
$procedure->parent   = $admission;
$procedure->name     = 'new';
$procedure->title    = 'QA Procedure';
$procedure->of(false);
$procedure->proc_name = 'Carpal Tunnel Release';
$procedure->proc_date = $ts;
$pages->save($procedure);
$createdIds['procedure'] = $procedure->id;
echo "  [PROCEDURE] PASS — id={$procedure->id}, name='{$procedure->name}'\n";

// 2d. Op-note (child of admission, optionally linked to procedure)
$opNote = new Page();
$opNote->template = $templates->get('operation-note');
$opNote->parent   = $admission;
$opNote->name     = 'new';
$opNote->title    = 'QA Operation Note';
$opNote->of(false);
if (wire('fields')->get('procedure_ref_id')) {
    $opNote->procedure_ref_id = $procedure;
}
$opNote->surgery_date    = $ts;
$opNote->procedure_steps = 'QA test procedure steps. Incision made. Tunnel released. Wound closed.';
$pages->save($opNote);
$createdIds['op-note'] = $opNote->id;
echo "  [OP-NOTE] PASS — id={$opNote->id}, name='{$opNote->name}'\n";

// 2e. Investigation (child of admission)
$investigation = new Page();
$investigation->template = $templates->get('investigation');
$investigation->parent   = $admission;
$investigation->name     = 'qa-inv-' . $ts;
$investigation->title    = 'QA X-Ray';
$investigation->of(false);
$investigation->investigation_name = 'X-Ray Wrist AP/Lateral';
$investigation->investigation_date = $ts;
$pages->save($investigation);
$createdIds['investigation'] = $investigation->id;
echo "  [INVESTIGATION] PASS — id={$investigation->id}\n";

echo "\n  Created IDs: " . json_encode($createdIds) . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 3: HIERARCHY TEST\n" . str_repeat("-", 40) . "\n";

echo "  Patient(id={$patient->id}) → Admission(id={$admission->id}) parent_id={$admission->parent_id}: " .
    ($admission->parent_id == $patient->id ? "PASS" : "FAIL") . "\n";
echo "  Admission(id={$admission->id}) → Procedure(id={$procedure->id}) parent_id={$procedure->parent_id}: " .
    ($procedure->parent_id == $admission->id ? "PASS" : "FAIL") . "\n";
echo "  Admission(id={$admission->id}) → OpNote(id={$opNote->id}) parent_id={$opNote->parent_id}: " .
    ($opNote->parent_id == $admission->id ? "PASS" : "FAIL") . "\n";
echo "  Admission(id={$admission->id}) → Investigation(id={$investigation->id}) parent_id={$investigation->parent_id}: " .
    ($investigation->parent_id == $admission->id ? "PASS" : "FAIL") . "\n";

$admChildren = $pages->find("parent={$admission->id}");
echo "  Admission children count: " . $admChildren->count() . "\n";
foreach ($admChildren as $ch) echo "    → id={$ch->id}, name={$ch->name}, tpl={$ch->template->name}\n";
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 4: HOOK TEST\n" . str_repeat("-", 40) . "\n";

// Reload all from DB fresh to get hook-set values
$patFresh  = $pages->getById($patient->id,    ['cache' => false]);
$admFresh  = $pages->getById($admission->id,  ['cache' => false]);
$procFresh = $pages->getById($procedure->id,  ['cache' => false]);
$opFresh   = $pages->getById($opNote->id,     ['cache' => false]);

$pid = $patFresh->patient_id;
echo "  patient_id: '" . $pid . "' — " . (preg_match('/^REG-\d{4}-\d{4}$/', $pid) ? "PASS" : "FAIL") . "\n";

$ipn = $admFresh->ip_number;
echo "  ip_number: '" . $ipn . "' — " . (preg_match('/^IP-\d{8}-\d{3}$/', $ipn) ? "PASS" : "FAIL") . "\n";

$admOn = $admFresh->getUnformatted('admitted_on');
echo "  admitted_on: " . ($admOn ? date('Y-m-d H:i:s', $admOn) . " — PASS" : "FAIL — empty") . "\n";

$pName = $procFresh->name;
echo "  procedure name: '" . $pName . "' — " . (strpos($pName, 'proc-') === 0 ? "PASS" : "FAIL (expected proc-* prefix)") . "\n";

$opName = $opFresh->name;
echo "  op-note name: '" . $opName . "' — " . (strpos($opName, 'opnote-') === 0 ? "PASS" : "FAIL (expected opnote-* prefix)") . "\n";

// Test discharged_on — admission now has procedure+investigation so discharge should be allowed
$admFresh->of(false);
$admFresh->case_status = 2;
$pages->save($admFresh);
$admFresh2 = $pages->getById($admission->id, ['cache' => false]);
$dOn = $admFresh2->getUnformatted('discharged_on');
echo "  discharged_on (after status=2): " . ($dOn ? date('Y-m-d H:i:s', $dOn) . " — PASS" : "FAIL — empty") . "\n";
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 5: BUSINESS RULE TEST\n" . str_repeat("-", 40) . "\n";

// 5a. Op-note parent restriction
echo "  [RULE 5a] Op-note parent restriction (op-note under admission/case):\n";
$tplOpNote = $templates->get('operation-note');
$parentTpls = $tplOpNote->parentTemplates;
echo "    operation-note parentTemplates setting: " . (count($parentTpls) ? implode(', ', array_map(fn($t)=>is_object($t)?$t->name:$t, $parentTpls)) : "(none set)") . "\n";
// Actually save one with wrong parent and see if PW blocks it
$badOp = new Page();
$badOp->template = $templates->get('operation-note');
$badOp->parent   = $patient; // wrong — should be admission-record
$badOp->name     = 'qa-bad-opnote-' . $ts;
$badOp->title    = 'Bad Op Note Under Admission';
$badOp->of(false);
try {
    $pages->save($badOp);
    echo "    FAIL — op-note saved outside admission (id={$badOp->id}). No parent template restriction enforced.\n";
    $createdIds['bad-opnote'] = $badOp->id;
} catch (\Exception $e) {
    echo "    PASS — blocked: " . $e->getMessage() . "\n";
}

// 5b. Discharge before clinical entries
echo "  [RULE 5b] Discharge block on empty admission:\n";
$bareAdm = new Page();
$bareAdm->template = $templates->get('admission-record');
$bareAdm->parent   = $patient;
$bareAdm->name     = 'qa-bare-adm-' . $ts;
$bareAdm->title    = 'QA Bare Admission (no clinical)';
$bareAdm->of(false);
$pages->save($bareAdm);
$createdIds['bare-adm'] = $bareAdm->id;
echo "    Bare admission created id={$bareAdm->id}\n";

$bareAdm->of(false);
$bareAdm->case_status = 2; // Try to discharge
$pages->save($bareAdm);
$bareFresh = $pages->getById($bareAdm->id, ['cache' => false]);
$bareStatus = (int)$bareFresh->getUnformatted('case_status');
if ($bareStatus === 2) {
    echo "    FAIL — discharge succeeded on empty admission (status={$bareStatus})\n";
} else {
    echo "    PASS — discharge blocked (status remains {$bareStatus})\n";
}
$sessionErrors = $session->getErrors(true);
if ($sessionErrors) echo "    Session error captured: " . implode('; ', (array)$sessionErrors) . "\n";

// 5c. PA edits IP number after procedure exists
echo "  [RULE 5c] PA ip_number lock:\n";
$paUser = $users->find('roles=physician-assistant, limit=1')->first();
if (!$paUser || !$paUser->id) {
    echo "    UNVERIFIED — No physician-assistant user found in system.\n";
    echo "    Available roles: ";
    $allRoles = $wire->roles->find('name!=guest|superuser');
    echo implode(', ', $allRoles->explode('name')) . "\n";
} else {
    $users->setCurrentUser($paUser);
    echo "    Switched to PA: {$paUser->name}\n";
    $admForPa = $pages->getById($admission->id, ['cache' => false]);
    $origIp = $admForPa->ip_number;
    $admForPa->of(false);
    $admForPa->ip_number = 'IP-TAMPERED-999';
    $pages->save($admForPa);
    $admCheck = $pages->getById($admission->id, ['cache' => false]);
    if ($admCheck->ip_number === 'IP-TAMPERED-999') {
        echo "    FAIL — PA was able to change ip_number (new value: {$admCheck->ip_number})\n";
    } else {
        echo "    PASS — ip_number NOT changed (still: {$admCheck->ip_number})\n";
    }
    $users->setCurrentUser($su);
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 6: SEARCH TEST\n" . str_repeat("-", 40) . "\n";

// Check search_index on our test admission (rebuilt by hook on save)
$admSearch = $pages->getById($admission->id, ['cache' => false]);
echo "  search_index on test admission: '" . substr($admSearch->search_index ?: '', 0, 150) . "'\n";

// Also check existing admissions for search_index content
$existingAdm = $pages->find('template=admission-record, limit=3');
foreach ($existingAdm as $ea) {
    echo "  Existing adm id={$ea->id} search_index: '" . substr($ea->search_index ?: '(empty)', 0, 80) . "'\n";
}

// Search by patient name
$r1 = $pages->find("template=admission-record, search_index%=qa test, limit=5");
echo "  Search 'qa test': " . $r1->count() . " result(s)";
foreach ($r1 as $r) echo " [id={$r->id},ip={$r->ip_number}]";
echo "\n";

// Search by IP number
if ($admFresh && $admFresh->ip_number) {
    $ipSearch = $sanitizer->selectorValue($admFresh->ip_number);
    $r2 = $pages->find("template=admission-record, search_index%=$ipSearch, limit=5");
    echo "  Search by ip '{$admFresh->ip_number}': " . $r2->count() . " result(s)\n";
}

// Search for 'carpal'
$r3 = $pages->find("template=admission-record, search_index%=carpal, limit=5");
echo "  Search 'carpal': " . $r3->count() . " result(s)";
foreach ($r3 as $r) echo " [id={$r->id}]";
echo "\n";

// Search for 'tunnel'
$r4 = $pages->find("template=admission-record, search_index%=tunnel, limit=5");
echo "  Search 'tunnel': " . $r4->count() . " result(s)";
foreach ($r4 as $r) echo " [id={$r->id}]";
echo "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 7: PDF TEST\n" . str_repeat("-", 40) . "\n";

$vendorAl = $config->paths->root . 'vendor/autoload.php';
if (!file_exists($vendorAl)) {
    echo "  vendor/autoload.php: MISSING — FAIL\n";
} else {
    require_once $vendorAl;
    $mpdfExists = class_exists('\Mpdf\Mpdf');
    echo "  vendor/autoload.php: EXISTS\n";
    echo "  \\Mpdf\\Mpdf class: " . ($mpdfExists ? "PASS" : "FAIL — class not found") . "\n";
    if ($mpdfExists) {
        try {
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
            $mpdf->WriteHTML('<h1>QA PDF Test</h1><p>Admission ID: ' . $admission->id . '</p>');
            $pdfStr = $mpdf->Output('', 'S');
            $isPdf = strpos($pdfStr, '%PDF') === 0;
            echo "  mPDF actual generation: " . ($isPdf ? "PASS — %PDF header, " . strlen($pdfStr) . " bytes" : "FAIL") . "\n";
        } catch (\Exception $e) {
            echo "  mPDF generation: FAIL — " . $e->getMessage() . "\n";
        }
    }
    $tplFile = $config->paths->templates . 'admission-record.php';
    $tplC = file_get_contents($tplFile);
    echo "  Template uses new \\Mpdf\\Mpdf: " . (strpos($tplC, 'new \Mpdf\Mpdf') !== false ? "YES" : "NO") . "\n";
    echo "  Template calls ->Output('...','I'): " . (strpos($tplC, "->Output(") !== false ? "YES" : "NO") . "\n";
    echo "  Template uses ob_start + WriteHTML: " . (strpos($tplC, 'ob_start') !== false ? "YES" : "NO") . "\n";

    // Check PDF URL would work by verifying admission URL + ?pdf=1
    echo "  PDF URL would be: http://discharge-app.test" . $admission->url . "?pdf=1\n";
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 8: AUDIT LOG TEST\n" . str_repeat("-", 40) . "\n";

$auditRoot = $pages->get('/audit-log/');
if (!$auditRoot || !$auditRoot->id) {
    echo "  /audit-log/ page: NOT FOUND — FAIL\n";
    // Check if there's an audit-log template at all
    $auditTpl = $templates->get('audit-log');
    echo "  audit-log template: " . ($auditTpl && $auditTpl->id ? "EXISTS (id={$auditTpl->id})" : "NOT FOUND") . "\n";
    // Find any page named audit-log
    $anyAudit = $pages->find('name=audit-log, limit=5');
    echo "  Pages named audit-log: " . $anyAudit->count() . "\n";
    foreach ($anyAudit as $ap) echo "    id={$ap->id}, path={$ap->path}\n";
} else {
    echo "  /audit-log/ page: EXISTS (id={$auditRoot->id})\n";
    $auditTpl = $templates->get('audit-log');
    echo "  audit-log template: " . ($auditTpl && $auditTpl->id ? "EXISTS (id={$auditTpl->id})" : "NOT FOUND") . "\n";

    $auditCount = $pages->count("template=audit-log, parent={$auditRoot->id}");
    echo "  Total audit entries: $auditCount\n";

    // Find entries for our test admission
    if ($auditTpl && $auditTpl->id) {
        $myAuditEntries = $pages->find("template=audit-log, parent={$auditRoot->id}, sort=-created, limit=10");
        echo "  Last 10 audit entries:\n";
        foreach ($myAuditEntries as $ae) {
            $entityId = $ae->getUnformatted('audit_entity_id');
            echo "    id={$ae->id}, entity_id=$entityId, action=" . $ae->getUnformatted('audit_action') . ", user={$ae->audit_user_name}\n";
        }

        // Now modify our test record and check for new audit entry
        $countBefore = $pages->count("template=audit-log, parent={$auditRoot->id}");
        $admForAudit = $pages->getById($admission->id, ['cache' => false]);
        $admForAudit->of(false);
        $admForAudit->chief_complaint = 'AUDIT TEST MODIFICATION ' . time();
        $pages->save($admForAudit);
        $countAfter = $pages->count("template=audit-log, parent={$auditRoot->id}");
        echo "\n  Before modify: $countBefore audit entries\n";
        echo "  After modify:  $countAfter audit entries\n";
        if ($countAfter > $countBefore) {
            echo "  AUDIT ON MODIFY: PASS — new entry created\n";
            $newEntry = $pages->find("template=audit-log, parent={$auditRoot->id}, sort=-created, limit=1")->first();
            if ($newEntry) {
                echo "  New audit id={$newEntry->id}, path={$newEntry->path}\n";
                echo "  entity_id: " . $newEntry->getUnformatted('audit_entity_id') . "\n";
                echo "  action: " . $newEntry->getUnformatted('audit_action') . "\n";
                echo "  user: {$newEntry->audit_user_name}\n";
                $changes = $newEntry->audit_field_changes;
                echo "  field_changes: " . ($changes ? substr($changes, 0, 300) : '(empty)') . "\n";
            }
        } else {
            echo "  AUDIT ON MODIFY: FAIL — no new entry (both $countBefore)\n";
        }
    }
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
// CLEANUP
// ─────────────────────────────────────────────────────────────────────────────
echo "CLEANUP\n" . str_repeat("-", 40) . "\n";
$users->setCurrentUser($su);
foreach (array_reverse($createdIds) as $type => $id) {
    $pg = $pages->getById($id);
    if ($pg && $pg->id) {
        try {
            $pages->delete($pg, true); // force delete with children
            echo "  Deleted $type (id=$id)\n";
        } catch (\Exception $e) {
            echo "  Could not delete $type (id=$id): " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== END QA VERIFICATION ===\n";
