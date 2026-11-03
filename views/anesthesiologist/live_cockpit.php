<?php
// groupd/views/anesthesiologist/live_cockpit.php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

$spid = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

// Asegurar codificación UTF-8 en la conexión si no está configurada por defecto
$pdo->exec("SET NAMES 'utf8mb4'");

$stmt = $pdo->prepare("SELECT sp.*, p.procedure_name, pat.first_name, pat.surnames, op.room_number 
                       FROM SURGICAL_PROCEDURE sp 
                       JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                       JOIN EPISODE ep ON sp.episode_id = ep.episode_id
                       JOIN PATIENT pat ON ep.patient_id = pat.patient_id
                       LEFT JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id
                       WHERE sp.surgical_procedure_id = ?");
$stmt->execute([$spid]);
$surgery = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$surgery) die("Surgery not found");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cockpit | <?php echo htmlspecialchars($surgery['procedure_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f6f9; padding-bottom: 80px; font-family: 'Segoe UI', sans-serif; }
        .monitor-card { background: #212529; border-radius: 12px; color: white; }
        .monitor-val { font-size: 2.5rem; font-weight: 800; line-height: 1; }
        .text-hr { color: #28a745; } .text-bp { color: #dc3545; } .text-spo2 { color: #17a2b8; }
        .btn-quick { height: 60px; font-weight: bold; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1.1; }
        .btn-quick small { font-weight: normal; font-size: 0.75rem; }
        /* Asegurar que el modal se vea por encima de todo */
        .modal { z-index: 1060; }
    </style>
</head>
<body>

<div class="modal fade" id="anesChecklistModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-2"></i> Anesthesia Safety Check</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-light border mb-3">
                    <strong>Patient:</strong> <?php echo htmlspecialchars($surgery['first_name'].' '.$surgery['surnames'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><br>
                    <span class="text-muted">Procedure: <?php echo htmlspecialchars($surgery['procedure_name']); ?></span>
                </div>

                <p class="text-muted small fw-bold text-uppercase mb-2">Safety Verification</p>
                
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="chk_equip">
                    <label class="form-check-label fw-bold" for="chk_equip">Anesthesia Machine Check Completed</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="chk_oximetry">
                    <label class="form-check-label fw-bold" for="chk_oximetry">Pulse Oximetry on Patient & Functioning</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="chk_allergies">
                    <label class="form-check-label fw-bold" for="chk_allergies">Known Allergies Reviewed</label>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="chk_airway">
                    <label class="form-check-label fw-bold" for="chk_airway">Difficult Airway Risk Assessed</label>
                </div>

                <div class="d-grid">
                    <button type="button" onclick="confirmTimeIn()" class="btn btn-success fw-bold py-2 shadow-sm">
                        CONFIRM & START TIME IN
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pocModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-eyedropper me-2"></i> Record Blood Gas (POC)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="pocForm">
                    <h6 class="text-muted text-uppercase small fw-bold mb-3">Blood Gases</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-4"><label class="form-label small mb-1">pH</label><input type="number" id="poc_ph" class="form-control text-center fw-bold" placeholder="7.40" step="0.01"></div>
                        <div class="col-4"><label class="form-label small mb-1">pCO2</label><input type="number" id="poc_pco2" class="form-control text-center" placeholder="35-45" step="1"></div>
                        <div class="col-4"><label class="form-label small mb-1">pO2</label><input type="number" id="poc_po2" class="form-control text-center" placeholder="80-100" step="1"></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-3"><label class="form-label small mb-1">Lac</label><input type="number" id="poc_lac" class="form-control text-center" placeholder="<2" step="0.1"></div>
                        <div class="col-3"><label class="form-label small mb-1">Na+</label><input type="number" id="poc_na" class="form-control text-center" placeholder="140"></div>
                        <div class="col-3"><label class="form-label small mb-1">K+</label><input type="number" id="poc_k" class="form-control text-center" placeholder="4.0" step="0.1"></div>
                        <div class="col-3"><label class="form-label small mb-1">Glu</label><input type="number" id="poc_glu" class="form-control text-center" placeholder="100"></div>
                    </div>
                    <button type="button" class="btn btn-dark w-100 fw-bold py-2 mt-2" onclick="savePOC()"><i class="bi bi-save me-2"></i> SAVE RESULTS</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="bg-white border-bottom py-3 px-4 sticky-top shadow-sm d-flex justify-content-between align-items-center">
    <div>
        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($surgery['first_name'].' '.$surgery['surnames'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php if (str_contains((string)$surgery['notes'], '[MOCK ANESTHESIA DEMO]')): ?> <span class="badge text-bg-info ms-2 align-middle">SYNTHETIC DEMO</span><?php endif; ?></h5>
        <small class="text-muted"><?php echo htmlspecialchars($surgery['procedure_name']); ?> | OR: <?php echo htmlspecialchars($surgery['room_number']); ?></small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success fw-bold px-4" onclick="openChecklistModal()">
            <i class="bi bi-play-circle-fill me-2"></i> TIME IN
        </button>
        <button class="btn btn-danger fw-bold px-4" onclick="recordTime('out')">
            <i class="bi bi-stop-circle-fill me-2"></i> TIME OUT
        </button>
    </div>
</div>

<div class="container-fluid p-3">
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="monitor-card p-3 mb-3 text-center shadow">
                <div class="row">
                    <div class="col-4 border-end border-secondary"><small class="text-secondary fw-bold">HR (bpm)</small><div class="monitor-val text-hr" id="disp_hr">--</div></div>
                    <div class="col-4 border-end border-secondary"><small class="text-secondary fw-bold">NIBP (mmHg)</small><div class="monitor-val text-bp" style="font-size: 1.8rem; line-height: 2.5rem;" id="disp_bp">--/--</div></div>
                    <div class="col-4"><small class="text-secondary fw-bold">SpO2 (%)</small><div class="monitor-val text-spo2" id="disp_spo2">--</div></div>
                </div>
            </div>
            <div class="card p-3 shadow-sm mb-3 border-0">
                <h6 class="text-muted small fw-bold mb-2">MANUAL VITALS</h6>
                <div class="input-group input-group-sm mb-2"><span class="input-group-text">HR</span><input type="number" id="v_hr" class="form-control"><span class="input-group-text">SpO2</span><input type="number" id="v_spo2" class="form-control"></div>
                <div class="input-group input-group-sm mb-2"><span class="input-group-text">BP</span><input type="number" id="v_sys" class="form-control" placeholder="Sys"><input type="number" id="v_dias" class="form-control" placeholder="Dia"></div>
                <button onclick="saveManualVitals()" class="btn btn-primary btn-sm w-100 fw-bold">RECORD</button>
            </div>
            <button class="btn btn-warning w-100 fw-bold shadow-sm mb-3" data-bs-toggle="modal" data-bs-target="#pocModal"><i class="bi bi-lungs-fill me-2"></i> RECORD GASES / POC</button>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-3"><div class="card-header bg-white small fw-bold">SIMULATED VITAL-SIGN TRENDS</div><div class="card-body p-2"><canvas id="liveChart" height="250"></canvas></div></div>
            <div class="card shadow-sm border-0" style="height: 300px; overflow-y: auto;">
                <div class="card-header bg-light small fw-bold">EVENT LOG</div>
                <ul class="list-group list-group-flush small" id="timelineList"></ul>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card shadow-sm border-0 p-3 h-100">
                <h6 class="text-primary small fw-bold mb-3">QUICK MEDS</h6>
                <div class="row g-2 mb-4">
                    <div class="col-6"><button onclick="quickMed('D0026', 'Propofol', '100mg')" class="btn btn-outline-primary btn-quick w-100">Propofol<small>100mg</small></button></div>
                    <div class="col-6"><button onclick="quickMed('D0026', 'Propofol', '50mg')" class="btn btn-outline-primary btn-quick w-100">Propofol<small>50mg</small></button></div>
                    <div class="col-6"><button onclick="quickMed('D0034', 'Fentanilo', '50mcg')" class="btn btn-outline-info text-dark btn-quick w-100">Fentanilo<small>50mcg</small></button></div>
                    <div class="col-6"><button onclick="quickMed('D0038', 'Rocuronio', '50mg')" class="btn btn-outline-danger btn-quick w-100">Rocuronio<small>50mg</small></button></div>
                    <div class="col-6"><button onclick="quickMed('D0035', 'Efedrina', '5mg')" class="btn btn-outline-danger btn-quick w-100">Efedrina<small>5mg</small></button></div>
                    <div class="col-6"><button onclick="quickMed('D0036', 'Atropina', '0.5mg')" class="btn btn-outline-warning text-dark btn-quick w-100">Atropina<small>0.5mg</small></button></div>
                    <div class="col-6"><button onclick="quickMed('D0037', 'Zofran', '4mg')" class="btn btn-outline-success btn-quick w-100">Zofran<small>4mg</small></button></div>
                    <div class="col-6"><button onclick="quickMed('D0013', 'Paracet.', '1g')" class="btn btn-outline-success btn-quick w-100">Paracet.<small>1g</small></button></div>
                </div>
                <h6 class="text-danger small fw-bold mb-3">EVENTS</h6>
                <div class="d-grid gap-2">
                    <button onclick="recordTime('intubation')" class="btn btn-light border text-start fw-bold">Intubation</button>
                    <button onclick="recordTime('incision')" class="btn btn-light border text-start fw-bold">Incision</button>
                    <button onclick="recordTime('closure')" class="btn btn-light border text-start fw-bold">Closure</button>
                    <button onclick="recordTime('extubation')" class="btn btn-light border text-start fw-bold">Extubation</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const spid = <?php echo $spid; ?>;
    let myChart = null;

    // 1. INICIALIZAR EL MODAL IGUAL QUE EN EL DASHBOARD DEL CIRUJANO
    // Se declara fuera para que esté disponible globalmente
    const anesChecklistModal = new bootstrap.Modal(document.getElementById('anesChecklistModal'));

    function openChecklistModal() {
        // Reseteamos los checks
        document.getElementById('chk_equip').checked = false;
        document.getElementById('chk_oximetry').checked = false;
        document.getElementById('chk_allergies').checked = false;
        document.getElementById('chk_airway').checked = false;
        
        // Mostramos el modal
        anesChecklistModal.show();
    }

    async function confirmTimeIn() {
        // Validar que todo esté marcado
        if (!document.getElementById('chk_equip').checked || 
            !document.getElementById('chk_oximetry').checked || 
            !document.getElementById('chk_allergies').checked ||
            !document.getElementById('chk_airway').checked) {
            alert("Safety Check Failed: You must confirm all safety items.");
            return;
        }

        // Llamar a la API enviando el booleano
        const res = await apiCall({ 
            action: 'save_timestamp', 
            type: 'in', 
            checklist_verified: true 
        });

        if(res.success) {
            anesChecklistModal.hide(); // Ocultar modal
            refreshData(); // Refrescar datos
        } else {
            alert('Error: ' + res.error);
        }
    }

    // --- RESTO DE FUNCIONES (API Call, Charts, Etc) ---

    async function apiCall(payload) {
        try {
            const res = await fetch('api_anesthesia.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ ...payload, spid: spid })
            });
            return await res.json();
        } catch(e) { console.error(e); return {success:false, error:"Net Error"}; }
    }

    async function recordTime(type) {
        if(!confirm(`Confirm ${type.toUpperCase()}?`)) return;
        const res = await apiCall({ action: 'save_timestamp', type: type });
        if(res.success) {
            refreshData();
            if(type==='out') { alert('Surgery Closed.'); window.location.href='anesthesiologist_dashboard.php'; }
        } else alert('Error: ' + res.error);
    }

    async function quickMed(id, name, dose) {
        if(!confirm(`Give ${name} ${dose}?`)) return;
        const res = await apiCall({ action: 'give_med', drug_id: id, dosage: dose });
        if(res.success) refreshData();
        else alert('Error: ' + res.error);
    }

    async function saveManualVitals() {
        await apiCall({
            action: 'save_vitals',
            hr: document.getElementById('v_hr').value,
            sys: document.getElementById('v_sys').value,
            dias: document.getElementById('v_dias').value,
            spo2: document.getElementById('v_spo2').value
        });
        document.querySelectorAll('#v_hr, #v_sys, #v_dias, #v_spo2').forEach(i => i.value = '');
        refreshData();
    }

    async function savePOC() {
        const payload = {
            action: 'save_poc',
            ph: document.getElementById('poc_ph').value,
            pco2: document.getElementById('poc_pco2').value,
            po2: document.getElementById('poc_po2').value,
            lac: document.getElementById('poc_lac').value,
            na: document.getElementById('poc_na').value,
            k: document.getElementById('poc_k').value,
            glu: document.getElementById('poc_glu').value
        };
        if(!payload.ph) return alert("pH is mandatory.");
        const res = await apiCall(payload);
        if(res.success) {
            bootstrap.Modal.getInstance(document.getElementById('pocModal')).hide();
            document.querySelectorAll('#pocModal input').forEach(i => i.value = '');
            refreshData();
        }
    }

    function initChart() {
        const ctx = document.getElementById('liveChart').getContext('2d');
        myChart = new Chart(ctx, {
            type: 'line',
            data: { labels: [], datasets: [
                { label: 'Heart rate (bpm)', borderColor: '#28a745', yAxisID: 'hr', data: [], tension: 0.2, pointRadius: 1 },
                { label: 'Systolic BP (mmHg)', borderColor: '#dc3545', yAxisID: 'bp', data: [], tension: 0.2, spanGaps: true, pointRadius: 4 }
            ]},
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                scales: {
                    x: { ticks: { maxTicksLimit: 8 } },
                    hr: { type: 'linear', position: 'left', suggestedMin: 65, suggestedMax: 90,
                          title: { display: true, text: 'bpm' } },
                    bp: { type: 'linear', position: 'right', suggestedMin: 105, suggestedMax: 135,
                          grid: { drawOnChartArea: false }, title: { display: true, text: 'mmHg' } }
                }
            }
        });
    }

    async function refreshData() {
        try {
            const res = await fetch(`api_anesthesia.php?spid=${spid}&action=get_data&t=${Date.now()}`);
            const data = await res.json();
            
            if(data.success) {
                const last = data.latest_vitals;
                document.getElementById('disp_hr').textContent = last.card_freq || '--';
                document.getElementById('disp_bp').textContent = (last.sys && last.dias) ? `${last.sys}/${last.dias}` : '--/--';
                document.getElementById('disp_spo2').textContent = last.oxygen_saturation || '--';

                if(data.chart_data) {
                    // CORRECCIÓN GRÁFICO: Filtro para evitar ceros
                    const validVitals = data.chart_data.filter(d => d.card_freq !== null && parseInt(d.card_freq) > 0);
                    
                    myChart.data.labels = validVitals.map(d => d.timestamp.split(' ')[1].substring(0,8));
                    myChart.data.datasets[0].data = validVitals.map(d => d.card_freq);
                    myChart.data.datasets[1].data = validVitals.map(d => d.sys);
                    myChart.update();
                }

                const list = document.getElementById('timelineList');
                list.innerHTML = '';
                data.timeline.forEach(t => {
                    let color = 'text-muted';
                    if(t.type === 'medication') color = 'text-primary fw-bold';
                    if(t.type === 'event') color = 'text-danger fw-bold';
                    if(t.type === 'note' && t.title.includes('Gases')) color = 'text-warning fw-bold text-dark';
                    let time = t.timestamp ? t.timestamp.split(' ')[1].substring(0,5) : '';
                    list.innerHTML += `<li class="list-group-item px-0 py-2"><div class="d-flex justify-content-between"><span class="${color}">${t.title}</span><small>${time}</small></div><small class="text-muted d-block">${t.details}</small></li>`;
                });
            }
        } catch(e) { console.error(e); }
    }

    // ARRANQUE AUTOMÁTICO
    document.addEventListener('DOMContentLoaded', () => {
        initChart();
        refreshData();
        setInterval(refreshData, 3000);
    });
</script>
</body>
</html>
