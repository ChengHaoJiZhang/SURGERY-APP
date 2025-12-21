<?php
require_once '../includes/functions.php';
checkAuth();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['surgery_id']) || !isset($input['new_date'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare("UPDATE SURGERIES SET surgery_date = :new_date WHERE surgery_id = :surgery_id");
    $stmt->execute([
        ':new_date' => $input['new_date'],
        ':surgery_id' => $input['surgery_id']
    ]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
```

---

## 🧪 Prueba rápida:

**Abre directamente en tu navegador:**
```
http://tudominio.com/api/get_surgeries.php