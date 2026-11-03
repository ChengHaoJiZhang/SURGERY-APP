# Local surgery-app demo and LinkedIn captures

This directory reproduces SURGERY-APP locally with the repository's 2025 demo SQL. It creates synthetic cases for the anesthesiologist (900001), nurse (900002), and scheduler (900003), and streams simulated HR and SpO2 every three seconds in the anesthesia case, with intermittent NIBP readings. It is a prototype, not a live clinical system or patient monitor.

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
    │       ├── seed_screenshot_cases.php
    │       ├── validate_screenshot_cases.php
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

The startup script starts MariaDB and PHP if needed, seeds 21 recent synthetic HR/SpO2 samples, intermittent BP readings, and two log entries. It then runs `seed_screenshot_cases.php` against the isolated local database: the nurse case shows a knee replacement in progress, the scheduler gets a fictional pending case, the surgeon's imported demo case (173) gets a coherent operative timeline and a medication report consistent with its log, and the completed patient example gets a prior-day consent date. Current-month material usage and stock dates are made consistent. A worker adds HR/SpO2 samples every three seconds and a new NIBP reading approximately every five minutes. Calling the startup script again while the worker runs does not reset the cases. After stopping the demo, starting again resets the synthetic fixtures. The original SQL dump is never edited.

## Live anesthesiologist demonstration

1. Open http://127.0.0.1:8000/login.php and choose the Anesthesiologist demo role. Sign in and accept the demo agreement if shown.
2. Open http://127.0.0.1:8000/views/anesthesiologist/live_cockpit.php?id=900001 or select the live case on the dashboard. A cyan SYNTHETIC DEMO label marks the case.
3. Watch HR and SpO2 update every three seconds while the last measured NIBP remains visible between intermittent readings. The chart uses separate bpm and mmHg axes. The browser reads samples written by the local worker. The event log contains synthetic monitoring and blood-gas entries.
4. The existing controls also let you demonstrate Time In with the safety checklist, manual vital-sign recording, blood-gas/POC entry, quick medication recording, intubation/incision/closure/extubation events, and Time Out. Time Out completes the case and stops the worker; restart the demo to reset it.

### Clinical meaning of the demo

The linked patient name comes from the repository's demonstration dataset. The added measurements are generated values, not a real patient's observations. The generator keeps HR roughly 72–86 bpm, SpO2 97–99%, and NIBP roughly 112–128/70–82 mmHg; those values are intended to be plausible for a stable adult example, not a prediction of any particular patient's response to anesthesia. HR and SpO2 change gradually every three seconds, while NIBP is recorded about every five minutes. The trend chart is not an ECG waveform, and the app does not simulate rhythm, respiration, anesthetic depth, alarms, or the full monitoring required in practice.

For general reference, [MedlinePlus describes adult resting pulse and BP ranges](https://medlineplus.gov/ency/article/002341.htm), and the [FDA describes typical SpO2 values and measurement limitations](https://www.fda.gov/consumers/consumer-updates/pulse-oximeter-basics). The demo is not a clinically validated simulator or decision-support tool.

The demo setup adds an `oxygen_saturation` column to `SURGERY_MONITORING`. The original app stored some SpO2 demo values in a column named `tidal_CO2`, which is a different clinical measurement. The fixture migrates the relevant imported demo readings into the dedicated column, and the nurse pre-op form writes to that column. Without the column, the API leaves SpO2 empty rather than presenting end-tidal CO2 as oxygen saturation. The source SQL dump is left unchanged.

## Rule for every clinical screenshot and demo fixture

Before publishing or changing **any** screenshot, check each visible clinical value and code against an authoritative source suited to that field. Check the full story as well: patient and procedure match; consent and safety checks precede incision; room entry precedes incision, closure and exit; procedure duration is credible; medication times fall in the correct phase; units and measurement frequencies are right; stock, expiry alerts and usage totals agree; and dates and statuses do not contradict one another. Inspect the rendered PNG after capture. Do not substitute values from a real hospital patient. If a number has no defensible basis, omit it or label it illustrative; never imply that these fixtures are clinically validated.

The current examples were checked against the [WHO Surgical Safety Checklist phases](https://www.who.int/docs/default-source/patient-safety/9789241598590-eng-checklist.pdf), [NHS knee-replacement duration and procedure overview](https://www.nhs.uk/tests-and-treatments/knee-replacement/how-its-done/), [MedlinePlus adult vital-sign ranges](https://medlineplus.gov/ency/article/002341.htm), [FDA pulse-oximetry basics](https://www.fda.gov/consumers/consumer-updates/pulse-oximeter-basics), [Canadian Anesthesiologists' Society monitoring guidance](https://www.cas.ca/CASAssets/Documents/Practice-Resources/Guidelines/CAS_Guidelines_Anesthesia_2026.pdf), and [HL7's SNOMED CT knee/hip concept list](https://www.hl7.org/fhir/uv/ips/STU1.1/ValueSet-problems-snomed-ct-ips-free-set.html). These references support plausibility and terminology, not diagnosis, drug dosing, or patient-specific clinical decisions.

The scheduler's fictional carpal-tunnel case shows a **45-minute room slot**, including preparation and turnover in this demo; it is not a claim that the operation itself lasts 45 minutes. The [NHS describes the operation itself as around 20 minutes](https://www.nhs.uk/conditions/carpal-tunnel-syndrome/).

The medication entries shown in the nurse case are fictional entries adapted from the imported demo dataset. The patient's weight and complete clinical history are absent, so their amounts must not be interpreted as suitable doses for that patient. Official [propofol labeling](https://www.accessdata.fda.gov/drugsatfda_docs/label/2014/019627s062lbl.pdf) and [fentanyl product information](https://www.medicines.org.uk/emc/product/100051/smpc) emphasize individual dosing and additional caution in older adults.

## Screenshot capture

    python .\preview_tools\capture_linkedin.py
    python .\preview_tools\capture_linkedin.py --scale 1.5 --output linkedin_screenshots_small

The first command saves reviewed 4800 x 3000 PNGs in linkedin_screenshots. The second renders smaller 2400 x 1500 PNGs in a separate folder. Rendering at higher pixel density creates more detail than enlarging an existing PNG.
The capture script refreshes the fictional nurse, scheduler, and inventory fixtures immediately before rendering, then runs `validate_screenshot_cases.php`. Capture stops if the synthetic case fails checks for patient/procedure links, operative milestones and duration, checklist phase, medication timing, intermittent blood-pressure history, consent preceding the operation day, future scheduling, or inventory use. The browser checks visible codes, vital ranges, statuses, and inventory alerts before saving each image. These checks enforce the documented example's internal consistency; they do not establish clinical validity or guarantee that every untested field is realistic. Inspect every PNG before publishing. For a fresh anesthesia timeline, stop and restart the demo before the capture command.
For the anesthesia PNG only, the capture script shows NIBP as separate points because the underlying readings are intermittent; it leaves the application source unchanged. Restart immediately before capture to avoid a gap between the 20-minute seeded history and the live stream.

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

The patient portal intentionally shows a completed historical case from 2025. The nurse and scheduler screenshots show separate current, fictional cases, and the inventory has current-month usage. The SNOMED search uses a local demonstration catalogue, not a live terminology service; check each displayed concept and code against the official terminology before publication.

Stop the PHP server, MariaDB, and synthetic stream with:

    .\preview_tools\stop_demo.ps1

The original public repository previously contained credentials in config/config.php. The preview configuration removes the API key and accepts environment variables for database credentials. Rotate credentials exposed in the public repository or its Git history before using them elsewhere.
