<?php
// grupd/views/surgeon/schedule_surgery.php
session_start();
require_once __DIR__ . '/../../controllers/LoginController.php';

$controller = new LoginController();
$controller->checkAuth();
if (strpos(strtolower($_SESSION['role_name']), 'surgeon') === false) die("Access Denied.");

$pdo = getDBConnection();
// No necesitamos cargar pacientes aquí si solo es para ver y pedir cambios
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Schedule | Hospital Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

    <style>
        :root { --hospital-blue: #0056b3; --bg-light: #f4f6f9; }
        
        /* FIX: Asegurar que el body ocupe toda la altura */
        body { 
            background-color: var(--bg-light); 
            font-family: 'Segoe UI', sans-serif; 
            display: flex; 
            flex-direction: column; 
            height: 100vh; 
            overflow: hidden; 
        }
        
        .brand-header { 
            background: linear-gradient(180deg, rgba(0, 86, 179, 1) 0%, rgba(2, 48, 97, 1) 100%); 
            color: white; 
            padding: 1rem 0; 
            flex-shrink: 0; 
        }
        
        /* Contenedor principal con flex-grow para ocupar el espacio restante */
        .main-container { 
            flex-grow: 1; 
            padding: 20px; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
        }
        
        /* Tarjeta del calendario ocupando todo el espacio */
        .calendar-card { 
            background: white; 
            border-radius: 12px; 
            border: none; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            padding: 20px; 
            flex-grow: 1; 
            display: flex; 
            flex-direction: column; 
        }
        
        /* El div del calendario debe crecer */
        #calendar { flex-grow: 1; }

        .fc-toolbar-title { font-size: 1.2rem !important; font-weight: 700; color: #343a40; }
        .fc-button-primary { background-color: var(--hospital-blue) !important; border-color: var(--hospital-blue) !important; }
        .fc-event { cursor: pointer; border: none; padding: 2px 4px; font-size: 0.85rem; }
    </style>
</head>
<body>

    <header class="brand-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <a href="surgeon_dashboard.php" class="text-white-50 text-decoration-none small mb-2 d-inline-block">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
                <h1 class="h4 fw-bold mb-0">My Surgical Calendar</h1>
            </div>
            <button class="btn btn-warning fw-bold shadow-sm btn-sm" onclick="openRequestModal('New Surgery Request')">
                <i class="bi bi-plus-lg"></i> New Request
            </button>
        </div>
    </header>

    <div class="main-container">
        <div class="calendar-card">
            <div id='calendar'></div>
        </div>
    </div>

    <div class="modal fade" id="eventOptionsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 bg-light">
                    <h5 class="modal-title fw-bold" id="eventTitle">Surgery Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <h4 class="text-primary mb-1" id="procName">Procedure Name</h4>
                    <p class="text-muted mb-4" id="patName">Patient Name</p>
                    
                    <div class="d-grid gap-3">
                        <a href="#" id="btnGoEpisode" class="btn btn-primary btn-lg shadow-sm">
                            <i class="bi bi-clipboard-pulse me-2"></i> Open Clinical Episode
                        </a>
                        
                        <button id="btnReqChange" class="btn btn-outline-danger">
                            <i class="bi bi-calendar-x me-2"></i> Request Reschedule / Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-send"></i> Send Request to Admin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="requestForm">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">TYPE</label>
                            <select id="reqType" class="form-select">
                                <option value="New Surgery Request">New Booking</option>
                                <option value="Reschedule">Reschedule</option>
                                <option value="Cancellation">Cancellation</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">DETAILS</label>
                            <textarea id="reqDetails" class="form-control" rows="4" required placeholder="Describe your request (e.g., patient name, preferred dates...)"></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-dark fw-bold">Submit Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var optModal = new bootstrap.Modal(document.getElementById('eventOptionsModal'));
            var reqModal = new bootstrap.Modal(document.getElementById('requestModal'));

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
                themeSystem: 'bootstrap5',
                events: 'api_or_calendar.php', // Carga tus eventos
                height: '100%', // Ocupa todo el espacio de la tarjeta
                
                // AL HACER CLIC EN UNA CIRUGÍA
                eventClick: function(info) {
                    // 1. Rellenar datos del Modal de Opciones
                    document.getElementById('procName').textContent = info.event.title;
                    document.getElementById('patName').textContent = 'Patient: ' + info.event.extendedProps.patient;
                    
                    // 2. Configurar botón "Ir al Episodio"
                    document.getElementById('btnGoEpisode').href = 'clinical_episode.php?id=' + info.event.extendedProps.episode_id;
                    
                    // 3. Configurar botón "Pedir Cambio"
                    document.getElementById('btnReqChange').onclick = function() {
                        optModal.hide(); // Cierra el menú
                        openRequestModal('Reschedule', 
                            `Requesting change for surgery: ${info.event.title}\nPatient: ${info.event.extendedProps.patient}\nCurrent Date: ${info.event.start.toLocaleString()}\n\nReason/New Date: `
                        );
                    };

                    // 4. Mostrar Modal
                    optModal.show();
                },

                // Al hacer clic en un día vacío (Opcional: abrir solicitud de nueva cirugía)
                dateClick: function(info) {
                    openRequestModal('New Surgery Request', `Requesting new surgery for date: ${info.dateStr}\n\nPatient:\nProcedure:`);
                }
            });
            calendar.render();

            // FUNCIÓN PARA ABRIR MODAL DE SOLICITUD
            window.openRequestModal = function(type, details = '') {
                document.getElementById('reqType').value = type;
                document.getElementById('reqDetails').value = details;
                reqModal.show();
            };

            // ENVIAR SOLICITUD
            document.getElementById('requestForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const payload = {
                    type: document.getElementById('reqType').value,
                    details: document.getElementById('reqDetails').value
                };

                try {
                    const res = await fetch('api_send_request.php', {
                        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
                    });
                    const data = await res.json();
                    if(data.success) {
                        alert("Request sent successfully.");
                        reqModal.hide();
                    } else { alert("Error: " + data.error); }
                } catch(err) { console.error(err); }
            });
        });
    </script>

</body>
</html>