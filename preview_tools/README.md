# Local surgery-app demo and LinkedIn captures

This directory reproduces SURGERY-APP locally with the repository's 2025 demo SQL. It also creates a separate synthetic anesthesia case (ID 900001) and streams simulated vital signs every three seconds. It is a prototype, not a live clinical system or patient monitor.

## Where the files go

    parent/
    ├── SURGERY-APP-linkedin-preview/       Git clone (the folder name may differ)
    │   ├── config/config.php
    │   ├── database/imtm2025_grupd.sql
    │   ├── linkedin_screenshots/           reviewed PNGs
    │   └── preview_tools/
    │       ├── README.md
    │       ├── import_demo_db.py
    │       ├── demo_anesthesia.php
    │       ├── start_demo.ps1
    │       ├── stop_demo.ps1
    │       └── capture_linkedin.py
    └── SURGERY-APP-preview-runtime/        local only; never committed
        ├── php/
        ├── mariadb/mariadb-11.8.9-winx64/
        ├── data/
        └── python-packages/

The runtime lives beside the clone. PHP and MariaDB bind only to 127.0.0.1. No Windows service is installed.

## Rebuild from a fresh clone on Windows

Use PowerShell, Python 3, and Chrome or Edge. Download the portable ZIPs from the official [PHP Windows page](https://www.php.net/downloads.php?os=windows&version=8.4) and [MariaDB downloads page](https://mariadb.org/downloads/). Extract PHP 8.4 x64 NTS directly into the sibling runtime/php folder. Extract MariaDB 11.8.9 x64 into runtime/mariadb, preserving the mariadb-11.8.9-winx64 folder. These are the versions used by the startup scripts.

From the clone root, define paths:

    $repo = (Resolve-Path .).Path
    $runtime = Join-Path (Split-Path $repo -Parent) 'SURGERY-APP-preview-runtime'
    $php = Join-Path $runtime 'php/php.exe'
    $dbBin = Join-Path $runtime 'mariadb/mariadb-11.8.9-winx64/bin'
    $data = Join-Path $runtime 'data'

Enable PHP extensions by copying php.ini-development to php.ini, then appending these settings. Check that PHP lists PDO, pdo_mysql, mysqli, mbstring, openssl, and curl.

    Copy-Item (Join-Path $runtime 'php/php.ini-development') (Join-Path $runtime 'php/php.ini')
    $ext = (Join-Path $runtime 'php/ext').Replace('\','/')
    @"
    extension_dir = "$ext"
    extension=pdo_mysql
    extension=mysqli
    extension=mbstring
    extension=openssl
    extension=curl
    display_errors=Off
    log_errors=On
    "@ | Add-Content (Join-Path $runtime 'php/php.ini')
    & $php -m

Initialize MariaDB and start it on localhost port 3308. The password below is only for this isolated demo; do not reuse it.

    & (Join-Path $dbBin 'mariadb-install-db.exe') "--datadir=$data" '--password=previewlocalonly' '--port=3308'
    Start-Process -FilePath (Join-Path $dbBin 'mariadbd.exe') -ArgumentList @("--defaults-file=$data/my.ini", "--datadir=$data", '--port=3308', '--bind-address=127.0.0.1', '--skip-name-resolve') -WorkingDirectory $dbBin -WindowStyle Hidden

Create the empty database and the local demo account. Then import the repository SQL. The import helper refuses a database that already has tables; it changes the MySQL 8 collation to a MariaDB-compatible one in memory without editing the source dump.

    $sql = "CREATE DATABASE imtm2025_grupd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'preview_user'@'127.0.0.1' IDENTIFIED BY 'preview_local_only'; GRANT ALL PRIVILEGES ON imtm2025_grupd.* TO 'preview_user'@'127.0.0.1';"
    & (Join-Path $dbBin 'mariadb.exe') --host=127.0.0.1 --port=3308 --user=root --password=previewlocalonly --execute=$sql
    python .\preview_tools\import_demo_db.py

Install Playwright into the sibling runtime, then start the app:

    python -m pip install --target (Join-Path $runtime 'python-packages') playwright==1.56.0
    .\preview_tools\start_demo.ps1

The startup script starts MariaDB and PHP if needed, seeds 15 recent synthetic HR/BP/SpO2 samples plus two log entries, and starts a worker that inserts a sample every three seconds. Calling it again while the worker runs does not reset the case. After stopping the demo, starting again resets only the synthetic case.

## Live anesthesiologist demonstration

1. Open http://127.0.0.1:8000/login.php and choose the Anesthesiologist demo role. Sign in and accept the demo agreement if shown.
2. Open http://127.0.0.1:8000/views/anesthesiologist/live_cockpit.php?id=900001 or select the live case on the dashboard. A cyan SYNTHETIC DEMO label marks the case.
3. Watch the HR, non-invasive blood pressure, SpO2, and chart update. The browser refreshes every three seconds and reads samples written by the local worker. The event log contains synthetic monitoring and blood-gas entries.
4. The existing controls also let you demonstrate Time In with the safety checklist, manual vital-sign recording, blood-gas/POC entry, quick medication recording, intubation/incision/closure/extubation events, and Time Out. Time Out completes the case and stops the worker; restart the demo to reset it.

The demo setup adds an oxygen_saturation column to SURGERY_MONITORING. Previously the anesthesia screen displayed SpO2 from a column named tidal_CO2. The API retains a legacy fallback so the unmodified source SQL still loads before demo setup; the synthetic case uses the dedicated column. The source SQL dump is left unchanged.

## Screenshot capture

    python .\preview_tools\capture_linkedin.py
    python .\preview_tools\capture_linkedin.py --scale 3 --output linkedin_screenshots_3x

The first command saves 2400 x 1500 PNGs in linkedin_screenshots. The second renders 4800 x 3000 PNGs in a separate folder. Rendering again at higher pixel density creates more detail than enlarging an existing PNG.

| PNG | View |
| --- | --- |
| 01_login.png | Demo role selection |
| 02_surgeon_snomed_lookup.png | Operative report and local SNOMED CT lookup |
| 03_nurse_safety_cockpit.png | Surgical safety cockpit |
| 04_anesthesiologist_cockpit.png | Live synthetic vitals and event log |
| 05_patient_portal.png | Patient portal and consent |
| 06_patient_surgery_details.png | Surgery details and allergy alert |
| 07_administrator_scheduler.png | Operating-room scheduler |
| 08_director_inventory.png | Inventory and stock alerts |

Some dashboards filtered to the current date appear empty because the imported records are from 2025. These curated views show populated features. The SNOMED search uses a local demonstration catalogue, not a live terminology service.

Stop the PHP server, MariaDB, and synthetic stream with:

    .\preview_tools\stop_demo.ps1

The original public repository previously contained credentials in config/config.php. The preview configuration removes the API key and accepts environment variables for database credentials. Rotate credentials exposed in the public repository or its Git history before using them elsewhere.
