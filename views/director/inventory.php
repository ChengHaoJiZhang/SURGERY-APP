<?php
// views/director/inventory.php
// UPDATED: Compact KPI Strip + General Inventory List + Modal

session_start();
require_once __DIR__ . '/../../config/config.php';

// Auth check
if (!isset($_SESSION['user_id'])) { /* redirect */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surgical Inventory | Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* Replicating Styles */
        :root { --bg-body: #f5f7fb; --primary-color: #0d6efd; }
        body { background-color: var(--bg-body); font-family: 'Segoe UI', sans-serif; }
        .main-container { max-width: 1200px; margin: 40px auto; padding: 20px; }
        
        .card-clean {
            background: white; border: none; border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 20px;
        }

        /* --- NEW COMPACT KPI STRIP STYLES --- */
        .kpi-strip {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            padding: 15px 10px;
        }
        .kpi-item {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 0 20px;
            border-right: 1px solid #e9ecef;
            min-width: 200px; /* Ensure wrapping on small screens */
            margin-bottom: 10px;
        }
        .kpi-item:last-child { border-right: none; }
        
        .kpi-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .kpi-content { display: flex; flex-direction: column; }
        .kpi-value { font-size: 1.5rem; font-weight: 700; color: #212529; line-height: 1.2; }
        .kpi-label { font-size: 0.75rem; text-transform: uppercase; color: #8898aa; font-weight: 600; letter-spacing: 0.5px; }
        
        /* Specific Colors */
        .bg-icon-blue { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .bg-icon-green { background: rgba(25, 135, 84, 0.1); color: #198754; }
        .bg-icon-purple { background: rgba(111, 66, 193, 0.1); color: #6f42c1; }
        .bg-icon-red { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        
        /* Filter Bar */
        .filter-bar { background: #fff; border-radius: 12px; padding: 10px 20px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .btn-check:checked + .btn-outline-primary { background-color: var(--primary-color); color: white; }
        
        .chart-container { display: flex; justify-content: center; align-items: center; height: 300px; }
    </style>
</head>
<body>

<div class="main-container">
    
    <div class="d-flex align-items-center mb-4">
        <a href="director_dashboard.php" class="btn btn-outline-secondary btn-sm me-3 rounded-circle" style="width:40px; height:40px; display:flex; align-items:center; justify-content:center;">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h3 class="fw-bold m-0">Surgical Inventory Management</h3>
            <p class="text-muted m-0 small">Real-time stock levels, low stock alerts, and usage distribution</p>
        </div>
    </div>
    
    <div class="filter-bar">
        <div class="fw-bold text-muted small text-uppercase me-2"><i class="bi bi-calendar-check-fill"></i> Usage Period:</div>
        <div class="btn-group btn-group-sm" role="group" id="periodGroup">
            <input type="radio" class="btn-check" name="period" id="t-day" value="day" onchange="loadInventoryData()">
            <label class="btn btn-outline-primary" for="t-day">Day</label>

            <input type="radio" class="btn-check" name="period" id="t-week" value="week" onchange="loadInventoryData()">
            <label class="btn btn-outline-primary" for="t-week">Week</label>

            <input type="radio" class="btn-check" name="period" id="t-month" value="month" checked onchange="loadInventoryData()">
            <label class="btn btn-outline-primary" for="t-month">Month</label>

            <input type="radio" class="btn-check" name="period" id="t-year" value="year" onchange="loadInventoryData()">
            <label class="btn btn-outline-primary" for="t-year">Year</label>
        </div>
    </div>

    <div class="card-clean p-0 mb-4">
        <div class="kpi-strip">
            <div class="kpi-item">
                <div class="kpi-icon bg-icon-blue"><i class="bi bi-box-seam"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value" id="val-quantity">...</div>
                    <div class="kpi-label">Total Stock</div>
                </div>
            </div>
            
            <div class="kpi-item">
                <div class="kpi-icon bg-icon-green"><i class="bi bi-currency-euro"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value" id="val-total-value">...</div>
                    <div class="kpi-label">Inv. Value</div>
                </div>
            </div>

            <div class="kpi-item">
                <div class="kpi-icon bg-icon-purple"><i class="bi bi-cart-check"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value" id="val-consumed">...</div>
                    <div class="kpi-label">Units Used</div>
                </div>
            </div>

            <div class="kpi-item">
                <div class="kpi-icon bg-icon-red"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value text-danger" id="val-alerts">...</div>
                    <div class="kpi-label">Critical Alerts</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card-clean h-100">
                <h6 class="fw-bold mb-3">Stock Distribution</h6>
                <div class="chart-container">
                    <canvas id="inventoryChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card-clean h-100">
                <h6 class="fw-bold mb-3">Material Usage (<span id="periodLabel">This Month</span>)</h6>
                <div class="chart-container">
                    <canvas id="usageChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card-clean p-0">
                <div class="d-flex justify-content-between align-items-center p-4 border-bottom">
                    <div>
                        <h5 class="fw-bold m-0"><i class="bi bi-list-ul me-2 text-primary"></i>Inventory Overview</h5>
                        <small class="text-muted">General list of available materials</small>
                    </div>
                    <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#inventoryModal" onclick="loadFullInventory()">
                        <i class="bi bi-eye-fill me-1"></i> Show All Materials
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-hover">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Material Name</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Category</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Location</th>
                                <th class="text-end pe-4 text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Quantity</th>
                            </tr>
                        </thead>
                        <tbody id="preview-table-body">
                            <tr><td colspan="4" class="text-center py-3 text-muted">Loading preview...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card-clean p-0">
                <div class="p-4 pb-3 border-bottom bg-danger-subtle">
                    <h5 class="fw-bold m-0 text-danger"><i class="bi bi-shield-exclamation me-2"></i>Critical Stock & Expiry Review</h5>
                    <small class="text-danger-emphasis">Items below minimum stock OR expiring within 90 days.</small>
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table align-middle mb-0 table-hover">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th class="ps-4 text-secondary text-xxs font-weight-bolder opacity-7">Material</th>
                                <th class="text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                <th class="text-secondary text-xxs font-weight-bolder opacity-7">Stock / Min</th>
                                <th class="text-secondary text-xxs font-weight-bolder opacity-7">Expiry / Lifespan (Years)</th>
                            </tr>
                        </thead>
                        <tbody id="critical-table-body">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="inventoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Full Material Inventory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="input-group mb-3 sticky-top" style="top: -16px; z-index: 5;">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0" id="modalSearch" placeholder="Search materials..." onkeyup="filterModalTable()">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Material Name</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Value</th>
                            </tr>
                        </thead>
                        <tbody id="full-inventory-body">
                            <tr><td colspan="5" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    let inventoryChart = null;
    let usageChart = null; 
    let fullInventoryLoaded = false;

    const periodLabels = { 'day': 'Today', 'week': 'This Week', 'month': 'This Month', 'year': 'This Year' };

    async function loadInventoryData() {
        const period = document.querySelector('input[name="period"]:checked').value;
        document.getElementById('periodLabel').innerText = periodLabels[period];

        try {
            const res = await fetch(`api/inventory_data.php?period=${period}`);
            const data = await res.json();
            
            if(data.error) { console.error("API Error:", data.error); return; }

            // 1. Update KPI Strip
            document.getElementById('val-quantity').innerText = data.kpi.total_quantity;
            document.getElementById('val-total-value').innerText = '€' + data.cost_kpi.total_inventory_value;
            document.getElementById('val-consumed').innerText = data.usage_kpi.total_consumed;
            
            // Combine Low Stock + Expired for single "Alerts" KPI
            const alerts = parseInt(data.kpi.low_stock) + parseInt(data.kpi.expired);
            document.getElementById('val-alerts').innerText = alerts;

            // 2. Charts
            renderChart(data.chart); 
            renderUsageChart(data.usage_chart); 
            
            // 3. General Preview Table (Top 5)
            renderPreviewTable(data.inventory_preview);

            // 4. Critical Stock Table
            renderCriticalTable(data.table_data);

        } catch (err) { console.error("Error:", err); }
    }

    // --- RENDER FUNCTIONS ---

    function renderPreviewTable(list) {
        const tbody = document.getElementById('preview-table-body');
        tbody.innerHTML = '';
        if(!list || list.length === 0) { tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No items found.</td></tr>'; return; }
        
        list.forEach(item => {
            tbody.innerHTML += `
            <tr>
                <td class="ps-4 fw-bold text-dark">${item.material_name}</td>
                <td><span class="badge bg-light text-dark border">${item.category}</span></td>
                <td class="text-muted small">${item.location}</td>
                <td class="text-end pe-4 fw-bold">${item.quantity_available}</td>
            </tr>`;
        });
    }

    function renderCriticalTable(list) {
        const tbody = document.getElementById('critical-table-body');
        tbody.innerHTML = '';
        if (!list || list.length === 0) { 
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-success small"><i class="bi bi-check-circle me-1"></i> All good! No critical issues.</td></tr>'; 
            return; 
        }

        list.forEach(item => {
            const isLow = parseInt(item.quantity_available) < parseInt(item.minimum_stock);
            
            // --- NEW LOGIC START ---
            let statusBadge = '';
            let dateDisplay = '';

            // Determine what date/value to show
            if (item.category === 'Prosthesis') {
                // If it is a prosthesis, use lifespan_years
                const years = item.lifespan_years ? item.lifespan_years : 'N/A';
                dateDisplay = `<span class="fw-bold text-dark">${years} Years</span>`;
            } else {
                // Otherwise, use standard expiration date
                dateDisplay = `<span class="font-monospace">${item.expiration_date}</span>`;
            }

            // Determine Badge Status
            if (isLow) {
                statusBadge = '<span class="badge bg-danger">Low Stock</span>';
            } else {
                // If checking expiry, ignore "Expiring" badge for Prosthesis if date is 0000
                if (item.category === 'Prosthesis' && item.expiration_date === '0000-00-00') {
                    statusBadge = '<span class="badge bg-info text-dark">Check Lifespan</span>';
                } else {
                    statusBadge = '<span class="badge bg-warning text-dark">Expiring</span>';
                }
            }
            // --- NEW LOGIC END ---
            
            tbody.innerHTML += `
            <tr>
                <td class="ps-4"><div class="d-flex flex-column"><span class="fw-bold">${item.material_name}</span><small class="text-muted">${item.category}</small></div></td>
                <td>${statusBadge}</td>
                <td><span class="${isLow ? 'text-danger fw-bold' : ''}">${item.quantity_available}</span> <span class="text-muted small">/ ${item.minimum_stock}</span></td>
                <td class="small">${dateDisplay}</td>
            </tr>`;
        });
    }

    async function loadFullInventory() {
        if(fullInventoryLoaded) return; // Don't reload if already loaded
        
        const tbody = document.getElementById('full-inventory-body');
        try {
            const res = await fetch('api/inventory_data.php?action=list_all');
            const data = await res.json();
            
            tbody.innerHTML = '';
            data.forEach(item => {
                const totalVal = (parseFloat(item.unit_cost || 0) * parseInt(item.quantity_available || 0)).toFixed(2);
                tbody.innerHTML += `
                <tr class="search-item">
                    <td class="fw-bold">${item.material_name}</td>
                    <td>${item.category}</td>
                    <td>${item.location}</td>
                    <td class="text-end fw-bold">${item.quantity_available}</td>
                    <td class="text-end text-muted">€${totalVal}</td>
                </tr>`;
            });
            fullInventoryLoaded = true;
        } catch(e) { 
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load data.</td></tr>'; 
        }
    }

    function filterModalTable() {
        const input = document.getElementById('modalSearch');
        const filter = input.value.toLowerCase();
        const rows = document.getElementsByClassName('search-item');

        for (let i = 0; i < rows.length; i++) {
            const txtValue = rows[i].textContent || rows[i].innerText;
            if (txtValue.toLowerCase().indexOf(filter) > -1) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }
    }

    // Chart Rendering (Standard)
    function renderChart(chartData) {
        const ctx = document.getElementById('inventoryChart').getContext('2d');
        if (inventoryChart) inventoryChart.destroy();
        inventoryChart = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: chartData.labels, datasets: [{ data: chartData.data, backgroundColor: ['#0d6efd', '#198754', '#6f42c1', '#fd7e14', '#dc3545'], borderWidth: 0, cutout: '70%' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } } }
        });
    }

    function renderUsageChart(usageData) {
        const ctx = document.getElementById('usageChart').getContext('2d');
        if (usageChart) usageChart.destroy();
        usageChart = new Chart(ctx, {
            type: 'bar',
            data: { labels: usageData.labels, datasets: [{ label: 'Units', data: usageData.data, backgroundColor: '#0d6efd', borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { display: false } } } }
        });
    }

    document.addEventListener('DOMContentLoaded', loadInventoryData);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>