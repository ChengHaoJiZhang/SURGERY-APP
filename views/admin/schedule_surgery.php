<?php
// grupd/views/surgeon/schedule_surgery.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'surgeon') === false) die("Access Denied.");

$pdo = getDBConnection();

// --- CARGAR DATOS PARA LOS DESPLEGABLES ---
// 1. Pacientes
$patients = $pdo->query("SELECT patient_id, first_name, surnames FROM PATIENT ORDER BY surnames")->fetchAll();
// 2. Procedimientos
$procedures = $pdo->query("SELECT procedure_id, procedure_name FROM PROCEDURES ORDER BY procedure_name")->fetchAll();
// 3. Quirófanos
$rooms = $pdo->query("SELECT room_id, room_number, room_type FROM OPERATING_ROOM ORDER BY room_number")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OR Schedule | Hospital Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

    <style>
        :root { --hospital-blue: #0056b3; --hospital-teal: #17a2b8; --bg-light: #f4f6f9; }
        body { background-color: var(--bg-light); font-family: 'Segoe UI', sans-serif; }
        
        .brand-header { background: linear-gradient(180deg, rgba(0, 86, 179, 1) 0%, rgba(2, 48, 97, 1) 100%); color: white; padding: 1.5rem 0 4rem; }
        .main-container { margin-top: -3rem; }
        
        .calendar-card { background: white; border-radius: 12px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 20px; }
        
        /* FullCalendar Customization */
        .fc-toolbar-title { font-size: 1.2rem !important; font-weight: 700; color: #343a40; }
        .fc-button-primary { background-color: var(--hospital-blue) !important; border-color: var(--hospital-blue) !important; }
        .fc-event { cursor: pointer; border: none; padding: 2px 4px; font-size: 0.85rem; }
    </style>
</head>
<body>

    <header class="brand-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <a href="surgeon_dashboard.php" class="text-white-50 text-decoration-none small mb-2 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
                <h1 class="h3 fw-bold mb-0">Operating Room Schedule</h1>
            </div>
            <button class="btn btn-light text-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#surgeryModal">
                <i class="bi bi-plus-lg"></i> Book Surgery
            </button>
        </div>
    </header>

    <div class="container main-container">
        <div class="calendar-card">
            <div id='calendar'></div>
        </div>
    </div>

    <div class="modal fade" id="surgeryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Schedule Procedure</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="bookingForm">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">PATIENT</label>
                            <select id="patientId" class="form-select" required>
                                <option value="">Select Patient...</option>
                                <?php foreach($patients as $p): ?>
                                    <option value="<?php echo $p['patient_id']; ?>">
                                        <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['surnames']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">PROCEDURE</label>
                            <select id="procId" class="form-select" required>
                                <?php foreach($procedures as $proc): ?>
                                    <option value="<?php echo $proc['procedure_id']; ?>">
                                        <?php echo htmlspecialchars($proc['procedure_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-muted">DATE & TIME</label>
                                <input type="datetime-local" id="surgeryDate" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-muted">DURATION (MIN)</label>
                                <input type="number" id="duration" class="form-control" value="120">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">OPERATING ROOM</label>
                            <select id="roomId" class="form-select" required>
                                <?php foreach($rooms as $r): ?>
                                    <option value="<?php echo $r['room_id']; ?>">
                                        <?php echo htmlspecialchars($r['room_number'] . ' (' . $r['room_type'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold">Confirm Booking</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            
            // 1. INICIAR CALENDARIO
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                themeSystem: 'bootstrap5',
                events: 'api_or_calendar.php', // Carga eventos desde tu API
                
                // Al hacer clic en un día vacío
                dateClick: function(info) {
                    // Abrir modal y pre-rellenar fecha
                    var dateStr = info.dateStr + 'T09:00'; // Por defecto a las 9am
                    document.getElementById('surgeryDate').value = dateStr;
                    var myModal = new bootstrap.Modal(document.getElementById('surgeryModal'));
                    myModal.show();
                },

                // Al hacer clic en un evento existente
                eventClick: function(info) {
                    alert('Surgery: ' + info.event.title + '\nRoom: ' + info.event.extendedProps.room);
                }
            });
            calendar.render();

            // 2. GUARDAR NUEVA CIRUGÍA
            document.getElementById('bookingForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const payload = {
                    patient_id: document.getElementById('patientId').value,
                    procedure_id: document.getElementById('procId').value,
                    date_time: document.getElementById('surgeryDate').value,
                    duration: document.getElementById('duration').value,
                    room_id: document.getElementById('roomId').value
                };

                try {
                    const res = await fetch('api_schedule_surgery.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();

                    if(data.success) {
                        alert("Surgery scheduled successfully!");
                        calendar.refetchEvents(); // Recargar calendario sin F5
                        bootstrap.Modal.getInstance(document.getElementById('surgeryModal')).hide();
                    } else {
                        alert("Error: " + data.error);
                    }
                } catch(err) {
                    console.error(err);
                    alert("System error saving surgery.");
                }
            });
        });
    </script>

</body>
</html>