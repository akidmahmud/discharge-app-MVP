<?php namespace ProcessWire;

header('Content-Type: application/json');

$query = trim($sanitizer->text($input->get->q));
$index = $sanitizer->text($input->get->index ?: 'all');
if (strlen($query) < 2) {
    echo json_encode([]);
    return;
}

$results = [];
$seen = [];
$needle = mb_strtolower($query);
$admissions = $pages->find("template=admission-record, sort=-created, limit=300");

foreach ($admissions as $admission) {
    if (isset($seen[$admission->id])) {
        continue;
    }
    $patient = $admission->parent;
    $primaryProcedure = $pages->get("template=procedure, parent={$admission->id}, sort=proc_date");
    $fields = [
        'name' => $patient && $patient->id ? (string) $patient->title : '',
        'date' => $admission->getUnformatted('admitted_on') ? date('Y-m-d', $admission->getUnformatted('admitted_on')) : '',
        'diagnosis' => $admission->primary_diagnosis_ref && $admission->primary_diagnosis_ref->id ? (string) $admission->primary_diagnosis_ref->title : trim(strip_tags((string) $admission->diagnosis)),
        'procedure' => $primaryProcedure && $primaryProcedure->id ? (string) ($primaryProcedure->proc_name ?: $primaryProcedure->title) : '',
        'implant' => $primaryProcedure && $primaryProcedure->id ? trim((string) $primaryProcedure->implant_details) : '',
        'address' => $patient && $patient->id ? (string) $patient->address : '',
        'phone' => $patient && $patient->id ? trim((string) ($patient->phone . ' ' . $patient->secondary_phone)) : '',
        'id' => trim((string) (($patient && $patient->id ? $patient->patient_id : '') . ' ' . $admission->ip_number)),
        'complaint' => trim(strip_tags((string) $admission->chief_complaint)),
    ];

    $score = 0;
    if ($index !== 'all' && isset($fields[$index])) {
        $haystack = mb_strtolower($fields[$index]);
        if (mb_strpos($haystack, $needle) !== false) {
            $score = ($index === 'name' || $index === 'id') ? 100 : 60;
        }
    } else {
        foreach ($fields as $key => $value) {
            if ($value === '') continue;
            $haystack = mb_strtolower($value);
            if (mb_strpos($haystack, $needle) !== false) {
                $score += ($key === 'name' || $key === 'id') ? 100 : 60;
                continue;
            }
            foreach (explode(' ', $haystack) as $word) {
                $pct = 0;
                similar_text($needle, $word, $pct);
                if ($pct >= 70) {
                    $score += ($key === 'name' || $key === 'id') ? (int)$pct : (int)($pct * 0.5);
                }
            }
        }
    }

    if ($score === 0) {
        continue;
    }

    $seen[$admission->id] = true;
    $results[] = [
        'id' => (int) $admission->id,
        'name' => $patient && $patient->id ? (string) $patient->title : 'Unknown patient',
        'mrn' => (string) $admission->ip_number,
        'ward' => (string) ($admission->ward_room ?: $admission->room_bed),
        'status' => is_object($admission->case_status) && isset($admission->case_status->title) ? (string) $admission->case_status->title : trim((string) $admission->case_status),
        'url' => (string) $admission->url,
        '_score' => $score,
    ];
}

usort($results, function ($a, $b) { return $b['_score'] - $a['_score']; });
$results = array_map(function ($r) { unset($r['_score']); return $r; }, $results);
echo json_encode(array_slice($results, 0, 15));
