<?php
// views/director/calendar.php
session_start();
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { /* redirect */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OR Calendar | Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    
    <style>
        :root { --bg-body: #f5f7fb; --primary-color: #0d6efd; }
        body { background-color: var(--bg-body); font-family: 'Segoe UI', sans-serif; }
        .main-container { max-width: 1400px; margin: 40px auto; padding: 20px; }
        .card-clean { background: white; border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 20px; height: 100%; }
        
        /* Calendar Styles */
        #calendar { max-width: 100%; margin: 0 auto; }
        .fc-event { cursor: pointer; border: none; padding: 2px 4px; font-size: 0.85em; }
        .fc-toolbar-title { font-size: 1.5rem !important; font-weight: 700; }
        .fc-button-primary { background-color: var(--primary-color) !important; border-color: var(--primary-color) !important; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="d-flex align-items-center mb-4">
        <a href="director_dashboard.php" class="btn btn-outline-secondary btn-sm me-3 rounded-circle" style="width:40px; height:40px; display:flex; align-items:center; justify-content:center;">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h3 class="fw-bold m-0">Surgical Calendar</h3>
            <p class="text-muted m-0 small">Schedule overview by Surgeon and Room</p>
        </div>
    </div>

    <div class="card-clean">
        <div id="calendar"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
            },
            themeSystem: 'bootstrap5',
            events: 'api/calendar_events.php', // Load events from API
            eventClick: function(info) {
                // Redirect to team history for that specific surgery
                // Use ISO string to get YYYY-MM-DD
                const date = info.event.start.toISOString().split('T')[0];
                const id = info.event.id;
                
                // Matches the URL pattern used in your dashboard
                window.location.href = `personnel.php?tab=history&date=${date}&id=${id}`;
            },
            eventDidMount: function(info) {
                // Add tooltip (simple browser title)
                info.el.title = info.event.title + " (" + info.event.extendedProps.status + ")";
            }
        });
        calendar.render();
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>