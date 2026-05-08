<?php namespace ProcessWire;

/**
 * Migration: Add file/image upload fields to admission-record template.
 *
 * Adds:
 *   clinical_images    — FieldtypeImage  — pre/intra/post-op photos
 *   investigation_files — FieldtypeFile  — investigation report uploads
 *   consent_file        — FieldtypeFile  — signed consent documents
 *
 * Run once from the site webroot:
 *   cd H:\laragon\www\discharge-app
 *   php archive/add_upload_fields.php
 */

include 'index.php';

$template = $templates->get('admission-record');
if (!$template) {
    echo "ERROR: admission-record template not found.\n";
    exit(1);
}

$fieldgroup = $template->fieldgroup;

// ── clinical_images (FieldtypeImage) ──────────────────────────────────────────
$f = $fields->get('clinical_images');
if (!$f) {
    $f = new Field();
    $f->type       = $modules->get('FieldtypeImage');
    $f->name       = 'clinical_images';
    $f->label      = 'Clinical Photos';
    $f->extensions = 'jpg jpeg png gif webp bmp';
    $f->maxFiles   = 0;
    $f->description = 'Pre-operative, intra-operative, and post-operative images.';
    $f->save();
    echo "Created field: clinical_images\n";
} else {
    echo "Field already exists: clinical_images\n";
}

if (!$fieldgroup->has($f)) {
    $fieldgroup->add($f);
    $fieldgroup->save();
    echo "Attached clinical_images to admission-record\n";
} else {
    echo "clinical_images already on admission-record\n";
}

// ── investigation_files (FieldtypeFile) ───────────────────────────────────────
$f2 = $fields->get('investigation_files');
if (!$f2) {
    $f2 = new Field();
    $f2->type       = $modules->get('FieldtypeFile');
    $f2->name       = 'investigation_files';
    $f2->label      = 'Investigation Reports';
    $f2->extensions = 'jpg jpeg png gif webp pdf doc docx';
    $f2->maxFiles   = 0;
    $f2->description = 'Lab reports, imaging reports, and investigation documents.';
    $f2->save();
    echo "Created field: investigation_files\n";
} else {
    echo "Field already exists: investigation_files\n";
}

if (!$fieldgroup->has($f2)) {
    $fieldgroup->add($f2);
    $fieldgroup->save();
    echo "Attached investigation_files to admission-record\n";
} else {
    echo "investigation_files already on admission-record\n";
}

// ── consent_file (FieldtypeFile) ──────────────────────────────────────────────
$f3 = $fields->get('consent_file');
if (!$f3) {
    $f3 = new Field();
    $f3->type       = $modules->get('FieldtypeFile');
    $f3->name       = 'consent_file';
    $f3->label      = 'Consent Documents';
    $f3->extensions = 'jpg jpeg png gif webp pdf doc docx';
    $f3->maxFiles   = 0;
    $f3->description = 'Signed consent forms and patient agreement documents.';
    $f3->save();
    echo "Created field: consent_file\n";
} else {
    echo "Field already exists: consent_file\n";
}

if (!$fieldgroup->has($f3)) {
    $fieldgroup->add($f3);
    $fieldgroup->save();
    echo "Attached consent_file to admission-record\n";
} else {
    echo "consent_file already on admission-record\n";
}

echo "\nDone. All upload fields are now on the admission-record template.\n";
