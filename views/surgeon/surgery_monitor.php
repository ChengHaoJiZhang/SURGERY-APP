<?php
// groupd/views/surgeon/surgery_monitor.php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

// 1. Verificación de Seguridad
$controller = new LoginController();
$controller->checkAuth();

$role = strtolower($_SESSION['role_name'] ?? '');
if (strpos($role, 'surgeon') === false && strpos($role, 'admin') === false) {
    die("Access Denied. Surgeon profile required.");
}

$spid = $_GET['id'] ?? 0;
if ($spid == 0) die("Invalid Surgery ID.");

// 2. Conexión y Datos de la Cirugía
try {
    $pdo = getDBConnection();
    $pdo->exec("SET NAMES utf8mb4");

    $stmt = $pdo->prepare("
        SELECT 
            sp.surgical_procedure_id,
            p.procedure_name, 
            pat.first_name, 
            pat.surnames, 
            op.room_number,
            ep.episode_id
        FROM SURGICAL_PROCEDURE sp 
        JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id 
        JOIN EPISODE ep ON sp.episode_id = ep.episode_id 
        JOIN PATIENT pat ON ep.patient_id = pat.patient_id 
        JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id
        WHERE sp.surgical_procedure_id = ?
    ");
    $stmt->execute([$spid]);
    $surgery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$surgery) die("Surgery not found or access denied.");

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Monitor | <?php echo htmlspecialchars($surgery['procedure_name']); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { 
            --bg-light: #f5f7fb; 
            --text-dark: #344767;
            --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        }
        body { 
            background-color: var(--bg-light) !important; 
            font-family: 'Segoe UI', sans-serif; 
            color: var(--text-dark);
            height: 100vh; 
            overflow: hidden; 
            display: flex;
            flex-direction: column;
        }
        .top-header { 
            background: white; 
            border-bottom: 1px solid #e9ecef; 
            padding: 15px 30px; 
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            z-index: 10;
        }
        .card-clean {
            background: white; 
            border: 1px solid #fff; 
            border-radius: 16px; 
            box-shadow: var(--card-shadow); 
            padding: 20px; 
            height: 100%;
            display: flex; flex-direction: column;
        }
        .vital-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: #8898aa; font-weight: 700; margin-bottom: 5px; }
        .vital-value { font-size: 3.5rem; font-weight: 800; line-height: 1; color: var(--text-dark); }
        .vital-unit { font-size: 1rem; color: #adb5bd; font-weight: 500; }
        
        .text-hr { color: #2dce89 !important; }
        .text-bp { color: #f5365c !important; }
        .text-spo2 { color: #11cdef !important; }

        .main-content { flex-grow: 1; display: flex; overflow: hidden; padding: 20px; gap: 20px; }
        .left-panel { flex: 1; display: flex; flex-direction: column; gap: 20px; overflow-y: auto; }
        .right-panel { width: 350px; display: flex; flex-direction: column; gap: 20px; }

        .timeline-header { font-weight: 700; color: var(--text-dark); border-bottom: 1px solid #f0f2f5; padding-bottom: 10px; margin-bottom: 10px; }
    </style>
</head>
<body>

    <div class="top-header">
        <div class="d-flex align-items-center gap-4">
            <a href="surgeon_dashboard.php" class="btn btn-outline-secondary btn-sm border-0 fw-bold text-muted">
                <i class="bi bi-chevron-left"></i> Dashboard
            </a>
            <div>
                <h1 class="h5 fw-bold mb-0 text-dark"><?php echo htmlspecialchars($surgery['first_name'].' '.$surgery['surnames']); ?></h1>
                <small class="text-muted"><i class="bi bi-door-open"></i> <?php echo htmlspecialchars($surgery['room_number']); ?> &bull; <?php echo htmlspecialchars($surgery['procedure_name']); ?></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill fw-bold" id="liveIndicator">
                <span class="spinner-grow spinner-grow-sm me-1" role="status"></span> CONNECTING...
            </span>
        </div>
    </div>

    <div class="main-content">
        <div class="left-panel">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card-clean text-center py-4">
                        <div class="vital-label">Heart Rate</div>
                        <div class="vital-value text-hr" id="disp_hr">--</div>
                        <div class="vital-unit">bpm</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-clean text-center py-4">
                        <div class="vital-label">Blood Pressure</div>
                        <div class="vital-value text-bp" id="disp_bp">--/--</div>
                        <div class="vital-unit">mmHg</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-clean text-center py-4">
                        <div class="vital-label">O2 Saturation</div>
                        <div class="vital-value text-spo2" id="disp_spo2">--</div>
                        <div class="vital-unit">%</div>
                    </div>
                </div>
            </div>

            <div class="card-clean flex-grow-1">
                <h6 class="text-muted fw-bold small mb-3">VITALS TREND (LIVE)</h6>
                <div style="position: relative; height: 100%; min-height: 250px;">
                    <canvas id="monitorChart"></canvas>
                </div>
            </div>
        </div>

        <div class="right-panel">
            <div class="card-clean">
                <div class="timeline-header">
                    <i class="bi bi-activity me-2 text-primary"></i> Simulation Log
                </div>
                <div id="liveFeed" style="overflow-y: auto; flex-grow: 1; font-size: 0.85rem; color: #666;">
                    <p class="text-center mt-4">Simulation Active.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const spid = <?php echo $spid; ?>;
        let myChart = null;

        // =========================================================================
        // 1. CONFIGURACIÓN DEL GRÁFICO (Escala Lineal de Tiempo)
        // =========================================================================
        function initChart() {
            const ctx = document.getElementById('monitorChart').getContext('2d');
            
            let gradientHR = ctx.createLinearGradient(0, 0, 0, 400);
            gradientHR.addColorStop(0, 'rgba(45, 206, 137, 0.2)');
            gradientHR.addColorStop(1, 'rgba(45, 206, 137, 0)');

            myChart = new Chart(ctx, {
                type: 'line',
                data: { 
                    datasets: [
                        { 
                            label: 'Heart Rate', 
                            borderColor: '#2dce89', 
                            backgroundColor: gradientHR,
                            borderWidth: 3,
                            pointRadius: 2, 
                            fill: true,
                            tension: 0.4,
                            data: [], // Se llenará con objetos {x, y}
                            yAxisID: 'y'
                        },
                        { 
                            label: 'Systolic BP', 
                            borderColor: '#f5365c', 
                            borderWidth: 2,
                            borderDash: [5, 5],
                            pointRadius: 2,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: '#f5365c',
                            tension: 0.3,
                            data: [],
                            yAxisID: 'y'
                        }
                    ] 
                },
                options: { 
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: { legend: { display: true, position: 'top', align: 'end' } },
                    scales: { 
                        x: { 
                            type: 'linear', // Escala numérica para el tiempo (milisegundos)
                            position: 'bottom',
                            grid: { display: false },
                            ticks: { 
                                color: '#8898aa',
                                maxTicksLimit: 8,
                                callback: function(value) {
                                    return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                                }
                            }
                        }, 
                        y: { 
                            grid: { color: '#f1f3f5', borderDash: [5, 5] }, 
                            ticks: { color: '#8898aa' },
                            suggestedMin: 40,
                            suggestedMax: 160
                        } 
                    } 
                } 
            });
        }

        // =========================================================================
        // 2. ACTUALIZACIÓN DE DATOS
        // =========================================================================
        function updateChart(data) {
            if (!myChart) initChart();
            
            if (data.length === 0) return;

            // Cogemos el último punto como referencia de "ahora"
            const lastPoint = data[data.length - 1];
            // Convertimos el timestamp SQL a milisegundos
            // Reemplazar espacios por T ayuda a la compatibilidad ISO en algunos navegadores
            const nowTime = new Date(lastPoint.timestamp.replace(' ', 'T')).getTime();
            const thirtyMinAgo = nowTime - (30 * 60 * 1000); // Ventana de 30 min

            // Procesar datos para Chart.js
           const processedData = data.map(r => {
                // Reemplazar espacios por T para compatibilidad
                const t = new Date(r.timestamp.replace(' ', 'T')).getTime();
                // Convertir a int, si es null será NaN
                return {
                    x: t,
                    hr: parseInt(r.card_freq),
                    sys: parseInt(r.sys)
                };
            })
            // CORRECCIÓN: Filtramos también si hr o sys son NaN o 0
            .filter(p => p.x > thirtyMinAgo && !isNaN(p.x) && !isNaN(p.hr) && p.hr > 0) 
            .sort((a, b) => a.x - b.x);
            // Asignar datos
            myChart.data.datasets[0].data = processedData.map(p => ({ x: p.x, y: p.hr }));
            myChart.data.datasets[1].data = processedData.map(p => ({ x: p.x, y: p.sys }));

            // Ajustar la ventana visual (Scroll)
            myChart.options.scales.x.min = thirtyMinAgo;
            myChart.options.scales.x.max = nowTime;
            
            myChart.update('none');
        }

        // =========================================================================
        // 3. POLLING (Leer BD)
        // =========================================================================
        // --- 2. FUNCIÓN DE LECTURA DE DATOS (POLLING) ---
        function refresh() {
            fetch(`api_intraop_monitoring.php?surgery_id=${spid}`)
                .then(r => r.json())
                .then(data => {
                    // --- NUEVO: DETECCIÓN DE CIERRE ---
                    if (data.surgery_status === 'Completed') {
                        alert("Surgery has been marked as COMPLETED by Anesthesiology.");
                        window.location.href = 'surgeon_dashboard.php'; // Redirigir al salir
                        return;
                    }

                    if (!data.success || !data.monitoring || data.monitoring.length === 0) {
                        const indicator = document.getElementById('liveIndicator');
                        if(indicator) indicator.innerHTML = '<span class="spinner-grow spinner-grow-sm"></span> WAITING FOR DATA...';
                        return;
                    }

                    // Actualizar indicador visual
                    const indicator = document.getElementById('liveIndicator');
                    if(indicator) {
                        indicator.innerHTML = '● LIVE FEED (CONNECTED)';
                        indicator.className = 'badge bg-success px-3 py-2 rounded-pill fw-bold';
                    }

                    // A. ACTUALIZAR TARJETAS
                    const last = data.monitoring[0]; 
                    
                    if(document.getElementById('disp_hr')) 
                        document.getElementById('disp_hr').innerText = last.card_freq || '--';
                    
                    if(document.getElementById('disp_bp')) 
                        document.getElementById('disp_bp').innerText = (last.sys && last.dias) ? `${last.sys}/${last.dias}` : '--/--';
                    
                    if(document.getElementById('disp_spo2')) 
                        document.getElementById('disp_spo2').innerText = last.tidal_CO2 || '--'; 

                    // B. ACTUALIZAR GRÁFICO
                    const chartData = [...data.monitoring].reverse();
                    updateChart(chartData);
                })
                .catch(err => console.error("Error leyendo datos:", err));
        }

        // =========================================================================
        // 4. SIMULADOR (Generar datos falsos y enviarlos a BD)
        // =========================================================================
        let simData = { hr: 75, sys: 120, dia: 80, spo2: 98, resp: 16 };

        function runSimulationStep() {
            // Variación aleatoria suave
            simData.hr   += Math.floor(Math.random() * 5) - 2;
            simData.sys  += Math.floor(Math.random() * 5) - 2;
            simData.dia  += Math.floor(Math.random() * 3) - 1;
            simData.spo2 += Math.floor(Math.random() * 3) - 1;

            // Límites de seguridad
            if (simData.hr < 50) simData.hr = 55; if (simData.hr > 130) simData.hr = 125;
            if (simData.sys < 90) simData.sys = 95; if (simData.sys > 170) simData.sys = 165;
            if (simData.dia < 50) simData.dia = 55; if (simData.dia > 100) simData.dia = 95;
            if (simData.spo2 < 92) simData.spo2 = 93; if (simData.spo2 > 100) simData.spo2 = 100;

            // Enviar a la API
            fetch('api_intraop_monitoring.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'vital_signs',
                    surgical_procedure_id: spid,
                    heart_rate: simData.hr,
                    sys_bp: simData.sys,
                    dias_bp: simData.dia,
                    mean_bp: Math.floor((simData.sys + 2 * simData.dia) / 3),
                    resp_rate: 16,
                    etco2: simData.spo2, // Usamos este campo para SpO2
                    observations: 'Auto-generated'
                })
            }).then(() => {
                // Log visual en el panel derecho
                const feed = document.getElementById('liveFeed');
                const time = new Date().toLocaleTimeString();
                feed.innerHTML = `<div style="border-bottom:1px solid #eee; padding:5px;">
                    <span class="fw-bold">${time}</span> - Data Received (HR: ${simData.hr})
                </div>` + feed.innerHTML;
            });
        }

        // =========================================================================
        // INICIO
        // =========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            // 1. Start Simulation (Sends data to DB)
            // CHANGE: 300000 ms = 5 minutes
            setInterval(runSimulationStep, 10000); 

            // 2. Start Reading (Updates the view)
            // You can keep this faster (e.g., 5s or 10s) to see the new data 
            // as soon as it arrives, or change it to 300000 to match the log.
            setInterval(refresh, 5000);
            refresh(); // Primera lectura inmediata
        });
    </script>
</body>
</html>