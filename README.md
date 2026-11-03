# 🏥 Surgery Management System (SURGERY-APP)

![Status](https://img.shields.io/badge/Status-Development-orange)
![Language](https://img.shields.io/badge/Language-PHP-blue)
![Database](https://img.shields.io/badge/Database-MySQL-yellow)

## 📋 About The Project
**SURGERY-APP** is a comprehensive web-based hospital management system designed to streamline the surgical workflow. It facilitates communication and data management between all key stakeholders in the operating room ecosystem.

From scheduling surgeries to real-time intraoperative monitoring and post-op reporting, this application serves as a central hub for hospital staff and patients.

---

## 🏗️ Project Configuration & Setup

To ensure the repository remains consistent and easy to run, we have configured specific Git rules.

### Repository Rules
* **`.gitattributes`**: Ensures that binary files and line endings (Windows vs. macOS/Linux) are handled correctly, preventing formatting errors across different operating systems.
* **`.gitignore`**: Filters out unnecessary system files and local build artifacts to keep the repository clean.

> **⚠️ Note on Credentials:** While standard practice is to hide configuration files, this project contains synthetic (dummy) database credentials and API keys for demonstration purposes. These are safe to view and are included to make the setup process easier.

---

## 👥 User Roles & Key Features

This system is divided into specific portals for each role in the surgical process:

### 👨‍⚕️ Surgeon
* **Dashboard:** Overview of upcoming surgeries and patient list.
* **Operative Reporting:** Create and save detailed operative reports (`api_save_op_report.php`).
* **Live Monitoring:** Access real-time data during procedures (`surgery_live.php`).
* **Scheduling:** Manage OR calendar and request surgeries.

### 👩‍⚕️ Nurse
* **Surgery Cockpit:** Manage the flow of the operation room.
* **Vitals Tracking:** Record and monitor patient vitals (`api_patient_vitals.php`).
* **Pre-Op Checks:** Digital checklists to ensure patient readiness.
* **Nursing Reports:** Generate automated nursing documentation.

### 💉 Anesthesiologist
* **Live Cockpit:** Real-time anesthesia monitoring interface.
* **Patient Charts:** detailed history and medication tracking.
* **Reporting:** specialized anesthesia reporting tools (`anesthesia_report.php`).

### 💼 Director
* **Financials:** Track hospital revenue and surgery costs (`financial_details.php`).
* **Inventory:** Monitor stock levels of surgical supplies.
* **Analytics:** View statistics on procedure types, personnel performance, and surgery teams.

### 🛠️ Admin
* **System Management:** Advanced scheduler and staff availability management.
* **Request Handling:** Approve or reschedule surgery requests (`api_handle_request.php`).
* **Patient Database:** Full access to manage patient records.

### 🤒 Patient
* **Portal:** View upcoming surgery details and clinical data.
* **Consents:** Digital signing of consent forms (`api_sign_consent.php`).
* **Communication:** Submit concerns or questions directly to the medical team.

---

## 🚀 Getting Started

### Prerequisites
* A local server environment (XAMPP, WAMP, or MAMP).
* PHP 7.4 or higher.
* MySQL Database.

### Installation
1.  **Clone the repo:**
    ```bash
    git clone [https://github.com/your-username/surgery-app.git](https://github.com/your-username/surgery-app.git)
    ```
2.  **Database Setup:**
    * Open your database management tool (e.g., phpMyAdmin).
    * Create a new database (e.g., `imtm2025_grupd`).
    * Import the provided SQL file: `database/imtm2025_grupd.sql`.
3.  **Configuration:**
    * Navigate to `config/config.php` and ensure the database credentials match your local setup.
4.  **Run:**
    * Start Apache and MySQL.
    * Open your browser and navigate to `http://localhost/surgery-app/index.php`.

---

## 📂 Project Structure

```text
SURGERY-APP/
├── config/             # Database connection & global settings
├── controllers/        # Auth logic (Login/Logout)
├── database/           # SQL dump files
├── views/              # Frontend views separated by role
│   ├── admin/          # Admin interfaces
│   ├── anesthesiologist/
│   ├── director/       # Management dashboards
│   ├── nurse/          # Nursing tools
│   ├── patient/        # Patient portal
│   └── surgeon/        # Surgeon tools
├── index.php           # Entry point
└── login.php           # Authentication page

---

## Local demo and screenshots

For a reproducible Windows setup with mock data, a streaming anesthesiologist cockpit, six demo roles, and LinkedIn PNG capture, see [preview_tools/README.md](preview_tools/README.md). The anesthesia feed is synthetic and runs only against a localhost demo database.
