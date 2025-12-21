<?php
// groupd/views/surgeon/clinical_episode.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

// 1. Auth Check & Dashboard Redirect Logic
$controller = new LoginController();
$controller->checkAuth();

$role = strtolower($_SESSION['role_name'] ?? '');

// PERMITIR ACCESO A MULTIPLES ROLES
if (strpos($role, 'surgeon') === false && strpos($role, 'anest') === false && strpos($role, 'admin') === false && strpos($role, 'nurse') === false) {
    die("Access Denied.");
}

// --- LÓGICA DE REDIRECCIÓN DINÁMICA ---
$dashboardLink = 'surgeon_dashboard.php'; // Por defecto para cirujanos

if (strpos($role, 'anest') !== false) {
    $dashboardLink = '../anesthesiologist/anesthesiologist_dashboard.php';
} elseif (strpos($role, 'nurse') !== false) {
    $dashboardLink = '../nurse/nurse_dashboard.php';
} elseif (strpos($role, 'admin') !== false) {
    $dashboardLink = '../admin/admin_dashboard.php';
}
// ---------------------------------------

$pdo = getDBConnection();

// 2. Validate Episode ID
if (!isset($_GET['id']) || empty($_GET['id'])) die("Error: No episode ID provided.");
$episodeId = $_GET['id'];

// 3. Data Queries

// A. Episode Data
$episode = $pdo->prepare("SELECT ep.*, p.first_name, p.surnames, p.date_of_birth, p.gender, p.patient_id as p_code 
                          FROM EPISODE ep JOIN PATIENT p ON ep.patient_id = p.patient_id WHERE ep.episode_id = ?");
$episode->execute([$episodeId]);
$epData = $episode->fetch();

if (!$epData) die("Episode not found.");

// B. Surgeries
$surgeries = $pdo->prepare("SELECT sp.*, p.procedure_name, op.room_number, s.status, 
                            sp.asa_score 
                            FROM SURGICAL_PROCEDURE sp 
                            JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id 
                            JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id 
                            JOIN SURGICAL_PROCEDURE s ON sp.surgical_procedure_id = s.surgical_procedure_id
                            WHERE sp.episode_id = ? ORDER BY sp.date_time DESC");
$surgeries->execute([$episodeId]);
$surgList = $surgeries->fetchAll();

$latestSurgery = !empty($surgList) ? $surgList[0] : null;
$currentAsa = $latestSurgery['asa_score'] ?? '';
$latestSurgId = $latestSurgery['surgical_procedure_id'] ?? 0;

// C. Consent Status
$stmtConsent = $pdo->prepare("SELECT signed, consent_date FROM PATIENT_CONSENTS 
                              WHERE episode_id = ? AND signed = 1 
                              ORDER BY consent_date DESC LIMIT 1");
$stmtConsent->execute([$episodeId]);
$consentData = $stmtConsent->fetch();
$isConsentSigned = (bool)$consentData;

// D. Lab Tests (Standard Labs)
$labs = $pdo->prepare("
    SELECT 
        lt.test_name, 
        lte.test_date, 
        lr.result_value, 
        lr.result_date
    FROM LAB_TESTS_EPISODES lte 
    JOIN LAB_TESTS lt ON lte.test_id = lt.test_id 
    LEFT JOIN LAB_RESULTS lr ON lte.test_episode_id = lr.test_episode_id
    WHERE lte.episode_id = ? 
    ORDER BY lte.test_date DESC
");
$labs->execute([$episodeId]);
$labList = $labs->fetchAll();

// E. Medication (Active Prescriptions - Ward/Home)
$meds = $pdo->prepare("SELECT m.comercial_name, ep.dosage, ep.prescribed_date 
                       FROM EPISODE_PRESCRIPTIONS ep 
                       JOIN MEDICATIONS m ON ep.drug_ID = m.drug_ID 
                       WHERE ep.episode_id = ? ORDER BY ep.prescribed_date DESC");
$meds->execute([$episodeId]);
$medList = $meds->fetchAll();

// F. INTRA-OP POC/GASES (Recuperado de SURGERY_MONITORING)
if ($latestSurgId) {
    $pocStmt = $pdo->prepare("SELECT timestamp as test_date, observations as result_value, 'Intra-Op Blood Gas' as test_name 
                              FROM SURGERY_MONITORING 
                              WHERE surgical_procedure_id = ? AND observations LIKE '%[POC]%' 
                              ORDER BY timestamp DESC");
    $pocStmt->execute([$latestSurgId]);
    $pocResults = $pocStmt->fetchAll();
    
    $labList = array_merge($labList, $pocResults);
    
    usort($labList, function($a, $b) {
        return strtotime($b['test_date']) - strtotime($a['test_date']);
    });
}

// G. MEDICACIÓN INTRAOPERATORIA (Propofol, Fentanilo, etc.)
$intraMedsStmt = $pdo->prepare("
    SELECT m.comercial_name, spm.dosage, spm.prescribed_datetime, p.procedure_name, u.username as admin_by
    FROM SURGICAL_PROCEDURE_MEDICATIONS spm
    JOIN SURGICAL_PROCEDURE sp ON spm.surgical_procedure_id = sp.surgical_procedure_id
    JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
    JOIN MEDICATIONS m ON spm.drug_ID = m.drug_ID
    LEFT JOIN USERS u ON spm.prescribed_by = u.user_id
    WHERE sp.episode_id = ?
    ORDER BY spm.prescribed_datetime DESC
");
$intraMedsStmt->execute([$episodeId]);
$intraOpList = $intraMedsStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Episode #<?php echo $episodeId; ?> | Clinical View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root { --hospital-blue: #0056b3; --hospital-teal: #17a2b8; --bg-light: #f4f6f9; --text-dark: #343a40; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background-color: var(--bg-light); color: var(--text-dark); padding-bottom: 50px; }
        .brand-header { background: linear-gradient(180deg, rgba(0, 86, 179, 1) 0%, rgba(2, 48, 97, 1) 100%); color: white; padding: 2rem 0 5rem; }
        .main-container { margin-top: -4rem; }
        .card-clinical { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: white; margin-bottom: 20px; overflow: hidden; }
        .card-header-clean { background: white; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f0f2f5; display: flex; justify-content: space-between; align-items: center; }
        .header-title { font-weight: 700; color: var(--hospital-blue); text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; margin: 0; }
        .patient-banner { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; border-left: 6px solid var(--hospital-teal); }
        .patient-name { font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.2rem; }
        .meta-pill { background: #f8f9fa; border: 1px solid #dee2e6; padding: 2px 10px; border-radius: 20px; font-size: 0.8rem; margin-right: 5px; }
        .list-item-clean { padding: 1rem 1.5rem; border-bottom: 1px solid #f8f9fa; transition: background 0.2s; }
        .list-item-clean:hover { background-color: #fcfcfc; }
        .btn-action { width: 100%; text-align: left; padding: 1rem; border: 1px solid #dee2e6; background: white; border-radius: 8px; margin-bottom: 10px; transition: all 0.2s; display: flex; align-items: center; justify-content: space-between; font-weight: 500; color: #495057; }
        .btn-action:hover { border-color: var(--hospital-blue); color: var(--hospital-blue); background: #f8fbff; transform: translateX(2px); }
        .status-badge { font-size: 0.7rem; padding: 0.3em 0.8em; border-radius: 50px; text-transform: uppercase; font-weight: 700; }
        .bg-active { background-color: #d1e7dd; color: #0f5132; }
    </style>
</head>
<body>

    <header class="brand-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="<?php echo $dashboardLink; ?>" class="text-white-50 text-decoration-none small mb-2 d-inline-block">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                    <h1 class="h3 fw-bold mb-0">Clinical Episode Management</h1>
                    <p class="text-white-50 small mb-0">Episode ID #<?php echo $episodeId; ?></p>
                </div>
                <div class="text-end text-white-50"><i class="bi bi-activity fs-4"></i></div>
            </div>
        </div>
    </header>

    <div class="container main-container">
        
        <div class="patient-banner">
            <div>
                <h2 class="patient-name"><?php echo htmlspecialchars($epData['first_name'] . ' ' . $epData['surnames']); ?></h2>
                <div class="patient-meta mt-2">
                    <span class="meta-pill"><i class="bi bi-person-badge"></i> <?php echo $epData['p_code']; ?></span>
                    <span class="meta-pill"><i class="bi bi-calendar"></i> <?php echo $epData['date_of_birth']; ?></span>
                    <span class="meta-pill"><i class="bi bi-gender-ambiguous"></i> <?php echo $epData['gender']; ?></span>
                </div>
            </div>
            <div class="text-end">
                <span class="status-badge bg-active">Active Admission</span>
                <div class="small text-muted mt-1">Admitted: <?php echo date('d M Y', strtotime($epData['date_time_start'])); ?></div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                
                <div class="card-clinical">
                    <div class="card-header-clean">
                        <h6 class="header-title"><i class="bi bi-bandaid me-2"></i>Surgical Procedures</h6>
                    </div>
                    <div>
                        <?php if (empty($surgList)): ?>
                            <div class="p-4 text-center text-muted small">No surgeries recorded.</div>
                        <?php else: ?>
                            <?php foreach ($surgList as $s): ?>
                                <div class="list-item-clean d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1 text-primary fw-bold"><?php echo htmlspecialchars($s['procedure_name']); ?></h6>
                                        <small class="text-muted"><i class="bi bi-geo-alt"></i> Room <?php echo $s['room_number']; ?> • <?php echo date('d M, H:i', strtotime($s['date_time'])); ?></small>
                                        
                                        <div class="mt-2">
                                            <?php if($s['status'] == 'In Progress' || $s['status'] == 'Scheduled'): ?>
                                                <a href="surgery_monitor.php?id=<?php echo $s['surgical_procedure_id']; ?>" class="badge bg-danger text-decoration-none shadow-sm animate-pulse me-1">
                                                    <i class="bi bi-broadcast"></i> Live Cockpit
                                                </a>
                                            <?php endif; ?>
                                            
                                            <button class="badge bg-white text-primary border border-primary text-decoration-none shadow-sm" style="cursor: pointer;"
                                                    onclick="openVitalsModal(<?php echo $s['surgical_procedure_id']; ?>, '<?php echo htmlspecialchars(addslashes($s['procedure_name'])); ?>')">
                                                <i class="bi bi-graph-up"></i> See Vitals & Graphs
                                            </button>

                                            <a href="intraop_record.php?id=<?php echo $s['surgical_procedure_id']; ?>" target="_blank" 
                                               class="badge bg-white text-dark border border-secondary text-decoration-none shadow-sm ms-1">
                                                <i class="bi bi-printer"></i> Print Record
                                            </a>
                                        </div>
                                    </div>
                                    <span class="badge bg-light text-dark border"><?php echo $s['status']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-clinical">
                    <div class="card-header-clean">
                        <h6 class="header-title"><i class="bi bi-capsule me-2"></i>Active Prescriptions (Ward)</h6>
                    </div>
                    <div>
                        <?php if (empty($medList)): ?>
                            <div class="p-4 text-center text-muted small">No active prescriptions.</div>
                        <?php else: ?>
                            <?php foreach ($medList as $m): ?>
                                <div class="list-item-clean">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($m['comercial_name']); ?></span>
                                        <small class="text-muted"><?php echo date('d/m', strtotime($m['prescribed_date'])); ?></small>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-secondary"><i class="bi bi-prescription2"></i> <?php echo htmlspecialchars($m['dosage']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-clinical">
                    <div class="card-header-clean bg-primary bg-opacity-10">
                        <h6 class="header-title text-primary-emphasis"><i class="bi bi-eyedropper me-2"></i>Intra-Operative Medication History</h6>
                    </div>
                    <div>
                        <?php if (empty($intraOpList)): ?>
                            <div class="p-4 text-center text-muted small">No intra-operative medications recorded.</div>
                        <?php else: ?>
                            <?php foreach ($intraOpList as $im): ?>
                                <div class="list-item-clean">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($im['comercial_name']); ?></span>
                                        <small class="text-muted"><?php echo date('d/m H:i', strtotime($im['prescribed_datetime'])); ?></small>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <div>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle me-2"><?php echo htmlspecialchars($im['dosage']); ?></span>
                                            <small class="text-muted fst-italic">Admin by: <?php echo htmlspecialchars($im['admin_by'] ?? 'Anesthesia'); ?></small>
                                        </div>
                                        <small class="text-secondary"><?php echo htmlspecialchars($im['procedure_name']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                 <div class="card-clinical">
                    <div class="card-header-clean">
                        <h6 class="header-title"><i class="bi bi-clipboard-data me-2"></i>Lab Results & Gases</h6>
                        <span class="badge bg-light text-secondary"><?php echo count($labList); ?> Tests</span>
                    </div>
                    <div>
                        <table class="table table-hover mb-0" style="font-size: 0.9rem;">
                            <thead class="bg-light text-muted">
                                <tr>
                                    <th class="ps-4">Test Name</th>
                                    <th>Ordered Date</th>
                                    <th>Status</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($labList)): ?>
                                    <tr><td colspan="4" class="p-4 text-center text-muted small">No lab tests ordered.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($labList as $l): ?>
                                        <?php 
                                            $isCompleted = !empty($l['result_value']);
                                            $statusLabel = $isCompleted ? 'Completed' : 'Ordered';
                                            $badgeClass  = $isCompleted ? 'bg-success' : 'bg-warning text-dark';
                                            $rowStyle = (strpos($l['test_name'], 'Blood Gas') !== false) ? 'background-color: #fff3cd;' : '';
                                        ?>
                                        <tr style="<?php echo $rowStyle; ?>">
                                            <td class="ps-4 fw-medium"><?php echo htmlspecialchars($l['test_name']); ?></td>
                                            <td class="text-muted"><?php echo date('d/m H:i', strtotime($l['test_date'])); ?></td>
                                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $statusLabel; ?></span></td>
                                            <td>
                                                <?php if($isCompleted): ?>
                                                    <strong><?php echo htmlspecialchars($l['result_value']); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic small">Pending...</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <div class="col-lg-4">
                
                <div class="card-clinical mb-4">
                    <div class="card-header-clean bg-light"><h6 class="header-title text-secondary">Admission Reason</h6></div>
                    <div class="p-4"><p class="fst-italic text-muted mb-0">"<?php echo nl2br(htmlspecialchars($epData['reason'])); ?>"</p></div>
                </div>
                
                <div class="card-clinical mb-4 border-warning">
                    <div class="card-header-clean bg-warning bg-opacity-10">
                        <h6 class="header-title text-warning-emphasis">
                            <i class="bi bi-shield-exclamation"></i> Pre-Op Risk & Consent
                        </h6>
                    </div>
                    <div class="p-3">
                        <label class="small text-muted fw-bold">ASA PHYSICAL STATUS</label>
                        <select id="asaSelector" class="form-select form-select-sm mb-3" onchange="updateASA(this.value)" <?php echo !$latestSurgId ? 'disabled' : ''; ?>>
                            <option value="">Select Score...</option>
                            <option value="1" <?php echo ($currentAsa == 1) ? 'selected' : ''; ?>>ASA I - Healthy</option>
                            <option value="2" <?php echo ($currentAsa == 2) ? 'selected' : ''; ?>>ASA II - Mild Systemic</option>
                            <option value="3" <?php echo ($currentAsa == 3) ? 'selected' : ''; ?>>ASA III - Severe Systemic</option>
                            <option value="4" <?php echo ($currentAsa == 4) ? 'selected' : ''; ?>>ASA IV - Threat to Life</option>
                            <option value="5" <?php echo ($currentAsa == 5) ? 'selected' : ''; ?>>ASA V - Moribund</option>
                        </select>
                        <?php if(!$latestSurgId): ?>
                            <small class="text-danger d-block mb-2">No surgery scheduled to attach score.</small>
                        <?php endif; ?>

                        <?php if (!$isConsentSigned): ?>
                            <div class="alert alert-danger d-flex align-items-center p-2 mb-0 shadow-sm">
                                <i class="bi bi-file-earmark-x fs-2 me-2"></i>
                                <div class="lh-1">
                                    <strong class="d-block">NO CONSENT</strong>
                                    <small>Do not proceed to OR</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success p-2 mb-0 d-flex align-items-center shadow-sm">
                                <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                                <div class="lh-1">
                                    <strong class="d-block">Consent Verified</strong>
                                    <small><?php echo date('d M Y', strtotime($consentData['consent_date'])); ?></small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <h6 class="text-uppercase text-muted fw-bold small mb-3 ps-1">Clinical Actions</h6>

                <button class="btn-action mt-2 border-primary text-primary" data-bs-toggle="modal" data-bs-target="#prescriptionModal">
                    <span><i class="bi bi-capsule text-primary me-2"></i> Prescribe Medication</span>
                </button>

                <button class="btn-action mt-4 border-danger text-danger" onclick="dischargePatient()">
                    <span><i class="bi bi-box-arrow-right text-danger me-2"></i> Discharge Patient</span>
                </button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="prescriptionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-prescription2 me-2"></i>New Prescription</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="prescribeForm">
                        <div class="mb-3 position-relative">
                            <label class="form-label small fw-bold text-muted">SEARCH MEDICATION</label>
                            <input type="text" id="medSearch" class="form-control" placeholder="Type drug name..." autocomplete="off">
                            <input type="hidden" id="selectedDrugId">
                            <div id="medSuggestions" class="list-group position-absolute w-100 shadow" style="display:none; z-index:1000; max-height: 200px; overflow-y:auto;"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">DOSAGE & FREQUENCY</label>
                            <input type="text" id="medDosage" class="form-control" placeholder="e.g. 500mg every 8h" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary fw-bold">Prescribe</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vitalsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="vitalsTitle"><i class="bi bi-activity me-2"></i>Intra-operative Vitals</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="chartLoader" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Loading monitoring data...</p>
                    </div>
                    
                    <div id="chartContainer" class="d-none" style="height: 350px; width: 100%;">
                        <canvas id="episodeVitalsChart"></canvas>
                    </div>
                    
                    <div id="noVitalsMsg" class="text-center text-muted py-5 d-none">
                        <i class="bi bi-graph-down fs-1 opacity-25"></i>
                        <p class="mt-2">No monitoring data recorded for this procedure yet.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const latestSurgId = <?php echo $latestSurgId ?: '0'; ?>;
        const episodeId = <?php echo $episodeId; ?>;

        // --- LÓGICA DE PRESCRIPCIÓN (RESTAURADA) ---
        const medInput = document.getElementById('medSearch');
        const medList = document.getElementById('medSuggestions');
        const medHidden = document.getElementById('selectedDrugId');

        if(medInput) {
            medInput.addEventListener('input', async function() {
                const q = this.value;
                if(q.length < 2) { medList.style.display = 'none'; return; }
                
                try {
                    const res = await fetch(`api_search_meds.php?q=${encodeURIComponent(q)}`);
                    const data = await res.json();
                    
                    medList.innerHTML = '';
                    if(data.length > 0) {
                        medList.style.display = 'block';
                        data.forEach(d => {
                            const item = document.createElement('a');
                            item.href = "#";
                            item.className = "list-group-item list-group-item-action";
                            item.innerHTML = `<strong>${d.comercial_name}</strong> <small class="text-muted">(${d.active_principle})</small>`;
                            item.onclick = (e) => {
                                e.preventDefault();
                                medInput.value = d.comercial_name;
                                medHidden.value = d.drug_ID;
                                medList.style.display = 'none';
                            };
                            medList.appendChild(item);
                        });
                    } else {
                        medList.style.display = 'none';
                    }
                } catch(e) { console.error(e); }
            });

            document.addEventListener('click', function(e) {
                if (!medInput.contains(e.target) && !medList.contains(e.target)) {
                    medList.style.display = 'none';
                }
            });

            document.getElementById('prescribeForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const drugId = medHidden.value;
                const dosage = document.getElementById('medDosage').value;
                
                if(!drugId) { alert("Please select a medication from the list."); return; }

                try {
                    const res = await fetch('api_clinical_actions.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            action: 'prescribe_med',
                            episode_id: episodeId,
                            drug_ID: drugId,
                            dosage: dosage
                        })
                    });
                    const data = await res.json();
                    if(data.success) {
                        alert("Prescription added.");
                        location.reload();
                    } else {
                        alert("Error: " + data.error);
                    }
                } catch(e) { console.error(e); alert("Network error."); }
            });
        }

        // --- VITALS CHART LOGIC ---
        let vitalsChart = null;
        const vitalsModal = new bootstrap.Modal(document.getElementById('vitalsModal'));

        async function openVitalsModal(spid, title) {
            document.getElementById('vitalsTitle').innerHTML = `<i class="bi bi-activity me-2"></i> Vitals: ${title}`;
            
            const loader = document.getElementById('chartLoader');
            const container = document.getElementById('chartContainer');
            const msg = document.getElementById('noVitalsMsg');
            
            loader.classList.remove('d-none');
            container.classList.add('d-none');
            msg.classList.add('d-none');
            
            vitalsModal.show();

            try {
                const res = await fetch(`api_intraop_monitoring.php?surgery_id=${spid}`);
                const data = await res.json();
                
                loader.classList.add('d-none');

                if(data.success && data.monitoring && data.monitoring.length > 0) {
                    container.classList.remove('d-none');
                    
                    // CORRECCIÓN: Filtramos los datos nulos/cero para evitar cortes
                    const cleanData = data.monitoring.filter(d => d.card_freq && parseInt(d.card_freq) > 0);
                    
                    renderVitalsChart(cleanData.reverse());
                } else {
                    msg.classList.remove('d-none');
                }
            } catch(e) { 
                console.error(e);
                loader.classList.add('d-none');
                msg.innerHTML = "Error loading data.";
                msg.classList.remove('d-none');
            }
        }

        function renderVitalsChart(data) {
            const ctx = document.getElementById('episodeVitalsChart').getContext('2d');
            if(vitalsChart) vitalsChart.destroy();

            let gradSys = ctx.createLinearGradient(0, 0, 0, 400);
            gradSys.addColorStop(0, 'rgba(220, 53, 69, 0.2)');
            gradSys.addColorStop(1, 'rgba(220, 53, 69, 0.0)');

            let gradDia = ctx.createLinearGradient(0, 0, 0, 400);
            gradDia.addColorStop(0, 'rgba(13, 110, 253, 0.2)'); 
            gradDia.addColorStop(1, 'rgba(13, 110, 253, 0.0)');

            let gradHR = ctx.createLinearGradient(0, 0, 0, 400);
            gradHR.addColorStop(0, 'rgba(25, 135, 84, 0.2)'); 
            gradHR.addColorStop(1, 'rgba(25, 135, 84, 0.0)');
            
            let gradSpO2 = ctx.createLinearGradient(0, 0, 0, 400);
            gradSpO2.addColorStop(0, 'rgba(13, 202, 240, 0.2)'); 
            gradSpO2.addColorStop(1, 'rgba(13, 202, 240, 0.0)');

            const labels = data.map(d => new Date(d.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
            
            vitalsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Heart Rate (bpm)', data: data.map(d => d.card_freq), borderColor: '#198754', backgroundColor: gradHR, tension: 0.4, borderWidth: 2, fill: true, pointRadius: 0, pointHoverRadius: 6 },
                        { label: 'Systolic BP (mmHg)', data: data.map(d => d.sys), borderColor: '#dc3545', backgroundColor: gradSys, tension: 0.4, borderWidth: 2, fill: true, pointRadius: 0, pointHoverRadius: 6 },
                        { label: 'Diastolic BP (mmHg)', data: data.map(d => d.dias), borderColor: '#0d6efd', backgroundColor: gradDia, tension: 0.4, borderWidth: 2, fill: true, pointRadius: 0, pointHoverRadius: 6 },
                        { label: 'SpO2 (%)', data: data.map(d => d.tidal_CO2), borderColor: '#0dcaf0', backgroundColor: gradSpO2, tension: 0.4, borderWidth: 2, borderDash: [5, 5], fill: true, pointRadius: 0, pointHoverRadius: 6 }
                    ]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 12, usePointStyle: true } } },
                    scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 8, color: '#94a3b8' } }, y: { grid: { borderDash: [4, 4], color: '#f1f5f9' }, ticks: { color: '#94a3b8' }, suggestedMin: 40, suggestedMax: 140 } }
                }
            });
        }

        async function updateASA(score) {
            if(!latestSurgId) return;
            try {
                const res = await fetch('api_surgery_update.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'asa_score', score: score, sp_id: latestSurgId }) });
                const data = await res.json();
                if(data.success) { const sel = document.getElementById('asaSelector'); sel.classList.add('border-success'); setTimeout(() => sel.classList.remove('border-success'), 1000); }
            } catch(e) { console.error(e); }
        }
    
        async function dischargePatient() {
            if (!confirm("Are you sure you want to discharge this patient? This will close the clinical episode.")) return;
            try {
                const res = await fetch('api_discharge_episode.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ episode_id: episodeId }) });
                const data = await res.json();
                if (data.success) {
                    alert("Patient discharged successfully.");
                    <?php if (strpos($role, 'anest') !== false): ?> window.location.href = "../anesthesiologist/anesthesiologist_dashboard.php";
                    <?php elseif (strpos($role, 'nurse') !== false): ?> window.location.href = "../nurse/nurse_dashboard.php";
                    <?php else: ?> window.location.href = "surgeon_dashboard.php"; <?php endif; ?>
                } else { alert("Error: " + data.error); }
            } catch (e) { alert("Network error."); }
        }
    </script>
</body>
</html>