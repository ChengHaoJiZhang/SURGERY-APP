<?php
// groupd/views/nurse/pre_op_check.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';
require_once __DIR__ . '/../nurse/nurse_controller.php'; 
require_once __DIR__ . '/../../config/config.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'nurse') === false) die("Access Denied.");

$spid = isset($_GET['spid']) ? (int)$_GET['spid'] : 0;
if ($spid === 0) {
    header("Location: nurse_dashboard.php?error=MissingSPID");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Op Check | SPID #<?php echo $spid; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* COLOR PALETTE */
        :root { 
            --bg-body: #f0f4f8; 
            --text-dark: #1e293b; 
            --text-muted: #64748b;
            --primary: #0ea5e9; 
            --surface: #ffffff;
            --success: #10b981;
            --danger: #ef4444;
            --glass-bg: rgba(255, 255, 255, 0.95);
        }
        
        body { 
            background-color: var(--bg-body); 
            font-family: 'Inter', system-ui, sans-serif; 
            color: var(--text-dark); 
            padding-bottom: 140px;
        }
        
        /* HEADER */
        .top-header { 
            background: var(--surface); 
            height: 72px; 
            border-bottom: 1px solid #e2e8f0; 
            display: flex; align-items: center; justify-content: space-between; 
            padding: 0 40px; position: sticky; top: 0; z-index: 1000; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        
        /* LOADING OVERLAY */
        .loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            transition: opacity 0.3s ease;
        }
        .loading-overlay.d-none { opacity: 0; visibility: hidden; }

        /* CARDS */
        .card-depth { background: var(--surface); border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden; height: 100%; transition: transform 0.2s; }
        .card-header-clean { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 10px; background: #fff; }
        
        /* CHECK ITEMS */
        .check-item { padding: 14px 18px; border-radius: 12px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; font-weight: 600; border: 1px solid transparent; background: white; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .check-item-fail { border-left: 4px solid var(--danger); background: #fff1f2; }
        .check-item-pass { border-left: 4px solid var(--success); background: #f0fdf4; }
        
        .icon-box { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; margin-right: 12px; }
        .icon-fail { background: #fee2e2; color: #ef4444; }
        .icon-pass { background: #d1fae5; color: #10b981; }

        /* INPUTS */
        .vitals-input { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; text-align: center; font-weight: 600; color: var(--text-dark); background: #f8fafc; }
        .vitals-input:focus { border-color: var(--primary); background: white; outline: none; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); }
        .input-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); font-weight: 700; margin-bottom: 6px; display: block; }

        /* STICKY FOOTER */
        .sticky-footer {
            position: fixed; bottom: 0; left: 0; width: 100%; background: white; border-top: 1px solid #e2e8f0; box-shadow: 0 -4px 20px rgba(0,0,0,0.05); padding: 20px 0; z-index: 1020; transition: background-color 0.3s ease;
        }
        .sticky-footer.ready { background: #f0fdf4; border-top-color: var(--success); }

        .status-wrapper { display: flex; align-items: center; justify-content: space-between; max-width: 1100px; margin: 0 auto; padding: 0 20px; }
        .status-content { display: flex; align-items: center; gap: 15px; }
        
        .status-icon {
            width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: #f1f5f9; color: #94a3b8; transition: all 0.3s ease;
        }
        .ready .status-icon { background: #10b981; color: white; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); }

        .btn-launch {
            padding: 12px 30px; border-radius: 50px; font-weight: 700; border: none; transition: all 0.2s; font-size: 1rem; background: #e2e8f0; color: #94a3b8; cursor: not-allowed;
        }
        .btn-launch.active {
            background: #10b981; color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); cursor: pointer;
        }
        .btn-launch.active:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(16, 185, 129, 0.4); }
        
        .btn-glass { background: white; border: 1px solid #e2e8f0; color: var(--text-dark); border-radius: 10px; padding: 8px 16px; font-weight: 600; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-glass:hover { background: #f8fafc; border-color: #cbd5e1; transform: translateY(-1px); }
        .btn-save { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; transition: 0.2s; }
        .btn-save:hover { background: #0284c7; transform: translateY(-1px); }
    </style>
</head>
<body>

    <header class="top-header">
        <div class="d-flex align-items-center gap-4">
            <a href="nurse_dashboard.php" class="btn-glass border-0 text-muted ps-0">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <div>
                <h1 class="h5 mb-0 fw-bold" id="patientInfo">Loading...</h1>
                <div class="text-muted small" id="procedureInfo">SPID: <?php echo $spid; ?></div>
            </div>
        </div>
    </header>

    <div id="loading" class="loading-overlay">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
        <p class="mt-3 text-dark fw-bold bg-white px-4 py-2 rounded-pill shadow-sm border">Checking Safety Requirements...</p>
    </div>

    <div class="container py-5" style="max-width: 1100px;">
        
        <div id="clearanceContent" class="d-none">
            
            <div class="row g-4">
                
                <div class="col-lg-7">
                    
                    <div class="card-depth mb-4">
                        <div class="card-header-clean text-primary">
                            <i class="bi bi-heart-pulse-fill"></i> Record Pre-Op Vitals
                        </div>
                        <div class="p-4">
                            <form id="preOpVitalsForm">
                                <div class="row g-3">
                                    <div class="col-md-3"><label class="input-label">HR (bpm)</label><input type="number" id="hr" class="form-control vitals-input" placeholder="--" required></div>
                                    <div class="col-md-3"><label class="input-label">SpO2 (%)</label><input type="number" id="spo2" class="form-control vitals-input" placeholder="--" required></div>
                                    <div class="col-md-6"><label class="input-label">BP (mmHg)</label><div class="input-group"><input type="number" id="sys" class="form-control vitals-input" placeholder="Sys" required><span class="input-group-text bg-white text-muted border-top border-bottom border-start-0 border-end-0">/</span><input type="number" id="dias" class="form-control vitals-input" placeholder="Dia" required></div></div>
                                    <div class="col-md-6"><label class="input-label">Temp (°C)</label><input type="number" id="temp" class="form-control vitals-input" step="0.1" placeholder="--" required></div>
                                    <div class="col-md-6"><label class="input-label">RR (rpm)</label><input type="number" id="rr" class="form-control vitals-input" placeholder="--"></div>
                                    <div class="col-12 mt-4 d-flex justify-content-between align-items-center">
                                        <span id="vitalsStatus" class="text-muted small fw-bold"></span>
                                        <button type="submit" id="vitalsSubmitBtn" class="btn-save w-auto px-4"><i class="bi bi-check-circle me-2"></i> Save Vitals</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h6 class="fw-bold text-muted text-uppercase mb-3 ps-1 ls-1" style="font-size: 0.8rem;">Mandatory Clearance Checks</h6>
                    
                    <div id="consentCheck" class="check-item check-item-fail">
                        <div class="d-flex align-items-center"><div class="icon-box icon-fail"><i class="bi bi-file-earmark-x"></i></div> Consent Status</div>
                        <span class="badge bg-white text-danger border border-danger-subtle">Pending</span>
                    </div>
                    <div id="allergyCheck" class="check-item check-item-pass">
                        <div class="d-flex align-items-center"><div class="icon-box icon-pass"><i class="bi bi-shield-check"></i></div> Allergy Check</div>
                        <span class="badge bg-white text-success border border-success-subtle">Verified</span>
                    </div>
                    
                    <div id="vitalsSavedCheck" class="check-item check-item-fail">
                        <div class="d-flex align-items-center"><div class="icon-box icon-fail"><i class="bi bi-activity"></i></div> Vitals Recorded</div>
                        <span class="badge bg-white text-danger border border-danger-subtle">Required</span>
                    </div>
                </div>

                <div class="col-lg-5">
                     <div class="card-depth mb-4">
                         <div class="card-header-clean"><i class="bi bi-tools text-dark"></i> Instrument Set</div>
                         <ul class="list-group list-group-flush" id="instrumentList"><li class="list-group-item text-muted small fst-italic p-3">Loading requirements...</li></ul>
                     </div>
                     <div class="card-depth">
                         <div class="card-header-clean"><i class="bi bi-sliders text-dark"></i> Surgeon Preferences</div>
                         <div class="p-3 text-secondary" id="preferencesDisplay"></div>
                     </div>
                </div>
            </div>

        </div>
    </div>

    <div class="sticky-footer" id="actionBar">
        <div class="status-wrapper">
            <div class="status-content">
                <div class="status-icon" id="statusIcon"><i class="bi bi-lock-fill"></i></div>
                <div>
                    <h6 class="mb-0 fw-bold text-dark" id="statusTitle">Safety Clearance Pending</h6>
                    <small class="text-muted" id="statusDesc">Complete mandatory checks above to unlock cockpit.</small>
                </div>
            </div>
            <button id="cockpitLink" class="btn-launch" disabled>
                Enter Live Cockpit <i class="bi bi-chevron-right ms-1"></i>
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initializePreOpCheck);

        const spid = <?php echo $spid; ?>;
        let vitalsRecorded = false; 

        document.getElementById('preOpVitalsForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('vitalsSubmitBtn');
            const statusSpan = document.getElementById('vitalsStatus');
            btn.disabled = true;
            statusSpan.textContent = "Saving...";

            const payload = {
                action: 'vital_signs', 
                spid: spid,
                hr: document.getElementById('hr').value,
                sys: document.getElementById('sys').value,
                dias: document.getElementById('dias').value,
                tidal_CO2: document.getElementById('spo2').value, 
                resp_freq: document.getElementById('rr').value,
                observations: `Pre-Op Temp: ${document.getElementById('temp').value} °C` 
            };

            try {
                const res = await fetch('api_nurse_actions.php', {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                
                if(data.success) {
                    statusSpan.textContent = "Saved successfully";
                    statusSpan.className = 'text-success small fw-bold me-3';
                    vitalsRecorded = true;
                    await checkVitalsStatus(); 
                    checkFinalClearance(); 
                } else {
                    statusSpan.textContent = "Error: " + data.error;
                    statusSpan.className = 'text-danger small fw-bold me-3';
                }
            } catch(e) { 
                console.error(e);
                statusSpan.textContent = "Connection error";
            } finally {
                btn.disabled = false;
            }
        });

        async function initializePreOpCheck() {
            try {
                const vitalsRes = await fetch(`api_patient_vitals.php?spid=${spid}`);
                const checkRes = await fetch(`api_nurse_data.php?spid=${spid}`);
                const vitalsData = await vitalsRes.json();
                const checkData = await checkRes.json();

                document.getElementById('loading').classList.add('d-none');
                document.getElementById('clearanceContent').classList.remove('d-none');

                if (!vitalsData.success || !checkData.success) {
                    alert("Error loading data.");
                    return;
                }

                const p = vitalsData.patient;
                document.getElementById('patientInfo').textContent = `${p.first_name} ${p.surnames}`;
                document.getElementById('procedureInfo').textContent = `Procedure: ${p.procedure_name} (ID: ${spid})`;

                renderPreOpData(vitalsData, checkData);
                await checkVitalsStatus(); 
                checkFinalClearance(); 

            } catch (e) {
                console.error("Init Error:", e);
                document.getElementById('loading').innerHTML = '<p class="text-danger bg-white p-3 rounded">Error loading data.</p>';
            }
        }
        
        async function checkVitalsStatus() {
            try {
                const res = await fetch(`api_nurse_actions.php?action=get_vitals&spid=${spid}`);
                const data = await res.json();
                const el = document.getElementById('vitalsSavedCheck');
                
                if(data.logs && data.logs.length > 0) {
                    el.className = 'check-item check-item-pass';
                    el.innerHTML = `<div class="d-flex align-items-center"><div class="icon-box icon-pass"><i class="bi bi-check-lg"></i></div> Vitals Recorded</div><span class="badge bg-white text-success border border-success-subtle">Done</span>`;
                    vitalsRecorded = true;
                } else {
                    el.className = 'check-item check-item-fail';
                    el.innerHTML = `<div class="d-flex align-items-center"><div class="icon-box icon-fail"><i class="bi bi-activity"></i></div> Vitals Recorded</div><span class="badge bg-white text-danger border border-danger-subtle">Pending</span>`;
                    vitalsRecorded = false;
                }
            } catch (e) { console.error(e); }
        }

        function renderPreOpData(vitalsData, checkData) {
            const consentEl = document.getElementById('consentCheck');
            if (checkData.consent_status) {
                consentEl.className = 'check-item check-item-pass';
                consentEl.innerHTML = `<div class="d-flex align-items-center"><div class="icon-box icon-pass"><i class="bi bi-file-earmark-check"></i></div> Informed Consent</div><span class="badge bg-white text-success border border-success-subtle">Signed</span>`;
            } else {
                consentEl.className = 'check-item check-item-fail';
                consentEl.innerHTML = `<div class="d-flex align-items-center"><div class="icon-box icon-fail"><i class="bi bi-file-earmark-x"></i></div> Informed Consent</div><span class="badge bg-white text-danger border border-danger-subtle">Missing</span>`;
            }

            // --- ALLERGY FIX START ---
            const allergyEl = document.getElementById('allergyCheck');
            // Safely get allergies
            const allAllergies = vitalsData.allergies || [];
            
            // Filter specifically for Critical allergies
            const criticalAllergies = allAllergies.filter(a => a.severity === 'Severe' || a.severity === 'Life-threatening');
            
            if (criticalAllergies.length > 0) {
                // CASE 1: Has Critical Allergies (Red Alert)
                allergyEl.className = 'check-item check-item-fail';
                const names = criticalAllergies.map(a => a.allergen_name).join(', ');
                allergyEl.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="icon-box icon-fail"><i class="bi bi-exclamation-triangle"></i></div> 
                        <div>Allergy Check <div class="small text-danger fw-bold mt-1">${names}</div></div>
                    </div>
                    <span class="badge bg-danger text-white border-0">${criticalAllergies.length} Critical</span>`;
            } else if (allAllergies.length > 0) {
                // CASE 2: Has Non-Critical Allergies (Yellow Warning) - PREVIOUSLY HIDDEN
                allergyEl.className = 'check-item';
                allergyEl.style.borderLeft = "4px solid #f59e0b"; // Warning Orange
                allergyEl.style.background = "#fffbeb"; 
                
                const names = allAllergies.map(a => `${a.allergen_name} (${a.severity})`).join(', ');
                
                allergyEl.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="icon-box" style="background: #fef3c7; color: #d97706;"><i class="bi bi-info-circle-fill"></i></div> 
                        <div>Allergy Check <div class="small text-muted fw-bold mt-1">${names}</div></div>
                    </div>
                    <span class="badge bg-warning text-dark border border-warning-subtle">Present</span>`;
            } else {
                // CASE 3: No Allergies (Green Clear)
                allergyEl.className = 'check-item check-item-pass';
                allergyEl.removeAttribute('style'); // Remove inline styles
                allergyEl.innerHTML = `<div class="d-flex align-items-center"><div class="icon-box icon-pass"><i class="bi bi-shield-check"></i></div> Allergy Check</div><span class="badge bg-white text-success border border-success-subtle">Clear</span>`;
            }
            // --- ALLERGY FIX END ---
            
            // Preferences
            const pref = checkData.preferences;
            document.getElementById('preferencesDisplay').innerHTML = `
                <div class="d-flex justify-content-between mb-2"><span class="text-muted text-uppercase small fw-bold">Glove Size</span><span class="fw-bold text-dark">${pref.glove_size}</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted text-uppercase small fw-bold">Sutures</span><span class="fw-bold text-dark text-end">${pref.sutures.join(', ')}</span></div>
                <hr class="my-2 border-light"><div class="small fst-italic text-muted">${pref.notes}</div>`;
            document.getElementById('instrumentList').innerHTML = pref.instruments.map(item => `<li class="list-group-item d-flex align-items-center small p-2"><i class="bi bi-dot text-primary fs-4"></i> ${item}</li>`).join('');
        }
        
        function checkFinalClearance() {
            const consentPassed = document.getElementById('consentCheck').classList.contains('check-item-pass');
            const isClear = consentPassed && vitalsRecorded; 

            const actionBar = document.getElementById('actionBar');
            const icon = document.getElementById('statusIcon').querySelector('i');
            const statusIconDiv = document.getElementById('statusIcon');
            const title = document.getElementById('statusTitle');
            const desc = document.getElementById('statusDesc');
            const btn = document.getElementById('cockpitLink');
            
            if (isClear) {
                actionBar.classList.add('ready');
                statusIconDiv.style.background = '#10b981';
                statusIconDiv.style.color = 'white';
                icon.className = 'bi bi-check-lg';
                title.textContent = "Patient Cleared for Surgery";
                desc.textContent = "All safety checks passed. You may proceed.";
                btn.disabled = false;
                btn.classList.add('active');
                // CORRECCIÓN REDIRECCIÓN
                btn.onclick = () => window.location.href = `surgery_live.php?id=${spid}`;
            } else {
                actionBar.classList.remove('ready');
                statusIconDiv.style.background = '#f1f5f9';
                statusIconDiv.style.color = '#94a3b8';
                icon.className = 'bi bi-lock-fill';
                title.textContent = "Safety Clearance Pending";
                desc.textContent = "Complete mandatory checks above to unlock cockpit.";
                btn.disabled = true;
                btn.classList.remove('active');
            }
        }
    </script>
</body>
</html>