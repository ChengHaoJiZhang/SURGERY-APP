<?php
// views/director/specialties.php
session_start();
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { /* redirect */ }

$pdo = getDBConnection();
// Fetch specialties excluding Administration (SP002)
$specs = $pdo->query("SELECT * FROM SPECIALITIES WHERE Speciality_ID != 'SP002' ORDER BY Speciality_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinical Specialties | Les Corts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bg-body: #121212; --card-bg: #1e1e1e; --text-main: #e0e0e0; --primary: #0d6efd; --border: #333; }
        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Segoe UI', sans-serif; }
        
        /* FULL WIDTH CONTAINER */
        .main-container { width: 100%; padding: 30px; }
        
        /* SEARCH BAR & DEPLOYABLE DROPDOWN */
        .search-container { position: relative; width: 500px; }
        .search-input { 
            background: #2a2a2a; border: 1px solid var(--border); color: white; 
            border-radius: 20px; padding: 12px 25px 12px 45px; width: 100%; transition: 0.2s; font-size: 1rem;
        }
        .search-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25); }
        .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #666; font-size: 1.1rem; }
        
        .suggestions-box {
            position: absolute; top: 100%; left: 0; right: 0; margin-top: 8px;
            background: #252525; border: 1px solid var(--border); border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); z-index: 2000; 
            max-height: 400px; overflow-y: auto; display: none;
        }
        .suggestion-item { 
            padding: 15px 25px; border-bottom: 1px solid #333; cursor: pointer; 
            display: flex; justify-content: space-between; align-items: center;
        }
        .suggestion-item:hover { background: #333; }
        .suggestion-item:last-child { border-bottom: none; }

        /* Sidebar List */
        .card-clean { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; height: 100%; }
        .spec-item { 
            padding: 20px 25px; cursor: pointer; border-bottom: 1px solid var(--border); 
            display: flex; justify-content: space-between; align-items: center; color: #aaa; transition: 0.2s; font-size: 1.1rem;
        }
        .spec-item:hover { background: #2a2a2a; color: white; padding-left: 30px; }
        .spec-item.active { background: rgba(13, 110, 253, 0.15); color: var(--primary); border-left: 4px solid var(--primary); font-weight: bold; padding-left: 30px; }
        
        /* Procedure Items */
        .proc-item { padding: 25px; border-bottom: 1px solid var(--border); cursor: pointer; transition: transform 0.2s; }
        .proc-item:hover { background: #252525; transform: translateX(8px); }
        .badge-code { font-family: monospace; font-size: 0.8rem; padding: 6px 10px; border-radius: 6px; }
        .badge-icd { background: rgba(13, 110, 253, 0.2); color: #6ea8fe; border: 1px solid rgba(13, 110, 253, 0.3); }
        .badge-snomed { background: rgba(220, 53, 69, 0.2); color: #ea868f; border: 1px solid rgba(220, 53, 69, 0.3); }
    </style>
</head>
<body>

<div class="main-container">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div class="d-flex align-items-center gap-3">
            <a href="director_dashboard.php" class="btn btn-outline-secondary btn-lg rounded-circle" style="width:50px; height:50px; display:flex; align-items:center; justify-content:center;"><i class="bi bi-arrow-left fs-4"></i></a>
            <div><h2 class="fw-bold m-0 text-white">Clinical Specialties & Procedures</h2><p class="text-muted m-0">Browse the complete surgical catalog</p></div>
        </div>
        
        <!-- SEARCH BAR -->
        <div class="search-container">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="procSearch" class="search-input" placeholder="Search Procedure (Name, ICD, SNOMED)..." autocomplete="off">
            <div id="suggestionsBox" class="suggestions-box"></div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Specialties List -->
        <div class="col-md-3">
            <div class="card-clean">
                <div class="p-4 border-bottom border-secondary text-muted small fw-bold text-uppercase bg-dark">Specialties Directory</div>
                <div style="height: 75vh; overflow-y: auto;">
                    <?php foreach($specs as $s): ?>
                        <div class="spec-item" onclick="filterBySpecialty(this, '<?php echo $s['Speciality_ID']; ?>', '<?php echo htmlspecialchars($s['Speciality_name']); ?>')">
                            <span><?php echo htmlspecialchars($s['Speciality_name']); ?></span>
                            <span style="font-size: 0.8rem; opacity: 0.5;"><?php echo $s['Speciality_ID']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right: Procedures List -->
        <div class="col-md-9">
            <div class="card-clean p-5" style="min-height: 80vh;">
                <h3 class="fw-bold text-white m-0 mb-4 border-bottom border-secondary pb-3" id="listTitle">Select a specialty...</h3>
                <div id="proceduresList">
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-arrow-left-circle display-1 opacity-25"></i>
                        <h4 class="mt-4">Please select a specialty on the left</h4>
                        <p>Or use the search bar above to find a specific procedure.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const searchInput = document.getElementById('procSearch');
    const suggestions = document.getElementById('suggestionsBox');
    let searchTimeout;

    // 1. Filter by Specialty ID (Sidebar Click)
    function filterBySpecialty(el, id, name) {
        document.querySelectorAll('.spec-item').forEach(i => i.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('listTitle').innerText = name;
        loadProcedures(`spec_id=${id}`);
    }

    // 2. Search Bar Logic (Predictive Dropdown)
    searchInput.addEventListener('input', (e) => {
        const q = e.target.value.trim();
        clearTimeout(searchTimeout);
        
        if(q.length < 2) { suggestions.style.display = 'none'; return; }

        searchTimeout = setTimeout(() => {
            fetch(`api/search_procedures.php?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    suggestions.innerHTML = '';
                    if(data.length > 0) {
                        data.forEach(p => {
                            suggestions.innerHTML += `
                                <div class="suggestion-item" onclick="goToDetails(${p.procedure_id})">
                                    <div>
                                        <div class="fw-bold text-white">${p.procedure_name}</div>
                                        <div class="small text-muted">ICD: ${p.icd_code || '-'}</div>
                                    </div>
                                    <span class="badge bg-secondary">${p.snomed_id || '-'}</span>
                                </div>`;
                        });
                        suggestions.style.display = 'block';
                    } else {
                        suggestions.innerHTML = '<div class="p-3 text-muted text-center">No matches found</div>';
                        suggestions.style.display = 'block';
                    }
                });
        }, 300);
    });
    
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.style.display = 'none';
        }
    });

    function goToDetails(id) {
        window.location.href = `procedure_details.php?id=${id}`;
    }

    // 3. Load Main List
    async function loadProcedures(params) {
        const container = document.getElementById('proceduresList');
        container.innerHTML = '<div class="text-center py-5 text-muted">Loading...</div>';
        
        try {
            const res = await fetch(`api/search_procedures.php?${params}`);
            const data = await res.json();
            container.innerHTML = '';

            if(data.length === 0) {
                container.innerHTML = '<div class="text-center py-5 text-muted fs-5">No procedures found for this category.</div>';
                return;
            }

            data.forEach(p => {
                container.innerHTML += `
                <div class="proc-item" onclick="goToDetails(${p.procedure_id})">
                    <h4 class="text-primary fw-bold mb-2">${p.procedure_name}</h4>
                    <p class="text-muted mb-3 fs-6">${p.description || 'No description available.'}</p>
                    <div class="d-flex gap-3">
                        <span class="badge-code badge-icd">ICD: ${p.icd_code || 'N/A'}</span>
                        <span class="badge-code badge-snomed">SNOMED: ${p.snomed_id || 'N/A'}</span>
                    </div>
                </div>`;
            });
        } catch (err) { console.error(err); }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>