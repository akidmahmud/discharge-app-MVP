<?php namespace ProcessWire;

/**
 * admin-setup.php — One-time DB migration
 * Visit /api/admin-setup/ as superuser to create all admin tables.
 */

if (!$user->isSuperuser()) {
    header('HTTP/1.0 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$db = $database;
$results = [];

function runTable($db, $name, $sql, &$results) {
    try {
        $db->exec($sql);
        $results[] = ['table' => $name, 'status' => 'ok'];
    } catch (\Exception $e) {
        $results[] = ['table' => $name, 'status' => 'error', 'msg' => $e->getMessage()];
    }
}

// ── Tables ───────────────────────────────────────────────────────────────────

runTable($db, 'admin_consultants', "
CREATE TABLE IF NOT EXISTS `admin_consultants` (
  `id`             int(11)                        NOT NULL AUTO_INCREMENT,
  `name`           varchar(200)                   NOT NULL,
  `department`     varchar(200)                   DEFAULT NULL,
  `is_default`     tinyint(1)                     DEFAULT 0,
  `signature_file` varchar(500)                   DEFAULT NULL,
  `status`         enum('active','inactive')      DEFAULT 'active',
  `created_at`     datetime                       DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
", $results);

runTable($db, 'admin_discharge_templates', "
CREATE TABLE IF NOT EXISTS `admin_discharge_templates` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `type`       varchar(50)  NOT NULL,
  `field_key`  varchar(100) DEFAULT NULL,
  `title`      varchar(300) NOT NULL,
  `body`       text         NOT NULL,
  `created_by` int(11)      DEFAULT NULL,
  `status`     enum('active','inactive') DEFAULT 'active',
  `created_at` datetime     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
", $results);
try {
    $db->exec("ALTER TABLE `admin_discharge_templates` ADD COLUMN `field_key` varchar(100) DEFAULT NULL AFTER `type`");
    $results[] = ['table' => 'admin_discharge_templates', 'status' => 'field_key added'];
} catch (\Exception $e) {
    $results[] = ['table' => 'admin_discharge_templates', 'status' => 'field_key exists'];
}
try {
    $db->exec("UPDATE `admin_discharge_templates`
        SET `field_key` = CASE
            WHEN `field_key` IS NOT NULL AND `field_key` <> '' THEN `field_key`
            WHEN REPLACE(LOWER(`type`), '_', '-') = 'history' THEN 'history_present_illness'
            WHEN REPLACE(LOWER(`type`), '_', '-') = 'examination' THEN 'examination_findings'
            WHEN REPLACE(LOWER(`type`), '_', '-') = 'advice' THEN 'follow_up_instructions'
            WHEN REPLACE(LOWER(`type`), '_', '-') = 'operation-note' THEN 'surgical_approach'
            ELSE NULL
        END");
    $results[] = ['table' => 'admin_discharge_templates', 'status' => 'field_key backfilled'];
} catch (\Exception $e) {
    $results[] = ['table' => 'admin_discharge_templates', 'status' => 'field_key backfill skipped', 'msg' => $e->getMessage()];
}

runTable($db, 'admin_workflow_config', "
CREATE TABLE IF NOT EXISTS `admin_workflow_config` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `module_name` varchar(100) NOT NULL,
  `label`       varchar(200) NOT NULL,
  `is_enabled`  tinyint(1)   DEFAULT 1,
  `is_mandatory`tinyint(1)   DEFAULT 0,
  `fields_config` json       DEFAULT NULL,
  `sort_order`  int(11)      DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
", $results);
try {
    $db->exec("ALTER TABLE `admin_workflow_config` ADD COLUMN `fields_config` JSON NULL AFTER `is_mandatory`");
    $results[] = ['table' => 'admin_workflow_config', 'status' => 'fields_config added'];
} catch (\Exception $e) {
    $results[] = ['table' => 'admin_workflow_config', 'status' => 'fields_config exists'];
}

runTable($db, 'admin_rules', "
CREATE TABLE IF NOT EXISTS `admin_rules` (
  `id`               int(11)      NOT NULL AUTO_INCREMENT,
  `condition_field`  varchar(200) NOT NULL,
  `operator`         varchar(50)  NOT NULL,
  `condition_value`  varchar(500) NOT NULL,
  `action_type`      varchar(100) NOT NULL,
  `action_value`     varchar(500) NOT NULL,
  `is_active`        tinyint(1)   DEFAULT 1,
  `created_at`       datetime     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
", $results);

runTable($db, 'admin_discharge_settings', "
CREATE TABLE IF NOT EXISTS `admin_discharge_settings` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`   varchar(100) NOT NULL,
  `setting_value` text         DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
", $results);

runTable($db, 'admin_login_settings', "
CREATE TABLE IF NOT EXISTS `admin_login_settings` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`   varchar(100) NOT NULL,
  `setting_value` text         DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
", $results);

runTable($db, 'admin_role_permissions', "
CREATE TABLE IF NOT EXISTS `admin_role_permissions` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `role_name`  varchar(100) NOT NULL,
  `module`     varchar(100) NOT NULL,
  `can_view`   tinyint(1)   DEFAULT 0,
  `can_edit`   tinyint(1)   DEFAULT 0,
  `can_delete` tinyint(1)   DEFAULT 0,
  `can_approve`tinyint(1)   DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_module` (`role_name`, `module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
", $results);

runTable($db, 'admin_audit_logs', "
CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      DEFAULT NULL,
  `username`   varchar(200) DEFAULT NULL,
  `module`     varchar(100) DEFAULT NULL,
  `action`     varchar(100) DEFAULT NULL,
  `record_id`  int(11)      DEFAULT NULL,
  `field_name` varchar(200) DEFAULT NULL,
  `old_value`  text         DEFAULT NULL,
  `new_value`  text         DEFAULT NULL,
  `ip_address` varchar(50)  DEFAULT NULL,
  `created_at` datetime     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
", $results);

runTable($db, 'admin_system_settings', "
CREATE TABLE IF NOT EXISTS `admin_system_settings` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`   varchar(100) NOT NULL,
  `setting_value` text         DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
", $results);

// ── Seed workflow modules ─────────────────────────────────────────────────────
try {
    $count = (int) $db->query("SELECT COUNT(*) FROM admin_workflow_config")->fetchColumn();
    if ($count === 0) {
        $defaults = [
            ['admission',       'Admission',              1, 1,  1],
            ['diagnosis',       'Diagnosis',              1, 1,  2],
            ['history',         'History',                1, 0,  3],
            ['examination',     'Examination',            1, 0,  4],
            ['investigations',  'Investigations',         1, 0,  5],
            ['ot-plan',         'OT Plan',                1, 0,  6],
            ['preop-print',     'Pre-op Print',           1, 0,  7],
            ['operation-note',  'Operation Note',         1, 0,  8],
            ['hospital-course', 'Hospital Course',        1, 0,  9],
            ['condition',       'Condition at Discharge', 1, 0, 10],
            ['medications',     'Medications',            1, 0, 11],
            ['advice',          'Advice & Follow-up',     1, 0, 12],
            ['discharge-engine','Final Output',           1, 0, 13],
        ];
        $stmt = $db->prepare("INSERT INTO admin_workflow_config (module_name, label, is_enabled, is_mandatory, sort_order) VALUES (?,?,?,?,?)");
        foreach ($defaults as $row) $stmt->execute($row);
        $results[] = ['table' => 'admin_workflow_config', 'status' => 'seeded with defaults'];
    }
} catch (\Exception $e) {
    $results[] = ['table' => 'workflow seed', 'status' => 'error', 'msg' => $e->getMessage()];
}

// ── Seed discharge settings ───────────────────────────────────────────────────
try {
    $count = (int) $db->query("SELECT COUNT(*) FROM admin_discharge_settings")->fetchColumn();
    if ($count === 0) {
        $defaults = [
            ['show_diagnosis',       '1'],
            ['show_history',         '1'],
            ['show_examination',     '1'],
            ['show_investigations',  '1'],
            ['show_operation_note',  '1'],
            ['show_hospital_course', '1'],
            ['show_medications',     '1'],
            ['show_advice',          '1'],
            ['pdf_header',           'Dr. Md. Tawfiq Alam Siddique'],
            ['pdf_footer',           'Clinical Registry — Confidential'],
            ['pdf_font_size',        '12'],
            ['pdf_margin',           '16'],
        ];
        $stmt = $db->prepare("INSERT IGNORE INTO admin_discharge_settings (setting_key, setting_value) VALUES (?,?)");
        foreach ($defaults as $row) $stmt->execute($row);
        $results[] = ['table' => 'admin_discharge_settings', 'status' => 'seeded with defaults'];
    }
} catch (\Exception $e) {
    $results[] = ['table' => 'discharge settings seed', 'status' => 'error', 'msg' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'results' => $results], JSON_PRETTY_PRINT);
exit;
