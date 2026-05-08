<?php
/**
 * Phase 4 Migration — Workflow Engine (Roles)
 * Creates Physician Assistant and Medical Officer roles in ProcessWire
 * Sets permissions per the blueprint specification
 */

$rootPath = __DIR__;
include($rootPath . '/index.php');

if (!$user->isSuperuser()) die('<h2 style="color:red;">Access denied. Must be superuser.</h2>');

function step($m) { echo "<div style='background:#1e3a5f;color:#7dd3fc;padding:8px 12px;margin:4px 0;border-radius:4px;font-family:monospace;'>⚙ $m</div>\n"; flush(); }
function ok($m)   { echo "<div style='background:#14532d;color:#86efac;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>✓ $m</div>\n"; flush(); }
function warn($m) { echo "<div style='background:#713f12;color:#fde68a;padding:6px 12px;margin:2px 0;border-radius:4px;font-family:monospace;'>⚠ $m</div>\n"; flush(); }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Phase 4 — Role & Workflow System</title>
<style>
body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',sans-serif; padding:20px; }
h1 { color:#38bdf8; border-bottom:2px solid #1e3a5f; padding-bottom:10px; }
h2 { color:#7dd3fc; margin-top:30px; }
</style>
</head>
<body>
<h1>🔐 Phase 4 Migration — Role & Workflow System</h1>
<p style="color:#94a3b8;">Running as: <?= $user->name ?></p>

<?php

// ─── SECTION 1: Create Roles ──────────────────────────────────────────────────
echo '<h2>SECTION 1 — Create Roles</h2>';

step('Creating physician-assistant role...');
$paRole = wire('roles')->get('physician-assistant');
if (!$paRole || !$paRole->id) {
    $paRole = new Role();
    $paRole->name  = 'physician-assistant';
    $paRole->title = 'Physician Assistant';
    wire('roles')->save($paRole);
    ok("Created role: physician-assistant");
} else {
    warn("Role 'physician-assistant' already exists — skipping");
    $paRole = wire('roles')->get('physician-assistant');
}

step('Creating medical-officer role...');
$moRole = wire('roles')->get('medical-officer');
if (!$moRole || !$moRole->id) {
    $moRole = new Role();
    $moRole->name  = 'medical-officer';
    $moRole->title = 'Medical Officer';
    wire('roles')->save($moRole);
    ok("Created role: medical-officer");
} else {
    warn("Role 'medical-officer' already exists — skipping");
    $moRole = wire('roles')->get('medical-officer');
}

// ─── SECTION 2: Assign Permissions ───────────────────────────────────────────
echo '<h2>SECTION 2 — Assign Permissions</h2>';

// Core permissions in PW: page-view, page-edit, page-add, page-delete, page-move
// PA permissions: view + edit patient-record and admission-record header only
// MO permissions: full clinical access

step('Assigning permissions to physician-assistant role...');
$paPermissions = ['page-view', 'page-edit', 'page-add'];
foreach ($paPermissions as $permName) {
    $perm = wire('permissions')->get($permName);
    if ($perm && $perm->id) {
        if (!$paRole->hasPermission($permName)) {
            $paRole->addPermission($perm);
            ok("PA: added permission '$permName'");
        } else {
            warn("PA: already has '$permName'");
        }
    }
}
wire('roles')->save($paRole);

step('Assigning permissions to medical-officer role...');
$moPermissions = ['page-view', 'page-edit', 'page-add', 'page-delete'];
foreach ($moPermissions as $permName) {
    $perm = wire('permissions')->get($permName);
    if ($perm && $perm->id) {
        if (!$moRole->hasPermission($permName)) {
            $moRole->addPermission($perm);
            ok("MO: added permission '$permName'");
        } else {
            warn("MO: already has '$permName'");
        }
    }
}
wire('roles')->save($moRole);

// ─── SECTION 3: Template Access Control ──────────────────────────────────────
echo '<h2>SECTION 3 — Template Access Control</h2>';

// PA can edit patient-record and admission-record header
// MO can edit everything
// This is enforced in ready.php via hooks (already written in Phase 1 setup)
// Here we set template-level role access

$clinicalTemplates = ['procedure', 'operation-note', 'investigation'];

step('Restricting clinical templates to medical-officer role...');
foreach ($clinicalTemplates as $tName) {
    $t = wire('templates')->get($tName);
    if (!$t) { warn("Template '$tName' not found"); continue; }

    // Add useRoles flag and set editing roles
    $t->useRoles = 1;

    // Grant access to MO role and superuser
    if (!$t->hasRole($moRole)) {
        $t->addRole($moRole, 'edit');
        ok("$tName: MO role granted edit access");
    } else {
        warn("$tName: MO role already has access");
    }

    wire('templates')->save($t);
}

// admission-record: both PA and MO can access, but field-level restriction via hooks
step('Setting admission-record role access (PA + MO)...');
$tAdm = wire('templates')->get('admission-record');
if ($tAdm) {
    $tAdm->useRoles = 1;
    if (!$tAdm->hasRole($paRole)) {
        $tAdm->addRole($paRole, 'edit');
        ok("admission-record: PA role granted edit access");
    }
    if (!$tAdm->hasRole($moRole)) {
        $tAdm->addRole($moRole, 'edit');
        ok("admission-record: MO role granted edit access");
    }
    wire('templates')->save($tAdm);
}

step('Setting patient-record role access (PA + MO)...');
$tPat = wire('templates')->get('patient-record');
if ($tPat) {
    $tPat->useRoles = 1;
    if (!$tPat->hasRole($paRole)) {
        $tPat->addRole($paRole, 'edit');
        ok("patient-record: PA role granted edit access");
    }
    if (!$tPat->hasRole($moRole)) {
        $tPat->addRole($moRole, 'edit');
        ok("patient-record: MO role granted edit access");
    }
    wire('templates')->save($tPat);
}

// ─── SECTION 4: Create Sample Users ──────────────────────────────────────────
echo '<h2>SECTION 4 — Create Sample Users</h2>';

step('Creating sample PA user (pa_user)...');
$paUser = wire('users')->get('pa_user');
if (!$paUser || !$paUser->id) {
    $paUser = new User();
    $paUser->name  = 'pa_user';
    $paUser->email = 'pa@hospital.local';
    $paUser->pass  = 'PA@hospital2025';
    $paUser->addRole(wire('roles')->get('physician-assistant'));
    wire('users')->save($paUser);
    ok("Created user: pa_user (role: physician-assistant) — password: PA@hospital2025");
} else {
    warn("User 'pa_user' already exists — skipping");
}

step('Creating sample MO user (mo_user)...');
$moUser = wire('users')->get('mo_user');
if (!$moUser || !$moUser->id) {
    $moUser = new User();
    $moUser->name  = 'mo_user';
    $moUser->email = 'mo@hospital.local';
    $moUser->pass  = 'MO@hospital2025';
    $moUser->addRole(wire('roles')->get('medical-officer'));
    wire('users')->save($moUser);
    ok("Created user: mo_user (role: medical-officer) — password: MO@hospital2025");
} else {
    warn("User 'mo_user' already exists — skipping");
}

// ─── SUMMARY ─────────────────────────────────────────────────────────────────
echo '<h2>✅ PHASE 4 COMPLETE</h2>';
echo '<div style="background:#0f2027;border:1px solid #22d3ee;padding:16px;border-radius:8px;margin-top:20px;">';
echo '<h3 style="color:#22d3ee;margin-top:0;">Roles & Permissions:</h3>';
echo '<table style="width:100%;border-collapse:collapse;color:#94a3b8;">';
echo '<tr style="color:#7dd3fc;"><th style="text-align:left;padding:6px;">Role</th><th style="text-align:left;padding:6px;">Can Do</th><th style="text-align:left;padding:6px;">Cannot Do</th></tr>';
echo '<tr><td style="padding:6px;">Physician Assistant</td><td style="padding:6px;">Create patient, create case, edit header</td><td style="padding:6px;">Access diagnosis, examination, procedures, operation notes, discharge</td></tr>';
echo '<tr><td style="padding:6px;">Medical Officer</td><td style="padding:6px;">Full clinical access — all sections</td><td style="padding:6px;">Nothing restricted</td></tr>';
echo '</table>';
echo '<br><p style="color:#86efac;font-weight:bold;">▶ Next: Run phase5_timeline.php to build the Timeline UI (Case Management View)</p>';
echo '</div>';
?>
</body>
</html>
