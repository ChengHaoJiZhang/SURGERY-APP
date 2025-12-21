<?php
// controllers/LoginController.php

session_start();

// Include database configuration
require_once __DIR__ . '/../config/config.php';

class LoginController {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    public function login($username, $password) {
        try {
            $query = "SELECT u.user_id, u.username, u.pass, u.email, 
                             u.role_id, r.role_name
                      FROM USERS u
                      INNER JOIN ROLES r ON u.role_id = r.role_id
                      WHERE u.username = :username 
                        AND u.deleted_at IS NULL
                      LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Verify password using bcrypt
                if (password_verify($password, $user['pass'])) { 

                    // Create Session
                    $_SESSION['user_id']    = $user['user_id'];
                    $_SESSION['username']   = $user['username'];
                    $_SESSION['email']      = $user['email'];
                    $_SESSION['role_id']    = $user['role_id'];
                    $_SESSION['role_name']  = $user['role_name'];
                    $_SESSION['logged_in']  = true; // Basic login true, but maybe pending agreement
                    $_SESSION['login_time'] = date('Y-m-d H:i:s');

                    // Audit Log
                    $this->logUserAction('LOGIN_ATTEMPT', 'USERS', $user['user_id']);

                    // Redirect to Agreement Page instead of Dashboard directly
                    // for specific roles that require consent
                    $this->handleConsentRedirect($user['role_name']);
                    return true;
                }
            }

            // User not found or wrong password
            return false;

        } catch (PDOException $e) {
            error_log("Error in login: " . $e->getMessage());
            return false;
        }
    }

    private function handleConsentRedirect($role_name) {
        $role = strtolower($role_name);

        // 1. Roles that need specific data usage agreement
        $clinical_roles = ['surgeon', 'physician', 'nurse', 'anesthesiologist', 'doctor'];
        
        // 2. Patient Roles
        $patient_roles = ['patient'];

        // 3. Admin Roles (Added so they see the System Integrity Protocol message)
        $admin_roles = ['admin', 'administrator'];

        // Check if role needs agreement
        if (in_array($role, $clinical_roles) || 
            in_array($role, $patient_roles) || 
            in_array($role, $admin_roles)) {
            
            // Redirect to the agreement page to show the specific message/image
            header("Location: /views/data_agreement.php");
            exit();
        } 
        
        // For Director or others not listed above, go straight to dashboard
        $this->redirectToDashboard($role_name);
    }

    // This function is now public so data_agreement.php can call it after acceptance
    public function redirectToDashboard($role_name) {
        $role = strtolower($role_name);

        // ---------------------------------------------------------
        // Surgeon Redirection
        // ---------------------------------------------------------
        if (strpos($role, 'surgeon') !== false || $role === 'physician') {
            header("Location: /views/surgeon/surgeon_dashboard.php");
        } 
        // ---------------------------------------------------------
        // EXISTING ROLES
        // ---------------------------------------------------------
        // Nurse
        elseif (strpos($role, 'nurse') !== false || $role === 'nurse') {
            header("Location: /views/nurse/nurse_dashboard.php");
        } 
        // Anesthesiologist 
        elseif (strpos($role, 'anesthesiologist') !== false) {
             header("Location: /views/anesthesiologist/anesthesiologist_dashboard.php"); 
        }
        // Patient
        elseif (strpos($role, 'patient') !== false || $role === 'patient') {
            header("Location: /views/patient/patient_dashboard.php");
        } 
        // Administrator
        elseif (strpos($role, 'administrator') !== false || $role === 'admin') {
            header("Location: /views/admin/admin_dashboard.php");
        } 
        // Director
        elseif (strpos($role, 'director') !== false || $role === 'director') {
            header("Location: /views/director/director_dashboard.php");
        } 
        // Fallback
        else {
            header("Location: /login.php");
        }
        exit();
    }

    public function logUserAction($action, $table, $record_id) {
        try {
            $query = "INSERT INTO AUDIT_LOG_PATIENTS (user_id, action, table_affected, record_id, timestamp)
                      VALUES (:user_id, :action, :table_affected, :record_id, NOW())";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':table_affected', $table);
            $stmt->bindParam(':record_id', $record_id);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error logging action: " . $e->getMessage());
        }
    }

    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logUserAction('LOGOUT', 'USERS', $_SESSION['user_id']);
        }
        
        session_unset();
        session_destroy();
        header("Location: /login.php?logout=1");
        exit();
    }

    public function checkAuth() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            header("Location: /login.php");
            exit();
        }
    }
}

// Process login form
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $loginController = new LoginController();
    
    if (!$loginController->login($username, $password)) {
        // Incorrect username or password
        header("Location: /login.php?error=1"); 
        exit();
    }
}

// Process Agreement Acceptance
if (isset($_POST['accept_agreement'])) {
    if (isset($_SESSION['role_name'])) {
        $loginController = new LoginController();
        // Log the acceptance
        $loginController->logUserAction('AGREEMENT_ACCEPTED', 'SYSTEM', $_SESSION['user_id']);
        // Final redirect
        $loginController->redirectToDashboard($_SESSION['role_name']);
    } else {
        header("Location: /login.php");
    }
}
?>