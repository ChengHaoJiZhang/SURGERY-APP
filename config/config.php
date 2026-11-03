<?php
/**
 * Sistema de Control de Gastos
 * Archivo de Configuración Principal
 * Incluye: Conexión DB, OpenAI API, Parámetros del Sistema
 */

// ===========================================
// CONFIGURACIÓN DE BASE DE DATOS
// ===========================================

// Configuración Local
define('DB_HOST_LOCAL', getenv('DB_HOST_LOCAL') ?: '127.0.0.1:3308');
define('DB_SOCKET_LOCAL', '/tmp/mysql8.sock');

// Configuración Remota  
define('DB_HOST_REMOTE', 'mysql.s816.sureserver.com');
define('DB_PORT_REMOTE', '330'); // CAMBIO DE 3308 A 3306

// Credenciales
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'preview_user');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'preview_local_only');
define('DB_NAME', getenv('DB_NAME') ?: 'imtm2025_grupd'); // CAMBIAR: era 'metales_flujo'

// Tipo de conexión: 'local' o 'remote'
define('DB_TYPE', 'local'); // CAMBIO DE LOCAL A REMOTO

// ===========================================
// CONFIGURACIÓN OPENAI
// ===========================================
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: ''); // Disabled for local screenshot preview
define('OPENAI_MODEL', 'gpt-3.5-turbo');
define('OPENAI_MAX_TOKENS', 500);

// ===========================================
// FUNCIÓN DE CONEXIÓN A BASE DE DATOS
// ===========================================
function getDBConnection() {
    try {
        $dsn = '';
        
        if (DB_TYPE === 'local') {
            // Conexión local usando socket
            $dsn = "mysql:host=" . DB_HOST_LOCAL . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        } else {
            // Conexión remota
            $dsn = "mysql:host=" . DB_HOST_REMOTE . ";port=" . DB_PORT_REMOTE . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        }
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Error de conexión DB: " . $e->getMessage());
        die("Error de conexión a la base de datos");
    }
}

// ===========================================
// FUNCIÓN OPENAI CHAT
// ===========================================
function chatWithOpenAI($message) {
    $apiKey = getSystemConfig('openai_api_key');
    $model = getSystemConfig('openai_model');
    
    if (!$apiKey || $apiKey === 'sk-tu_api_key_aqui') {
        return 'El chat inteligente no está configurado. Contacte al administrador.';
    }
    
    $url = 'https://api.openai.com/v1/chat/completions';
    
    // Prompt específico para finanzas personales
    $system_prompt = "Eres un asistente especializado en finanzas personales, temas financieros, contables y economía personal. Mantén las respuestas concisas y enfocadas en los temas de economía personal.";
    
    $data = [
        'model' => $model ?: 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $message]
        ],
        'max_tokens' => OPENAI_MAX_TOKENS,
        'temperature' => 0.7
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? 'No se pudo obtener respuesta';
    }
    
    return 'Error al conectar con el asistente inteligente';
}

// ===========================================
// FUNCIÓN PHPMAILER
// ===========================================
function sendEmail($to, $subject, $body, $isHTML = true) {
    // Cargar configuración de correo desde BD
    $smtpHost = getSystemConfig('smtp_host');
    $smtpPort = getSystemConfig('smtp_port');
    $smtpSecure = getSystemConfig('smtp_secure');
    $smtpUsername = getSystemConfig('smtp_username');
    $smtpPassword = getSystemConfig('smtp_password');
    $fromName = getSystemConfig('mail_from_name');
    $fromEmail = getSystemConfig('mail_from_email');
    
    if (!$smtpHost || !$smtpUsername || $smtpUsername === 'tucorreo@imtm2025.digital') {
        error_log('Configuración de correo no completada');
        return false;
    }
    
    try {
        // Para este ejemplo, usaremos mail() nativo de PHP
        $headers = "MIME-Version: 1.0" . "\r\n";
        if ($isHTML) {
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        }
        $headers .= "From: {$fromName} <{$fromEmail}>" . "\r\n";
        $headers .= "Reply-To: {$fromEmail}" . "\r\n";
        
        return mail($to, $subject, $body, $headers);
        
    } catch (Exception $e) {
        error_log("Error enviando email: " . $e->getMessage());
        return false;
    }
}

// ===========================================
// FUNCIONES AUXILIARES
// ===========================================

// Obtener configuración del sistema
function getSystemConfig($key) {
    $config = [
        'openai_api_key' => defined('OPENAI_API_KEY') ? OPENAI_API_KEY : null,
        'openai_model' => defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-3.5-turbo',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => '587',
        'smtp_secure' => 'tls',
        'smtp_username' => 'info@imtm2025.digital',
        'smtp_password' => 'jiba72490',
        'mail_from_name' => 'Hospital Les Corts',
        'mail_from_email' => 'info@imtm2025.digital',
    ];
    
    return isset($config[$key]) ? $config[$key] : null;
}

// Validar email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Formatear fecha para mostrar
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Formatear fecha y hora
function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

// Sanitizar entrada
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Verificar si directorio existe, si no, crearlo
function ensureDirectoryExists($path) {
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }
}

// Configuración de zona horaria
date_default_timezone_set('Europe/Madrid');

// NOTA: Las sesiones se manejan en config.php para evitar conflictos
?>
