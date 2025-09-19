<?php
session_start();
include 'db_connect.php';

// Redirige si l'utilisateur n'est pas un enseignant connecté ou est un admin
if (!isset($_SESSION['teacher_id']) || $_SESSION['role'] === 'admin') {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// Récupère uniquement les dates distinctes où des enregistrements d'absence existent pour cet enseignant
$dates_query = $conn->prepare("
    SELECT DISTINCT a.attendance_date 
    FROM attendance a 
    JOIN timetable tt ON a.timetable_id = tt.timetable_id 
    WHERE tt.teacher_id = ? 
    ORDER BY a.attendance_date DESC
");
$dates_query->bind_param("i", $teacher_id);
$dates_query->execute();
$dates_result = $dates_query->get_result();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Corriger un Appel - EMG</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* --- CHARTE GRAPHIQUE EMG --- */
        :root {
            --emg-blue: #00529b;
            --emg-yellow: #ffd100;
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

        /* --- En-tête Unifié --- */
        .emg-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 40px; background-color: var(--emg-blue); color: var(--text-light);
        }
        .emg-header .logo img { height: 40px; }
        .emg-header .user-info { display: flex; align-items: center; gap: 16px; }
        .user-info span { font-weight: 500; }
        .btn-logout {
            display: inline-block; padding: 8px 16px; background-color: var(--text-light);
            color: var(--emg-blue); text-decoration: none; border-radius: 8px; font-weight: 600;
            transition: background-color 0.2s, color 0.2s;
        }
        .btn-logout:hover { background-color: var(--emg-yellow); color: var(--text-dark); }
        
        /* --- Boutons Génériques --- */
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; border: 2px solid transparent; transition: all 0.2s; cursor: pointer; }
        .btn-primary { background-color: var(--emg-blue); color: var(--text-light); }
        .btn-primary:hover { background-color: #00417a; }
        .btn-secondary { background-color: var(--text-light); color: var(--emg-blue); border-color: var(--emg-blue); }
        .btn-secondary:hover { background-color: var(--emg-blue); color: var(--text-light); }
        
        /* --- Conteneur & Carte de Correction --- */
        .page-container { max-width: 700px; margin: 3rem auto; padding: 0 1rem; }
        .correction-card {
            background-color: #ffffff; border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .correction-card-header {
            display: flex; align-items: center; gap: 1rem;
            padding: 1.5rem; border-bottom: 1px solid var(--border-color);
        }
        .correction-card-header i { font-size: 1.5rem; color: var(--emg-blue); }
        .correction-card-header h2 { font-size: 1.5rem; margin: 0; }
        .correction-card-body { padding: 1.5rem; }
        .correction-card-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem 1.5rem; background-color: #f9fafb;
            border-top: 1px solid var(--border-color);
        }

        /* --- Style des Formulaires --- */
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-control {
            width: 100%; padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px; font-size: 1rem;
        }
        .form-control:focus {
            outline: none; border-color: var(--emg-blue);
            box-shadow: 0 0 0 3px rgba(0, 82, 155, 0.1);
        }
    </style>
</head>
<body>
    <header class="emg-header">
        <div class="logo"><img src="assets/logo-emg.png" alt="Logo EMG"></div>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['teacher_name']); ?></span>
            <a href="logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </header>

    <main class="page-container">
        <div class="correction-card">
            <div class="correction-card-header">
                <i class="fas fa-edit"></i>
                <h2>Corriger un Appel Passé</h2>
            </div>
            <form action="edit_attendance.php" method="GET">
                <div class="correction-card-body">
                    <!-- Le champ input type="date" a été supprimé. -->
                    <div class="form-group">
                        <label for="attendance_date">Choisissez la date de la séance :</label>
                        <select id="attendance_date" name="date" class="form-control" required>
                            <option value="">-- Sélectionnez une date --</option>
                            <?php if ($dates_result->num_rows > 0): ?>
                                <?php while($date_row = $dates_result->fetch_assoc()): ?>
                                    <option value="<?php echo $date_row['attendance_date']; ?>">
                                        <?php echo date('d/m/Y', strtotime($date_row['attendance_date'])); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="" disabled>Aucun appel n'a été enregistré</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="correction-card-footer">
                     <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Annuler</a>
                     <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Rechercher</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>