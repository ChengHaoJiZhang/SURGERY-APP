<?php
// views/director/financial_details.php
session_start();
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { /* redirect */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Deep Dive | Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root { --bg-body: #f5f7fb; --primary-color: #0d6efd; }
        body { background-color: var(--bg-body); font-family: 'Segoe UI', sans-serif; }
        
        .main-container { max-width: 1400px; margin: 20px auto; padding: 20px; } 
        
        .card-clean {
            background: white; border: none; border-radius: 16px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 25px; 
            margin-bottom: 20px;
            height: 100%;
        }
        
        /* New KPI Styling */
        .section-title { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #adb5bd; margin-bottom: 15px; }
        
        .kpi-box { padding: 15px; border-radius: 12px; background: #f8f9fa; border: 1px solid #eee; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center; transition: transform 0.2s; }
        .kpi-box:hover { transform: translateY(-3px); border-color: #e2e6ea; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .kpi-value { font-size: 1.8rem; font-weight: 800; color: #212529; margin-bottom: 5px; }
        .kpi-label { font-size: 0.8rem; color: #6c757d; font-weight: 600; text-transform: uppercase; }
        .kpi-sub { font-size: 0.75rem; color: #999; margin-top: 5px; }

        .text-purple { color: #6f42c1 !important; }
        .bg-purple-light { background-color: #f2eaff !important; }
        
        .btn-inventory { 
            background: linear-gradient(135deg, #6610f2 0%, #520dc2 100%); color: white; border: none; 
            border-radius: 16px; padding: 25px; width: 100%; height: 100%;
            display: flex; align-items: center; text-decoration: none; 
            box-shadow: 0 8px 20px rgba(102, 16, 242, 0.2); transition: transform 0.2s;
        }
        .btn-inventory:hover { transform: translateY(-2px); color: white; box-shadow: 0 12px 25px rgba(102, 16, 242, 0.3); }
    </style>
</head>
<body>

<div class="main-container">
    
    <div class="d-flex align-items-center mb-4">
        <a href="director_dashboard.php" class="btn btn-outline-secondary btn-sm me-3 rounded-circle" style="width:40px; height:40px; display:flex; align-items:center; justify-content:center;">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="fw-bold m-0">Financial Performance</h4>
            <p class="text-muted m-0 small">Operational Costs (OPEX) vs Capital Inventory (CAPEX)</p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card-clean p-4 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold text-dark m-0">Time Analysis Filter</h6>
                    <small class="text-muted">Adjusts all Operational Cost metrics below</small>
                </div>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="period" id="f-day" value="day" onchange="loadFinanceData()">
                    <label class="btn btn-outline-primary px-3" for="f-day">Day</label>

                    <input type="radio" class="btn-check" name="period" id="f-week" value="week" onchange="loadFinanceData()">
                    <label class="btn btn-outline-primary px-3" for="f-week">Week</label>

                    <input type="radio" class="btn-check" name="period" id="f-month" value="month" onchange="loadFinanceData()">
                    <label class="btn btn-outline-primary px-3" for="f-month">Month</label>

                    <input type="radio" class="btn-check" name="period" id="f-year" value="year" checked onchange="loadFinanceData()">
                    <label class="btn btn-outline-primary px-3" for="f-year">Year</label>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <a href="inventory.php" class="btn-inventory">
                <div class="me-3 bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                    <i class="bi bi-box-seam fs-3 text-white"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1">Manage Inventory Stock</h6>
                    <small class="opacity-75">View detailed material levels & orders</small>
                </div>
                <i class="bi bi-arrow-right ms-auto fs-4 opacity-75"></i>
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12"><div class="section-title"><i class="bi bi-bank me-2"></i>Capital Investment (Current Assets)</div></div>
        
        <div class="col-lg-12">
            <div class="card-clean bg-light border-0">
                <div class="d-flex align-items-center justify-content-between px-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3 text-success"><i class="bi bi-cash-coin fs-2"></i></div>
                        <div>
                            <div class="kpi-label text-success">Total Inventory Value</div>
                            <div class="kpi-value text-dark" id="val-remaining-value">...</div>
                            <small class="text-muted">Value of unused materials currently in stock</small>
                        </div>
                    </div>
                    <div class="text-end d-none d-md-block">
                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Asset Value</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12"><div class="section-title"><i class="bi bi-graph-down-arrow me-2"></i>Operational Costs (Expensed)</div></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-box">
                <div class="kpi-value text-primary" id="val-surgical-total">...</div>
                <div class="kpi-label">Total Surgical Base Cost</div>
                <div class="kpi-sub">Standard Procedure Fees</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="kpi-box">
                <div class="kpi-value text-purple" id="val-consumed-cost">...</div>
                <div class="kpi-label">Inventory Used Cost</div>
                <div class="kpi-sub">Materials Consumed</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="kpi-box" style="background: #fff5f5; border-color: #ffe0e3;">
                <div class="kpi-value text-danger" id="val-delay-cost">...</div>
                <div class="kpi-label text-danger">Inefficiency Cost (Delays)</div>
                <div class="kpi-sub text-danger opacity-75">
                    Late Starts & Overruns<br>
                    <span style="font-size: 0.7rem; font-weight: 700;">(Formula: €20 × Minute Delay)</span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="kpi-box bg-white border-primary border-opacity-25">
                <div class="kpi-value text-dark" id="val-avg">...</div>
                <div class="kpi-label">Avg. Base Cost / Case</div>
                <div class="kpi-sub">Efficiency Metric</div>
            </div>
        </div>
    </div>

    <div class="card-clean">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <div>
                <h5 class="fw-bold m-0">Cost Breakdown by Procedure</h5>
                <small class="text-muted">Includes Base Cost, Material Usage, and Delay Penalties</small>
            </div>
            <div class="text-end">
                <small class="text-uppercase text-muted fw-bold d-block">Total Operational Expense</small>
                <h3 class="fw-bold text-dark m-0" id="val-total-opex">€0</h3>
            </div>
        </div>
        <div style="height: 450px; width: 100%;">
            <canvas id="financeChart"></canvas>
        </div>
    </div>

</div>

<script>
    let myChart = null;
    const currency = 'EUR';

    async function loadFinanceData() {
        const period = document.querySelector('input[name="period"]:checked').value;
        const timestamp = new Date().getTime(); // Cache-buster
        
        try {
            // Fetch both endpoints
            const [financeRes, inventoryRes] = await Promise.all([
                fetch(`api/financial_analysis.php?period=${period}&t=${timestamp}`),
                fetch(`api/inventory_data.php?period=${period}&t=${timestamp}`)
            ]);

            const financeData = await financeRes.json();
            const inventoryData = await inventoryRes.json();

            // --- 1. POPULATE CAPITAL (Stock) ---
            document.getElementById('val-remaining-value').innerText = '€' + inventoryData.cost_kpi.total_inventory_value;

            // --- 2. POPULATE OPERATIONAL COSTS ---
            
            // Base Costs
            document.getElementById('val-surgical-total').innerText = '€' + financeData.kpi.total_base_cost;
            document.getElementById('val-avg').innerText = '€' + financeData.kpi.avg_base_cost;

            // Material Usage (Consumed)
            const consumedCostVal = parseFloat(inventoryData.cost_kpi.total_consumption_cost.replace(/,/g, ''));
            document.getElementById('val-consumed-cost').innerText = '€' + inventoryData.cost_kpi.total_consumption_cost;

            // Delay Costs (NEW)
            const delayCostVal = parseFloat(financeData.kpi.total_delay_cost.replace(/,/g, ''));
            document.getElementById('val-delay-cost').innerText = '€' + financeData.kpi.total_delay_cost;

            // Grand Total OPEX
            const totalBase = parseFloat(financeData.kpi.total_base_cost.replace(/,/g, ''));
            const totalOpex = totalBase + consumedCostVal + delayCostVal;
            document.getElementById('val-total-opex').innerText = '€' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(totalOpex);
            
            // --- 3. RENDER CHART ---
            renderChart(financeData.chart);

        } catch (err) {
            console.error("Error loading financial data:", err);
        }
    }

    function renderChart(chartData) {
        const ctx = document.getElementById('financeChart').getContext('2d');
        if (myChart) myChart.destroy();

        myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: chartData.datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', 
                scales: {
                    x: {
                        stacked: true, // Enable stacking
                        beginAtZero: true,
                        grid: { color: '#f0f0f0' }
                    },
                    y: {
                        stacked: true, // Enable stacking
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true } },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed.x !== null) {
                                    label += new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(context.parsed.x);
                                }
                                return label;
                            },
                            footer: function(tooltipItems) {
                                let total = 0;
                                tooltipItems.forEach(function(tooltipItem) {
                                    total += tooltipItem.parsed.x;
                                });
                                return 'Total: ' + new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(total);
                            }
                        }
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', loadFinanceData);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>