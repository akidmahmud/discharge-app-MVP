<?php namespace ProcessWire;

$_isAdminUser = $user->isSuperuser() || $user->hasRole('admin');
$_isConsultantUser = $user->hasRole('consultant');
$_isPhysicianAssistantUser = $user->hasRole('physician-assistant');
$_isMedicalOfficerUser = $user->hasRole('medical-officer');
wire()->set('authRoleFlags', [
    'is_admin' => $_isAdminUser,
    'is_consultant' => $_isConsultantUser,
    'is_physician_assistant' => $_isPhysicianAssistantUser,
    'is_medical_officer' => $_isMedicalOfficerUser,
]);

// ── Shared dashboard permission helper ────────────────────────────────────────
$dashPerms = [];
try {
    $db = wire('database');
    $stmt = $db->query("SELECT role_name, element_key, is_visible FROM admin_dashboard_permissions");
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $dashPerms[$row['role_name']][$row['element_key']] = (bool)$row['is_visible'];
    }
} catch (\Exception $e) {}

$dashPerm = static function (string $elementKey) use ($user, $dashPerms): bool {
    if ($user->isSuperuser()) return true;
    foreach ($user->roles as $r) {
        if ($r->name === 'guest') continue;
        $visible = $dashPerms[$r->name][$elementKey] ?? true;
        if ($visible) return true;
    }
    return false;
};
wire()->set('dashPerm', $dashPerm);

// Logout handler — must run before auth guard
if ($input->get->int('logout') === 1 && $user->isLoggedin()) {
    $session->logout();
    $session->redirect('/?expired=1');
}

// Auth guard — runs before every template file.
// Unauthenticated users are redirected to the login page (/).
// Exempt: home template (IS the login page), ProcessWire admin, and API endpoints.
$_isAdmin = strpos($page->url, $config->urls->admin) === 0;
$_isHome  = $page->template->name === 'home';
$_isApi   = strpos($page->url, '/api/') !== false;

if ($user->isLoggedin()) {
    $timeoutSeconds = 60 * 60 * 8;
    $lastActivity = (int) $session->get('auth_last_activity');
    if ($lastActivity > 0 && (time() - $lastActivity) > $timeoutSeconds) {
        $session->logout();
        $session->redirect('/?expired=1');
    }

    $session->set('auth_user_id', (int) $user->id);
    $session->set('auth_role', $_isAdminUser ? 'admin' : ($_isConsultantUser ? 'consultant' : ($_isMedicalOfficerUser ? 'medical-officer' : ($_isPhysicianAssistantUser ? 'physician-assistant' : 'clinical-user'))));
    if (!$session->get('auth_login_ts')) {
        $session->set('auth_login_ts', time());
    }
    $session->set('auth_last_activity', time());
}

if (!$user->isLoggedin() && !$_isHome && !$_isAdmin && !$_isApi) {
    $session->redirect('/?unauthorized=1');
}
