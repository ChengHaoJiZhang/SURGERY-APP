<?php
// groupd/views/surgeon/operative_report.php

// 1. GESTIÓN DE ERRORES (Ocultar rutas del servidor)
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

// 2. SEGURIDAD
$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'surgeon') === false) die("Access Denied.");

$spid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// --- VERIFICACIÓN CRÍTICA: Si no hay ID, detenemos.
if ($spid === 0) die("Surgical Procedure ID is missing."); 
$pdo = getDBConnection();

// 3. OBTENER DATOS DE LA CIRUGÍA
$stmt = $pdo->prepare("SELECT sp.*, p.procedure_name, pat.first_name, pat.surnames 
                       FROM SURGICAL_PROCEDURE sp 
                       JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                       JOIN EPISODE ep ON sp.episode_id = ep.episode_id
                       JOIN PATIENT pat ON ep.patient_id = pat.patient_id
                       WHERE sp.surgical_procedure_id = ?");
$stmt->execute([$spid]);
$surgery = $stmt->fetch(PDO::FETCH_ASSOC);

// --- CORRECCIÓN PRINCIPAL: Si no existe la cirugía, detener ---
if (!$surgery) {
    die('<div class="container p-5 text-center">
            <h1 class="text-danger">Error 404</h1>
            <p class="lead">No se encontró la cirugía con ID: <strong>' . htmlspecialchars($spid) . '</strong></p>
            <a href="surgeon_dashboard.php" class="btn btn-primary">Volver al Dashboard</a>
         </div>');
}

// 4. OBTENER IMPLANTES
$implants = $pdo->prepare("SELECT * FROM IMPLANT_REGISTRY WHERE surgical_procedure_id = ?");
$implants->execute([$spid]);
$existingImplants = $implants->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Op Report | Dr. <?php echo htmlspecialchars($_SESSION['username']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        .card-death { border: 2px solid #dc3545; background-color: #fff5f5; }
        .card-death .card-header { background-color: #dc3545; color: white; }
        
        /* SNOMED Search Styles */
        .snomed-wrapper { position: relative; }
        .snomed-results { position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; background: white; border: 1px solid #dee2e6; border-radius: 0 0 8px 8px; max-height: 250px; overflow-y: auto; box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: none; }
        .snomed-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f1f1f1; font-size: 0.9rem; }
        .snomed-item:hover { background-color: #e9ecef; color: #0d6efd; }
        .snomed-code { font-size: 0.75rem; color: #6c757d; float: right; font-family: monospace; }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="surgeon_dashboard.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            <h3 class="fw-bold mt-1">Surgical Operative Report</h3>
            <p class="text-muted mb-0">Proc: <strong><?php echo htmlspecialchars($surgery['procedure_name']); ?></strong> | Patient: <strong><?php echo htmlspecialchars($surgery['first_name'].' '.$surgery['surnames']); ?></strong></p>
        </div>
        <button onclick="saveReport()" class="btn btn-primary btn-lg shadow"><i class="bi bi-save2 me-2"></i> Save & Sign</button>
    </div>

    <div class="row g-4">
        
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><i class="bi bi-file-text"></i> Procedure Details</div>
                <div class="card-body">
                    
                    <div class="mb-3 snomed-wrapper">
                        <label class="fw-bold small text-muted">POST-OPERATIVE DIAGNOSIS (SNOMED CT)</label>
                        <input type="text" id="snomed_search" class="form-control" 
                               placeholder="Type to search diagnosis..." 
                               value="<?php echo htmlspecialchars($surgery['post_op_diagnosis'] ?? ''); ?>" 
                               autocomplete="off">
                        <input type="hidden" id="snomed_code" value="<?php echo htmlspecialchars($surgery['snomed_code'] ?? ''); ?>">
                        <div id="snomed_list" class="snomed-results"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold small text-muted">SURGICAL REPORT (FINDINGS & TECHNIQUE)</label>
                        <textarea id="surgical_report" class="form-control" rows="15"><?php echo htmlspecialchars($surgery['surgical_report'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold small text-muted">EST. BLOOD LOSS (ml)</label>
                            <input type="number" id="blood_loss" class="form-control" value="<?php echo htmlspecialchars($surgery['blood_loss_ml'] ?? '0'); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold small text-muted">INTRA-OP COMPLICATIONS?</label>
                            <select id="complications" class="form-select" onchange="toggleComp(this.value)">
                                <option value="0" <?php echo empty($surgery['complications_yes_no']) ? 'selected' : ''; ?>>No</option>
                                <option value="1" <?php echo !empty($surgery['complications_yes_no']) ? 'selected' : ''; ?>>Yes</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="comp_box" class="mb-3 <?php echo empty($surgery['complications_yes_no']) ? 'd-none' : ''; ?>">
                        <label class="fw-bold text-danger small">COMPLICATION DETAILS</label>
                        <textarea id="comp_details" class="form-control border-danger" rows="3" placeholder="Describe unexpected events, injuries, or issues..."><?php echo htmlspecialchars($surgery['complications_details'] ?? ''); ?></textarea>
                    </div>
                    </div>
            </div>
        </div>

        <div class="col-md-4">
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white fw-bold"><i class="bi bi-cpu"></i> Implant Registry</div>
                <div class="card-body">
                    <div id="implantList" class="mb-3">
                        <?php if(empty($existingImplants)): ?>
                            <p class="text-muted small fst-italic">No implants recorded.</p>
                        <?php else: ?>
                            <?php foreach($existingImplants as $imp): ?>
                                <div class="alert alert-light border py-2 px-2 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo htmlspecialchars($imp['device_name']); ?></strong>
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    </div>
                                    <small class="text-muted d-block">SN: <?php echo htmlspecialchars($imp['serial_number']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <h6 class="small fw-bold">Register New Device</h6>
                    <input type="text" id="imp_name" class="form-control form-control-sm mb-2" placeholder="Device Name">
                    <input type="text" id="imp_serial" class="form-control form-control-sm mb-2" placeholder="Serial / Lot #">
                    <button onclick="addImplant()" class="btn btn-sm btn-dark w-100">Add Implant</button>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white fw-bold"><i class="bi bi-clipboard-check"></i> Post-Op Orders</div>
                <div class="card-body">
                    <textarea id="post_op_orders" class="form-control" rows="4" placeholder="Diet, Meds, Wound Care..."><?php echo htmlspecialchars($surgery['post_op_instructions'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="card shadow-sm" id="outcomeCard">
                <div class="card-header bg-light text-dark fw-bold border-bottom" id="outcomeHeader">
                    <i class="bi bi-heart-pulse"></i> Patient Outcome
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="chk_deceased" onchange="toggleDeath(this.checked)" <?php echo ($surgery['patient_deceased']??0) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="chk_deceased">Patient Deceased in OR</label>
                    </div>

                    <div id="death_fields" class="<?php echo ($surgery['patient_deceased']??0) ? '' : 'd-none'; ?>">
                        <div class="alert alert-danger d-flex align-items-center p-2 mb-3">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                            <small class="lh-1"><strong>Critical Event:</strong> Please document time and cause of death accurately.</small>
                        </div>
                        
                        <div class="mb-2">
                            <label class="small fw-bold text-danger">TIME OF DEATH</label>
                            <input type="datetime-local" id="death_time" class="form-control form-control-sm border-danger" 
                                   value="<?php echo !empty($surgery['death_time']) ? date('Y-m-d\TH:i', strtotime($surgery['death_time'])) : ''; ?>">
                        </div>
                        
                        <div class="mb-2 snomed-wrapper">
                            <label class="small fw-bold text-danger">CAUSE OF DEATH (SNOMED CT)</label>
                            <input type="text" id="death_cause_search" class="form-control form-control-sm border-danger" 
                                   placeholder="Search cause (e.g. Cardiac Arrest)..." 
                                   value="<?php echo htmlspecialchars($surgery['cause_of_death'] ?? ''); ?>"
                                   autocomplete="off">
                            <input type="hidden" id="death_cause_code" value="<?php echo htmlspecialchars($surgery['cause_of_death_code'] ?? ''); ?>">
                            <div id="death_cause_list" class="snomed-results"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // **CRÍTICO: Aseguramos que el ID de la cirugía se pasa al JavaScript**
    const spid = <?php echo $spid; ?>; 

    // --- GENERIC SNOMED SEARCH FUNCTION ---
    function setupSnomedSearch(inputId, codeId, listId) {
        const input = document.getElementById(inputId);
        const codeInput = document.getElementById(codeId);
        const list = document.getElementById(listId);

        if (!input) return;

        input.addEventListener('input', async function() {
            const q = this.value;
            if (q.length < 2) { list.style.display = 'none'; return; }
            
            try {
                // Asegúrate de que esta ruta es correcta y tu API de búsqueda SNOMED funciona
                const res = await fetch(`api_search_snomed.php?q=${q}`); 
                const data = await res.json();
                list.innerHTML = '';
                
                if (data.length > 0) {
                    list.style.display = 'block';
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'snomed-item';
                        div.innerHTML = `${item.term} <span class="snomed-code">${item.id}</span>`;
                        div.onclick = () => {
                            input.value = item.term;
                            if(codeInput) codeInput.value = item.id;
                            list.style.display = 'none';
                        };
                        list.appendChild(div);
                    });
                } else {
                    list.style.display = 'none';
                }
            } catch (e) { console.error("SNOMED Search Error:", e); }
        });

        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !list.contains(e.target)) {
                list.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupSnomedSearch('snomed_search', 'snomed_code', 'snomed_list');
        setupSnomedSearch('death_cause_search', 'death_cause_code', 'death_cause_list');
    });

    // --- UI LOGIC ---
    function toggleComp(val) {
        const box = document.getElementById('comp_box');
        if (val == '1') {
            box.classList.remove('d-none');
            document.getElementById('comp_details').focus();
        } else {
            box.classList.add('d-none');
        }
    }

    function toggleDeath(isDeceased) {
        const fields = document.getElementById('death_fields');
        const card = document.getElementById('outcomeCard');
        const header = document.getElementById('outcomeHeader');

        if(isDeceased) {
            fields.classList.remove('d-none');
            card.classList.add('card-death'); 
            header.classList.remove('bg-light', 'text-dark');
            header.classList.add('bg-danger', 'text-white');
            header.innerHTML = '<i class="bi bi-activity"></i> MORTALITY EVENT';
        } else {
            fields.classList.add('d-none');
            card.classList.remove('card-death');
            header.classList.add('bg-light', 'text-dark');
            header.classList.remove('bg-danger', 'text-white');
            header.innerHTML = '<i class="bi bi-heart-pulse"></i> Patient Outcome';
        }
    }
    toggleDeath(document.getElementById('chk_deceased').checked);

    // --- SAVE REPORT FUNCTION (Guarda todos los campos) ---
    async function saveReport() {
        const isDeceased = document.getElementById('chk_deceased').checked;
        
        if(isDeceased) {
            if(!document.getElementById('death_time').value || !document.getElementById('death_cause_search').value) {
                alert("CRITICAL: You must provide Time and Cause of Death.");
                return;
            }
            if(!confirm("CONFIRMATION: You are marking this patient as DECEASED. This action is irreversible. Proceed?")) return;
        }

        const payload = {
            action: 'save_report', // Indicamos la acción al backend
            spid: spid,
            
            snomed_term: document.getElementById('snomed_search').value,
            snomed_code: document.getElementById('snomed_code').value,
            
            report: document.getElementById('surgical_report').value,
            blood: document.getElementById('blood_loss').value,
            
            comp_yn: document.getElementById('complications').value,
            comp_det: document.getElementById('comp_details').value,
            
            orders: document.getElementById('post_op_orders').value,
            patient_deceased: isDeceased ? 1 : 0,
            death_time: document.getElementById('death_time').value,
            death_cause: document.getElementById('death_cause_search').value // Termino SNOMED
            // Nota: El código SNOMED de causa de muerte no se está enviando aquí,
            // si es necesario, debería incluirse en el payload desde death_cause_code
        };

        try {
            const res = await fetch('api_save_op_report.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
            });
            const data = await res.json();
            if(data.success) alert('Report Saved Successfully'); 
            else alert('Error: ' + data.error);
        } catch (e) { console.error("Save Report Network Error:", e); alert('Network Error'); }
    }

    // --- ADD IMPLANT FUNCTION (Guarda un implante) ---
    async function addImplant() {
        const name = document.getElementById('imp_name').value;
        const serial = document.getElementById('imp_serial').value;
        if(!name) {
            alert('Device Name cannot be empty.');
            return;
        }
        if(spid === 0) {
            alert('Error: Surgical Procedure ID is missing.');
            return;
        }

        try {
            const res = await fetch('api_save_op_report.php', {
                method: 'POST', 
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    action: 'add_implant', 
                    spid: spid, 
                    name: name, 
                    serial: serial 
                }) // Envía la acción 'add_implant'
            });
            const data = await res.json();
            
            if(data.success) {
                // Recargar la página para mostrar el nuevo implante en la lista
                location.reload(); 
            }
            else {
                alert('Error adding implant: ' + data.error);
            }
        } catch (e) { 
            console.error("Add Implant Network Error:", e); 
            alert('Network Error while adding implant.');
        }
    }
</script>
</body>
</html>