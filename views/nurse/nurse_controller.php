<?php
// /www/groupd/views/nurse/nurse_controller.php (Final Filter Fix)

require_once __DIR__ . '/../../config/config.php';

class NurseController {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Retrieves all surgeries that are currently 'Scheduled', 'In Progress', OR 'Completed' 
     * (as requested for post-op documentation access).
     */
    public function getScheduledSurgeriesByNurse($user_id) {
        if (!$user_id) {
            return [];
        }

        try {
            // FIX: Added sp.episode_id to the SELECT list so the dashboard link works
            $query = "
                SELECT
                    sp.surgical_procedure_id,
                    sp.date_time,
                    sp.episode_id,  -- << AÑADIDO: Necesario para el enlace 'View Episode'
                    p.procedure_name,
                    CONCAT(pat.first_name, ' ', pat.surnames) AS patient_name,
                    pat.patient_id,
                    oroom.room_number,
                    spn.role_in_surgery,
                    sp.status
                FROM SURGICAL_PROCEDURE sp
                INNER JOIN PROCEDURES p ON sp.procedure_id = p.procedure_id
                INNER JOIN EPISODE e ON sp.episode_id = e.episode_id
                INNER JOIN PATIENT pat ON e.patient_id = pat.patient_id
                LEFT JOIN OPERATING_ROOM oroom ON sp.operating_room_id = oroom.room_id 
                INNER JOIN SURGICAL_PROCEDURE_NURSES spn ON sp.surgical_procedure_id = spn.surgical_procedure_id
                INNER JOIN NURSES n ON spn.nurse_id = n.nurse_id
                WHERE n.user_id = :user_id
                  AND sp.status IN ('Scheduled', 'In Progress', 'Completed')
                ORDER BY sp.date_time DESC
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Error fetching nurse schedule: " . $e->getMessage());
            return [];
        }
    }
}