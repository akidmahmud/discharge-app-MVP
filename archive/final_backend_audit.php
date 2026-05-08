<?php namespace ProcessWire;
include(__DIR__ . '/index.php');

$output = "🔴 FINAL BACKEND AUDIT REPORT\n";
$output .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

// STEP 1: DATABASE STRUCTURE
$output .= "--- STEP 1: DATABASE STRUCTURE ---\n";
$templatesToCheck = ['procedure', 'operation-note', 'investigation'];
$fieldsToCheck = ['proc_date', 'surgery_date', 'case_status', 'investigation_date', 'review_date'];

$output .= "## TEMPLATES\n";
foreach ($templatesToCheck as $tName) {
    $tpl = wire('templates')->get($tName);
    if ($tpl) {
        $output .= "✅ $tName: YES\n";
        $fields = [];
        foreach ($tpl->fieldgroup as $f) {
            $fields[] = $f->name;
        }
        $output .= "   Fields: " . implode(', ', $fields) . "\n";
    } else {
        $output .= "❌ $tName: NO\n";
    }
}

$output .= "\n## FIELDS\n";
foreach ($fieldsToCheck as $fName) {
    $f = wire('fields')->get($fName);
    if ($f) {
        $output .= "✅ $fName: YES\n";
    } else {
        $output .= "❌ $fName: NO\n";
    }
}

// STEP 2: PAGE STRUCTURE
$output .= "\n--- STEP 2: PAGE STRUCTURE ---\n";
$patients = wire('pages')->get('/patients/');
$search = wire('pages')->get('/search/');

$output .= "## /patients/\n";
if ($patients->id) {
    $output .= "✅ /patients/: YES\n";
    $count = wire('pages')->count("parent=$patients");
    $output .= "   Children count: $count\n";
    if ($count > 0) {
        $output .= "   Sample patients: ";
        $sample = wire('pages')->find("parent=$patients, limit=3");
        $names = [];
        foreach($sample as $s) $names[] = $s->title . " (ID: " . $s->id . ")";
        $output .= implode(', ', $names) . "\n";
    }
} else {
    $output .= "❌ /patients/: NO\n";
}

$output .= "\n## /search/\n";
if ($search->id) {
    $output .= "✅ /search/: YES\n";
    $output .= "   Template: " . $search->template->name . "\n";
    $output .= "   Template File: " . ($search->template->filenameExists() ? "EXISTS" : "MISSING") . "\n";
} else {
    $output .= "❌ /search/: NO\n";
}

// STEP 3: CODE + DATA INTEGRATION
$output .= "\n--- STEP 3: CODE + DATA INTEGRATION ---\n";
$procPages = wire('pages')->find("template=procedure|operation-note|investigation, limit=5");
if ($procPages->count) {
    $output .= "✅ Related pages exist: YES (" . $procPages->count . " found)\n";
    foreach ($procPages as $p) {
        $output .= "   - Page: {$p->path} (Parent: {$p->parent->path}, Template: {$p->template->name})\n";
        foreach ($fieldsToCheck as $f) {
            if ($p->hasField($f)) {
                $val = $p->getUnformatted($f);
                $output .= "     $f: " . (is_null($val) ? "NULL" : $val) . "\n";
            }
        }
    }
} else {
    $output .= "❌ Related pages exist: NO\n";
}

// STEP 4: DATETIME (RUNTIME)
$output .= "\n--- STEP 4: DATETIME (RUNTIME) ---\n";
$dateCheck = wire('pages')->findOne("template=procedure|operation-note|investigation, " . implode('|', $fieldsToCheck) . ">0");
if ($dateCheck->id) {
    $output .= "✅ Found page with date data: {$dateCheck->path}\n";
    foreach ($fieldsToCheck as $f) {
        if ($dateCheck->hasField($f)) {
            $val = $dateCheck->get($f); // Formatted
            $raw = $dateCheck->getUnformatted($f);
            $output .= "   $f: Raw=$raw, Formatted=$val\n";
            if ($val && (strpos($val, "1970") !== false || strpos($val, "1969") !== false)) {
                 $output .= "   ⚠️ WARNING: 1970/1969 detected in formatted output!\n";
            }
        }
    }
} else {
    $output .= "❓ No pages found with date values > 0 to verify.\n";
}

// STEP 5: SEARCH SYSTEM (RUNTIME)
$output .= "\n--- STEP 5: SEARCH SYSTEM (RUNTIME) ---\n";
if ($search->id && $search->template->name == 'search-results') {
    $output .= "✅ Search page exists and uses correct template.\n";
    $tplFile = $search->template->filename;
    if (file_exists($tplFile)) {
        $content = file_get_contents($tplFile);
        if (strpos($content, '$pages->find') !== false) {
            $output .= "✅ Search logic (pages->find) detected in template file.\n";
        } else {
            $output .= "❌ Search logic NOT detected in template file.\n";
        }
    } else {
        $output .= "❌ Template file missing: $tplFile\n";
    }
} else {
    $output .= "❌ Search page or template missing.\n";
}

// STEP 6: PATIENT FLOW INFRASTRUCTURE
$output .= "\n--- STEP 6: PATIENT FLOW INFRASTRUCTURE ---\n";
$patientTpl = wire('templates')->get('patient');
$admissionTpl = wire('templates')->get('admission');
$procedureTpl = wire('templates')->get('procedure');

if ($patientTpl && $admissionTpl && $procedureTpl) {
    $output .= "✅ All flow templates exist.\n";
    
    $pParents = wire('templates')->get($patientTpl->id)->parentTemplates;
    $aParents = wire('templates')->get($admissionTpl->id)->parentTemplates;
    $prParents = wire('templates')->get($procedureTpl->id)->parentTemplates;

    $output .= "   Patient allowed parents: " . (empty($pParents) ? "ANY" : implode(', ', array_map(fn($id) => wire('templates')->get($id)->name, $pParents))) . "\n";
    $output .= "   Admission allowed parents: " . (empty($aParents) ? "ANY" : implode(', ', array_map(fn($id) => wire('templates')->get($id)->name, $aParents))) . "\n";
    $output .= "   Procedure allowed parents: " . (empty($prParents) ? "ANY" : implode(', ', array_map(fn($id) => wire('templates')->get($id)->name, $prParents))) . "\n";
    
    // Check if any existing flow exists
    $sampleProc = wire('pages')->findOne("template=procedure");
    if ($sampleProc->id) {
        $sampleAdm = $sampleProc->parent;
        $samplePat = $sampleAdm->parent;
        $output .= "   EXISTING FLOW DETECTED:\n";
        $output .= "   Patient: {$samplePat->path} (Tpl: {$samplePat->template->name})\n";
        $output .= "   Admission: {$sampleAdm->path} (Tpl: {$sampleAdm->template->name})\n";
        $output .= "   Procedure: {$sampleProc->path} (Tpl: {$sampleProc->template->name})\n";
    } else {
        $output .= "   No existing procedure pages to verify full flow.\n";
    }
} else {
    $output .= "❌ Missing flow templates (Patient: " . ($patientTpl?'OK':'NO') . ", Admission: " . ($admissionTpl?'OK':'NO') . ", Procedure: " . ($procedureTpl?'OK':'NO') . ")\n";
}

echo $output;
file_put_contents(__DIR__ . '/final_audit_results.txt', $output);
