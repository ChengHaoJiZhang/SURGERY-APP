<?php
// views/director/surgery_stats.php
session_start();
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { /* header("Location: /login.php"); exit(); */ }

$pdo = getDBConnection();
try {
    $surgeons = $pdo->query("SELECT surgeon_id, CONCAT(first_name, ' ', surnames) as name FROM SURGEONS")->fetchAll(PDO::FETCH_ASSOC);
    $rooms = $pdo->query("SELECT room_id, room_number FROM OPERATING_ROOM ORDER BY room_number ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $surgeons = []; $rooms = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surgery Statistics | Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --bg-body: #f5f7fb; --primary-color: #0d6efd; }
        body { background-color: var(--bg-body); font-family: 'Segoe UI', sans-serif; }
        
        .main-container { max-width: 1600px; margin: 40px auto; padding: 20px; } 
        .card-clean { background: white; border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; height: 100%; }
        
        .chart-container { position: relative; height: 300px; width: 100%; display: flex; justify-content: center; }
        .stat-header { padding: 15px 20px; border-bottom: 1px solid #f0f0f0; background: #fff; }
        
        /* Filter Bar */
        .filter-bar { background: white; padding: 15px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); }

        /* KPI Cards */
        .kpi-card { padding: 20px; display: flex; align-items: center; justify-content: space-between; border-left: 4px solid transparent; }
        .kpi-card.blue { border-color: #0d6efd; }
        .kpi-card.green { border-color: #198754; }
        .kpi-card.orange { border-color: #fd7e14; }
        .kpi-card.purple { border-color: #6f42c1; }
        .kpi-value { font-size: 2rem; font-weight: 700; line-height: 1; }
        .kpi-label { font-size: 0.75rem; text-transform: uppercase; color: #6c757d; font-weight: 600; letter-spacing: 0.5px; }
        
        /* List Styles for Delays */
        .list-group-item { border: none; border-bottom: 1px solid #f0f0f0; padding: 12px 15px; font-size: 0.9rem; }
        .list-group-item:last-child { border-bottom: none; }
        .delay-badge { font-size: 0.75rem; font-weight: 600; padding: 4px 8px; border-radius: 6px; }
        .bg-start-delay { background: #fff3cd; color: #856404; } /* Yellow */
        .bg-end-delay { background: #ffe5d0; color: #9c4400; } /* Orange */
    </style>
</head>
<body>

<div class="main-container">
    
    <div class="d-flex align-items-center mb-4">
        <a href="director_dashboard.php" class="btn btn-outline-secondary btn-sm me-3 rounded-circle" style="width:40px; height:40px; display:flex; align-items:center; justify-content:center;">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h3 class="fw-bold m-0">Surgery Analytics</h3>
            <p class="text-muted m-0 small">Outcomes, performance, and volume analysis</p>
        </div>
    </div>

    <div class="filter-bar d-flex flex-wrap align-items-end gap-3">
        <div>
            <label class="form-label small fw-bold text-muted mb-1">Period</label>
            <select id="periodFilter" class="form-select border-0 bg-light" style="min-width: 150px;">
                <option value="day">Today</option>
                <option value="week">This Week</option>
                <option value="month" selected>This Month</option>
                <option value="year">This Year</option>
            </select>
        </div>
        <div>
            <label class="form-label small fw-bold text-muted mb-1">Surgeon</label>
            <select id="surgeonFilter" class="form-select border-0 bg-light">
                <option value="">All Surgeons</option>
                <?php foreach($surgeons as $s): ?>
                    <option value="<?php echo htmlspecialchars($s['surgeon_id']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small fw-bold text-muted mb-1">Operating Room</label>
            <select id="roomFilter" class="form-select border-0 bg-light">
                <option value="">All Rooms</option>
                <?php foreach($rooms as $r): ?>
                    <option value="<?php echo htmlspecialchars($r['room_id']); ?>"><?php echo htmlspecialchars($r['room_number']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary px-4" onclick="loadStats()">
            <i class="bi bi-arrow-clockwise me-1"></i> Update
        </button>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card-clean kpi-card blue">
                <div><div class="kpi-value text-primary" id="kpi-total">...</div><div class="kpi-label">Total Procedures</div></div>
                <i class="bi bi-activity text-primary fs-3 opacity-25"></i>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card-clean kpi-card green">
                <div><div class="kpi-value text-success" id="kpi-success">...%</div><div class="kpi-label">Success Rate</div></div>
                <i class="bi bi-check-circle-fill text-success fs-3 opacity-25"></i>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card-clean kpi-card orange">
                <div><div class="kpi-value text-warning" id="kpi-comp-rate">...%</div><div class="kpi-label">Complication Rate</div></div>
                <i class="bi bi-exclamation-triangle-fill text-warning fs-3 opacity-25"></i>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card-clean kpi-card purple">
                <div>
                    <div class="kpi-value" style="color: #6f42c1;" id="kpi-total-delays">...</div>
                    <div class="kpi-label" style="color: #6f42c1;">Total Delay Events</div>
                </div>
                <i class="bi bi-clock-history fs-3 opacity-25" style="color: #6f42c1;"></i>
            </div>
        </div>
    </div>

    <h6 class="text-uppercase text-muted fw-bold mb-3 small ps-1">Operational Efficiency</h6>
    <div class="row g-4 mb-4">
        
        <div class="col-lg-8">
            <div class="card-clean h-100">
                <div class="stat-header">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="fw-bold m-0"><i class="bi bi-stopwatch text-info me-2"></i>Avg. Time Deviation</h6>
                        <span class="badge bg-light text-muted border">Actual Duration vs. Standard</span>
                    </div>
                    <div class="bg-light p-2 rounded small text-muted border">
                        <i class="bi bi-info-circle-fill me-1"></i> 
                        <strong>Interpretation:</strong>
                        <ul class="mb-0 ps-3 mt-1">
                            <li><span class="text-info fw-bold">Negative Value (-)</span>: Faster than conventional time. (High Efficiency)</li>
                            <li><span class="text-danger fw-bold">Positive Value (+)</span>: Slower than conventional time. (Potential Delay)</li>
                        </ul>
                    </div>
                </div>
                <div class="p-4">
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                    <div id="no-perf-msg" class="text-center small text-muted d-none mt-3">No performance data available.</div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-clean h-100 p-0 overflow-hidden border-top border-warning border-3">
                <div class="stat-header bg-light">
                    <h6 class="fw-bold m-0 text-dark"><i class="bi bi-exclamation-circle text-warning me-2"></i>Delay Insights</h6>
                    <small class="text-muted">Start >10m late OR End >15m late</small>
                </div>
                
                <div class="p-0">
                    <ul class="nav nav-tabs nav-fill" id="delayTabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active rounded-0 border-0 border-bottom" data-bs-toggle="tab" data-bs-target="#tab-surgeons">By Surgeon</button></li>
                        <li class="nav-item"><button class="nav-link rounded-0 border-0 border-bottom" data-bs-toggle="tab" data-bs-target="#tab-rooms">By Room</button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-surgeons">
                            <div class="list-group list-group-flush" id="surgeonDelayList">
                                <div class="text-center py-4 text-muted small">No significant delays recorded.</div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-rooms">
                            <div class="list-group list-group-flush" id="roomDelayList">
                                <div class="text-center py-4 text-muted small">All rooms running on schedule.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <h6 class="text-uppercase text-muted fw-bold mb-3 small ps-1">Clinical Outcomes & Volume</h6>
    <div class="row g-4 mb-5">
        <div class="col-lg-4">
            <div class="card-clean">
                <div class="stat-header">
                    <h6 class="fw-bold m-0"><i class="bi bi-pie-chart text-primary me-2"></i>Volume Breakdown</h6>
                </div>
                <div class="p-4">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="volChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <div class="card-clean border-top border-danger border-3">
                <div class="stat-header">
                    <h6 class="fw-bold m-0 text-danger"><i class="bi bi-shield-exclamation me-2"></i>Safety Events (Filtered)</h6>
                </div>
                <div class="p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="text-uppercase text-muted fw-bold small text-center mb-3">Complications</p>
                            <div class="chart-container" style="height: 200px;">
                                <canvas id="compChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6 border-start">
                            <p class="text-uppercase text-muted fw-bold small text-center mb-3">Mortality Causes</p>
                            <div class="chart-container" style="height: 200px;">
                                <canvas id="mortalityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    let charts = {}; 

    async function loadStats() {
        const period = document.getElementById('periodFilter').value;
        const surgeonId = document.getElementById('surgeonFilter').value;
        const roomId = document.getElementById('roomFilter').value;
        const queryParams = new URLSearchParams({ period, surgeon_id: surgeonId, room_id: roomId }).toString();

        try {
            const res = await fetch(`api/chart_data.php?${queryParams}`);
            const data = await res.json();
            
            // 1. Update KPIs
            document.getElementById('kpi-total').innerText = data.metrics.total_surgeries_filtered;
            document.getElementById('kpi-success').innerText = data.metrics.success_rate + '%';
            document.getElementById('kpi-comp-rate').innerText = data.metrics.complication_rate + '%';
            
            const totalRoomDelays = data.rooms.late_starts.reduce((a,b)=>parseInt(a)+parseInt(b),0) + data.rooms.late_finishes.reduce((a,b)=>parseInt(a)+parseInt(b),0);
            document.getElementById('kpi-total-delays').innerText = totalRoomDelays;

            // 2. Volume Chart
            renderChart('volChart', 'doughnut', data.volume, ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#fd7e14']);

            // 3. Surgeon Speed (Deviation Chart)
            const hasPerfData = data.performance.data.length > 0;
            toggleMsg('no-perf-msg', !hasPerfData);
            if(hasPerfData) {
                // Color Logic: Negative/Zero = Blue (Good), Positive = Red (Late)
                const perfColors = data.performance.data.map(v => v > 0 ? '#dc3545' : '#0dcaf0');
                renderBarChart('performanceChart', data.performance, perfColors, 'Avg Minutes');
            }

            // 4. Delay Lists
            renderSurgeonDelayList(data.surgeon_delays);
            renderRoomDelayList(data.rooms);

            // 5. Safety Charts
            renderBarChart('compChart', data.complications, '#fd7e14', 'Cases');
            renderBarChart('mortalityChart', data.mortality, '#dc3545', 'Cases');

        } catch (err) { console.error("Error loading statistics:", err); }
    }

    // --- List Renderers ---

    function renderSurgeonDelayList(data) {
        const container = document.getElementById('surgeonDelayList');
        container.innerHTML = '';
        
        if (!data.labels || data.labels.length === 0) {
            container.innerHTML = '<div class="text-center py-4 text-muted small"><i class="bi bi-check-circle text-success fs-4 d-block mb-2"></i>No surgeon delays recorded.</div>';
            return;
        }

        data.labels.forEach((name, index) => {
            const count = data.data[index];
            container.innerHTML += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">${name}</span>
                    <span class="badge bg-warning text-dark border border-warning rounded-pill px-3">${count} Delays</span>
                </div>
            `;
        });
    }

    function renderRoomDelayList(data) {
        const container = document.getElementById('roomDelayList');
        container.innerHTML = '';

        if (!data.labels || data.labels.length === 0) {
            container.innerHTML = '<div class="text-center py-4 text-muted small"><i class="bi bi-check-circle text-success fs-4 d-block mb-2"></i>No room delays recorded.</div>';
            return;
        }

        data.labels.forEach((room, index) => {
            const starts = data.late_starts[index];
            const finishes = data.late_finishes[index];
            
            if (starts > 0 || finishes > 0) {
                container.innerHTML += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold">${room}</span>
                        </div>
                        <div class="d-flex gap-2">
                            ${starts > 0 ? `<span class="delay-badge bg-start-delay"><i class="bi bi-hourglass-top me-1"></i>${starts} Late Starts</span>` : ''}
                            ${finishes > 0 ? `<span class="delay-badge bg-end-delay"><i class="bi bi-hourglass-bottom me-1"></i>${finishes} Late Ends</span>` : ''}
                        </div>
                    </div>
                `;
            }
        });
    }

    // --- Chart Helpers ---

    function toggleMsg(id, show) {
        const el = document.getElementById(id);
        const canvasDiv = el.previousElementSibling; 
        if(show) { el.classList.remove('d-none'); canvasDiv.style.display = 'none'; }
        else { el.classList.add('d-none'); canvasDiv.style.display = 'flex'; }
    }

    function renderChart(id, type, data, colors) {
        if(charts[id]) charts[id].destroy();
        charts[id] = new Chart(document.getElementById(id), {
            type: type,
            data: { labels: data.labels, datasets: [{ data: data.data, backgroundColor: colors, borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'right', labels: { boxWidth: 10 } } } }
        });
    }

    function renderBarChart(id, data, colorOrArray, label) {
        if(charts[id]) charts[id].destroy();
        if(data.data.length === 0) return; 
        
        charts[id] = new Chart(document.getElementById(id), {
            type: 'bar',
            indexAxis: 'y',
            data: { 
                labels: data.labels, 
                datasets: [{ 
                    label: label, 
                    data: data.data, 
                    backgroundColor: colorOrArray, 
                    borderRadius: 4,
                    barThickness: 20
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false } },
                scales: { 
                    x: { beginAtZero: true, grid: { display: true, color: '#f0f0f0' } }, 
                    y: { grid: { display: false }, ticks: { font: { size: 11 } } } 
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', loadStats);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>