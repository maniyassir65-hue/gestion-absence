<?php
// Affiche les erreurs pour faciliter le débogage. À retirer en production.
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db_connect.php';

// Vérifie si l'utilisateur est un administrateur connecté
if (!isset($_SESSION['teacher_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// --- LOGIQUE DE FILTRAGE ---
$class_filter = $_GET['class_id'] ?? null;
$group_filter = $_GET['group_id'] ?? null;
$search_name = $_GET['student_name'] ?? '';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// --- ÉTAPE 1 : Récupérer les données d'absence en appliquant les filtres de date ---
$absences_sql = "
    SELECT 
        s.student_id,
        m.module_name,
        COUNT(a.attendance_id) AS absence_count
    FROM students s
    JOIN attendance a ON s.student_id = a.student_id AND a.status = 'Absent'
    JOIN timetable tt ON a.timetable_id = tt.timetable_id
    JOIN modules m ON tt.module_id = m.module_id
";

$absences_where_clauses = [];
$absences_params = [];
$absences_param_types = '';

// Ajout du filtre par date à la requête des absences
if (!empty($start_date) && !empty($end_date)) {
    $absences_where_clauses[] = "a.attendance_date BETWEEN ? AND ?";
    $absences_params[] = $start_date;
    $absences_params[] = $end_date;
    $absences_param_types .= 'ss';
}

if (!empty($absences_where_clauses)) {
    $absences_sql .= " WHERE " . implode(' AND ', $absences_where_clauses);
}

$absences_sql .= " GROUP BY s.student_id, m.module_id";

$absences_stmt = $conn->prepare($absences_sql);
if ($absences_stmt === false) { die("Erreur de préparation de la requête des absences: " . $conn->error); }
if (!empty($absences_params)) { $absences_stmt->bind_param($absences_param_types, ...$absences_params); }
$absences_stmt->execute();
$absences_query = $absences_stmt->get_result();

$absences_data = [];
while ($row = $absences_query->fetch_assoc()) {
    $absences_data[$row['student_id']][] = [
        'name' => $row['module_name'],
        'absences' => $row['absence_count']
    ];
}

// --- ÉTAPE 2 : Récupérer la liste des étudiants en appliquant les filtres de nom, filière et groupe ---
$students_sql = "
    SELECT 
        s.student_id, s.first_name, s.last_name,
        c.class_name, g.group_name
    FROM students s
    JOIN `groups` g ON s.group_id = g.group_id
    JOIN classes c ON g.class_id = c.class_id
";

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($class_filter)) {
    $where_clauses[] = "c.class_id = ?";
    $params[] = $class_filter;
    $param_types .= 'i';
}
if (!empty($group_filter)) {
    $where_clauses[] = "g.group_id = ?";
    $params[] = $group_filter;
    $param_types .= 'i';
}
if (!empty($search_name)) {
    $where_clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ?)";
    $search_term = "%" . $search_name . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'ss';
}

if (!empty($where_clauses)) {
    $students_sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$students_sql .= " ORDER BY s.last_name, s.first_name";

$stmt = $conn->prepare($students_sql);
if ($stmt === false) { die("Erreur de préparation de la requête des étudiants: " . $conn->error); }
if (!empty($params)) { $stmt->bind_param($param_types, ...$params); }
$stmt->execute();
$students_result = $stmt->get_result();

// --- ÉTAPE 3 : Assembler les données pour l'affichage ---
$students_report = [];
while ($student = $students_result->fetch_assoc()) {
    $student_id = $student['student_id'];
    // On utilise les données d'absences pré-filtrées par date
    $student_absences = $absences_data[$student_id] ?? [];
    
    $total_absences = 0;
    $modules_with_penalty = [];
    foreach ($student_absences as $absence_module) {
        $total_absences += $absence_module['absences'];
        $penalty = $absence_module['absences'] * 0.25;
        $modules_with_penalty[] = [
            'name' => $absence_module['name'],
            'absences' => $absence_module['absences'],
            'penalty' => $penalty
        ];
    }

    $students_report[$student_id] = [
        'info' => [
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'class_name' => $student['class_name'],
            'group_name' => $student['group_name']
        ],
        'total_absences' => $total_absences,
        'modules' => $modules_with_penalty
    ];
}

// Requêtes pour peupler les menus déroulants des filtres
$classes = $conn->query("SELECT class_id, class_name FROM classes ORDER BY class_name");
$groups = $conn->query("SELECT group_id, group_name, class_id FROM `groups` ORDER BY group_name");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport d'Absences - EMG</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* --- CHARTE GRAPHIQUE EMG --- */
        :root {
            --emg-blue: #00529b;
            --emg-yellow: #ffd100;
            --danger-color: #dc3545;
            --text-light: #ffffff;
            --background-light: #f8fafc;
            --border-color: #e5e7eb;
            --text-dark: #1f2937;
            --text-medium: #4b5563;
        }

        /* --- Styles Généraux --- */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--background-light);
            color: var(--text-dark);
        }

        /* --- En-tête de page (Style Admin) --- */
        .emg-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 40px; background-color: var(--emg-blue); color: var(--text-light);
        }
        .emg-header .logo img { height: 40px; }
        .user-info { display: flex; align-items: center; gap: 16px; }
        .btn-logout {
            display: inline-block; padding: 8px 16px; background-color: var(--text-light);
            color: var(--emg-blue); text-decoration: none; border-radius: 8px; font-weight: 600;
            transition: background-color 0.2s, color 0.2s;
        }
        .btn-logout:hover { background-color: var(--emg-yellow); color: var(--text-dark); }
        
        /* --- Conteneur & En-tête de contenu --- */
        .container { padding: 32px 40px; max-width: 1400px; margin: 0 auto; }
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { font-size: 28px; font-weight: 700; }
        
        /* --- Filtres --- */
        .report-filters { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; background-color: #fff; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 14px; }
        .filter-group select, .filter-group input, .filter-group button { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 16px; }
        .filter-group button { background-color: var(--emg-blue); color: white; border: none; cursor: pointer; font-weight: 600; }

        /* --- Liste Accordéon --- */
        .report-container { background-color: #fff; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .report-header, .student-row { display: grid; grid-template-columns: 50px 3fr 2fr 1fr 50px; align-items: center; padding: 16px 24px; }
        .report-header { background-color: #f9fafb; font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-medium); border-bottom: 1px solid var(--border-color); }
        .student-row { border-top: 1px solid var(--border-color); cursor: pointer; transition: background-color 0.2s; }
        .student-row:hover { background-color: #f9fafb; }
        .avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background-color: var(--emg-blue); color: var(--text-light);
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 0.9rem; flex-shrink: 0;
        }
        .total-absences { text-align: center; font-weight: 500; }
        .total-absences.high { color: var(--danger-color); font-weight: 700; }
        .toggle-icon { text-align: right; color: var(--text-medium); }

        /* --- Panneau de Détails --- */
        .student-details-panel { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; background-color: #f9fafb; }
        .student-details-panel.is-open { max-height: 500px; transition: max-height 0.4s ease-in; }
        .details-content { padding: 24px 48px; border-top: 1px solid var(--border-color); }
        .details-content table { width: 100%; border-collapse: collapse; }
        .details-content th, .details-content td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .details-content th { font-weight: 600; color: var(--text-medium); }
        .penalty { color: var(--danger-color); font-weight: 500; }
    </style>
</head>
<body>
    <header class="emg-header">
        <div class="logo"><img src="assets/logo-emg.png" alt="Logo EMG"></div>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['teacher_name']); ?></span>
            <a href="dashboard.php" class="btn-logout">Retour</a>
        </div>
    </header>

    <main class="container">
        <div class="page-header"><h1>Rapport d'Absences Global</h1></div>

        <form class="report-filters" method="GET" action="">
            <div class="filter-group">
                <label for="student_name">Rechercher par Nom</label>
                <input type="text" name="student_name" id="student_name" placeholder="Nom ou prénom..." value="<?php echo htmlspecialchars($search_name); ?>">
            </div>
            <div class="filter-group">
                <label for="class_id">Filtrer par Filière</label>
                <select name="class_id" id="class_id">
                    <option value="">Toutes les filières</option>
                    <?php while($class = $classes->fetch_assoc()): ?>
                        <option value="<?php echo $class['class_id']; ?>" <?php echo ($class_filter == $class['class_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($class['class_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="group_id">Filtrer par Groupe</label>
                <select name="group_id" id="group_id">
                    <option value="">Tous les groupes</option>
                     <?php $groups->data_seek(0); // Réinitialiser le pointeur pour la deuxième boucle ?>
                     <?php while($group = $groups->fetch_assoc()): ?>
                        <option value="<?php echo $group['group_id']; ?>" data-class-id="<?php echo $group['class_id']; ?>" <?php echo ($group_filter == $group['group_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($group['group_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <!-- NOUVEAUX CHAMPS POUR LE FILTRE PAR DATE -->
            <div class="filter-group">
                <label for="start_date">Date de début :</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date ?? ''); ?>">
            </div>
            <div class="filter-group">
                <label for="end_date">Date de fin :</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">
            </div>

            <div class="filter-group" style="align-self: flex-end;"><button type="submit"><i class="fas fa-search"></i> Appliquer</button></div>
        </form>

        <div class="report-container">
            <div class="report-header">
                <span></span><span>Étudiant</span><span>Filière - Groupe</span><span style="text-align: center;">Total Absences</span><span class="toggle-icon"></span>
            </div>
            <?php if (!empty($students_report)): ?>
                <?php foreach ($students_report as $student_id => $student): ?>
                    <?php
                        // On affiche l'étudiant seulement s'il a des absences dans la période filtrée, ou si aucun filtre de date n'est appliqué
                        if ($student['total_absences'] > 0 || (empty($start_date) && empty($end_date))):
                            $initials = (isset($student['info']['first_name'][0]) ? $student['info']['first_name'][0] : '') . (isset($student['info']['last_name'][0]) ? $student['info']['last_name'][0] : '');
                    ?>
                    <div class="student-row">
                        <div class="avatar"><?php echo htmlspecialchars(strtoupper($initials)); ?></div>
                        <span class="student-name"><?php echo htmlspecialchars($student['info']['first_name'] . ' ' . $student['info']['last_name']); ?></span>
                        <span class="student-group"><?php echo htmlspecialchars($student['info']['class_name'] . ' - ' . $student['info']['group_name']); ?></span>
                        <span class="total-absences <?php echo ($student['total_absences'] >= 4) ? 'high' : ''; ?>"><?php echo $student['total_absences']; ?></span>
                        <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="student-details-panel">
                        <div class="details-content">
                            <?php if (!empty($student['modules'])): ?>
                                <table>
                                    <thead><tr><th>Module</th><th>Absences et Pénalités</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($student['modules'] as $module): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($module['name']); ?></td>
                                            <td><?php echo $module['absences']; ?> absence(s) <?php if ($module['penalty'] > 0): ?><span class="penalty">(-<?php echo number_format($module['penalty'], 2); ?> points)</span><?php endif; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p>Aucune absence enregistrée pour cet étudiant dans la période sélectionnée.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php 
                        endif; // Fin de la condition d'affichage
                    ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding: 24px; text-align: center;">Aucun étudiant ne correspond aux filtres sélectionnés.</div>
            <?php endif; ?>
        </div>
    </main>
    <script>
        // Le JavaScript est inchangé, il gère la dépendance des menus déroulants et l'accordéon.
        const classSelect = document.getElementById('class_id');
        const groupSelect = document.getElementById('group_id');
        const allGroupOptions = Array.from(groupSelect.options);
        classSelect.addEventListener('change', function() {
            const selectedClassId = this.value; 
            const currentGroupValue = groupSelect.value;
            groupSelect.innerHTML = '';
            allGroupOptions.forEach(option => { 
                if (option.value === "" || !selectedClassId || option.dataset.classId === selectedClassId) { 
                    groupSelect.add(option.cloneNode(true)); 
                } 
            });
            // Tenter de préserver la sélection du groupe si possible
            const newOptionExists = Array.from(groupSelect.options).some(opt => opt.value === currentGroupValue);
            if (newOptionExists) {
                groupSelect.value = currentGroupValue;
            }
        });
        // Déclencher l'événement au chargement pour initialiser l'état du filtre de groupe
        classSelect.dispatchEvent(new Event('change'));

        document.querySelectorAll('.student-row').forEach(row => { 
            row.addEventListener('click', () => { 
                const panel = row.nextElementSibling; 
                const icon = row.querySelector('.toggle-icon i'); 
                panel.classList.toggle('is-open'); 
                icon.classList.toggle('fa-chevron-down'); 
                icon.classList.toggle('fa-chevron-up'); 
            }); 
        });
    </script>
</body>
</html>