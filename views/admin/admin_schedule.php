<?php
// groupd/views/admin/admin_schedule.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'admin') === false) die("Access Denied.");

$pdo = getDBConnection();
$rooms = $pdo->query("SELECT room_id, room_number FROM OPERATING_ROOM ORDER BY room_number")->fetchAll();

// --- LÓGICA DE MODO ---
$mode = 'view';
$rescheduleId = '';
$requestId = ''; // Variable nueva
$patientName = '';

if (isset($_GET['cancel_id'])) {
    $mode = 'reschedule';
    $rescheduleId = htmlspecialchars($_GET['cancel_id']);
    // Capturamos el request_id de la URL
    $requestId = isset($_GET['req_id']) ? htmlspecialchars($_GET['req_id']) : '';
    $patientName = isset($_GET['pname']) ? htmlspecialchars($_GET['pname']) : 'Patient';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Schedule</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📅</text></svg>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    
    <style>
        /* ESTILO ABSOLUTO PARA EVITAR PANTALLA BLANCA */
        html, body { height: 100%; margin: 0; overflow: hidden; font-family: 'Segoe UI', sans-serif; background: #f8f9fa; }
        
        .header { height: 60px; background: white; border-bottom: 1px solid #dee2e6; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; }
        .calendar-container { position: absolute; top: 60px; bottom: 0; left: 0; right: 0; padding: 20px; }
        .card-cal { background: white; height: 100%; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 15px; }
        
        /* MODO REPROGRAMACIÓN (Amarillo) */
        .mode-reschedule { background-color: #fff3cd !important; border-bottom: 3px solid #ffc107; }
        
        /* ESTILOS DE EVENTOS */
        /* 1. La cirugía objetivo (Amarillo) */
        .fc-event.target-event {
            background-color: #ffc107 !important;
            border: 2px solid #000 !important;
            color: #000 !important;
            font-weight: bold;
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.8);
            animation: pulse 1.5s infinite;
            z-index: 999;
        }
        /* 2. El resto (Opacas) */
        .fc-event.dimmed-event {
            opacity: 0.3 !important;
            filter: grayscale(100%);
            pointer-events: none; /* No clicables */
        }
        
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.02); } 100% { transform: scale(1); } }
    </style>
</head>
<body>

    <div class="header <?php echo ($mode === 'reschedule') ? 'mode-reschedule' : ''; ?>">
        <div class="d-flex align-items-center gap-3">
            <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house"></i></a>
            
            <?php if ($mode === 'reschedule'): ?>
                <div>
                    <h5 class="m-0 fw-bold text-dark"><i class="bi bi-arrow-repeat"></i> Rescheduling Mode</h5>
                    <small class="text-dark">Click any <strong>empty slot</strong> to move the YELLOW surgery.</small>
                </div>
            <?php else: ?>
                <h5 class="m-0 fw-bold text-primary">Master Schedule</h5>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2">
            <select id="roomFilter" class="form-select form-select-sm" style="width: 200px;">
                <option value="all">All Rooms</option>
                <?php foreach($rooms as $r): ?>
                    <option value="<?php echo $r['room_id']; ?>">Room <?php echo $r['room_number']; ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($mode === 'reschedule'): ?>
                <a href="admin_requests.php" class="btn btn-dark btn-sm fw-bold">Cancel</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="calendar-container">
        <div class="card-cal">
            <div id="calendar" style="height: 100%;"></div>
        </div>
    </div>

    <div class="modal fade" id="moveModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h6 class="modal-title fw-bold">Confirm Reschedule</h6>
                </div>
                <div class="modal-body text-center">
                    <p class="small mb-1">Move surgery to:</p>
                    <h5 class="fw-bold mb-3" id="newDateDisplay">...</h5>
                    <p class="small text-muted mb-3" id="newRoomDisplay"></p>
                    
                    <button id="btnConfirm" class="btn btn-warning w-100 fw-bold">Confirm Move</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // VARIABLES PHP A JS
        const TARGET_ID = "<?php echo $rescheduleId; ?>"; // ID de la cirugía a mover
        const REQUEST_ID = "<?php echo $requestId; ?>";
        
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var roomSelect = document.getElementById('roomFilter');
            var moveModal = new bootstrap.Modal(document.getElementById('moveModal'));
            
            // Variables temporales para el modal
            let selectedDateISO = '';
            let selectedRoomId = '';

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
                themeSystem: 'bootstrap5',
                height: '100%', 
                slotMinTime: '07:00:00',
                slotMaxTime: '22:00:00',
                allDaySlot: false,
                
                // CARGA DE EVENTOS (Usa el archivo del Paso 1)
                events: {
                    url: 'api_admin_calendar.php',
                    extraParams: function() { return { room: roomSelect.value }; }
                },

                // 1. COLOREAR LA CIRUGÍA AMARILLA
                eventClassNames: function(arg) {
                    if (TARGET_ID) {
                        // Si es la cirugía que queremos mover -> AMARILLO
                        if (String(arg.event.id) === String(TARGET_ID)) {
                            return ['target-event'];
                        } 
                        // El resto -> GRIS
                        else {
                            return ['dimmed-event'];
                        }
                    }
                    return [];
                },

                // 2. CLIC EN HUECO VACÍO (Lógica de Mover)
                dateClick: function(info) {
                    // Solo actuar si estamos en modo reprogramación
                    if (TARGET_ID) {
                        // Calcular fecha ISO (ajuste zona horaria simple)
                        const d = info.date;
                        selectedDateISO = new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0, 19).replace('T', ' ');
                        
                        // Mostrar datos en modal
                        document.getElementById('newDateDisplay').textContent = d.toLocaleString();
                        
                        // Determinar sala (si hay filtro, usamos esa, si no, null y el backend decide o mantiene)
                        selectedRoomId = roomSelect.value !== 'all' ? roomSelect.value : '';
                        document.getElementById('newRoomDisplay').textContent = selectedRoomId ? "Room ID: " + selectedRoomId : "Keep current room (if available)";

                        moveModal.show();
                    }
                }
            });

            calendar.render();
            
            // Recargar al cambiar filtro
            roomSelect.addEventListener('change', () => calendar.refetchEvents());

            // 3. CONFIRMAR MOVIMIENTO (Llamada a API Paso 2)
            document.getElementById('btnConfirm').onclick = async () => {
                try {
                    const res = await fetch('api_schedule_surgery.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            action: 'move_surgery',
                            surgery_id: TARGET_ID,
                            request_id: REQUEST_ID, // <--- ENVIAMOS EL ID AQUÍ
                            start: selectedDateISO,
                            room_id: selectedRoomId
                        })
                    });
                    const data = await res.json();
                    
                    if(data.success) {
                        alert("✅ Rescheduled Successfully!");
                        window.location.href = "admin_requests.php"; // Volver al inbox
                    } else {
                        alert("Error: " + data.error);
                    }
                } catch(e) { console.error(e); alert("Network Error"); }
            };
        });
    </script>
</body>
</html>