<?php
// views/director/personnel.php
session_start();
if (!isset($_SESSION['user_id'])) { /* redirect */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnel Management | Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bg-body: #f5f7fb; --primary-color: #0d6efd; }
        body { background-color: var(--bg-body); font-family: 'Segoe UI', sans-serif; }
        
        .main-container { max-width: 1200px; margin: 40px auto; padding: 20px; }
        .card-clean { background: white; border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        
        .nav-tabs .nav-link { border: none; color: #6c757d; font-weight: 600; padding: 15px 25px; border-bottom: 3px solid transparent; }
        .nav-tabs .nav-link.active { color: var(--primary-color); border-bottom: 3px solid var(--primary-color); background: transparent; }
        .nav-tabs { border-bottom: 1px solid #e9ecef; }
        
        .table th { font-size: 0.75rem; text-transform: uppercase; color: #8898aa; padding: 15px; border-bottom: 1px solid #e9ecef; background: #f8f9fa; }
        .table td { padding: 15px; vertical-align: middle; color: #525f7f; border-bottom: 1px solid #e9ecef; font-size: 0.9rem; }
        .avatar-initial { width: 32px; height: 32px; background: #e9ecef; color: var(--primary-color); display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold; margin-right: 12px; font-size: 0.8rem; }
        
        /* Search Bar & Suggestions Style */
        .search-container { position: relative; max-width: 400px; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #6c757d; }
        .form-search { padding-left: 40px; border-radius: 20px; }
        .form-search:focus { border-radius: 20px 20px 0 0; border-bottom: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

        .suggestions-box {
            position: absolute; top: 100%; left: 0; right: 0; z-index: 1000;
            background: white; border: 1px solid #dee2e6; border-top: none;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            max-height: 300px; overflow-y: auto; display: none;
        }
        .suggestion-item {
            padding: 12px 20px; border-bottom: 1px solid #f1f1f1; cursor: pointer;
            display: flex; justify-content: space-between; align-items: center;
        }
        .suggestion-item:hover { background: #f8f9fa; color: var(--primary-color); }
        .suggestion-item:last-child { border-bottom: none; }
        .suggestion-role { font-size: 0.7rem; text-transform: uppercase; font-weight: 700; color: #adb5bd; background: #f8f9fa; padding: 2px 6px; border-radius: 4px; }

        /* History Tab Styles */
        .surgery-card { border: 1px solid #e9ecef; border-radius: 12px; margin-bottom: 20px; transition: all 0.3s ease; background: #fff; }
        .surgery-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        .surgery-card.active-highlight { border: 2px solid var(--primary-color); box-shadow: 0 0 15px rgba(13, 110, 253, 0.15); background-color: #f8fbff; }

        .surgery-header { background: #f8f9fa; padding: 12px 20px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
        .surgery-body { padding: 20px; }
        .staff-chip { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; padding: 5px; border-radius: 6px; }
        .staff-chip:hover { background: #f1f3f5; }
        .role-title { font-size: 0.7rem; text-transform: uppercase; font-weight: 700; color: #adb5bd; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        
        .bg-surgeon { background-color: #0d6efd; color: white; }
        .bg-nurse { background-color: #198754; color: white; }
        .bg-anes { background-color: #6f42c1; color: white; }
        .staff-avatar-sm { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold; }
    </style>
</head>
<body>

<div class="main-container">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center">
            <a href="director_dashboard.php" class="btn btn-outline-secondary btn-sm me-3 rounded-circle" style="width:40px; height:40px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h3 class="fw-bold m-0">Medical Staff Directory</h3>
                <p class="text-muted m-0 small">Manage Surgeons, Nurses, Specialists, Admin & History</p>
            </div>
        </div>

        <div class="search-container w-100" id="global-search-container">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="searchInput" class="form-control form-search" placeholder="Search by Name or License..." autocomplete="off" onkeyup="fetchPersonnel()">
            <div id="suggestionsBox" class="suggestions-box"></div>
        </div>
    </div>

    <div class="card-clean">
        <ul class="nav nav-tabs" id="staffTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" id="tab-surgeons" data-bs-toggle="tab" data-bs-target="#surgeons" onclick="toggleSearch(true)">Surgeons <span class="badge bg-light text-dark ms-1" id="count-surgeons">0</span></button></li>
            <li class="nav-item"><button class="nav-link" id="tab-nurses" data-bs-toggle="tab" data-bs-target="#nurses" onclick="toggleSearch(true)">Nurses <span class="badge bg-light text-dark ms-1" id="count-nurses">0</span></button></li>
            <li class="nav-item"><button class="nav-link" id="tab-anes" data-bs-toggle="tab" data-bs-target="#anes" onclick="toggleSearch(true)">Anesthesiologists <span class="badge bg-light text-dark ms-1" id="count-anes">0</span></button></li>
            <li class="nav-item"><button class="nav-link" id="tab-admins" data-bs-toggle="tab" data-bs-target="#admins" onclick="toggleSearch(true)">Administration <span class="badge bg-light text-dark ms-1" id="count-admins">0</span></button></li>
            
            <li class="nav-item"><button class="nav-link" id="tab-history" data-bs-toggle="tab" data-bs-target="#history" onclick="toggleSearch(false)">Surgical History</button></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="surgeons">
                <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>License ID</th><th>Specialization</th><th>Contact (Int.)</th></tr></thead><tbody id="tbody-surgeons"></tbody></table></div>
            </div>
            <div class="tab-pane fade" id="nurses">
                <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>License ID</th><th>Specialization</th><th>Contact (Int.)</th></tr></thead><tbody id="tbody-nurses"></tbody></table></div>
            </div>
            <div class="tab-pane fade" id="anes">
                <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>License ID</th><th>Specialization</th><th>Contact (Int.)</th></tr></thead><tbody id="tbody-anes"></tbody></table></div>
            </div>
            <div class="tab-pane fade" id="admins">
                <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>License ID</th><th>Role/Dept</th><th>Contact (Int.)</th></tr></thead><tbody id="tbody-admins"></tbody></table></div>
            </div>
            
            <div class="tab-pane fade" id="history">
                <div class="p-4">
                    <div class="row g-3 mb-4 align-items-end">
                        <div class="col-md-3"><label class="small text-muted fw-bold">Date</label><input type="date" id="histDate" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
                        <div class="col-md-2"><label class="small text-muted fw-bold">Hour</label><select id="histHour" class="form-select"><option value="">All Day</option><?php for($i=6; $i<=22; $i++) echo "<option value='".str_pad($i,2,'0',STR_PAD_LEFT)."'>$i:00</option>"; ?></select></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100" onclick="loadHistory()">Filter</button></div>
                    </div>
                    <div id="history-container"><div class="text-center text-muted py-5">Select a date to view history.</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let searchTimeout;

    // Toggle search bar visibility based on tab
    function toggleSearch(show) {
        document.getElementById('global-search-container').style.display = show ? 'block' : 'none';
    }

    // --- STAFF DATA & SEARCH SUGGESTIONS ---
    function fetchPersonnel() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const query = document.getElementById('searchInput').value;
            loadStaffData(query);
        }, 300);
    }

    async function loadStaffData(search = '') {
        try {
            const url = search ? `api/personnel.php?q=${encodeURIComponent(search)}` : 'api/personnel.php';
            const res = await fetch(url);
            const data = await res.json();

            // Populate Tables
            renderStaffTable('tbody-surgeons', data.surgeons);
            renderStaffTable('tbody-nurses', data.nurses);
            renderStaffTable('tbody-anes', data.anesthesiologists);
            renderStaffTable('tbody-admins', data.administration);

            // Update Counts
            document.getElementById('count-surgeons').innerText = data.surgeons ? data.surgeons.length : 0;
            document.getElementById('count-nurses').innerText = data.nurses ? data.nurses.length : 0;
            document.getElementById('count-anes').innerText = data.anesthesiologists ? data.anesthesiologists.length : 0;
            document.getElementById('count-admins').innerText = data.administration ? data.administration.length : 0;

            // --- SUGGESTION BOX LOGIC ---
            const suggestionsBox = document.getElementById('suggestionsBox');
            // Only show suggestions if searching and results exist
            if (search.length > 0 && data.all_results && data.all_results.length > 0) {
                suggestionsBox.innerHTML = '';
                suggestionsBox.style.display = 'block';
                
                // Show top 8 matches
                data.all_results.slice(0, 8).forEach(item => {
                    const fullName = `${item.first_name} ${item.surnames}`;
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.innerHTML = `
                        <div>
                            <div class="fw-bold">${fullName}</div>
                            <small class="text-muted">${item.license_number}</small>
                        </div>
                        <span class="suggestion-role">${item.role}</span>
                    `;
                    // UPDATED CLICK HANDLER: Pass role to handle redirection
                    div.onclick = () => selectSuggestion(fullName, item.role);
                    suggestionsBox.appendChild(div);
                });
            } else {
                suggestionsBox.style.display = 'none';
            }

        } catch (err) { console.error(err); }
    }

    // UPDATED FUNCTION: Redirects to correct tab
    function selectSuggestion(name, role) {
        // 1. Fill input & hide box
        document.getElementById('searchInput').value = name;
        document.getElementById('suggestionsBox').style.display = 'none';

        // 2. Map Role to Tab ID
        let tabId = '';
        const r = role.toLowerCase();
        
        if (r.includes('surgeon')) {
            tabId = 'tab-surgeons';
        } else if (r.includes('nurse')) {
            tabId = 'tab-nurses';
        } else if (r.includes('anesthesiologist')) {
            tabId = 'tab-anes';
        } else if (r.includes('admin') || r.includes('ministrator')) {
            tabId = 'tab-admins';
        }

        // 3. Switch Tab if found
        if (tabId) {
            const triggerEl = document.getElementById(tabId);
            const tabInstance = bootstrap.Tab.getOrCreateInstance(triggerEl);
            tabInstance.show();
        }

        // 4. Reload data filtered by the selected name
        loadStaffData(name); 
    }

    function renderStaffTable(elId, list) {
        const tbody = document.getElementById(elId);
        tbody.innerHTML = '';
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">No records found.</td></tr>';
            return;
        }
        list.forEach(p => {
            let specDisplay = p.spec_name;
            if(p.spec_id && p.spec_name !== p.spec_id) {
                specDisplay += ` <span class="text-muted ms-1 small">(${p.spec_id})</span>`;
            }
            let contactDisplay = '<span class="text-muted text-xs">N/A</span>';
            if (p.work_extension) {
                contactDisplay = `<span class="badge bg-light text-primary border"><i class="bi bi-telephone-fill me-1"></i> Ext. ${p.work_extension}</span>`;
            }

            tbody.innerHTML += `
                <tr>
                    <td><div class="d-flex align-items-center"><div class="avatar-initial">${p.first_name.charAt(0)}</div><span class="fw-bold text-dark">${p.first_name} ${p.surnames}</span></div></td>
                    <td><span class="badge bg-light text-dark border">${p.license_number}</span></td>
                    <td>${specDisplay}</td>
                    <td>${contactDisplay}</td>
                </tr>`;
        });
    }

    // --- HISTORY TAB ---
    async function loadHistory() {
        const date = document.getElementById('histDate').value;
        const hour = document.getElementById('histHour').value;
        const container = document.getElementById('history-container');
        container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
        
        try {
            const res = await fetch(`api/surgery_teams.php?date=${date}&hour=${hour}`);
            const data = await res.json();
            container.innerHTML = '';
            
            if (data.length === 0) { 
                container.innerHTML = '<div class="text-center py-5 text-muted">No surgeries found for this time.</div>'; 
                return; 
            }
            
            data.forEach(op => {
                const sList = renderTeamList(op.surgeons, 'bg-surgeon');
                const nList = renderTeamList(op.nurses, 'bg-nurse');
                const aList = renderTeamList(op.anesthesiologists, 'bg-anes');
                
                container.innerHTML += `
                <div class="surgery-card" id="surgery-${op.surgical_procedure_id}">
                    <div class="surgery-header">
                        <div>
                            <span class="badge bg-dark me-2">${op.time_formatted}</span>
                            <span class="fw-bold text-dark">${op.procedure_name}</span>
                            <small class="text-muted ms-2">${op.room_number || 'No Room'}</small>
                        </div>
                        <span class="badge bg-light text-dark border">${op.status}</span>
                    </div>
                    <div class="surgery-body">
                        <div class="row">
                            <div class="col-md-4 border-end">
                                <div class="role-title text-primary">Surgeons</div>
                                ${sList||'-'}
                            </div>
                            <div class="col-md-4 border-end">
                                <div class="role-title text-success">Nurses</div>
                                ${nList||'-'}
                            </div>
                            <div class="col-md-4">
                                <div class="role-title text-info">Anesthesia</div>
                                ${aList||'-'}
                            </div>
                        </div>
                    </div>
                </div>`;
            });
        } catch(e) { console.error(e); }
    }

    function renderTeamList(list, bgClass) {
        if (!list || list.length === 0) return '';
        return list.map(p => `<div class="staff-chip"><div class="staff-avatar-sm ${bgClass}">${p.first_name.charAt(0)}</div><div><div class="fw-bold small text-dark">${p.first_name} ${p.surnames}</div><div class="text-muted" style="font-size: 0.65rem;">${p.role_in_surgery || 'Assigned'}</div></div></div>`).join('');
    }

    // --- INIT ---
    document.addEventListener('DOMContentLoaded', () => { 
        // Close suggestions on click outside
        document.addEventListener('click', (e) => {
            if (!document.getElementById('global-search-container').contains(e.target)) {
                document.getElementById('suggestionsBox').style.display = 'none';
            }
        });

        // URL deep linking logic
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        
        if (tab === 'history') {
            const triggerEl = document.getElementById('tab-history');
            const tabInstance = new bootstrap.Tab(triggerEl);
            tabInstance.show();
            toggleSearch(false);

            const dateParam = urlParams.get('date');
            if (dateParam) document.getElementById('histDate').value = dateParam;

            loadHistory().then(() => {
                const idParam = urlParams.get('id');
                if (idParam) {
                    setTimeout(() => {
                        const targetCard = document.getElementById('surgery-' + idParam);
                        if (targetCard) {
                            targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            targetCard.classList.add('active-highlight');
                        }
                    }, 200);
                }
            });
        } else {
            loadStaffData(); 
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>