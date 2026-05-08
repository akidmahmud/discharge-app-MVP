<?php namespace ProcessWire;
/**
 * QA Runtime Verification Script
 * Bootstraps ProcessWire CLI and runs all 8 test suites.
 */

$rootPath = __DIR__;
if (DIRECTORY_SEPARATOR != '/') $rootPath = str_replace(DIRECTORY_SEPARATOR, '/', $rootPath);

$composerAutoloader = $rootPath . '/vendor/autoload.php';
if (file_exists($composerAutoloader)) require_once($composerAutoloader);
if (!class_exists("ProcessWire\\ProcessWire", false)) require_once("$rootPath/wire/core/ProcessWire.php");

$config = ProcessWire::buildConfig($rootPath);
$config->internal = false; // CLI mode — suppress HTTP output
$wire = new ProcessWire($config);

if (!$wire) {
    echo "FATAL: ProcessWire failed to bootstrap.\n";
    exit(1);
}

// Extract API vars
extract($wire->wire('all')->getArray());

// Use superuser for all tests
$su = $wire->users->get('admin');
if (!$su || !$su->id) {
    $su = $wire->users->find('roles=superuser')->first();
}
if ($su && $su->id) {
    $wire->users->setCurrentUser($su);
}

$pages     = $wire->pages;
$templates = $wire->templates;
$fields    = $wire->fields;

echo "\n=== QA RUNTIME VERIFICATION REPORT ===\n";
echo "Run Time: " . date('Y-m-d H:i:s') . "\n";
echo "ProcessWire: " . $wire->config->version . "\n";
echo "Current User: " . $wire->user->name . " (id=" . $wire->user->id . ")\n";
echo str_repeat("=", 60) . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// TEST 1: SCHEMA CHECK
// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 1: SCHEMA CHECK\n";
echo str_repeat("-", 40) . "\n";

$requiredTemplates = ['patient-record','admission-record','procedure','operation-note','investigation'];
$templateResults = [];
foreach ($requiredTemplates as $tn) {
    $t = $templates->get($tn);
    $templateResults[$tn] = ($t && $t->id) ? "PASS (id={$t->id})" : "FAIL — NOT FOUND";
    echo "  Template '$tn': " . $templateResults[$tn] . "\n";
}

$requiredFields = ['case_status','proc_date','surgery_date','investigation_date','review_date'];
$fieldResults = [];
foreach ($requiredFields as $fn) {
    $f = $fields->get($fn);
    $fieldResults[$fn] = ($f && $f->id) ? "PASS (id={$f->id}, type={$f->type})" : "FAIL — NOT FOUND";
    echo "  Field '$fn': " . $fieldResults[$fn] . "\n";
}

$schemaPass = !in_array(true, array_map(fn($v) => strpos($v,'FAIL')!==false, array_merge($templateResults,$fieldResults)));
echo "\n  SCHEMA CHECK: " . ($schemaPass ? "PASS" : "FAIL") . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// TEST 2: RECORD CREATION TEST
// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 2: RECORD CREATION TEST\n";
echo str_repeat("-", 40) . "\n";

$createdIds = [];

// Find patient container
$patientContainer = $pages->get('/patients/');
if (!$patientContainer || !$patientContainer->id) {
    $patientContainer = $pages->find('template=patients-list')->first();
}
if (!$patientContainer || !$patientContainer->id) {
    // Find any page that is parent of patient-record pages
    $existingPatient = $pages->find('template=patient-record, limit=1')->first();
    $patientContainer = $existingPatient ? $existingPatient->parent : null;
}
if (!$patientContainer || !$patientContainer->id) {
    echo "  ERROR: Cannot find patient container page\n";
} else {
    echo "  Patient container: " . $patientContainer->path . " (id={$patientContainer->id})\n";
}

// 2a. Create patient
$patient = null;
try {
    $patient = new \ProcessWire\Page();
    $patient->template = $templates->get('patient-record');
    $patient->parent   = $patientContainer;
    $patient->name     = 'qa-test-patient-' . time();
    $patient->title    = 'QA Test Patient ' . date('Ymd-His');
    $patient->of(false);
    $patient->gender   = 1; // assume option 1 exists
    $pages->save($patient);
    $createdIds['patient'] = $patient->id;
    echo "  [PATIENT] PASS — id={$patient->id}, name={$patient->name}\n";
    echo "  [PATIENT] patient_id auto-value: '" . $patient->patient_id . "'\n";
} catch (\Exception $e) {
    echo "  [PATIENT] FAIL — " . $e->getMessage() . "\n";
}

// 2b. Create admission
$admission = null;
if ($patient && $patient->id) {
    try {
        $admission = new \ProcessWire\Page();
        $admission->template = $templates->get('admission-record');
        $admission->parent   = $patient;
        $admission->name     = 'qa-admission-' . time();
        $admission->title    = 'QA Admission ' . date('Ymd-His');
        $admission->of(false);
        $admission->chief_complaint = 'QA test complaint';
        $pages->save($admission);
        $createdIds['admission'] = $admission->id;
        echo "  [ADMISSION] PASS — id={$admission->id}, name={$admission->name}\n";
        echo "  [ADMISSION] ip_number auto-value: '" . $admission->ip_number . "'\n";
        echo "  [ADMISSION] admitted_on auto-value: " . ($admission->admitted_on ? date('Y-m-d H:i:s', $admission->getUnformatted('admitted_on')) : 'EMPTY') . "\n";
    } catch (\Exception $e) {
        echo "  [ADMISSION] FAIL — " . $e->getMessage() . "\n";
    }
}

// 2c. Create procedure
$procedure = null;
if ($admission && $admission->id) {
    try {
        $procedure = new \ProcessWire\Page();
        $procedure->template = $templates->get('procedure');
        $procedure->parent   = $admission;
        $procedure->name     = 'new'; // hook should replace this
        $procedure->title    = 'QA Procedure';
        $procedure->of(false);
        $procedure->proc_name = 'Carpal Tunnel Release';
        $procedure->proc_date = time();
        $pages->save($procedure);
        $createdIds['procedure'] = $procedure->id;
        echo "  [PROCEDURE] PASS — id={$procedure->id}, name={$procedure->name}\n";
    } catch (\Exception $e) {
        echo "  [PROCEDURE] FAIL — " . $e->getMessage() . "\n";
    }
}

// 2d. Create operation note (child of procedure)
$opNote = null;
if ($procedure && $procedure->id) {
    try {
        $opNote = new \ProcessWire\Page();
        $opNote->template = $templates->get('operation-note');
        $opNote->parent   = $procedure;
        $opNote->name     = 'new'; // hook should replace
        $opNote->title    = 'QA Operation Note';
        $opNote->of(false);
        $opNote->surgery_date    = time();
        $opNote->procedure_steps = 'QA test procedure steps';
        $pages->save($opNote);
        $createdIds['operation-note'] = $opNote->id;
        echo "  [OP-NOTE] PASS — id={$opNote->id}, name={$opNote->name}\n";
    } catch (\Exception $e) {
        echo "  [OP-NOTE] FAIL — " . $e->getMessage() . "\n";
    }
}

// 2e. Create investigation (child of admission)
$investigation = null;
if ($admission && $admission->id) {
    try {
        $investigation = new \ProcessWire\Page();
        $investigation->template = $templates->get('investigation');
        $investigation->parent   = $admission;
        $investigation->name     = 'qa-investigation-' . time();
        $investigation->title    = 'QA X-Ray';
        $investigation->of(false);
        $investigation->investigation_name = 'X-Ray Wrist AP/Lateral';
        $investigation->investigation_date = time();
        $pages->save($investigation);
        $createdIds['investigation'] = $investigation->id;
        echo "  [INVESTIGATION] PASS — id={$investigation->id}, name={$investigation->name}\n";
    } catch (\Exception $e) {
        echo "  [INVESTIGATION] FAIL — " . $e->getMessage() . "\n";
    }
}

echo "\n  Created IDs: " . json_encode($createdIds) . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// TEST 3: HIERARCHY TEST
// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 3: HIERARCHY TEST\n";
echo str_repeat("-", 40) . "\n";

if ($patient && $admission) {
    // Patient → Admission
    $admissionParentId = $admission->parent_id;
    echo "  Admission parent_id={$admissionParentId}, patient id={$patient->id}: " .
        ($admissionParentId == $patient->id ? "PASS" : "FAIL") . "\n";
}
if ($admission && $procedure) {
    $procParentId = $procedure->parent_id;
    echo "  Procedure parent_id={$procParentId}, admission id={$admission->id}: " .
        ($procParentId == $admission->id ? "PASS" : "FAIL") . "\n";
}
if ($procedure && $opNote) {
    $opNoteParentId = $opNote->parent_id;
    echo "  OpNote parent_id={$opNoteParentId}, procedure id={$procedure->id}: " .
        ($opNoteParentId == $procedure->id ? "PASS" : "FAIL") . "\n";
}
if ($admission && $investigation) {
    $invParentId = $investigation->parent_id;
    echo "  Investigation parent_id={$invParentId}, admission id={$admission->id}: " .
        ($invParentId == $admission->id ? "PASS" : "FAIL") . "\n";
}

// Verify via DB query
if ($admission && $admission->id) {
    $children = $pages->find("parent={$admission->id}");
    echo "  Children of admission (id={$admission->id}):\n";
    foreach ($children as $ch) {
        echo "    → id={$ch->id}, name={$ch->name}, template={$ch->template->name}\n";
    }
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
// TEST 4: HOOK TEST
// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 4: HOOK TEST\n";
echo str_repeat("-", 40) . "\n";

// 4a. patient_id auto-generated
if ($patient) {
    $pid = $patient->patient_id;
    $hookPass = !empty($pid) && preg_match('/^REG-\d{4}-\d{4}$/', $pid);
    echo "  patient_id auto-generated: '" . $pid . "' — " . ($hookPass ? "PASS" : "FAIL (wrong format or empty)") . "\n";
}

// 4b. ip_number auto-generated
if ($admission) {
    $ipn = $admission->ip_number;
    $hookPass2 = !empty($ipn) && preg_match('/^IP-\d{8}-\d{3}$/', $ipn);
    echo "  ip_number auto-generated: '" . $ipn . "' — " . ($hookPass2 ? "PASS" : "FAIL (wrong format or empty)") . "\n";
}

// 4c. admitted_on auto-set
if ($admission) {
    $admOn = $admission->getUnformatted('admitted_on');
    echo "  admitted_on auto-set: " . ($admOn ? date('Y-m-d H:i:s', $admOn) . " — PASS" : "FAIL — empty") . "\n";
}

// 4d. procedure page name auto-generated
if ($procedure) {
    $pName = $procedure->name;
    $hookPass4 = strpos($pName, 'proc-') === 0;
    echo "  Procedure name auto-generated: '" . $pName . "' — " . ($hookPass4 ? "PASS" : "FAIL (expected proc-* prefix)") . "\n";
}

// 4e. operation-note page name auto-generated
if ($opNote) {
    $opName = $opNote->name;
    $hookPass5 = strpos($opName, 'opnote-') === 0;
    echo "  OpNote name auto-generated: '" . $opName . "' — " . ($hookPass5 ? "PASS" : "FAIL (expected opnote-* prefix)") . "\n";
}

// 4f. discharged_on auto-set when discharge status applied
echo "  Testing discharged_on hook (setting case_status=2 on admission)...\n";
if ($admission && $admission->id) {
    // Must have clinical entries first (procedure or investigation exists)
    $admission->of(false);
    $admission->case_status = 2;
    $saved = false;
    try {
        $pages->save($admission);
        $saved = true;
    } catch (\Exception $e) {
        echo "  discharged_on hook: SAVE EXCEPTION — " . $e->getMessage() . "\n";
    }
    // Reload from DB
    $admFresh = $pages->getById($admission->id, ['cache' => false]);
    $dischargedOn = $admFresh ? $admFresh->getUnformatted('discharged_on') : null;
    echo "  discharged_on after status=2: " . ($dischargedOn ? date('Y-m-d H:i:s', $dischargedOn) . " — PASS" : "FAIL — empty") . "\n";
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
// TEST 5: BUSINESS RULE TEST
// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 5: BUSINESS RULE TEST\n";
echo str_repeat("-", 40) . "\n";

// 5a. Create op-note WITHOUT a procedure (should fail or be blocked)
echo "  [RULE 5a] Attempting op-note without procedure parent...\n";
if ($admission && $admission->id) {
    try {
        $badOpNote = new \ProcessWire\Page();
        $badOpNote->template = $templates->get('operation-note');
        $badOpNote->parent   = $admission; // Wrong parent — should be procedure
        $badOpNote->name     = 'qa-bad-opnote-' . time();
        $badOpNote->title    = 'Bad OpNote';
        $badOpNote->of(false);
        $pages->save($badOpNote);
        // If saved — check if template/hierarchy validator blocked it
        // ProcessWire allows saving under wrong parent unless template has parent restriction
        $tplOpNote = $templates->get('operation-note');
        $allowedParents = $tplOpNote->parentTemplates ?? [];
        echo "  [RULE 5a] Page saved with id={$badOpNote->id} (parent=" . $admission->id . ")\n";
        echo "           Template 'operation-note' parentTemplates: " . implode(',', array_map(fn($t)=>is_object($t)?$t->name:$t, $allowedParents)) . "\n";
        // Trash this bad page
        $pages->trash($badOpNote);
        echo "           VERDICT: Business rule NOT enforced at save level — check template parent restriction\n";
    } catch (\Exception $e) {
        echo "  [RULE 5a] PASS — blocked: " . $e->getMessage() . "\n";
    }
}

// 5b. Discharge before clinical entries
echo "  [RULE 5b] Attempting discharge on fresh admission with no clinical entries...\n";
if ($patient && $patient->id) {
    try {
        $bareAdm = new \ProcessWire\Page();
        $bareAdm->template = $templates->get('admission-record');
        $bareAdm->parent   = $patient;
        $bareAdm->name     = 'qa-bare-adm-' . time();
        $bareAdm->title    = 'QA Bare Admission';
        $bareAdm->of(false);
        $pages->save($bareAdm);
        echo "  [RULE 5b] Bare admission created id={$bareAdm->id}\n";

        // Now try to discharge it
        $bareAdm->of(false);
        $bareAdm->case_status = 2;
        $cancelCalled = false;

        // Check session for errors after save attempt
        $wire->session->clearAll();
        $pages->save($bareAdm);

        // Check if it actually got saved with status=2
        $bareFresh = $pages->getById($bareAdm->id, ['cache' => false]);
        $freshStatus = (int)$bareFresh->getUnformatted('case_status');
        if ($freshStatus === 2) {
            echo "  [RULE 5b] FAIL — discharge succeeded on empty admission (status={$freshStatus})\n";
        } else {
            echo "  [RULE 5b] PASS — discharge blocked (status remains {$freshStatus})\n";
        }
        // Check session error
        $sessionErrors = $wire->session->getErrors(true);
        if ($sessionErrors) echo "  [RULE 5b] Session errors: " . implode(', ', $sessionErrors) . "\n";

        $pages->trash($bareAdm);
    } catch (\Exception $e) {
        echo "  [RULE 5b] Exception during test: " . $e->getMessage() . "\n";
    }
}

// 5c. PA edits IP number after procedure exists
echo "  [RULE 5c] Testing PA ip_number lock...\n";
// Switch to PA user if exists
$paUser = $wire->users->find('roles=physician-assistant, limit=1')->first();
if (!$paUser || !$paUser->id) {
    echo "  [RULE 5c] UNVERIFIED — No physician-assistant role user found in DB\n";
} else {
    $wire->users->setCurrentUser($paUser);
    echo "  Switched to PA user: {$paUser->name} (id={$paUser->id})\n";

    if ($admission && $admission->id) {
        $originalIp = $admission->ip_number;
        $admission->of(false);
        $admission->ip_number = 'IP-TAMPERED-999';

        try {
            $pages->save($admission);
            $admFresh2 = $pages->getById($admission->id, ['cache' => false]);
            if ($admFresh2->ip_number === 'IP-TAMPERED-999') {
                echo "  [RULE 5c] FAIL — PA was able to change ip_number to tampered value\n";
            } else {
                echo "  [RULE 5c] PASS — ip_number reverted to: " . $admFresh2->ip_number . "\n";
            }
        } catch (\Exception $e) {
            echo "  [RULE 5c] PASS (exception) — " . $e->getMessage() . "\n";
        }
    }
    // Switch back to superuser
    $wire->users->setCurrentUser($su);
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
// TEST 6: SEARCH TEST
// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 6: SEARCH TEST\n";
echo str_repeat("-", 40) . "\n";

// Rebuild search_index for our test admission
if ($admission && $admission->id) {
    $adm = $pages->getById($admission->id, ['cache' => false]);
    echo "  search_index on test admission: '" . substr($adm->search_index, 0, 100) . "'\n";
}

// Search by patient name (partial)
$searchName = 'qa test';
$r1 = $pages->find("template=admission-record, search_index%=" . $pages->sanitizer->selectorValue($searchName) . ", limit=5");
echo "  Search by name '$searchName': " . $r1->count() . " result(s)";
foreach ($r1 as $r) echo " [id={$r->id}, ip={$r->ip_number}]";
echo "\n";

// Search by IP number
if ($admission) {
    $searchIp = $admission->ip_number;
    $r2 = $pages->find("template=admission-record, search_index%=" . $pages->sanitizer->selectorValue($searchIp) . ", limit=5");
    echo "  Search by IP '$searchIp': " . $r2->count() . " result(s)";
    foreach ($r2 as $r) echo " [id={$r->id}]";
    echo "\n";
}

// Search by diagnosis keyword
$r3 = $pages->find("template=admission-record, search_index%=carpal, limit=5");
echo "  Search by diagnosis 'carpal': " . $r3->count() . " result(s)";
foreach ($r3 as $r) echo " [id={$r->id}, ip={$r->ip_number}]";
echo "\n";

// Search by procedure keyword
$r4 = $pages->find("template=admission-record, search_index%=tunnel, limit=5");
echo "  Search by procedure 'tunnel': " . $r4->count() . " result(s)";
foreach ($r4 as $r) echo " [id={$r->id}, ip={$r->ip_number}]";
echo "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// TEST 7: PDF TEST (static check only — can't render in CLI)
// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 7: PDF TEST\n";
echo str_repeat("-", 40) . "\n";

$autoloadPath = $wire->config->paths->root . 'vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    $mpdfClass = class_exists('\Mpdf\Mpdf');
    echo "  vendor/autoload.php: EXISTS\n";
    echo "  \\Mpdf\\Mpdf class loadable: " . ($mpdfClass ? "PASS" : "FAIL") . "\n";

    if ($mpdfClass) {
        try {
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
            $mpdf->WriteHTML('<h1>PDF Test</h1><p>QA verification test.</p>');
            $pdfOutput = $mpdf->Output('', 'S'); // return as string
            $isPdf = strpos($pdfOutput, '%PDF') === 0;
            echo "  mPDF generate test: " . ($isPdf ? "PASS — %PDF header confirmed (" . strlen($pdfOutput) . " bytes)" : "FAIL — output not PDF") . "\n";
        } catch (\Exception $e) {
            echo "  mPDF generate test: FAIL — " . $e->getMessage() . "\n";
        }
    }

    // Verify admission-record.php uses mPDF
    $tplFile = $wire->config->paths->templates . 'admission-record.php';
    $tplContent = file_get_contents($tplFile);
    $hasMpdfNew    = strpos($tplContent, 'new \Mpdf\Mpdf') !== false;
    $hasOutput     = strpos($tplContent, "->Output(") !== false;
    $hasWriteHTML  = strpos($tplContent, "->WriteHTML(") !== false;
    echo "  admission-record.php uses new \\Mpdf\\Mpdf: " . ($hasMpdfNew ? "YES" : "NO") . "\n";
    echo "  admission-record.php calls ->WriteHTML(): " . ($hasWriteHTML ? "YES" : "NO") . "\n";
    echo "  admission-record.php calls ->Output('I'): " . ($hasOutput ? "YES" : "NO") . "\n";
} else {
    echo "  vendor/autoload.php: MISSING — FAIL\n";
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
// TEST 8: AUDIT LOG TEST
// ─────────────────────────────────────────────────────────────────────────────
echo "TEST 8: AUDIT LOG TEST\n";
echo str_repeat("-", 40) . "\n";

$auditRoot = $pages->get('/audit-log/');
if (!$auditRoot || !$auditRoot->id) {
    echo "  /audit-log/ root page: NOT FOUND — FAIL\n";
} else {
    echo "  /audit-log/ root page: EXISTS (id={$auditRoot->id})\n";

    $auditTemplate = $templates->get('audit-log');
    echo "  audit-log template: " . ($auditTemplate && $auditTemplate->id ? "EXISTS (id={$auditTemplate->id})" : "MISSING — FAIL") . "\n";

    // Check audit entries created during this test session
    if ($auditTemplate && $auditTemplate->id && $admission && $admission->id) {
        // Count audit entries for our test admission
        $auditEntries = $pages->find("template=audit-log, parent={$auditRoot->id}, limit=20, sort=-created");
        echo "  Total audit-log entries: " . $auditEntries->count() . "\n";

        // Look for entry related to our test admission
        $relatedAudit = null;
        foreach ($auditEntries as $ae) {
            if ($ae->getUnformatted('audit_entity_id') == $admission->id) {
                $relatedAudit = $ae;
                break;
            }
        }

        if ($relatedAudit) {
            echo "  Audit entry for admission id={$admission->id}: FOUND (audit page id={$relatedAudit->id})\n";
            echo "  Audit page path: {$relatedAudit->path}\n";
            echo "  Audit action: " . $relatedAudit->getUnformatted('audit_action') . "\n";
            echo "  Audit user: " . $relatedAudit->audit_user_name . "\n";
            $changes = $relatedAudit->audit_field_changes;
            echo "  Field changes JSON: " . ($changes ? substr($changes, 0, 200) : '(empty)') . "\n";
        } else {
            echo "  Audit entry for our test admission: NOT FOUND\n";
            echo "  Recent audit entries:\n";
            foreach ($auditEntries->slice(0, 5) as $ae) {
                echo "    id={$ae->id}, entity_id=" . $ae->getUnformatted('audit_entity_id') . ", action=" . $ae->getUnformatted('audit_action') . ", title={$ae->title}\n";
            }
        }

        // Now modify the admission and check new audit entry
        if ($admission && $admission->id) {
            $countBefore = $pages->count("template=audit-log, parent={$auditRoot->id}");
            $admission->of(false);
            $admission->chief_complaint = 'Updated complaint for QA audit test';
            $pages->save($admission);
            $countAfter = $pages->count("template=audit-log, parent={$auditRoot->id}");
            echo "  Audit entries before modify: $countBefore, after modify: $countAfter\n";
            if ($countAfter > $countBefore) {
                echo "  AUDIT LOG ON MODIFY: PASS — new entry created\n";
                $newEntry = $pages->find("template=audit-log, parent={$auditRoot->id}, sort=-created, limit=1")->first();
                if ($newEntry) {
                    echo "  New audit entry id={$newEntry->id}, path={$newEntry->path}\n";
                    echo "  Field changes: " . ($newEntry->audit_field_changes ?: '(empty — field not in diff list)') . "\n";
                }
            } else {
                echo "  AUDIT LOG ON MODIFY: FAIL — no new entry (count unchanged at $countAfter)\n";
            }
        }
    }
}
echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
// CLEANUP
// ─────────────────────────────────────────────────────────────────────────────
echo "CLEANUP\n";
echo str_repeat("-", 40) . "\n";
// Trash test pages (superuser can do this)
$wire->users->setCurrentUser($su);
foreach (array_reverse($createdIds) as $type => $id) {
    try {
        $pg = $pages->getById($id);
        if ($pg && $pg->id) {
            $pages->trash($pg);
            echo "  Trashed $type (id=$id)\n";
        }
    } catch (\Exception $e) {
        echo "  Could not trash $type (id=$id): " . $e->getMessage() . "\n";
    }
}

echo "\n=== END QA VERIFICATION ===\n";
