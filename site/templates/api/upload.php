<?php namespace ProcessWire;

header('Content-Type: application/json');

if (!$user->isLoggedin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'files' => []]);
    return;
}

$caseId = (int) $sanitizer->int($input->post->case_id);
$zone   = $sanitizer->text($input->post->zone);
$case   = $caseId ? $pages->get($caseId) : null;

if (!$case || !$case->id || $case->template->name !== 'admission-record') {
    http_response_code(404);
    echo json_encode(['success' => false, 'files' => []]);
    return;
}

$fieldMap = [
    'clinical-photos'       => 'clinical_images',
    'consent-file'          => 'consent_file',
    'investigation-reports' => 'investigation_files',
];
$fieldName = $fieldMap[$zone] ?? '';

// Guard: field must exist on this page's template and files must be present
if ($fieldName === '' || !$case->template->hasField($fieldName) || empty($_FILES['files'])) {
    echo json_encode(['success' => false, 'files' => []]);
    return;
}

$case->of(false);
$fieldObj = $case->$fieldName;

if (!$fieldObj) {
    // Field is not attached to this template or unavailable in this bootstrap context
    http_response_code(422);
    echo json_encode(['success' => false, 'files' => [], 'error' => "Field '$fieldName' not available"]);
    return;
}

$fileCount = is_array($_FILES['files']['name']) ? count($_FILES['files']['name']) : 0;

for ($i = 0; $i < $fileCount; $i++) {
    if ((int) $_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;

    $tmpName = $_FILES['files']['tmp_name'][$i];
    if (!$tmpName || !is_uploaded_file($tmpName)) continue;

    // Rename to original filename so ProcessWire stores the real name,
    // not the PHP temp name (e.g. "phpAB12CD").
    $originalName = $sanitizer->filename(basename((string) $_FILES['files']['name'][$i]));
    if (!$originalName) continue;

    $renamedTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $originalName;
    if (!move_uploaded_file($tmpName, $renamedTmp)) continue;

    $fieldObj->add($renamedTmp);
    @unlink($renamedTmp);
}

$case->save($fieldName);

// Reload the page to get fresh field data after save
$case = $pages->get($caseId);
$case->of(false);

$uploaded = [];
$savedField = $case->$fieldName;
foreach (($savedField ?: []) as $file) {
    $isImage    = $file->ext && in_array(strtolower($file->ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
    $uploaded[] = [
        'name'  => $file->basename,
        'url'   => $file->url,
        'thumb' => ($isImage && method_exists($file, 'size')) ? $file->size(96, 96)->url : '',
    ];
}

echo json_encode([
    'success' => true,
    'files'   => $uploaded,
]);
