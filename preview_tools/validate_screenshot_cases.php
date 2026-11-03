<?php
declare(strict_types=1);

// Fail closed before taking public screenshots. Checks internal consistency of
// synthetic fixtures, not clinical validity or patient-specific suitability.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/config.php';
if (getenv('SURGERY_PREVIEW_FIXTURES') !== '1' || DB_TYPE !== 'local'
    || DB_NAME !== 'imtm2025_grupd' || DB_HOST_LOCAL !== '127.0.0.1:3308') {
    fwrite(STDERR, "Refusing to validate a database outside the isolated preview.\n");
    exit(1);
}

function demand(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function seconds_between(string $a, string $b): int {
    return (new DateTimeImmutable($b))->getTimestamp() - (new DateTimeImmutable($a))->getTimestamp();
}

try {
    $pdo = getDBConnection();
    $getCase = $pdo->prepare('SELECT * FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = ?');
    $cases = [];
    foreach ([119, 173, 900001, 900002, 900003] as $id) {
        $getCase->execute([$id]);
        $cases[$id] = $getCase->fetch(PDO::FETCH_ASSOC);
        demand((bool)$cases[$id], "Missing screenshot case $id");
    }

    $archived = $cases[173];
    $nurse = $cases[900002];
    $live = $cases[900001];
    $pending = $cases[900003];
    demand($archived['status'] === 'Completed' && $archived['procedure_id'] == 7,
        'Archived knee case has the wrong status or procedure.');
    demand($nurse['status'] === 'In Progress' && $nurse['procedure_id'] == 7
        && $live['status'] === 'In Progress' && $live['procedure_id'] == 7,
        'Live knee cases have the wrong status or procedure.');
    demand($archived['patient_id'] === $nurse['patient_id']
        && $archived['patient_id'] === $live['patient_id'],
        'Linked demo patient and procedure no longer agree.');
    foreach ([$archived, $nurse, $live] as $case) {
        demand(seconds_between($case['time_in_room'], $case['time_intubation']) > 0
            && seconds_between($case['time_intubation'], $case['time_incision']) > 0,
            'Room entry, induction, and incision are out of order.');
    }
    demand(seconds_between($archived['time_incision'], $archived['time_closure']) >= 3600
        && seconds_between($archived['time_incision'], $archived['time_closure']) <= 10800,
        'Archived knee-operation duration is outside the documented example range.');
    demand(seconds_between($archived['time_closure'], $archived['time_extubation']) > 0
        && seconds_between($archived['time_extubation'], $archived['time_out_room']) > 0,
        'Archived closure, extubation, and room exit are out of order.');
    demand((int)$archived['blood_loss_ml'] === 200
        && !str_contains((string)$archived['anesthesiology_report'], '0.45 mg'),
        'Archived report still contains a conflicting demo value.');
    foreach ([$nurse, $live] as $case) {
        demand($case['time_closure'] === null && $case['time_out_room'] === null,
            'A live case is already closed in the database.');
    }
    $checks = json_decode((string)$nurse['safety_checklist_data'], true, 512, JSON_THROW_ON_ERROR);
    demand(($checks['patient'] ?? false) && ($checks['anesthesia'] ?? false)
        && ($checks['timeout'] ?? false) && !($checks['counts'] ?? true)
        && !($checks['specimen'] ?? true),
        'Nurse safety-checklist phases contradict the live case.');

    $medication = $pdo->query('SELECT drug_ID, prescribed_datetime, dosage
        FROM SURGICAL_PROCEDURE_MEDICATIONS WHERE surgical_procedure_id = 900002
        ORDER BY prescribed_datetime')->fetchAll(PDO::FETCH_ASSOC);
    demand(count($medication) === 2, 'The live nurse case needs exactly two demo medication records.');
    foreach ($medication as $entry) {
        demand(seconds_between($nurse['time_in_room'], $entry['prescribed_datetime']) >= 0
            && seconds_between($entry['prescribed_datetime'], $nurse['time_incision']) > 0,
            'Demo medication time falls outside the intended phase.');
    }
    demand($medication[0]['drug_ID'] === 'D0026' && $medication[0]['dosage'] === '100mg'
        && $medication[1]['drug_ID'] === 'D0034' && $medication[1]['dosage'] === '50mcg',
        'Medication log no longer matches the displayed synthetic scenario.');

    $vitals = $pdo->query('SELECT card_freq, sys, dias, oxygen_saturation, timestamp
        FROM SURGERY_MONITORING WHERE surgical_procedure_id = 900002
        ORDER BY timestamp')->fetchAll(PDO::FETCH_ASSOC);
    demand(count($vitals) === 9, 'Nurse monitoring history is incomplete.');
    $bpTimes = [];
    foreach ($vitals as $row) {
        demand((int)$row['card_freq'] >= 72 && (int)$row['card_freq'] <= 86
            && (int)$row['oxygen_saturation'] >= 97
            && (int)$row['oxygen_saturation'] <= 99,
            'Displayed HR or SpO2 is outside the synthetic example.');
        if ($row['sys'] !== null || $row['dias'] !== null) {
            demand((int)$row['sys'] >= 112 && (int)$row['sys'] <= 128
                && (int)$row['dias'] >= 70 && (int)$row['dias'] <= 82,
                'Displayed BP is outside the synthetic example.');
            $bpTimes[] = $row['timestamp'];
        }
    }
    demand(count($bpTimes) === 5, 'Nurse BP history should contain five intermittent readings.');
    for ($i = 1; $i < count($bpTimes); $i++) {
        demand(seconds_between($bpTimes[$i - 1], $bpTimes[$i]) === 300,
            'Nurse BP readings are not five minutes apart.');
    }
    demand((int)$pdo->query('SELECT COALESCE(SUM(amount_used),0)
        FROM SURGICAL_MATERIAL_USAGE WHERE surgical_procedure_id = 900002')->fetchColumn() === 12,
        'Material usage does not match the inventory screenshot.');

    demand($pending['status'] === 'Pending' && $pending['procedure_id'] == 30
        && strtotime($pending['date_time']) > time() && $pending['time_in_room'] === null
        && $pending['time_incision'] === null && $pending['time_closure'] === null,
        'Elective scheduler case is no longer a future, unstarted case.');
    $historical = $cases[119];
    demand($historical['status'] === 'Completed' && $historical['procedure_id'] == 1
        && seconds_between($historical['time_incision'], $historical['time_closure']) > 0
        && seconds_between($historical['time_closure'], $historical['time_out_room']) > 0,
        'Patient portal history has an inconsistent completed case.');
    $consent = $pdo->prepare('SELECT consent_date FROM PATIENT_CONSENTS WHERE patient_id = ?
        AND episode_id = ? AND procedure_id = ? AND signed = 1 AND deleted_at IS NULL');
    $consent->execute([$historical['patient_id'], $historical['episode_id'], $historical['procedure_id']]);
    $consentDates = $consent->fetchAll(PDO::FETCH_COLUMN);
    demand(count($consentDates) > 0, 'Completed patient case has no signed consent.');
    foreach ($consentDates as $date) {
        demand($date < substr($historical['date_time'], 0, 10),
            'Demo consent date must precede the operation day.');
    }
    echo "PASS: synthetic screenshot cases, timelines, monitoring, medications, consent, and inventory agree.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Screenshot validation failed: " . $e->getMessage() . "\n");
    exit(1);
}
