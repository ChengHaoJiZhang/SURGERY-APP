<?php
// groupd/views/patient/patient_dashboard.php
// REDESIGNED (Fixed Layout): Content-Fit Cards (No Whitespace) + Original Logic

// 1. CONFIGURACIÓN DE ERRORES
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 0);

// 2. INCLUIR CONTROLADOR
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

// 3. AUTENTICACIÓN
$controller = new LoginController();
$controller->checkAuth();

// Verificar Rol
$role = strtolower($_SESSION['role_name'] ?? '');
if ($role !== 'patient' && $role !== 'paciente' && $role !== 'pacient') {
    header("Location: ../auth/login.php");
    exit();
}

// 4. INICIALIZAR VARIABLES DE DATOS
$user_id = $_SESSION['user_id'];
$patient_name = 'Patient';
$patient_allergies = [];
$patient_id = null;
$error_message = null;
$surgery_count = 0;

try {
    $pdo = getDBConnection();

    // A. Obtener ID y Nombre del Paciente
    $sql_patient = "SELECT P.patient_id, P.first_name, P.surnames 
                    FROM PATIENT P 
                    JOIN USERS U ON P.email = U.email 
                    WHERE U.user_id = ?";
    $stmt = $pdo->prepare($sql_patient);
    $stmt->execute([$user_id]);
    $patient_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($patient_details) {
        $patient_name = htmlspecialchars($patient_details['first_name'] . ' ' . $patient_details['surnames']);
        $patient_id = $patient_details['patient_id'];

        // B. Contar Total de Cirugías
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM SURGICAL_PROCEDURE WHERE patient_id = ?");
        $count_stmt->execute([$patient_id]);
        $surgery_count = $count_stmt->fetchColumn();

        // C. Obtener Alergias
        $sql_allergies = "SELECT A.allergen_name, PA.severity 
                          FROM PATIENT_ALLERGIES PA 
                          JOIN ALLERGIES A ON PA.allergen_ID = A.allergen_ID 
                          WHERE PA.patient_id = ?";
        $stmt = $pdo->prepare($sql_allergies);
        $stmt->execute([$patient_id]);
        $patient_allergies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error_message = "No patient profile found for this user account.";
    }

} catch (Exception $e) {
    $error_message = "System Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal | Hospital Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        /* DESIGN SYSTEM: "Wellness & Clarity" */
        :root { 
            --bg-body: #f0f4f8; 
            --text-dark: #1e293b; 
            --text-muted: #64748b;
            --primary: #0891b2; /* Cyan/Teal for Patients */
            --primary-dark: #155e75;
            --surface: #ffffff;
            --accent-warm: #f59e0b;
            --accent-danger: #ef4444;
            
            --shadow-soft: 0 2px 4px -1px rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.03);
            --shadow-depth: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.025);
            --radius-lg: 12px;
            --glass-bg: rgba(255, 255, 255, 0.95);
        }
        
        body { background-color: var(--bg-body); font-family: 'Inter', system-ui, sans-serif; color: var(--text-dark); padding-bottom: 60px; font-size: 0.95rem; }
        
        /* HEADER */
        .top-header { 
            background: var(--surface); 
            height: 60px; 
            border-bottom: 1px solid #e2e8f0; 
            display: flex; align-items: center; justify-content: space-between; 
            padding: 0 30px; position: sticky; top: 0; z-index: 1000; 
            box-shadow: var(--shadow-soft);
        }
        .brand { font-weight: 800; color: var(--primary-dark); font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
        .brand-icon { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(8, 145, 178, 0.3); font-size: 0.9rem; }

        /* CARDS - FIXED: Removed height:100% to prevent blank space */
        .card-clean { 
            background: var(--surface); 
            border: 1px solid #e2e8f0; 
            border-radius: var(--radius-lg); 
            box-shadow: var(--shadow-soft); 
            overflow: hidden; 
            height: auto; /* Ensures card fits content exactly */
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }
        .card-clean:hover { border-color: #cbd5e1; }
        
        .card-header-simple { 
            padding: 0.85rem 1.25rem; 
            border-bottom: 1px solid #f1f5f9; 
            font-weight: 700; 
            font-size: 0.95rem;
            color: var(--text-dark);
            display: flex; justify-content: space-between; align-items: center;
            background: #fff;
        }
        
        /* ACTION CARDS */
        .card-highlight { background: #ecfeff; border-color: #cffafe; }
        .card-highlight .card-header-simple { background: #ecfeff; border-bottom-color: #cffafe; color: var(--primary-dark); }
        
        /* ALERTS */
        .alert-modern { border-radius: 10px; border: none; font-weight: 600; padding: 0.75rem 1rem; display: flex; align-items: center; gap: 12px; font-size: 0.9rem; }
        .alert-danger-modern { background: #fef2f2; color: #b91c1c; border-left: 4px solid #ef4444; }
        
        /* KPI BOXES */
        .kpi-row { display: flex; gap: 15px; margin-bottom: 20px; }
        .kpi-box { flex: 1; background: var(--surface); padding: 15px; border-radius: var(--radius-lg); box-shadow: var(--shadow-soft); display: flex; align-items: center; gap: 15px; border: 1px solid #e2e8f0; }
        .kpi-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .kpi-icon.blue { background: #e0f2fe; color: #0284c7; }
        .kpi-icon.orange { background: #fef3c7; color: #d97706; }
        .kpi-text { line-height: 1.2; }
        .kpi-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-val { font-size: 1.5rem; font-weight: 700; color: var(--text-dark); }
        
        /* TABLE STYLES - COMPACT */
        .table-custom thead th { text-transform: uppercase; font-size: 0.7rem; color: var(--text-muted); font-weight: 700; border-bottom: 1px solid #f1f5f9; padding: 10px 15px; background-color: #f8fafc; }
        .table-custom tbody td { padding: 10px 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 0.9rem; }
        .table-custom tbody tr:hover { background-color: #f8fafc; cursor: pointer; }
        
        /* BADGES */
        .status-badge { padding: 4px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .bg-primary { background-color: #e0f2fe !important; color: #0284c7 !important; }
        .bg-success { background-color: #dcfce7 !important; color: #166534 !important; }
        .bg-warning { background-color: #fef9c3 !important; color: #854d0e !important; }
        .bg-secondary { background-color: #f1f5f9 !important; color: #475569 !important; }
        .bg-danger { background-color: #fee2e2 !important; color: #991b1b !important; } 

        /* FORM ELEMENTS */
        .filter-select { border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 10px; font-size: 0.85rem; color: var(--text-dark); background-color: #fff; cursor: pointer; }
        .filter-select:focus { border-color: var(--primary); outline: none; }
        
        /* BUTTONS */
        .btn-glass { background: white; border: 1px solid #e2e8f0; color: var(--text-dark); border-radius: 8px; padding: 6px 12px; font-weight: 600; transition: 0.2s; font-size: 0.85rem; }
        .btn-glass:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-primary-soft { background: var(--primary); color: white; border: none; border-radius: 8px; padding: 8px 16px; font-weight: 600; transition: 0.2s; box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2); font-size: 0.9rem; }
        .btn-primary-soft:hover { background: var(--primary-dark); transform: translateY(-1px); }

        .animate-pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
    </style>
</head>
<body>

    <header class="top-header">
        <div class="brand">
            <div class="brand-icon"><i class="bi bi-heart-pulse-fill"></i></div>
            <span>Patient Portal</span>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-md-block">
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Welcome back</div>
                <div class="fw-bold text-dark" style="font-size: 0.9rem;" id="patientNameDisplay"><?php echo $patient_name; ?></div>
            </div>
            <a href="../../controllers/logout.php" class="btn btn-glass btn-sm text-danger border-0"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </header>

    <div class="container py-4" style="max-width: 1200px;">
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger-modern mb-3">
                <i class="bi bi-exclamation-circle-fill fs-5"></i>
                <div><?php echo $error_message; ?></div>
            </div>
        <?php endif; ?>

        <div class="kpi-row">
            <div class="kpi-box">
                <div class="kpi-icon blue"><i class="bi bi-journal-medical"></i></div>
                <div class="kpi-text">
                    <div class="text-muted fw-bold kpi-label">Procedures</div>
                    <div class="kpi-val"><?php echo $surgery_count; ?></div>
                </div>
            </div>
            <div class="kpi-box">
                <div class="kpi-icon orange"><i class="bi bi-pen-fill"></i></div>
                <div class="kpi-text">
                    <div class="text-muted fw-bold kpi-label">Pending Actions</div>
                    <div class="kpi-val" id="kpiPending" style="font-size: 1.2rem;">--</div>
                </div>
            </div>
        </div>

        <?php if (!empty($patient_allergies)): ?>
            <div class="alert alert-danger-modern mb-4 shadow-sm py-2">
                <i class="bi bi-shield-exclamation fs-5"></i>
                <div class="flex-grow-1 d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-uppercase small fw-bold text-danger me-2" style="letter-spacing: 0.5px;">Allergy Alert:</span>
                    <?php foreach ($patient_allergies as $allergy): ?>
                        <span class="badge bg-white text-danger border border-danger-subtle fw-bold">
                            <?php echo htmlspecialchars($allergy['allergen_name']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            
            <div class="col-lg-8">
                
                <div class="card-clean card-highlight mb-3">
                    <div class="card-header-simple">
                        <span><i class="bi bi-file-earmark-check-fill me-2"></i> Documents to Sign</span>
                        <span class="badge bg-warning text-dark" id="pendingCount">Loading...</span>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="consents-body">
                            <li class="list-group-item bg-transparent text-center p-3 text-muted small">
                                <div class="spinner-border spinner-border-sm text-primary mb-1"></div>
                                <div>Loading...</div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card-clean">
                    <div class="card-header-simple border-0 pb-0">
                        <span><i class="bi bi-clock-history me-2 text-primary"></i> My Journey</span>
                    </div>
                    
                    <div class="px-3 pt-2 pb-3">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <select id="statusSelect" class="filter-select w-100">
                                    <option value="all">Status: All</option>
                                    <option value="Scheduled">Scheduled</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select id="typeSelect" class="filter-select w-100">
                                    <option value="all">Type: All</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select id="periodSelect" class="filter-select w-100">
                                    <option value="all">Date: Any Time</option>
                                    <option value="month">This Month</option>
                                    <option value="year">This Year</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-4">Procedure</th>
                                    <th>Date</th>
                                    <th>Room</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="surgery-table-body">
                                <tr><td colspan="5" class="text-center py-4 text-muted small">Loading history...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <div class="col-lg-4">
                
                <div class="card-clean mb-3">
                    <div class="card-header-simple">
                        <span><i class="bi bi-chat-heart-fill me-2 text-danger"></i> Speak to Doctor</span>
                    </div>
                    <div class="p-3">
                        <textarea id="concernMessage" class="form-control bg-light border-0 mb-2" rows="2" placeholder="Type your concern..." style="border-radius: 8px; resize: none; font-size: 0.9rem;"></textarea>
                        <button onclick="submitConcern()" class="btn btn-primary-soft w-100 btn-sm">Send Message</button>
                    </div>
                </div>

                <div class="card-clean">
                    <div class="card-header-simple">
                        <span><i class="bi bi-cpu-fill me-2 text-secondary"></i> My Implants</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead><tr><th class="ps-3">Device</th><th>Serial</th></tr></thead>
                            <tbody id="implants-body">
                                <tr><td colspan="2" class="text-center py-3 text-muted small">Checking registry...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="signModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark ms-2 mt-2" style="font-size: 1.1rem;">Digital Signature</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <input type="hidden" id="signConsentId">
                    <div class="text-center mb-3 mt-2">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-3 mb-2">
                            <i class="bi bi-pen-fill fs-4"></i>
                        </div>
                        <h6 id="signTitle" class="fw-bold mb-0">Procedure Name</h6>
                        <p class="text-muted small mb-0">Informed Consent Form</p>
                    </div>

                    <div class="alert alert-light border small text-secondary mb-3 p-3 rounded-3" style="font-size: 0.85rem;">
                        <i class="bi bi-info-circle me-2"></i> I hereby authorize the performance of the procedure listed above. I understand the risks, benefits, and alternatives as explained to me by my physician.
                    </div>

                    <div class="form-check p-3 bg-light rounded-3 mb-3 border">
                        <input class="form-check-input mt-1" type="checkbox" id="agreeCheck" onchange="document.getElementById('btnConfirmSign').disabled = !this.checked">
                        <label class="form-check-label fw-bold ms-2 small" for="agreeCheck">
                            I agree to sign this document electronically.
                        </label>
                    </div>
                    
                    <button type="button" class="btn btn-primary-soft w-100 py-2 fw-bold" id="btnConfirmSign" onclick="submitConsent()" disabled>
                        Confirm & Sign
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE_PATH = './api';
        const signModal = new bootstrap.Modal(document.getElementById('signModal'));

        document.addEventListener('DOMContentLoaded', () => {
            loadMetadata();
            refreshSurgeryList();
            loadConsents();
            loadImplants();
        });

        // 1. IMPLANTS
        async function loadImplants() {
            const tbody = document.getElementById('implants-body');
            try {
                const res = await fetch(`${API_BASE_PATH}/implants.php`);
                const data = await res.json();

                if (data.length > 0) {
                    tbody.innerHTML = '';
                    data.forEach(item => {
                        tbody.innerHTML += `
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark small">${item.name}</div>
                                    <div class="text-muted text-truncate" style="font-size:0.7rem; max-width:120px;">${item.procedure}</div>
                                </td>
                                <td class="text-end pe-3"><span class="badge bg-light text-dark border font-monospace" style="font-size:0.75rem">${item.serial}</span></td>
                            </tr>
                        `;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center py-3 text-muted small">No implants registered.</td></tr>';
                }
            } catch (e) {
                console.error("Implants error", e);
                tbody.innerHTML = '<tr><td colspan="2" class="text-center py-3 text-danger small">Error loading data.</td></tr>';
            }
        }

        // 2. CONSENTS
        async function loadConsents() {
            try {
                const response = await fetch(`${API_BASE_PATH}/consents.php`);
                const data = await response.json();
                const container = document.getElementById('consents-body');
                container.innerHTML = '';

                let pending = 0;

                if (data.length === 0) {
                    container.innerHTML = '<li class="list-group-item text-center text-muted p-3 small">No pending documentation.</li>';
                } else {
                    data.forEach(c => {
                        if (!c.signed) pending++;

                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center py-2 px-3 bg-transparent';

                        const leftDiv = document.createElement('div');
                        leftDiv.innerHTML = `<div class="fw-bold text-dark" style="font-size:0.85rem">${c.type}</div><div class="text-muted" style="font-size:0.75rem">${c.procedure}</div>`;

                        const rightDiv = document.createElement('div');

                        if (c.signed) {
                            rightDiv.innerHTML = `<span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-lg me-1"></i> Signed</span>`;
                        } else {
                            const btn = document.createElement('button');
                            btn.className = 'btn btn-sm btn-primary-soft shadow-sm py-1 px-3';
                            btn.style.fontSize = '0.8rem';
                            btn.innerHTML = 'Sign <i class="bi bi-pen ms-1"></i>';
                            btn.onclick = function () { openSignModal(c.id, c.procedure); };
                            rightDiv.appendChild(btn);
                        }

                        li.appendChild(leftDiv);
                        li.appendChild(rightDiv);
                        container.appendChild(li);
                    });
                }

                const badge = document.getElementById('pendingCount');
                const kpi = document.getElementById('kpiPending');
                
                badge.textContent = pending > 0 ? `${pending} Pending` : "All Done";
                kpi.textContent = pending;
                
                if (pending > 0) {
                    badge.classList.remove('bg-success'); badge.classList.add('bg-warning');
                } else {
                    badge.classList.remove('bg-warning'); badge.classList.add('bg-success');
                }

            } catch (e) {
                console.error("Consents error:", e);
                document.getElementById('consents-body').innerHTML = '<li class="list-group-item text-danger text-center small p-3">Error loading consents.</li>';
            }
        }

        // 3. SIGNING
        function openSignModal(id, title) {
            document.getElementById('signConsentId').value = id;
            document.getElementById('signTitle').textContent = title;
            document.getElementById('agreeCheck').checked = false;
            document.getElementById('btnConfirmSign').disabled = true;
            signModal.show();
        }

        async function submitConsent() {
            const cid = document.getElementById('signConsentId').value;
            try {
                const res = await fetch(`${API_BASE_PATH}/sign_consent.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ consent_id: cid })
                });
                const data = await res.json();

                if (data.success) {
                    signModal.hide();
                    loadConsents(); 
                    refreshSurgeryList(); 
                } else {
                    alert("Error: " + (data.error || 'Unknown error'));
                }
            } catch (e) { console.error(e); alert("System error."); }
        }

        // 4. SURGERY LIST
        async function refreshSurgeryList() {
            const tbody = document.getElementById('surgery-table-body');
            const period = document.getElementById('periodSelect').value;
            const status = document.getElementById('statusSelect').value;
            const type = document.getElementById('typeSelect').value;
            const queryParams = `?period=${period}&status=${status}&type=${type}`;

            try {
                const response = await fetch(`${API_BASE_PATH}/surgery_list_fixed.php${queryParams}`);
                const responseText = await response.text();
                let data;
                try { data = JSON.parse(responseText); } catch (e) { throw new Error("Invalid JSON"); }

                tbody.innerHTML = '';

                if (data.error) throw new Error(data.error);

                if (data.length > 0) {
                    data.forEach(proc => {
                        const tr = document.createElement('tr');
                        tr.onclick = () => window.location.href = `surgery_details.php?id=${proc.id}`;

                        let alertBadge = proc.pending_consents > 0 ? `<span class="badge bg-danger animate-pulse ms-2" style="font-size:0.6rem">SIGN</span>` : '';

                        tr.innerHTML = `
                            <td class="ps-4">
                                <div class="fw-bold text-dark" style="font-size:0.9rem">${proc.name}</div>
                                ${alertBadge}
                            </td>
                            <td class="text-muted small">${proc.date_time}</td>
                            <td class="fw-bold small text-dark">${proc.room}</td>
                            <td><span class="status-badge ${proc.status_class}">${proc.status}</span></td>
                            <td class="text-end pe-4"><i class="bi bi-chevron-right text-muted small"></i></td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted small">No surgeries found.</td></tr>';
                }
            } catch (e) {
                console.error(e);
                tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger small">Error loading data.</td></tr>`;
            }
        }

        // 5. METADATA & UTILS
        async function loadMetadata() {
            try {
                const res = await fetch(`${API_BASE_PATH}/metadata.php`);
                const data = await res.json();
                if (data.patient_name) {
                    document.getElementById('patientNameDisplay').textContent = data.patient_name;
                }
                const typeSelect = document.getElementById('typeSelect');
                while (typeSelect.options.length > 1) { typeSelect.remove(1); }
                if (data.procedures) {
                    data.procedures.forEach(p => {
                        let opt = document.createElement('option');
                        opt.value = p; opt.textContent = p;
                        typeSelect.appendChild(opt);
                    });
                }
            } catch (e) { console.error("Meta error", e); }
        }

        async function submitConcern() {
            const msg = document.getElementById('concernMessage').value;
            if (!msg.trim()) return alert("Please type a message.");
            try {
                const res = await fetch(`${API_BASE_PATH}/submit_concern.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: msg })
                });
                const data = await res.json();
                if (data.success) {
                    alert("Concern sent to care team.");
                    document.getElementById('concernMessage').value = '';
                } else {
                    alert("Error: " + data.error);
                }
            } catch (e) { console.error(e); alert("System error."); }
        }
        
        // Filter event listeners
        document.getElementById('statusSelect').addEventListener('change', refreshSurgeryList);
        document.getElementById('typeSelect').addEventListener('change', refreshSurgeryList);
        document.getElementById('periodSelect').addEventListener('change', refreshSurgeryList);
    </script>
</body>
</html>