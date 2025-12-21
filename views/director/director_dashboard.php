<?php
// views/director/director_dashboard.php
session_start();
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) { /* header("Location: /login.php"); exit(); */ }

$pdo = getDBConnection();
// Fetch Procedures for the filter dropdown
$all_procs = $pdo->query("SELECT DISTINCT procedure_name FROM PROCEDURES ORDER BY procedure_name")->fetchAll(PDO::FETCH_COLUMN);

// REMOVED: Fetch Specialties query is no longer needed for the sidebar
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Director | Les Corts</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root { --sidebar-width: 260px; --topbar-height: 70px; --bg-body: #f5f7fb; }
        body { background-color: var(--bg-body); font-family: 'Segoe UI', sans-serif; }
        
        /* SIDEBAR */
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; top: 0; left: 0; background: #fff; border-right: 1px solid #e9ecef; padding-top: 20px; z-index: 1000; overflow-y: auto; }
        .main-content { margin-left: var(--sidebar-width); padding: 20px; min-height: 100vh; }
        
        .nav-link { color: #67748e; padding: 10px 20px; display: flex; align-items: center; gap: 10px; font-size: 0.9rem; }
        .nav-link.active { background: #f8f9fa; color: #344767; border-right: 4px solid #0d6efd; }
        .nav-header { font-size: 0.7rem; font-weight: 700; color: #8392ab; padding: 20px 20px 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        /* SIDEBAR SEARCH */
        .sidebar-search { padding: 0 15px 15px; position: relative; margin-top: 15px; }
        .sidebar-search input { background: #f8f9fa; border: 1px solid #e9ecef; font-size: 0.85rem; padding-left: 35px; }
        .sidebar-search .bi-search { position: absolute; left: 25px; top: 8px; color: #adb5bd; }
        
        .search-results {
            position: absolute; top: 100%; left: 15px; right: 15px;
            background: white; border: 1px solid #dee2e6; border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); z-index: 1050;
            max-height: 200px; overflow-y: auto; display: none;
        }
        .result-item { padding: 8px 12px; cursor: pointer; font-size: 0.85rem; border-bottom: 1px solid #f1f1f1; display: block; text-decoration: none; color: #333; }
        .result-item:hover { background: #f8f9fa; color: #0d6efd; }
        
        /* DASHBOARD CARDS */
        .card-dash { background: #fff; border-radius: 16px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 20px; height: 100%; transition: 0.2s; }
        .card-dash:hover { transform: translateY(-2px); }
        .cursor-pointer { cursor: pointer; }
        .icon-shape { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; }
        
        .bg-gradient-info { background: #0dcaf0; }
        .bg-gradient-success { background: #198754; }
        .bg-gradient-dark { background: #212529; }
        .bg-gradient-purple { background: #6f42c1; }
        
        .clickable-row { cursor: pointer; transition: all 0.2s ease; }
        .clickable-row:hover { background-color: #f1f3f5 !important; transform: translateX(4px); }
        .clickable-row:hover td { color: #0d6efd; }
        
        /* MINI CALENDAR */
        #miniCalendar { cursor: pointer; }
        #miniCalendar .fc-toolbar-title { font-size: 1rem; font-weight: bold; }
        #miniCalendar .fc-button { padding: 2px 6px; font-size: 0.75rem; }
        #miniCalendar .fc-daygrid-day-number { font-size: 0.8rem; text-decoration: none; color: #333; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="px-4 mb-2 fw-bold text-primary fs-5"><i class="bi bi-hospital-fill"></i> Les Corts</div>
    
    <div class="sidebar-search">
        <i class="bi bi-search"></i>
        <input type="text" id="procSearch" class="form-control rounded-pill" placeholder="Search procedure (ICD/Name)..." autocomplete="off">
        <div id="searchResults" class="search-results"></div>
    </div>

    <nav class="nav flex-column">
        <div class="nav-header">Main</div>
        <a href="#" class="nav-link active"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="financial_details.php" class="nav-link"><i class="bi bi-currency-dollar"></i> Financials</a>
        <a href="surgery_stats.php" class="nav-link"><i class="bi bi-pie-chart-fill"></i> Statistics</a>
        <a href="calendar.php" class="nav-link"><i class="bi bi-calendar3"></i> OR Calendar</a>

        <div class="nav-header">Medical</div>
        <a href="specialties.php" class="nav-link">
            <i class="bi bi-grid"></i> Clinical Specialties
        </a>
        
        <div class="nav-header">Operations</div>
        <a href="personnel.php" class="nav-link"><i class="bi bi-people-fill"></i> Personnel & History</a>
        <a href="inventory.php" class="nav-link"><i class="bi bi-box-seam"></i> Stock & Inv.</a>
        <a href="/controllers/logout.php" class="nav-link text-danger mt-4"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
    </nav>
</div>

<div class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold m-0">Director Overview</h4>
        <div class="d-flex gap-2">
            <select id="periodFilter" class="form-select" onchange="refreshData()">
                <option value="day">Today</option>
                <option value="week">This Week</option>
                <option value="month" selected>This Month</option>
                <option value="year">This Year</option>
            </select>
            <select id="procFilter" class="form-select" style="max-width: 200px;" onchange="refreshData()">
                <option value="all">All Procedures</option>
                <?php foreach($all_procs as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card-dash cursor-pointer d-flex align-items-center justify-content-between" onclick="window.location.href='surgery_stats.php'">
                <div>
                    <p class="text-uppercase text-muted small fw-bold mb-1">Total Surgeries</p>
                    <h3 class="fw-bold mb-0" id="kpi-count">...</h3>
                    <small class="text-danger fw-bold" id="kpi-comp-count" style="font-size: 0.75rem;">... Complications</small>
                </div>
                <div class="icon-shape bg-gradient-info"><i class="bi bi-activity"></i></div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card-dash cursor-pointer d-flex align-items-center justify-content-between" onclick="window.location.href='financial_details.php'">
                <div>
                    <p class="text-uppercase text-muted small fw-bold mb-1">Total Cost</p>
                    <h3 class="fw-bold mb-0 text-success" id="kpi-cost">...</h3>
                    <small class="text-muted" style="font-size: 0.65rem;">Click for analysis</small>
                </div>
                <div class="icon-shape bg-gradient-success"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card-dash d-flex align-items-center justify-content-between">
                <div>
                    <p class="text-uppercase text-muted small fw-bold mb-1">Top Procedure</p>
                    <h5 class="fw-bold mb-0 text-truncate" style="max-width: 130px;" id="kpi-top">...</h5>
                </div>
                <div class="icon-shape bg-gradient-dark"><i class="bi bi-trophy"></i></div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card-dash cursor-pointer d-flex align-items-center justify-content-between" onclick="window.location.href='inventory.php'">
                <div>
                    <p class="text-uppercase text-muted small fw-bold mb-1">Inventory</p>
                    <h5 class="fw-bold mb-0">Surgery Stock</h5>
                    <small class="text-primary fw-bold" style="font-size: 0.65rem;">Manage Materials ➔</small>
                </div>
                <div class="icon-shape bg-gradient-purple"><i class="bi bi-box-seam"></i></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        
        <div class="col-lg-4">
            <div class="card-dash p-3" onclick="window.location.href='calendar.php'">
                <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-calendar-event me-2 text-primary"></i>Schedule Overview</h6>
                <div id="miniCalendar" style="height: 300px;"></div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <div class="card-dash p-0 overflow-hidden">
                <div class="p-4 border-bottom bg-light">
                    <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Recent Surgery Records</h6>
                    <small class="text-muted">Click a row to view the full team roster</small>
                </div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table align-middle mb-0 table-hover">
                        <thead class="bg-white sticky-top">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Procedure</th>
                                <th>Surgeon</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Base Cost</th>
                            </tr>
                        </thead>
                        <tbody id="table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // --- SIDEBAR SEARCH ---
    const searchInput = document.getElementById('procSearch');
    const resultsBox = document.getElementById('searchResults');
    let searchTimeout;

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) { resultsBox.style.display = 'none'; return; }

        searchTimeout = setTimeout(() => {
            fetch(`api/search_procedures.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    resultsBox.innerHTML = '';
                    if (data.length === 0) {
                        resultsBox.innerHTML = '<div class="p-2 text-muted small">No matches found.</div>';
                    } else {
                        data.forEach(item => {
                            const link = `procedure_details.php?id=${item.procedure_id}`;
                            const div = document.createElement('a');
                            div.className = 'result-item';
                            div.href = link;
                            div.innerHTML = `<strong>${item.procedure_name}</strong><br><span class="text-muted small">ICD: ${item.icd_code || 'N/A'}</span>`;
                            resultsBox.appendChild(div);
                        });
                    }
                    resultsBox.style.display = 'block';
                });
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
            resultsBox.style.display = 'none';
        }
    });

    // --- DASHBOARD DATA ---
    async function refreshData() {
        const period = document.getElementById('periodFilter').value;
        const proc = document.getElementById('procFilter').value;
        const queryParams = `?period=${period}&proc=${proc}`;
        const apiBase = './api';

        try {
            const kpiRes = await fetch(`${apiBase}/metrics.php${queryParams}`);
            const kpiData = await kpiRes.json();
            
            document.getElementById('kpi-count').innerText = kpiData.count;
            const compText = kpiData.complications ? `${kpiData.complications} Complications` : 'No Complications';
            document.getElementById('kpi-comp-count').innerText = compText;
            document.getElementById('kpi-cost').innerText = '€' + kpiData.cost;
            document.getElementById('kpi-top').innerText = kpiData.top_proc;

            const listRes = await fetch(`${apiBase}/recent_surgeries.php${queryParams}`);
            const listData = await listRes.json();
            const tableBody = document.getElementById('table-body');
            tableBody.innerHTML = '';

            if (listData.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted">No surgeries found.</td></tr>';
            } else {
                listData.forEach(row => {
                    const dateOnly = row.date_time.split(' ')[0];
                    const procId = row.surgical_procedure_id;
                    
                    // Create clickable row that redirects to personnel.php with surgical history tab
                    const tr = document.createElement('tr');
                    tr.className = 'clickable-row';
                    tr.setAttribute('data-surgery-id', procId);
                    tr.setAttribute('data-surgery-date', dateOnly);
                    tr.title = 'Click to view full team roster';
                    
                    tr.innerHTML = `
                        <td class="ps-4">
                            <div class="fw-bold">${row.date_formatted}</div>
                            <small class="text-muted">${row.time_formatted}</small>
                        </td>
                        <td>
                            <div class="fw-bold">${row.procedure_name}</div>
                            <small class="text-muted">${row.room_number || 'N/A'}</small>
                        </td>
                        <td>${row.surgeon_name || 'Unassigned'}</td>
                        <td><span class="badge ${row.status_color}">${row.status}</span></td>
                        <td class="text-end pe-4 fw-bold">$${row.cost_formatted}</td>
                    `;
                    
                    // Add click event to redirect to personnel page with surgical history
                    tr.addEventListener('click', function() {
                        const surgeryId = this.getAttribute('data-surgery-id');
                        const surgeryDate = this.getAttribute('data-surgery-date');
                        window.location.href = `personnel.php?tab=history&date=${surgeryDate}&id=${surgeryId}`;
                    });
                    
                    tableBody.appendChild(tr);
                });
            }

        } catch (error) { 
            console.error("API Error:", error); 
            document.getElementById('table-body').innerHTML = '<tr><td colspan="5" class="text-center py-5 text-danger">Error loading data</td></tr>';
        }
    }

    // --- MINI CALENDAR ---
    document.addEventListener('DOMContentLoaded', function() {
        refreshData(); 
        var calendar = new FullCalendar.Calendar(document.getElementById('miniCalendar'), {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'title', center: '', right: '' }, 
            height: '100%',
            events: 'api/calendar_events.php', 
            displayEventTime: false, 
            eventDisplay: 'block',
            eventClick: function() { window.location.href = 'calendar.php'; },
            dateClick: function() { window.location.href = 'calendar.php'; }
        });
        calendar.render();
    });
</script>
</body>
</html>