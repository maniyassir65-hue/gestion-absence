<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['teacher_id']) || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: index.php");
    exit();
}

$session_id = (int)$_POST['session_id'];
$statuses = $_POST['status']; // C'est maintenant un tableau à 2 dimensions
$attendance_date = date('Y-m-d');

if (empty($session_id) || empty($statuses)) {
    header("Location: dashboard.php?error=missing_data");
    exit();
}

mysqli_begin_transaction($conn);

try {
    // La requête inclut maintenant la nouvelle colonne 'attendance_period'
    $stmt = $conn->prepare("
        INSERT INTO attendance (student_id, timetable_id, attendance_date, status, attendance_period) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status)
    ");
    
    // Boucle sur chaque étudiant
    foreach ($statuses as $student_id => $periods) {
        // Boucle sur chaque période (1 et 2) pour cet étudiant
        foreach ($periods as $period => $status) {
            $student_id_int = (int)$student_id;
            $period_int = (int)$period;
            
            // "ii" pour les entiers, "ssi" pour les chaînes... maintenant "iissi"
            $stmt->bind_param("iissi", $student_id_int, $session_id, $attendance_date, $status, $period_int);
            $stmt->execute();
        }
    }
    
    mysqli_commit($conn);
    header("Location: dashboard.php?success=attendance_saved");

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Erreur enregistrement appel: " . $e->getMessage());
    header("Location: teacher_view.php?session_id=$session_id&error=database");
}

$stmt->close();
$conn->close();
exit();
?>