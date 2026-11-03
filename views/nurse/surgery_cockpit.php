<?php
// groupd/views/nurse/surgery_cockpit.php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'nurse') === false) die("Access Denied.");

$spid = isset($_GET['spid']) ? (int)$_GET['spid'] : 0;
if ($spid === 0) die("Error: Missing SPID");

$pdo = getDBConnection();

// Fetch Surgery Data
// FIX: Using LEFT JOIN to allow access even if OR room is NULL (addressing previous issues)
$sql = "SELECT sp.*, p.procedure_name, pat.first_name, pat.surnames, pat.date_of_birth, op.room_number
        FROM SURGICAL_PROCEDURE sp
        JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
        JOIN EPISODE ep ON sp.episode_id = ep.episode_id
        JOIN PATIENT pat ON ep.patient_id = pat.patient_id
        LEFT JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id
        WHERE sp.surgical_procedure_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$spid]);
$surgery = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$surgery) die("Surgery not found.");

// Decode Checklist Data
$checklist = json_decode($surgery['safety_checklist_data'] ?? '{}', true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Live Cockpit | <?php echo htmlspecialchars($surgery['procedure_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --hospital-green: #11998e; --danger-red: #dc3545; }
        .timestamp-btn { height: 80px; font-weight: 700; border-radius: 8px; font-size: 0.9rem; }
        .timestamp-btn.active { background-color: var(--hospital-green); color: white; border-color: var(--hospital-green); }
        .navbar { background-color: var(--hospital-green); }
        .live-pulse { animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
    </style>
</head>
<body class="bg-light pb-5">

<nav class="navbar navbar-expand-lg navbar-dark shadow-sm mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="nurse_dashboard.php">
        <i class="bi bi-arrow-left me-2"></i> Exit Live Mode
    </a>
    <span class="navbar-text text-white fw-bold">
        OR <?php echo htmlspecialchars($surgery['room_number'] ?? 'TBD'); ?>: <?php echo htmlspecialchars($surgery['procedure_name']); ?>
    </span>
  </div>
</nav>

<div class="container-fluid px-4">
    <div class="row g-3">
        
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <h6 class="text-muted text-uppercase fw-bold mb-3 small">Procedure Milestones</h6>
                    <div class="row g-2">
                        <?php 
                        $stages = [
                            'time_in_room' => ['Patient In', 'bi-door-open'],
                            'time_incision' => ['Incision', 'bi-knife'],
                            'time_closure' => ['Closure', 'bi-bandaid'],
                            'time_out_room' => ['Patient Out', 'bi-door-closed']
                        ];
                        foreach($stages as $field => $data): 
                            $isDone = !empty($surgery[$field]);
                            $timeVal = $isDone ? date('H:i', strtotime($surgery[$field])) : '--:--';
                            $btnClass = $isDone ? 'btn-success' : 'btn-outline-secondary';
                        ?>
                        <div class="col-md-3">
                            <button onclick="updateTime('<?php echo $field; ?>', this)" 
                                    class="btn <?php echo $btnClass; ?> w-100 timestamp-btn d-flex flex-column align-items-center justify-content-center"
                                    <?php echo $isDone ? 'disabled' : ''; ?>>
                                <i class="bi <?php echo $data[1]; ?> fs-5"></i>
                                <span><?php echo $data[0]; ?></span>
                                <span class="small badge bg-white text-dark mt-1 timestamp-display"><?php echo $timeVal; ?></span>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold"><i class="bi bi-list-check"></i> WHO Safety Checklist</div>
                <div class="card-body">
                    <form id="checklistForm">
                        <div class="form-check py-2 border-bottom">
                            <input class="form-check-input" type="checkbox" id="chk_patient" <?php echo ($checklist['patient']??false)?'checked':''; ?>>
                            <label class="form-check-label fw-bold" for="chk_patient">Identity & Site Confirmed</label>
                        </div>
                        <div class="form-check py-2 border-bottom">
                            <input class="form-check-input" type="checkbox" id="chk_anesthesia" <?php echo ($checklist['anesthesia']??false)?'checked':''; ?>>
                            <label class="form-check-label" for="chk_anesthesia">Anesthesia Safety Check</label>
                        </div>
                        <div class="form-check py-2 border-bottom bg-danger bg-opacity-10 px-2 rounded my-2">
                            <input class="form-check-input" type="checkbox" id="chk_timeout" <?php echo ($checklist['timeout']??false)?'checked':''; ?>>
                            <label class="form-check-label text-danger fw-bold" for="chk_timeout">TIMEOUT Performed</label>
                        </div>
                        <div class="form-check py-2 border-bottom">
                            <input class="form-check-input" type="checkbox" id="chk_counts" <?php echo ($checklist['counts']??false)?'checked':''; ?>>
                            <label class="form-check-label" for="chk_counts">Nurse Counts Correct</label>
                        </div>
                        <div class="form-check py-2">
                            <input class="form-check-input" type="checkbox" id="chk_specimen" <?php echo ($checklist['specimen']??false)?'checked':''; ?>>
                            <label class="form-check-label" for="chk_specimen">Specimen Labeling Verified</label>
                        </div>
                    </form>
                    <button onclick="saveChecklist()" class="btn btn-primary w-100 mt-3">Save Checklist</button>
                </div>
            </div>
            
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-danger text-white fw-bold live-pulse"><i class="bi bi-capsule"></i> Administered Medications (LIVE)</div>
                <div class="card-body p-0">
                    <div class="border rounded p-0 overflow-hidden bg-white" style="height: 200px; overflow-y: auto;">
                         <table class="table table-sm table-striped mb-0">
                            <thead class="table-light sticky-top">
                                <tr><th>Time</th><th>Drug Name</th><th>Dose</th></tr>
                            </thead>
                            <tbody id="medsTableBody">
                                <tr><td colspan="3" class="text-center text-muted small py-3">Loading medications...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-info text-white fw-bold"><i class="bi bi-activity"></i> Vitals Monitoring Log (READ-ONLY)</div>
                <div class="card-body">
                    <div class="alert alert-info py-2">
                        <i class="bi bi-info-circle-fill me-1"></i> New vitals must be logged by the Anesthesiology team.
                    </div>
                    
                    <div class="border rounded p-0 overflow-hidden bg-white" style="height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-striped mb-0 text-center">
                            <thead class="table-light sticky-top">
                                <tr><th>Time</th><th>HR (bpm)</th><th>NIBP (mmHg)</th><th>SpO2 (%)</th></tr>
                            </thead>
                            <tbody id="vitalsTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    const spid = <?php echo $spid; ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Load Vitals and Medication history on initial load
        loadVitalsHistory();
        loadMedicationHistory(); 
    });

    // 1. TIMESTAMPS
    async function updateTime(field, btn) {
        if(!confirm('Mark timestamp?')) return;
        try {
            const res = await fetch('api_nurse_actions.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'timestamp', field: field, spid: spid })
            });
            const data = await res.json();
            if(data.success) {
                btn.className = 'btn btn-success w-100 timestamp-btn d-flex flex-column align-items-center justify-content-center';
                btn.disabled = true;
                btn.querySelector('.timestamp-display').textContent = data.time;
            } else {
                alert("Error updating time: " + (data.error || 'Unknown error'));
            }
        } catch(e) { console.error(e); alert("Network error during milestone update."); }
    }

    // 2. CHECKLIST
    async function saveChecklist() {
        const data = {
            patient: document.getElementById('chk_patient').checked,
            anesthesia: document.getElementById('chk_anesthesia').checked,
            timeout: document.getElementById('chk_timeout').checked,
            counts: document.getElementById('chk_counts').checked,
            specimen: document.getElementById('chk_specimen').checked
        };
        await fetch('api_nurse_actions.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'checklist', data: data, spid: spid })
        });
        alert('Checklist Saved');
    }

    // 3. VITALS HISTORY (READ-ONLY)
    // NOTE: logVitals() function removed as per requirement.
    async function loadVitalsHistory() {
        const tbody = document.getElementById('vitalsTableBody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted small py-3">Fetching live data...</td></tr>';

        // Reuse the nurse actions API to get the Vitals Log
        const res = await fetch(`api_nurse_actions.php?action=get_vitals&spid=${spid}`);
        const data = await res.json();
        
        tbody.innerHTML = ''; // Clear loading row
        
        if(data.logs && data.logs.length > 0) {
            data.logs.forEach(row => {
                const spo2Display = row.oxygen_saturation ?? '--';
                const bpDisplay = row.sys !== null && row.dias !== null ? `${row.sys}/${row.dias}` : '--';
                const time = new Date(row.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: false});
                tbody.innerHTML += `<tr><td>${time}</td><td>${row.card_freq ?? '--'}</td><td>${bpDisplay}</td><td>${spo2Display}</td></tr>`;
            });
        } else { 
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted small py-3">No monitoring entries recorded yet.</td></tr>';
        }
    }
    
    // 4. MEDICATION HISTORY (READ-ONLY)
    async function loadMedicationHistory() {
        const res = await fetch(`api_patient_meds.php?spid=${spid}`);
        const data = await res.json();
        const tbody = document.getElementById('medsTableBody');
        tbody.innerHTML = '';

        if (data.success && data.history.length > 0) {
            data.history.forEach(row => {
                const time = new Date(row.prescribed_datetime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: false});
                tbody.innerHTML += `
                    <tr>
                        <td>${time}</td>
                        <td>${row.comercial_name} <span class="badge bg-secondary ms-1 small">${row.prescribed_by_name ? row.prescribed_by_name : 'N/A'}</span></td>
                        <td>${row.dosage}</td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted small py-3">No medications recorded yet.</td></tr>';
        }
    }
    
    // Auto-refresh the read-only sections (Vitals/Meds) every 5 seconds
    setInterval(() => {
        loadVitalsHistory();
        loadMedicationHistory();
    }, 5000); 

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
