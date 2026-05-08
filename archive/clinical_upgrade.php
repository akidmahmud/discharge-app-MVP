<?php namespace ProcessWire;
include 'index.php';

// 1. Install InputfieldCKEditor if not already
if(!$modules->isInstalled('InputfieldCKEditor')) {
    $modules->install('InputfieldCKEditor');
}

// 2. Upgrade Fields to CKEditor
$richFields = ['diagnosis', 'history_complaints', 'examination_findings', 'operation_notes', 'course_in_hospital'];
foreach($richFields as $fn) {
    $f = $fields->get($fn);
    if($f) {
        $f->type = $modules->get('FieldtypeTextarea');
        $f->inputfieldClass = 'InputfieldCKEditor';
        // Set standard clinical toolbar
        $f->contentType = FieldtypeTextarea::contentTypeHTML;
        $f->save();
        echo "Upgraded $fn to CKEditor.\n";
    }
}

// 3. Add Images Field
$imgField = $fields->get('clinical_images');
if(!$imgField) {
    $imgField = new Field();
    $imgField->type = $modules->get('FieldtypeImage');
    $imgField->name = 'clinical_images';
    $imgField->label = 'Clinical Photos';
    $imgField->extensions = 'jpg jpeg png gif webp';
    $imgField->maxFiles = 0;
    $imgField->description = 'Upload pre-op, intra-op, and post-op photos.';
    $imgField->save();
    echo "Created clinical_images field.\n";
}

// Add images to Admission and Procedures
$tAdm = $templates->get('admission-record');
if(!$tAdm->fieldgroup->has($imgField)) {
    $tAdm->fieldgroup->add($imgField);
    $tAdm->fieldgroup->save();
}

$rFg = $fieldgroups->get('repeater_procedures');
if($rFg && !$rFg->has($imgField)) {
    $rFg->add($imgField);
    $rFg->save();
}

// 4. Organize Admin UX (Tabs)
// We create "Virtual" fieldgroup markers for tabs if we want, 
// but in PW we usually do this via the Template settings or FieldsetTab fields.

function createTab($name, $label) {
    global $fields, $modules;
    $f = $fields->get($name);
    if(!$f) {
        $f = new Field();
        $f->type = $modules->get('FieldtypeFieldsetTabOpen');
        $f->name = $name;
        $f->label = $label;
        $f->save();
    }
    return $f;
}

$tab1 = createTab('tab_history', 'History & Exam');
$tab2 = createTab('tab_operation', 'Operative Details');
$tab3 = createTab('tab_discharge', 'Discharge & Course');
$tabClose = $fields->get('tab_close') ?: new Field();
if(!$tabClose->id) {
    $tabClose->type = $modules->get('FieldtypeFieldsetClose');
    $tabClose->name = 'tab_close';
    $tabClose->label = 'Close Tab';
    $tabClose->save();
}

// Re-order admission-record fields
$fg = $tAdm->fieldgroup;
$fg->removeAll();
$fg->add($fields->get('title'));
$fg->add($fields->get('ip_number'));
$fg->add($fields->get('admitted_on'));
$fg->add($fields->get('discharged_on'));

$fg->add($tab1);
$fg->add($fields->get('diagnosis'));
$fg->add($fields->get('history_complaints'));
$fg->add($fields->get('examination_findings'));
$fg->add($fields->get('radiology_report'));
$fg->add($tabClose);

$fg->add($tab2);
$fg->add($fields->get('procedures'));
$fg->add($fields->get('clinical_images'));
$fg->add($tabClose);

$fg->add($tab3);
$fg->add($fields->get('course_in_hospital'));
$fg->add($fields->get('condition_at_discharge'));
$fg->add($tabClose);

$fg->save();

echo "Clinical Upgrade Implemented: CKEditor enabled, Tabs organized, Images added.\n";
