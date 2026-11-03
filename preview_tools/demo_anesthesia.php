<?php
declare(strict_types=1);

// Local, CLI-only mock monitoring for screenshots. Never run against clinical data.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/config.php';

if (DB_NAME !== 'imtm2025_grupd' || !preg_match('/^(127\.0\.0\.1|localhost)(:\d+)?$/', DB_HOST_LOCAL)) {
    fwrite(STDERR, "Refusing to write outside the local demonstration database.\n");
    exit(1);
}
$mode = $argv[1] ?? '';
if (!in_array($mode, ['seed', 'stream'], true)) {
    fwrite(STDERR, "Usage: php demo_anesthesia.php seed|stream\n");
    exit(1);
}
$pdo = getDBConnection();
$demoId = 900001;
$sourceId = 173;
$marker = '[MOCK ANESTHESIA DEMO]';
$points = [
    // One historical sample per minute; NIBP is measured every five minutes.
    [78, 118, 76, 98], [79, null, null, 98], [80, null, null, 99],
    [79, null, null, 98], [78, null, null, 98], [77, 117, 75, 98],
    [78, null, null, 99], [79, null, null, 98], [80, null, null, 98],
    [80, null, null, 99], [81, 120, 77, 98], [80, null, null, 98],
    [79, null, null, 98], [78, null, null, 99], [79, null, null, 98],
    [80, 121, 78, 98], [81, null, null, 98], [80, null, null, 99],
    [79, null, null, 98], [78, null, null, 98], [79, 119, 76, 99],
];
try {
    if ($mode === 'seed') {
        $column = $pdo->query("SHOW COLUMNS FROM SURGERY_MONITORING LIKE 'oxygen_saturation'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE SURGERY_MONITORING ADD COLUMN oxygen_saturation TINYINT UNSIGNED NULL AFTER dias");
        }
        $pdo->beginTransaction();
        $source = $pdo->prepare("SELECT surgical_procedure_id FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = ?");
        $source->execute([$sourceId]);
        if (!$source->fetch()) {
            throw new RuntimeException("Source case $sourceId is missing. Import the repository SQL first.");
        }
        $existing = $pdo->prepare("SELECT notes FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = ? FOR UPDATE");
        $existing->execute([$demoId]);
        $row = $existing->fetch();
        if ($row && !str_contains((string)$row['notes'], $marker)) {
            throw new RuntimeException("Case ID $demoId belongs to non-demo data.");
        }
        if (!$row) {
            $insert = $pdo->prepare(
                "INSERT INTO SURGICAL_PROCEDURE
                 (surgical_procedure_id, procedure_id, episode_id, date_time, status, notes,
                  operating_room_id, patient_id, asa_score, priority_level, time_in_room,
                  time_intubation, time_incision)
                 SELECT ?, procedure_id, episode_id, DATE_SUB(NOW(), INTERVAL 25 MINUTE),
                        'In Progress', ?, operating_room_id, patient_id, asa_score,
                        priority_level, DATE_SUB(NOW(), INTERVAL 21 MINUTE),
                        DATE_SUB(NOW(), INTERVAL 18 MINUTE), DATE_SUB(NOW(), INTERVAL 12 MINUTE)
                 FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = ?"
            );
            $insert->execute([$demoId, $marker . ' Synthetic vital signs for local preview only.', $sourceId]);
        } else {
            $pdo->prepare(
                "UPDATE SURGICAL_PROCEDURE SET date_time = DATE_SUB(NOW(), INTERVAL 25 MINUTE),
                 status = 'In Progress', time_in_room = DATE_SUB(NOW(), INTERVAL 21 MINUTE),
                 time_out_room = NULL, time_incision = DATE_SUB(NOW(), INTERVAL 12 MINUTE),
                 time_closure = NULL, time_intubation = DATE_SUB(NOW(), INTERVAL 18 MINUTE),
                 time_extubation = NULL
                 WHERE surgical_procedure_id = ?"
            )->execute([$demoId]);
        }
        $pdo->prepare(
            "INSERT IGNORE INTO SURGICAL_PROCEDURE_ANESTHESIOLOGISTS
             (surgical_procedure_id, anesthesiologist_id, role_in_surgery)
             SELECT ?, anesthesiologist_id, role_in_surgery
             FROM SURGICAL_PROCEDURE_ANESTHESIOLOGISTS WHERE surgical_procedure_id = ?"
        )->execute([$demoId, $sourceId]);
        $pdo->prepare("DELETE FROM SURGICAL_PROCEDURE_MEDICATIONS WHERE surgical_procedure_id = ?")->execute([$demoId]);
        $pdo->prepare("DELETE FROM SURGERY_MONITORING WHERE surgical_procedure_id = ?")->execute([$demoId]);
        $now = new DateTimeImmutable((string)$pdo->query('SELECT NOW()')->fetchColumn());
        $insertVital = $pdo->prepare(
            "INSERT INTO SURGERY_MONITORING
             (surgical_procedure_id, card_freq, sys, dias, oxygen_saturation, timestamp, observations)
             VALUES (?, ?, ?, ?, ?, ?, 'Demo stream')"
        );
        foreach ($points as $index => $point) {
            $time = $now->modify('-' . ((count($points) - 1 - $index) * 60) . ' seconds')->format('Y-m-d H:i:s');
            $insertVital->execute([$demoId, ...$point, $time]);
        }
        $insertEvent = $pdo->prepare(
            "INSERT INTO SURGERY_MONITORING (surgical_procedure_id, timestamp, observations) VALUES (?, ?, ?)"
        );
        $insertEvent->execute([$demoId, $now->modify('-20 minutes')->format('Y-m-d H:i:s'), '[DEMO] Monitoring started; synthetic values only']);
        $insertEvent->execute([$demoId, $now->modify('-6 minutes')->format('Y-m-d H:i:s'), '[POC] Demo blood gas panel recorded']);
        $pdo->commit();
        echo "Seeded synthetic live case $demoId with " . count($points) . " vital samples.\n";
        exit;
    }

    echo "Streaming synthetic vitals for case $demoId; stop this process to end the demo.\n";
    $insertVital = $pdo->prepare(
        "INSERT INTO SURGERY_MONITORING
         (surgical_procedure_id, card_freq, sys, dias, oxygen_saturation, timestamp, observations)
         VALUES (?, ?, ?, ?, ?, NOW(), 'Demo stream')"
    );
    while (true) {
        $case = $pdo->prepare("SELECT status, notes FROM SURGICAL_PROCEDURE WHERE surgical_procedure_id = ?");
        $case->execute([$demoId]);
        $row = $case->fetch();
        if (!$row || !str_contains((string)$row['notes'], $marker) || $row['status'] !== 'In Progress') {
            echo "Demo case is no longer in progress; stream stopped.\n";
            break;
        }
        $latest = $pdo->prepare(
            "SELECT card_freq, oxygen_saturation FROM SURGERY_MONITORING
             WHERE surgical_procedure_id = ? AND card_freq IS NOT NULL
             ORDER BY timestamp DESC, monitoring_id DESC LIMIT 1"
        );
        $latest->execute([$demoId]);
        $last = $latest->fetch(PDO::FETCH_ASSOC);
        $hr = max(72, min(86, (int)($last['card_freq'] ?? 79) + random_int(-1, 1)));
        $spo2 = max(97, min(99, (int)($last['oxygen_saturation'] ?? 98) + random_int(-1, 1)));

        $latestBp = $pdo->prepare(
            "SELECT sys, dias, timestamp FROM SURGERY_MONITORING
             WHERE surgical_procedure_id = ? AND sys IS NOT NULL AND dias IS NOT NULL
             ORDER BY timestamp DESC, monitoring_id DESC LIMIT 1"
        );
        $latestBp->execute([$demoId]);
        $bp = $latestBp->fetch(PDO::FETCH_ASSOC);
        $dbNow = (string)$pdo->query('SELECT NOW()')->fetchColumn();
        $bpDue = !$bp || strtotime($dbNow) - strtotime((string)$bp['timestamp']) >= 300;
        $sys = $bpDue ? max(112, min(128, (int)($bp['sys'] ?? 119) + random_int(-2, 2))) : null;
        $dias = $bpDue ? max(70, min(82, (int)($bp['dias'] ?? 76) + random_int(-2, 2))) : null;
        $insertVital->execute([$demoId, $hr, $sys, $dias, $spo2]);
        $pdo->prepare(
            "DELETE FROM SURGERY_MONITORING WHERE surgical_procedure_id = ? AND observations = 'Demo stream'
             AND timestamp < DATE_SUB(NOW(), INTERVAL 3 HOUR)"
        )->execute([$demoId]);
        sleep(3);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Demo fixture failed: " . $e->getMessage() . "\n");
    exit(1);
}
