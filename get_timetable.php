<?php
session_start();
include 'db_connect.php';
include 'check_admin.php';

$view_by = $_GET['view_by'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($view_by) || empty($id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres invalides.']);
    exit();
}

$query = "
    SELECT tt.timetable_id, tt.day_of_week, tt.start_time, tt.end_time, 
           c.class_name, g.group_name, m.module_name, t.first_name, t.last_name
    FROM timetable tt
    JOIN `groups` g ON tt.group_id = g.group_id
    JOIN classes c ON g.class_id = c.class_id
    JOIN modules m ON tt.module_id = m.module_id
    JOIN teachers t ON tt.teacher_id = t.teacher_id
";

// Adapter la requête en fonction du filtre
switch ($view_by) {
    case 'group':
        $query .= " WHERE tt.group_id = ?";
        break;
    case 'class':
        $query .= " WHERE g.class_id = ?";
        break;
    case 'teacher':
        $query .= " WHERE tt.teacher_id = ?";
        break;
    default:
        echo json_encode([]);
        exit();
}

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$timetable = [];
while ($row = $result->fetch_assoc()) {
    $row['start_time'] = date('H:i', strtotime($row['start_time']));
    $row['end_time'] = date('H:i', strtotime($row['end_time']));
    $timetable[] = $row;
}

header('Content-Type: application/json');
echo json_encode($timetable);
?>