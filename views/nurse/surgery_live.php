<?php
// groupd/views/nurse/surgery_live.php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();

// Nurse or Admin
if (strpos(strtolower($_SESSION['role_name']), 'nurse') === false && strpos(strtolower($_SESSION['role_name']), 'admin') === false) {
    die("Access Denied.");
}

$spid = $_GET['id'] ?? 0;
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT sp.*, p.procedure_name, pat.first_name, pat.surnames, op.room_number 
                       FROM SURGICAL_PROCEDURE sp 
                       JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id 
                       JOIN EPISODE ep ON sp.episode_id = ep.episode_id 
                       JOIN PATIENT pat ON ep.patient_id = pat.patient_id 
                       LEFT JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id 
                       WHERE sp.surgical_procedure_id = ?");
$stmt->execute([$spid]);
$surgery = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$surgery) die("Surgery not found.");

// Material List
$materials = [
    ['id' => 1, 'name' => 'Gauze Pad 10x10'],
    ['id' => 2, 'name' => 'Suture Vicryl 2-0'],
    ['id' => 3, 'name' => 'Suture Silk 3-0'],
    ['id' => 4, 'name' => 'Scalpel Blade #10'],
    ['id' => 5, 'name' => 'Suction Tube'],
    ['id' => 6, 'name' => 'Lap Sponge']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Live Monitor | <?php echo htmlspecialchars($surgery['procedure_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { 
            --hospital-blue: #0d6efd; --bg-light: #f5f7fb; --text-dark: #344767;
            --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        }
        body { background-color: var(--bg-light); font-family: 'Segoe UI', sans-serif; color: var(--text-dark); height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        .top-header { background: white; border-bottom: 1px solid #e9ecef; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); z-index: 10; }
        .patient-title { font-size: 1.25rem; font-weight: 700; color: var(--text-dark); margin: 0; }
        .meta-badge { background: #f0f2f5; color: #6c757d; font-weight: 600; font-size: 0.85rem; padding: 5px 10px; border-radius: 8px; }
        .main-content { flex-grow: 1; display: flex; overflow: hidden; }
        .monitor-panel { flex: 1; padding: 30px; overflow-y: auto; display: flex; flex-direction: column; }
        .vitals-card { background: white; border-radius: 16px; box-shadow: var(--card-shadow); padding: 25px; text-align: center; transition: transform 0.2s; border: 1px solid #fff; }
        .vital-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: #8898aa; font-weight: 700; margin-bottom: 10px; }
        .vital-value { font-size: 3.5rem; font-weight: 800; line-height: 1; color: var(--text-dark); }
        .vital-unit { font-size: 1rem; color: #adb5bd; font-weight: 500; }
        .text-hr { color: #2dce89 !important; } .text-bp { color: #f5365c !important; } .text-spo2 { color: #11cdef !important; }
        .chart-container { background: white; border-radius: 16px; box-shadow: var(--card-shadow); padding: 20px; margin-top: 30px; flex-grow: 1; min-height: 300px; position: relative; }
        .timeline-panel { width: 350px; background: white; border-left: 1px solid #e9ecef; display: flex; flex-direction: column; box-shadow: -4px 0 20px rgba(0,0,0,0.02); }
        .timeline-header { padding: 20px; border-bottom: 1px solid #e9ecef; font-weight: 700; color: var(--text-dark); background: #fafbfc; }
        .timeline-feed { flex-grow: 1; overflow-y: auto; padding: 20px; }
        .feed-item { padding-left: 20px; position: relative; margin-bottom: 25px; border-left: 2px solid #e9ecef; }
        .feed-item::before { content: ''; position: absolute; left: -6px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: #cbd5e0; border: 2px solid white; box-shadow: 0 0 0 2px #fff; }
        .type-medication::before { background: #5e72e4; } .type-event::before { background: #f5365c; }
        .feed-time { font-size: 0.75rem; color: #8898aa; font-weight: 600; }
        .feed-title { font-weight: 700; font-size: 0.95rem; color: var(--text-dark); margin-bottom: 4px; display: block; }
        .feed-details { font-size: 0.85rem; color: #525f7f; background: #f6f9fc; padding: 8px 12px; border-radius: 8px; display: inline-block; margin-top: 5px; }
        .fab-btn { position: fixed; bottom: 30px; right: 380px; background: linear-gradient(135deg, #2dce89 0%, #2dcecc 100%); color: white; border: none; border-radius: 50px; padding: 12px 24px; font-weight: 700; box-shadow: 0 4px 15px rgba(45, 206, 137, 0.4); transition: all 0.2s; z-index: 100; display: flex; align-items: center; gap: 10px; }
        .fab-btn:hover { transform: translateY(-2px); box-shadow: 0 7px 20px rgba(45, 206, 137, 0.5); color: white; }
    </style>
</head>
<body>

    <div class="top-header">
        <div class="d-flex align-items-center gap-4">
            <a href="nurse_dashboard.php" class="btn btn-outline-secondary btn-sm border-0 fw-bold text-muted"><i class="bi bi-chevron-left"></i> Dashboard</a>
            <div>
                <h1 class="h5 fw-bold mb-0 text-dark"><?php echo htmlspecialchars($surgery['first_name'].' '.$surgery['surnames']); ?></h1>
                <small class="text-muted"><i class="bi bi-door-open"></i> <?php echo htmlspecialchars($surgery['room_number'] ?? 'TBD'); ?> &bull; <?php echo htmlspecialchars($surgery['procedure_name']); ?></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2"><span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill fw-bold"><span class="spinner-grow spinner-grow-sm me-1" role="status"></span> LIVE</span></div>
    </div>

    <div class="main-content">
        <div class="monitor-panel">
            <div class="row g-4">
                <div class="col-md-4"><div class="vitals-card"><div class="vital-label">Heart Rate</div><div class="vital-value text-hr" id="disp_hr">--</div><div class="vital-unit">bpm</div></div></div>
                <div class="col-md-4"><div class="vitals-card"><div class="vital-label">Blood Pressure</div><div class="vital-value text-bp" id="disp_bp">--/--</div><div class="vital-unit">mmHg</div></div></div>
                <div class="col-md-4"><div class="vitals-card"><div class="vital-label">O2 Saturation</div><div class="vital-value text-spo2" id="disp_spo2">--</div><div class="vital-unit">%</div></div></div>
            </div>
            <div class="chart-container"><h6 class="text-muted fw-bold small mb-3">VITALS TREND (LAST 30 MIN)</h6><canvas id="readOnlyChart"></canvas></div>
        </div>
        <div class="timeline-panel">
            <div class="timeline-header"><i class="bi bi-activity me-2 text-primary"></i> Surgical Events</div>
            <div class="timeline-feed" id="liveFeed"><div class="text-center text-muted mt-5 small">Waiting for data...</div></div>
        </div>
    </div>

    <button class="fab-btn" data-bs-toggle="modal" data-bs-target="#materialModal"><i class="bi bi-box-seam fs-5"></i> <span>Log Material</span></button>

    <div class="modal fade" id="materialModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-bottom-0 pb-0"><h5 class="modal-title fw-bold text-dark">Log Surgical Material</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <form id="materialForm">
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">ITEM NAME</label>
                            <select id="matSelect" class="form-select form-select-lg bg-light border-0" required>
                                <option value="">Select Item...</option>
                                <?php foreach($materials as $m): ?><option value="<?php echo $m['id']; ?>"><?php echo $m['name']; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">QUANTITY</label>
                            <div class="d-flex align-items-center gap-3">
                                <button type="button" class="btn btn-outline-secondary rounded-circle" style="width:40px;height:40px;" onclick="adjustQty(-1)"><i class="bi bi-dash"></i></button>
                                <input type="number" id="matQty" class="form-control form-control-lg text-center border-0 bg-light fw-bold" value="1" min="1" style="width: 80px;">
                                <button type="button" class="btn btn-outline-secondary rounded-circle" style="width:40px;height:40px;" onclick="adjustQty(1)"><i class="bi bi-plus"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm">CONFIRM & SAVE</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const spid = <?php echo $spid; ?>;
    let myChart = null;

    function initChart() {
        const ctx = document.getElementById('readOnlyChart').getContext('2d');
        let gradientHR = ctx.createLinearGradient(0, 0, 0, 400);
        gradientHR.addColorStop(0, 'rgba(45, 206, 137, 0.2)');
        gradientHR.addColorStop(1, 'rgba(45, 206, 137, 0)');

        myChart = new Chart(ctx, {
            type: 'line',
            data: { labels: [], datasets: [
                { label: 'Heart Rate', borderColor: '#2dce89', backgroundColor: gradientHR, borderWidth: 3, pointRadius: 0, fill: true, tension: 0.4, data: [], yAxisID: 'y' },
                { label: 'Systolic BP', borderColor: '#f5365c', borderWidth: 2, borderDash: [5, 5], pointRadius: 3, pointBackgroundColor: '#fff', pointBorderColor: '#f5365c', tension: 0.3, data: [], yAxisID: 'y' }
            ]},
            options: { 
                responsive: true, maintainAspectRatio: false, animation: false,
                plugins: { legend: { position: 'top', align: 'end' } },
                scales: { x: { display: false }, y: { grid: { color: '#f1f3f5' }, suggestedMin: 40, suggestedMax: 160 } } 
            }
        });
    }

    function updateChart(data) {
        if (!myChart) initChart();
        
        // CORRECCIÓN GRÁFICO (Filtrar ceros)
        const validData = data.filter(d => d.card_freq !== null && parseInt(d.card_freq) > 0);
        const recent = validData.slice(-30);
        
        myChart.data.labels = recent.map(r => r.timestamp);
        myChart.data.datasets[0].data = recent.map(r => r.card_freq);
        myChart.data.datasets[1].data = recent.map(r => r.sys);
        myChart.update();
    }

    function adjustQty(delta) {
        const input = document.getElementById('matQty');
        let val = parseInt(input.value) + delta;
        if(val < 1) val = 1;
        input.value = val;
    }

    document.getElementById('materialForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        try {
            const res = await fetch('api_nurse_actions.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    action: 'log_material', spid: spid, 
                    material_id: document.getElementById('matSelect').value, 
                    quantity: document.getElementById('matQty').value 
                })
            });
            const data = await res.json();
            if(data.success) {
                bootstrap.Modal.getInstance(document.getElementById('materialModal')).hide();
                refresh();
            } else alert("Error: " + data.error);
        } catch(e) { console.error(e); }
    });

    function refresh() {
        // CORRECCIÓN: Llamamos a api_anesthesia.php y procesamos la respuesta correcta
        fetch(`../anesthesiologist/api_anesthesia.php?spid=${spid}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                // 1. VITALES (Usando 'latest_vitals' del JSON)
                if (data.latest_vitals) {
                    const last = data.latest_vitals;
                    document.getElementById('disp_hr').innerText = last.card_freq || '--';
                    document.getElementById('disp_bp').innerText = (last.sys && last.dias) ? `${last.sys}/${last.dias}` : '--/--';
                    document.getElementById('disp_spo2').innerText = last.tidal_CO2 || '--';
                }
                
                // 2. GRÁFICO (Usando 'chart_data' del JSON)
                if (data.chart_data) {
                    updateChart(data.chart_data);
                }

                // 3. TIMELINE
                if (data.timeline) {
                    const feed = document.getElementById('liveFeed');
                    feed.innerHTML = '';
                    if(data.timeline.length === 0) feed.innerHTML = '<div class="text-center text-muted mt-5 small">No events.</div>';

                    data.timeline.forEach(t => {
                        const html = `
                            <div class="feed-item type-${t.type}">
                                <span class="feed-time">${t.timestamp.split(' ')[1].substring(0,5)}</span>
                                <span class="feed-title">${t.title}</span>
                                <div class="feed-details">${t.details}</div>
                            </div>`;
                        feed.innerHTML += html;
                    });
                }
            })
            .catch(err => console.error(err));
    }

    document.addEventListener('DOMContentLoaded', () => {
        initChart();
        refresh();
        setInterval(refresh, 3000); 
    });
</script>
</body>
</html>