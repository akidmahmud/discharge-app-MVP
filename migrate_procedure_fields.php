<?php
// Run once via browser: http://discharge-app.test/migrate_procedure_fields.php
// Delete after running.

$pdo = new PDO('mysql:host=localhost;dbname=discharge_app;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$log = [];

// ── Helpers ──────────────────────────────────────────────────────────────────

function fieldId(PDO $pdo, string $name): ?int {
    $s = $pdo->prepare("SELECT id FROM fields WHERE name = ?");
    $s->execute([$name]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) $row['id'] : null;
}

function fieldgroupId(PDO $pdo, string $templateName): ?int {
    $s = $pdo->prepare("SELECT fieldgroups_id FROM templates WHERE name = ?");
    $s->execute([$templateName]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) $row['fieldgroups_id'] : null;
}

function addFieldToFieldgroup(PDO $pdo, int $fieldsId, int $fieldgroupsId): void {
    $s = $pdo->prepare("SELECT 1 FROM fieldgroups_fields WHERE fieldgroups_id = ? AND fields_id = ?");
    $s->execute([$fieldgroupsId, $fieldsId]);
    if (!$s->fetch()) {
        $sort = (int) $pdo->query("SELECT COALESCE(MAX(sort),0)+1 FROM fieldgroups_fields WHERE fieldgroups_id = $fieldgroupsId")->fetchColumn();
        $pdo->prepare("INSERT INTO fieldgroups_fields (fieldgroups_id, fields_id, sort) VALUES (?,?,?)")
            ->execute([$fieldgroupsId, $fieldsId, $sort]);
    }
}

function createTextField(PDO $pdo, string $name, string $label, array &$log): int {
    $id = fieldId($pdo, $name);
    if (!$id) {
        $pdo->prepare("INSERT INTO fields (name, label, type, flags, data) VALUES (?,?,'FieldtypeText',0,'{\"maxlength\":255}')")
            ->execute([$name, $label]);
        $id = (int) $pdo->lastInsertId();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `field_{$name}` (
            pages_id INT UNSIGNED NOT NULL,
            data VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (pages_id),
            KEY data (data(255))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $log[] = "Created text field: $name";
    } else {
        $log[] = "Already exists: $name";
    }
    return $id;
}

function createTextareaField(PDO $pdo, string $name, string $label, array &$log): int {
    $id = fieldId($pdo, $name);
    if (!$id) {
        $pdo->prepare("INSERT INTO fields (name, label, type, flags, data) VALUES (?,?,'FieldtypeTextarea',0,'{\"rows\":5}')")
            ->execute([$name, $label]);
        $id = (int) $pdo->lastInsertId();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `field_{$name}` (
            pages_id INT UNSIGNED NOT NULL,
            data MEDIUMTEXT,
            PRIMARY KEY (pages_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $log[] = "Created textarea field: $name";
    } else {
        $log[] = "Already exists: $name";
    }
    return $id;
}

function createIntegerField(PDO $pdo, string $name, string $label, array &$log): int {
    $id = fieldId($pdo, $name);
    if (!$id) {
        $pdo->prepare("INSERT INTO fields (name, label, type, flags, data) VALUES (?,?,'FieldtypeInteger',0,'{}')")
            ->execute([$name, $label]);
        $id = (int) $pdo->lastInsertId();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `field_{$name}` (
            pages_id INT UNSIGNED NOT NULL,
            data INT NOT NULL DEFAULT 0,
            PRIMARY KEY (pages_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $log[] = "Created integer field: $name";
    } else {
        $log[] = "Already exists: $name";
    }
    return $id;
}

function createOptionsField(PDO $pdo, string $name, string $label, array $options, array &$log): int {
    $id = fieldId($pdo, $name);
    if (!$id) {
        $pdo->prepare("INSERT INTO fields (name, label, type, flags, data) VALUES (?,?,'FieldtypeOptions',0,'{}')")
            ->execute([$name, $label]);
        $id = (int) $pdo->lastInsertId();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `field_{$name}` (
            pages_id INT UNSIGNED NOT NULL,
            data INT UNSIGNED NOT NULL,
            PRIMARY KEY (pages_id, data),
            KEY pages_id (pages_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach ($options as $i => $title) {
            $pdo->prepare("INSERT INTO fieldtype_options (fields_id, option_id, title, value, sort) VALUES (?,?,?,?,?)")
                ->execute([$id, $i + 1, $title, '', $i]);
        }
        $log[] = "Created options field: $name (" . implode(', ', $options) . ")";
    } else {
        $log[] = "Already exists: $name";
    }
    return $id;
}

// ── procedure template fields ─────────────────────────────────────────────────
$procFg = fieldgroupId($pdo, 'procedure');
if (!$procFg) die("Template 'procedure' not found.\n");

$procTextFields = [
    'proc_time'             => 'OT Time',
    'implant_details'       => 'Implant Details',
    'anesthesiologist_name' => 'Anesthesiologist Name',
    'surgeon_name'          => 'Surgeon',
];
foreach ($procTextFields as $name => $label) {
    $id = createTextField($pdo, $name, $label, $log);
    addFieldToFieldgroup($pdo, $id, $procFg);
}

$id = createOptionsField($pdo, 'c_arm_required', 'C-Arm Required', ['Yes', 'No'], $log);
addFieldToFieldgroup($pdo, $id, $procFg);

$id = createOptionsField($pdo, 'microscope_required', 'Microscope Required', ['Yes', 'No'], $log);
addFieldToFieldgroup($pdo, $id, $procFg);

// Fix anesthesia_type options to match form (Local, BB, Spinal, GA)
$anesthesiaId = fieldId($pdo, 'anesthesia_type');
if ($anesthesiaId) {
    $pdo->prepare("DELETE FROM fieldtype_options WHERE fields_id = ?")->execute([$anesthesiaId]);
    foreach (['Local', 'BB', 'Spinal', 'GA'] as $i => $title) {
        $pdo->prepare("INSERT INTO fieldtype_options (fields_id, option_id, title, value, sort) VALUES (?,?,?,?,?)")
            ->execute([$anesthesiaId, $i + 1, $title, '', $i]);
    }
    $log[] = "Updated anesthesia_type options: Local, BB, Spinal, GA";
}

// ── admission-record template fields ─────────────────────────────────────────
$admFg = fieldgroupId($pdo, 'admission-record');
if (!$admFg) die("Template 'admission-record' not found.\n");

$id = createIntegerField($pdo, 'pain_scale', 'Pain Scale', $log);
addFieldToFieldgroup($pdo, $id, $admFg);

$id = createTextareaField($pdo, 'medications_on_discharge', 'Medications on Discharge', $log);
addFieldToFieldgroup($pdo, $id, $admFg);

$id = createTextareaField($pdo, 'follow_up_instructions', 'Follow-up Instructions', $log);
addFieldToFieldgroup($pdo, $id, $admFg);

// ─────────────────────────────────────────────────────────────────────────────
echo "<pre style='font-family:monospace;font-size:14px;padding:20px;'>\n";
echo "Migration complete:\n\n";
foreach ($log as $line) {
    echo "  ✓ $line\n";
}
echo "\n⚠  Delete this file after running.\n</pre>\n";
