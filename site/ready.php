<?php namespace ProcessWire;

/**
 * Clinical Registry — Hooks & Workflow Engine
 * Phase 2 Implementation
 */

// ─────────────────────────────────────────────────────────────────────────────
// AUTO-GENERATE: Patient ID (REG-YYYY-XXXX)
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'patient-record') return;
    if ($page->patient_id) return;

    $lastPatient = wire('pages')->get("template=patient-record, sort=-created");
    $nextNum = 1;
    if ($lastPatient->id && $lastPatient->patient_id) {
        if (preg_match('/REG-\d{4}-(\d+)/', $lastPatient->patient_id, $m)) {
            $nextNum = (int)$m[1] + 1;
        }
    }
    $page->patient_id = 'REG-' . date('Y') . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
});

// ─────────────────────────────────────────────────────────────────────────────
// AUTO-GENERATE: IP Number for admissions (IP-YYYYMMDD-NNN)
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'admission-record') return;
    if ($page->ip_number) return;

    $today      = date('Ymd');
    $countToday = wire('pages')->count("template=admission-record, created>=" . strtotime('today'));
    $page->ip_number = 'IP-' . $today . '-' . str_pad($countToday + 1, 3, '0', STR_PAD_LEFT);
});

// ─────────────────────────────────────────────────────────────────────────────
// AUTO-SET: admitted_on timestamp when a new admission record is first saved
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'admission-record') return;
    if ($page->id) return; // only on first save (new page)
    if ($page->admitted_on) return;
    if (wire('fields')->get('admitted_on')) {
        $page->admitted_on = time();
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// AUDIT TRAIL: Record created_by_user / updated_by_user on every save
// ─────────────────────────────────────────────────────────────────────────────
$auditTemplates = ['patient-record', 'admission-record', 'procedure', 'operation-note', 'hospital-course-entry'];

$pages->addHookBefore('save', function(HookEvent $event) use ($auditTemplates) {
    $page = $event->arguments(0);
    if (!in_array($page->template->name, $auditTemplates)) return;

    $uid = wire('user')->id;

    if (!$page->id && wire('fields')->get('created_by_user')) {
        $page->created_by_user = $uid;
    }
    if (wire('fields')->get('updated_by_user')) {
        $page->updated_by_user = $uid;
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// AUTO-GENERATE: Procedure child page name (proc-IPNUMBER-N)
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'procedure') return;
    if ($page->name && $page->name !== 'new') return;

    $caseId = $page->parent->ip_number ?: $page->parent->id;
    $count  = wire('pages')->count("template=procedure, parent={$page->parent->id}");
    $slug   = strtolower(preg_replace('/[^a-z0-9]/i', '-', $caseId));
    $page->name = 'proc-' . $slug . '-' . ($count + 1);
    if (!$page->title) {
        $page->title = 'Procedure ' . ($count + 1);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// AUTO-GENERATE: Operation Note name linked to its case
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'operation-note') return;
    if ($page->name && $page->name !== 'new') return;

    $caseKey = $page->parent && $page->parent->template->name === 'admission-record'
        ? ($page->parent->ip_number ?: $page->parent->id)
        : ($page->parent ? ($page->parent->name ?: $page->parent->id) : time());
    $slug = strtolower(preg_replace('/[^a-z0-9]/i', '-', (string) $caseKey));
    $count = ($page->parent && $page->parent->id)
        ? wire('pages')->count("template=operation-note, parent={$page->parent->id}")
        : 0;
    $page->name  = 'opnote-' . trim($slug, '-') . '-' . ($count + 1);
    if (!$page->title) {
        $page->title = 'Operation Note - ' . ($page->parent ? $page->parent->title : 'Case');
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// RULE 5 — HEADER LOCK
// PA-role users cannot modify header fields once procedures exist on the case
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'admission-record') return;

    $u = wire('user');
    if ($u->isSuperuser()) return;
    if ($u->hasRole('medical-officer')) return;
    if (!$u->hasRole('physician-assistant')) return;
    if (!$page->id) return;

    $hasProcedures = wire('pages')->count("template=procedure, parent={$page->id}");
    if ($hasProcedures === 0) return;

    // Restore locked IP number to prevent modification.
    // `getById()` returns a PageArray, but this hook needs the saved page itself.
    $saved = wire('pages')->get((int) $page->id);
    if ($saved instanceof Page && $saved->id && $page->ip_number !== $saved->ip_number) {
        $page->ip_number = $saved->ip_number;
        wire('session')->warning("Case header is locked once clinical work has started.");
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// RULE 4 — AUTO DISCHARGE TIMESTAMP
// Handled by the addHookBefore('save') hook below (RULE — Auto-set discharged_on)
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// RULE 6 — NO HARD DELETE of clinical records
// ─────────────────────────────────────────────────────────────────────────────
$protectedTemplates = ['patient-record', 'admission-record', 'procedure', 'operation-note'];

$pages->addHookBefore('trash', function(HookEvent $event) use ($protectedTemplates) {
    $page = $event->arguments(0);
    if (!in_array($page->template->name, $protectedTemplates)) return;

    $u = wire('user');
    if ($u->isSuperuser()) return;

    $event->replace = true;
    if (wire('fields')->get('case_status')) {
        $page->of(false);
        $page->case_status = 4; // Cancelled
        $page->save('case_status');
    }
    wire('session')->warning("Clinical records cannot be deleted. Record marked as Cancelled.");
});

$pages->addHookBefore('delete', function(HookEvent $event) use ($protectedTemplates) {
    $page = $event->arguments(0);
    if (!in_array($page->template->name, $protectedTemplates)) return;

    $u = wire('user');
    if ($u->isSuperuser()) return;

    $event->replace = true;
    wire('session')->error("Hard deletion of clinical records is not permitted.");
});

// ─────────────────────────────────────────────────────────────────────────────
// PHASE 8 — GRANULAR AUDIT LOGGING
// Captures before/after field values on every clinical record save
// Writes an audit-log child page under /audit-log/
// ─────────────────────────────────────────────────────────────────────────────

// Fields to diff per template
$_auditDiffFields = [
    'patient-record'   => ['title', 'patient_id', 'secondary_phone'],
    'admission-record' => ['case_status', 'ip_number', 'consultant_ref', 'primary_diagnosis_ref',
                           'diagnosis_side', 'discharged_on', 'general_condition'],
    'procedure'        => ['proc_name', 'proc_date', 'proc_status', 'anesthesia_type'],
    'operation-note'   => ['surgery_date', 'complications_intraop', 'procedure_steps'],
    'investigation'    => ['investigation_date', 'investigation_type', 'investigation_name'],
];

// Store keyed by page ID — persists across before/after hook pair within one request
$_auditStore = [];

// Capture pre-save field values
$pages->addHookBefore('save', function(HookEvent $event) use ($_auditDiffFields, &$_auditStore) {
    $page = $event->arguments(0);
    $tpl  = $page->template->name;
    if ($tpl === 'audit-log' || !isset($_auditDiffFields[$tpl])) return;
    if (!$page->id) return; // new page — no before state

    $savedPage = wire('pages')->get((int) $page->id);
    if (!$savedPage instanceof Page || !$savedPage->id) return;

    $before = [];
    foreach ($_auditDiffFields[$tpl] as $fn) {
        $raw = $savedPage->getUnformatted($fn);
        if ($raw instanceof Page)      $raw = $raw->id;
        if ($raw instanceof PageArray) $raw = $raw->explode('id');
        $before[$fn] = $raw;
    }
    $_auditStore[$page->id] = $before;
});

// After save: compare before/after, write audit-log entry if anything changed
$pages->addHookAfter('save', function(HookEvent $event) use ($_auditDiffFields, &$_auditStore) {
    $page = $event->arguments(0);
    $tpl  = $page->template->name;
    if ($tpl === 'audit-log' || !isset($_auditDiffFields[$tpl])) return;

    $auditRoot = wire('pages')->get('/audit-log/');
    if (!$auditRoot || !$auditRoot->id) return;
    if (!wire('templates')->get('audit-log')) return;

    $u       = wire('user');
    $isNew   = !isset($_auditStore[$page->id]);
    $changes = [];

    if (!$isNew) {
        $before = $_auditStore[$page->id];
        unset($_auditStore[$page->id]); // clean up
        foreach ($_auditDiffFields[$tpl] as $fn) {
            $raw = $page->getUnformatted($fn);
            if ($raw instanceof Page)      $raw = $raw->id;
            if ($raw instanceof PageArray) $raw = $raw->explode('id');
            $old = isset($before[$fn]) ? $before[$fn] : null;
            if ($raw != $old) {
                $changes[$fn] = ['before' => $old, 'after' => $raw];
            }
        }
    }

    // Determine action
    $statusValRaw = $page->getUnformatted('case_status');
    $statusVal    = is_object($statusValRaw) && method_exists($statusValRaw, 'first') ? (int)($statusValRaw->first() ? $statusValRaw->first()->id : 0) : (int)$statusValRaw;
    if ($isNew)           $action = 1; // Created
    elseif ($statusVal === 2) $action = 5; // Discharged
    elseif ($statusVal === 4) $action = 3; // Cancelled
    elseif (count($changes)) $action = 2; // Updated
    else                  return; // Nothing changed — skip writing log

    // Build audit log page (bypass output formatting for this write)
    $log = new Page();
    $log->template = wire('templates')->get('audit-log');
    $log->parent   = $auditRoot;
    $log->name     = 'audit-' . $page->id . '-' . time() . '-' . rand(100, 999);
    $log->title    = strtoupper($tpl) . ' #' . $page->id . ' — ' . date('Y-m-d H:i:s');

    $log->of(false);
    $log->audit_entity_id       = $page->id;
    $log->audit_entity_template = $tpl;
    $log->audit_entity_title    = $page->title ?: ('Page #' . $page->id);
    $log->audit_action          = $action;
    $log->audit_user_id         = $u->id;
    $log->audit_user_name       = $u->name;
    $log->audit_timestamp       = time();
    $log->audit_ip_address      = (php_sapi_name() === 'cli') ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '');
    $log->audit_field_changes   = count($changes) ? json_encode($changes, JSON_PRETTY_PRINT) : '';

    wire('pages')->save($log, ['noHooks' => true, 'quiet' => true]);
});


// ─────────────────────────────────────────────────────────────────────────────
// PHASE 9 — REBUILD SEARCH INDEX
// Denormalised text blob rebuilt on every admission-record save
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookAfter('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'admission-record') return;
    if (!wire('fields')->get('search_index')) return;

    $parts = [];
    $parts[] = $page->ip_number;
    $parts[] = $page->parent->title;
    $parts[] = $page->parent->patient_id;

    $diag = $page->getUnformatted('primary_diagnosis_ref');
    if ($diag instanceof Page && $diag->id) $parts[] = $diag->title;
    $parts[] = $page->diagnosis_side;

    $cons = $page->getUnformatted('consultant_ref');
    if ($cons instanceof Page && $cons->id) $parts[] = $cons->title;

    $procs = wire('pages')->find("template=procedure, parent={$page->id}");
    foreach ($procs as $proc) { $parts[] = $proc->proc_name; $parts[] = $proc->anesthesia_type; }

    $invs = wire('pages')->find("template=investigation, parent={$page->id}");
    foreach ($invs as $inv) { $parts[] = $inv->investigation_name; $parts[] = $inv->investigation_type; }

    $parts[] = $page->chief_complaint;
    $parts[] = $page->post_op_course;
    $parts[] = $page->proposed_procedure;

    $index = implode(' ', array_filter(array_map('trim', $parts)));
    $page->of(false);
    $page->search_index = strtolower($index);
    $page->save('search_index', ['noHooks' => true]);
});

// ─────────────────────────────────────────────────────────────────────────────
// BUSINESS RULE — Block discharge if no clinical entries exist
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'admission-record') return;
    $newStatusRaw = $page->getUnformatted('case_status');
    $newStatus    = is_object($newStatusRaw) && method_exists($newStatusRaw, 'first') ? (int)($newStatusRaw->first() ? $newStatusRaw->first()->id : 0) : (int)$newStatusRaw;
    if ($newStatus !== 2) return; // only block on discharge attempt
    $hasClinical = wire('pages')->count("template=procedure|investigation, parent={$page->id}");
    if (!$hasClinical) {
        $event->replace = true;
        wire('session')->error("Cannot discharge: no procedures or investigations recorded.");
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// BUSINESS RULE — Auto-set discharged_on when case_status → 2
// ─────────────────────────────────────────────────────────────────────────────
$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'admission-record') return;
    $newStatusRaw2 = $page->getUnformatted('case_status');
    $newStatus     = is_object($newStatusRaw2) && method_exists($newStatusRaw2, 'first') ? (int)($newStatusRaw2->first() ? $newStatusRaw2->first()->id : 0) : (int)$newStatusRaw2;
    if ($newStatus === 2 && !$page->discharged_on) {
        $page->of(false);
        $page->discharged_on = time();
    }
});

// ─────────────────────────────────────────────────────────────────────────────

$pages->addHookBefore('save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'admission-record') return;
    $user = wire('user');
    if ($user->isSuperuser() || $user->hasRole('medical-officer')) return;
    if (!$page->isChanged('ip_number')) return;
    $procCount = wire('pages')->count("template=procedure, parent={$page->id}");
    if ($procCount > 0) {
        $event->cancelAction = true;
        wire('session')->error("IP Number cannot be changed after procedures are recorded.");
    }
});
