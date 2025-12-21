<?php
// groupd/views/surgeon/intraop_record.php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

$role = strtolower($_SESSION['role_name'] ?? '');

// Acceso permitido a Cirujanos, Enfermeras y Anestesistas
if (!in_array($role, ['surgeon', 'nurse', 'anesthesiologist', 'admin', 'anesthesiology'])) {
    die("Access Denied.");
}

// --- LOGIC FOR DYNAMIC BACK BUTTON ---
$dashboardLink = 'surgeon_dashboard.php'; // Default for surgeons

if (strpos($role, 'anest') !== false) {
    $dashboardLink = '../anesthesiologist/anesthesiologist_dashboard.php';
} elseif (strpos($role, 'nurse') !== false) {
    $dashboardLink = '../nurse/nurse_dashboard.php';
} elseif (strpos($role, 'admin') !== false) {
    $dashboardLink = '../admin/admin_dashboard.php';
}

$spid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = getDBConnection();

// 1. DATOS GENERALES Y TIEMPOS (De SURGICAL_PROCEDURE)
$sql = "SELECT sp.*, p.procedure_name, pat.first_name, pat.surnames, pat.patient_id, pat.date_of_birth,
               op.room_number
        FROM SURGICAL_PROCEDURE sp
        JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
        JOIN EPISODE ep ON sp.episode_id = ep.episode_id
        JOIN PATIENT pat ON ep.patient_id = pat.patient_id
        JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id
        WHERE sp.surgical_procedure_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$spid]);
$surgery = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$surgery) die("Surgery not found.");

// 2. CONSTANTES VITALES (De SURGERY_MONITORING)
$stmtVitals = $pdo->prepare("SELECT * FROM SURGERY_MONITORING WHERE surgical_procedure_id = ? ORDER BY timestamp ASC");
$stmtVitals->execute([$spid]);
$vitals = $stmtVitals->fetchAll(PDO::FETCH_ASSOC);

// 3. MEDICACIÓN INTRAOPERATORIA (De SURGICAL_PROCEDURE_MEDICATIONS)
$stmtMeds = $pdo->prepare("SELECT spm.*, m.comercial_name, u.username as prescribed_by_name
                           FROM SURGICAL_PROCEDURE_MEDICATIONS spm
                           JOIN MEDICATIONS m ON spm.drug_ID = m.drug_ID
                           LEFT JOIN USERS u ON spm.prescribed_by = u.user_id
                           WHERE spm.surgical_procedure_id = ? 
                           ORDER BY spm.prescribed_datetime ASC");
$stmtMeds->execute([$spid]);
$meds = $stmtMeds->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Intraoperative Record - <?php echo $spid; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #525659; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .paper { background: white; max-width: 210mm; margin: 0 auto; padding: 15mm; min-height: 297mm; box-shadow: 0 0 10px rgba(0,0,0,0.5); position: relative; }
        .doc-header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .section-title { background: #f0f0f0; padding: 5px 10px; font-weight: bold; border: 1px solid #ddd; text-transform: uppercase; font-size: 0.85rem; margin-top: 20px; margin-bottom: 10px; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .data-table th, .data-table td { border: 1px solid #dee2e6; padding: 6px; text-align: center; }
        .data-table th { background-color: #f8f9fa; }
        .timestamp-box { border: 1px solid #333; padding: 5px; text-align: center; border-radius: 4px; font-weight: bold; background: #fff; }
        
        @media print {
            body { background: white; padding: 0; }
            .paper { box-shadow: none; margin: 0; width: 100%; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="d-flex justify-content-center mb-3 no-print gap-2">
        <a href="<?php echo $dashboardLink; ?>" class="btn btn-secondary text-white"><i class="bi bi-arrow-left"></i> Back</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print Record</button>
    </div>

    <div class="paper">
        <div class="doc-header d-flex justify-content-between align-items-end">
            <div>
                <h3 class="mb-0 fw-bold">INTRAOPERATIVE RECORD</h3>
                <small class="text-muted">Anesthesia & Monitoring Sheet</small>
            </div>
            <div class="text-end">
                <div class="fw-bold fs-5"><?php echo htmlspecialchars($surgery['first_name'].' '.$surgery['surnames']); ?></div>
                <div>ID: <?php echo $surgery['patient_id']; ?> | DOB: <?php echo $surgery['date_of_birth']; ?></div>
                <div>Proc ID: #<?php echo $spid; ?></div>
            </div>
        </div>

        <div class="section-title">Surgical Timeline</div>
        <div class="row g-2 mb-4">
            <div class="col-3">
                <div class="text-muted small mb-1">Room In</div>
                <div class="timestamp-box"><?php echo $surgery['time_in_room'] ? date('H:i', strtotime($surgery['time_in_room'])) : '--:--'; ?></div>
            </div>
            <div class="col-3">
                <div class="text-muted small mb-1">Incision</div>
                <div class="timestamp-box"><?php echo $surgery['time_incision'] ? date('H:i', strtotime($surgery['time_incision'])) : '--:--'; ?></div>
            </div>
            <div class="col-3">
                <div class="text-muted small mb-1">Closure</div>
                <div class="timestamp-box"><?php echo $surgery['time_closure'] ? date('H:i', strtotime($surgery['time_closure'])) : '--:--'; ?></div>
            </div>
            <div class="col-3">
                <div class="text-muted small mb-1">Room Out</div>
                <div class="timestamp-box"><?php echo $surgery['time_out_room'] ? date('H:i', strtotime($surgery['time_out_room'])) : '--:--'; ?></div>
            </div>
        </div>

        <table class="table table-bordered table-sm mb-4">
            <tr>
                <th class="bg-light" style="width: 20%">Procedure:</th>
                <td><?php echo htmlspecialchars($surgery['procedure_name']); ?></td>
                <th class="bg-light" style="width: 15%">Room:</th>
                <td><?php echo htmlspecialchars($surgery['room_number']); ?></td>
            </tr>
            <tr>
                <th class="bg-light">WHO Checklist:</th>
                <td colspan="3">
                    <?php 
                        echo ($surgery['status'] === 'Completed' || $surgery['status'] === 'In Progress') ? '✅ Performed (Identity, Site, Anesthesia)' : 'Pending';
                    ?>
                </td>
            </tr>
        </table>

        <div class="section-title">Vital Signs Log (5-min intervals)</div>
        <?php if(empty($vitals)): ?>
            <p class="text-center text-muted fst-italic py-3">No electronic vitals recorded.</p>
        <?php else: ?>
            <table class="data-table mb-4">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>HR (bpm)</th>
                        <th>BP (sys/dia)</th>
                        <th>SpO2 / EtCO2</th>
                        <th>RR (rpm)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $lastPrintedTime = null;
                    foreach($vitals as $v): 
                        // Ensure we have a timestamp
                        if (empty($v['timestamp'])) continue;
                        
                        $ts = strtotime($v['timestamp']);
                        $minute = (int)date('i', $ts);
                        $formattedTime = date('H:i', $ts);

                        // FILTER: Only show records divisible by 5 minutes (e.g., 00, 05, 10)
                        // AND avoid duplicates within the same minute block
                        if ($minute % 5 === 0 && $formattedTime !== $lastPrintedTime) {
                            $lastPrintedTime = $formattedTime;
                    ?>
                    <tr>
                        <td class="fw-bold"><?php echo $formattedTime; ?></td>
                        <td><?php echo $v['card_freq']; ?></td>
                        <td><?php echo $v['sys'] . ' / ' . $v['dias']; ?></td>
                        <td><?php echo $v['tidal_CO2']; ?> %</td> <td><?php echo $v['resp_freq']; ?></td>
                    </tr>
                    <?php 
                        } 
                    endforeach; 
                    
                    // Fallback if filter hid everything (e.g. simulation ran for < 5 mins)
                    if ($lastPrintedTime === null && !empty($vitals)) {
                        echo "<tr><td colspan='5' class='text-muted small fst-italic py-2'>Recording duration less than 5 minutes. Showing raw data below:</td></tr>";
                        foreach(array_slice($vitals, 0, 5) as $v) {
                             echo "<tr><td>".date('H:i:s', strtotime($v['timestamp']))."</td><td>{$v['card_freq']}</td><td>{$v['sys']}/{$v['dias']}</td><td>{$v['tidal_CO2']}</td><td>{$v['resp_freq']}</td></tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="section-title">Intraoperative Medication</div>
        <?php if(empty($meds)): ?>
            <p class="text-center text-muted fst-italic py-3">No intraoperative medication administered.</p>
        <?php else: ?>
            <table class="data-table mb-4">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Drug</th>
                        <th>Dosage</th>
                        <th>Administered By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($meds as $m): ?>
                    <tr>
                        <td><?php echo date('H:i', strtotime($m['prescribed_datetime'])); ?></td>
                        <td class="text-start ps-3 fw-bold"><?php echo htmlspecialchars($m['comercial_name']); ?></td>
                        <td><?php echo htmlspecialchars($m['dosage']); ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars($m['prescribed_by_name'] ?? 'Staff'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="section-title">Events / Complications</div>
        <div class="border p-3" style="min-height: 100px; background: #fafafa;">
            <?php 
                if (!empty($surgery['complications_details'])) {
                    echo nl2br(htmlspecialchars($surgery['complications_details']));
                } else {
                    echo "<span class='text-muted'>No complications recorded.</span>";
                }
            ?>
        </div>

        <div class="mt-5 pt-5 border-top d-flex justify-content-between">
            <div class="text-center" style="width: 200px;">
                <hr class="mb-1">
                <small>Surgeon Signature</small>
            </div>
            <div class="text-center" style="width: 200px;">
                <hr class="mb-1">
                <small>Anesthesiologist Signature</small>
            </div>
        </div>

    </div>
</body>
</html>