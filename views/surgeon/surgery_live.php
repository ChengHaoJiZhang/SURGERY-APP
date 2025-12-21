<?php
// groupd/views/surgeon/surgery_live.php
// VISTA LIMPIA (LIGHT MODE) - Sincronizada con diseño de Enfermería
// Sin botón de materiales.

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/LoginController.php';

// 1. Seguridad
$controller = new LoginController();
$controller->checkAuth();

$role = strtolower($_SESSION['role_name'] ?? '');
// Permitir acceso a Cirujanos y Admin
if (strpos($role, 'surgeon') === false && strpos($role, 'admin') === false) {
    die("Access Denied. Surgeon profile required.");
}

$spid = $_GET['id'] ?? 0;
if ($spid == 0) die("Error: Missing SPID");

// 2. Datos
$pdo = getDBConnection();
$pdo->exec("SET NAMES utf8mb4");

$stmt = $pdo->prepare("
    SELECT sp.*, p.procedure_name, pat.first_name, pat.surnames, op.room_number, ep.episode_id
    FROM SURGICAL_PROCEDURE sp
    JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
    JOIN EPISODE ep ON sp.episode_id = ep.episode_id
    JOIN PATIENT pat ON ep.patient_id = pat.patient_id
    JOIN OPERATING_ROOM op ON sp.operating_room_id = op.room_id
    WHERE sp.surgical_procedure_id = ?
");
$stmt->execute([$spid]);
$surgery = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$surgery) die("Surgery not found.");
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
        /* ESTÉTICA LIGHT / CLEAN (Igual que Nurse Dashboard) */
        :root { 
            --bg-light: #f5f7fb; 
            --text-dark: #344767;
            --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        }
        
        /* FORZAR FONDO CLARO */
        body { 
            background-color: var(--bg-light) !important; 
            font-family: 'Segoe UI', sans-serif; 
            color: var(--text-dark);
            height: 100vh; 
            overflow: hidden; 
            display: flex;
            flex-direction: column;
        }
        
        /* HEADER BLANCO */
        .top-header { 
            background: white; 
            border-bottom: 1px solid #e9ecef; 
            padding: 15px 30px; 
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            z-index: 10;
        }
        
        /* TARJETAS BLANCAS CON SOMBRA SUAVE */
        .card-clean {
            background: white; 
            border: 1px solid #fff; 
            border-radius: 16px; 
            box-shadow: var(--card-shadow); 
            padding: 20px; 
            height: 100%;
            display: flex; flex-direction: column;
        }
        
        /* TEXTOS GRANDES PARA VITALES */
        .vital-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: #8898aa; font-weight: 700; margin-bottom: 5px; }
        .vital-value { font-size: 3.5rem; font-weight: 800; line-height: 1; color: var(--text-dark); }
        .vital-unit { font-size: 1rem; color: #adb5bd; font-weight: 500; }
        
        /* COLORES DE DATOS */
        .text-hr { color: #2dce89 !important; } /* Verde suave */
        .text-bp { color: #f5365c !important; } /* Rojo suave */
        .text-spo2 { color: #11cdef !important; } /* Azul suave */

        /* LAYOUT */
        .main-content { flex-grow: 1; display: flex; overflow: hidden; padding: 20px; gap: 20px; }
        .left-panel { flex: 1; display: flex; flex-direction: column; gap: 20px; overflow-y: auto; }
        .right-panel { width: 350px; display: flex; flex-direction: column; gap: 20px; }

        /* TIMELINE FEED */
        .timeline-header { font-weight: 700; color: var(--text-dark); border-bottom: 1px solid #f0f2f5; padding-bottom: 10px; margin-bottom: 10px; }
        .feed-item { padding: 10px 0; border-bottom: 1px solid #f8f9fa; }
        .feed-time { font-size: 0.75rem; color: #8898aa; font-weight: 600; display: block; }
        .feed-title { font-weight: 700; font-size: 0.95rem; color: var(--text-dark); }
        .feed-details { font-size: 0.85rem; color: #67748e; margin-top: 2px; }
        
        /* TIPOS DE EVENTOS (Colores sutiles) */
        .type-medication { border-left: 3px solid #5e72e4; padding-left: 10px; }
        .type-event { border-left: 3px solid #f5365c; padding-left: 10px; }
        .type-material { border-left: 3px solid #2dce89; padding-left: 10px; } /* Materiales solo lectura */
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
            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill fw-bold">
                <span class="spinner-grow spinner-grow-sm me-1" role="status"></span> LIVE
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
                <h6 class="text-muted fw-bold small mb-3">VITALS TREND (LAST 30 MIN)</h6>
                <div style="position: relative; height: 100%; min-height: 250px;">
                    <canvas id="monitorChart"></canvas>
                </div>
            </div>
        </div>

        <div class="right-panel">
            <div class="card-clean">
                <div class="timeline-header">
                    <i class="bi bi-activity me-2 text-primary"></i> Surgical Events Log
                </div>
                <div id="liveFeed" style="overflow-y: auto; flex-grow: 1;">
                    <div class="text-center text-muted mt-5 small">Waiting for data...</div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const spid = <?php echo $spid; ?>;
        let myChart = null;

        // --- 1. CONFIGURACIÓN DEL GRÁFICO (Estilo Claro) ---
        function initChart() {
            const ctx = document.getElementById('monitorChart').getContext('2d');
            
            // Degradado verde suave
            let gradientHR = ctx.createLinearGradient(0, 0, 0, 400);
            gradientHR.addColorStop(0, 'rgba(45, 206, 137, 0.2)');
            gradientHR.addColorStop(1, 'rgba(45, 206, 137, 0)');

            myChart = new Chart(ctx, {
                type: 'line',
                data: { 
                    labels: [], 
                    datasets: [
                        { 
                            label: 'Heart Rate', 
                            borderColor: '#2dce89', 
                            backgroundColor: gradientHR,
                            borderWidth: 3,
                            pointRadius: 0,
                            fill: true,
                            tension: 0.4,
                            data: [],
                            yAxisID: 'y'
                        },
                        { 
                            label: 'Systolic BP', 
                            borderColor: '#f5365c', 
                            borderWidth: 2,
                            borderDash: [5, 5],
                            pointRadius: 3,
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
                    plugins: { legend: { display: false } },
                    scales: { 
                        x: { display: false }, 
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

        function updateChart(data) {
            if (!myChart) initChart();
            const recent = data.slice(-30); // Últimos 30 puntos
            myChart.data.labels = recent.map(r => r.timestamp);
            myChart.data.datasets[0].data = recent.map(r => r.card_freq);
            myChart.data.datasets[1].data = recent.map(r => r.sys);
            myChart.update();
        }

        // --- 2. POLLING DE DATOS (Cada 5s) ---
        function refresh() {
            // Usamos la API centralizada de Anestesia
            // NOTA: Ajusta la ruta si tu estructura de carpetas es diferente
            fetch(`../anesthesiologist/api_anesthesia.php?spid=${spid}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;

                    // A. Actualizar Tarjetas
                    if (data.vitals && data.vitals.length > 0) {
                        const last = data.vitals[data.vitals.length - 1];
                        document.getElementById('disp_hr').innerText = last.card_freq || '--';
                        document.getElementById('disp_bp').innerText = (last.sys && last.dias) ? `${last.sys}/${last.dias}` : '--/--';
                        document.getElementById('disp_spo2').innerText = last.tidal_CO2 || '--'; // Asumiendo mapeo SpO2 -> tidal_CO2
                        updateChart(data.vitals);
                    }

                    // B. Actualizar Timeline
                    if (data.timeline) {
                        const feed = document.getElementById('liveFeed');
                        feed.innerHTML = '';
                        
                        if(data.timeline.length === 0) {
                            feed.innerHTML = '<div class="text-center text-muted mt-5 small">No events yet.</div>';
                        }

                        data.timeline.forEach(t => {
                            const details = t.details ? t.details.replace(/\n/g, '<br>') : '';
                            const timeStr = new Date(t.time).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
                            
                            const html = `
                                <div class="feed-item type-${t.type}">
                                    <span class="feed-time">${timeStr}</span>
                                    <div class="feed-title">${t.title}</div>
                                    <div class="feed-details">${details}</div>
                                </div>`;
                            feed.innerHTML += html;
                        });
                    }
                })
                .catch(err => console.error("Polling error:", err));
        }

        // Inicio
        document.addEventListener('DOMContentLoaded', () => {
            initChart();
            refresh();
            setInterval(refresh, 5000); 
        });
    </script>
</body>
</html>