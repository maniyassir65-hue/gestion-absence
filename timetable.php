<?php
session_start();
include 'db_connect.php';
// include 'check_admin.php';

if (!isset($_SESSION['teacher_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Récupérer les données pour les filtres et la modale
$classes_result = mysqli_query($conn, "SELECT class_id, class_name FROM classes ORDER BY class_name");
$groups_result = mysqli_query($conn, "SELECT g.group_id, g.group_name, c.class_name FROM `groups` g JOIN classes c ON g.class_id = c.class_id ORDER BY c.class_name, g.group_name");
$teachers_result = mysqli_query($conn, "SELECT teacher_id, first_name, last_name FROM teachers WHERE role != 'admin' ORDER BY last_name");
$modules_result = mysqli_query($conn, "SELECT * FROM modules ORDER BY module_name");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Emploi du Temps - EMG</title>
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
        .page-container { max-width: 1400px; margin: 2rem auto; padding: 0 1rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h1 { font-size: 28px; font-weight: 700; }

        /* --- Boutons Génériques --- */
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; border: 2px solid transparent; transition: all 0.2s; cursor: pointer; }
        .btn-primary { background-color: var(--emg-blue); color: var(--text-light); }
        .btn-primary:hover { background-color: #00417a; }
        .btn-secondary { background-color: var(--text-light); color: var(--emg-blue); border-color: var(--emg-blue); }
        .btn-secondary:hover { background-color: var(--emg-blue); color: var(--text-light); }
        
        /* --- Filtres --- */
        .selection-filters { display: flex; align-items: flex-end; gap: 1rem; margin-bottom: 2rem; background-color: #fff; padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border-color); }
        .filter-options { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
        .filter-options input[type="radio"] { display: none; }
        .filter-options label {
            padding: 0.5rem 1rem; border-radius: 8px; border: 2px solid var(--border-color);
            font-weight: 500; cursor: pointer; transition: all 0.2s;
        }
        .filter-options input[type="radio"]:checked + label {
            background-color: var(--emg-blue); color: var(--text-light); border-color: var(--emg-blue);
        }

        /* --- Grille de l'Emploi du Temps --- */
        .timetable-container { display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; }
        .day-column { background-color: #fff; border-radius: 12px; padding: 1rem; border: 1px solid var(--border-color); }
        .day-column h3 { text-align: center; margin-bottom: 1rem; font-size: 1rem; text-transform: uppercase; color: var(--emg-blue); }
        .session-card {
            background-color: #f9fafb; border-left: 5px solid var(--emg-blue);
            padding: 0.75rem; border-radius: 8px; margin-bottom: 0.75rem;
        }
        .session-time { font-weight: 600; font-size: 0.9rem; }
        .session-module { font-weight: 500; margin: 0.25rem 0; }
        .session-details, .session-teacher { font-size: 0.8rem; color: var(--text-medium); }
        .no-session { text-align: center; color: var(--text-medium); font-style: italic; padding: 2rem 0; }
        .table-placeholder { grid-column: 1 / -1; display: flex; justify-content: center; align-items: center; flex-direction: column; gap: 1rem; min-height: 200px; color: var(--text-medium); font-style: italic; }

        /* --- Style des Modales --- */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 10% auto; padding: 0; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: fadeIn 0.3s; }
        @keyframes fadeIn { from {opacity: 0; transform: translateY(-20px);} to {opacity: 1; transform: translateY(0);} }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); }
        .modal-header h2 { font-size: 1.25rem; }
        .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: var(--text-dark); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); }
        
        /* --- Style des Formulaires --- */
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; }
        .form-control:focus { outline: none; border-color: var(--emg-blue); box-shadow: 0 0 0 3px rgba(0, 82, 155, 0.1); }
    </style>
</head>
<body>
    <!-- Header de la page (Style EMG) -->
    <header class="emg-header">
        <div class="logo"><img src="assets/logo-emg.png" alt="Logo EMG"></div>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['teacher_name']); ?></span>
            <a href="dashboard.php" class="btn-logout">Retour</a>
        </div>
    </header>

    <main class="page-container">
        <div class="page-header">
            <h1>Gestion de l'Emploi du Temps</h1>
            <button id="open-add-modal-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter une Séance</button>
        </div>

        <div class="selection-filters">
            <div class="form-group">
                <label>Afficher l'emploi du temps par :</label>
                <div class="filter-options">
                    <input type="radio" id="view_by_class" name="view_by" value="class" checked><label for="view_by_class">Filière</label>
                    <input type="radio" id="view_by_group" name="view_by" value="group"><label for="view_by_group">Groupe</label>
                    <input type="radio" id="view_by_teacher" name="view_by" value="teacher"><label for="view_by_teacher">Professeur</label>
                </div>
            </div>
            <div class="form-group" style="flex: 2;">
                <label for="filter_id_select">Sélectionnez :</label>
                <select id="filter_id_select" class="form-control"></select>
            </div>
        </div>

        <div id="timetable-grid" class="timetable-container">
            <div id="timetable-placeholder" class="table-placeholder">
                <i class="fas fa-mouse-pointer" style="font-size: 2rem;"></i>
                <p>Veuillez sélectionner un filtre pour afficher un emploi du temps.</p>
            </div>
        </div>
    </main>

    <!-- Modale d'ajout -->
    <div id="addSessionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Ajouter une Séance</h2><span class="close-btn" data-modal="addSessionModal">&times;</span></div>
            <form action="add_timetable.php" method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Groupe :</label>
                        <select name="group_id" class="form-control" required>
                            <option value="">-- Choisissez un groupe --</option>
                            <?php mysqli_data_seek($groups_result, 0); while($group = mysqli_fetch_assoc($groups_result)): ?>
                                <option value="<?php echo $group['group_id']; ?>"><?php echo htmlspecialchars($group['class_name'] . ' - ' . $group['group_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Module :</label><select name="module_id" class="form-control" required>
                        <option value="">-- Choisissez un module --</option>
                        <?php mysqli_data_seek($modules_result, 0); while($module = mysqli_fetch_assoc($modules_result)): ?><option value="<?php echo $module['module_id']; ?>"><?php echo htmlspecialchars($module['module_name']); ?></option><?php endwhile; ?>
                    </select></div>
                    <div class="form-group"><label>Professeur :</label><select name="teacher_id" class="form-control" required>
                        <option value="">-- Choisissez un professeur --</option>
                        <?php mysqli_data_seek($teachers_result, 0); while($teacher = mysqli_fetch_assoc($teachers_result)): ?><option value="<?php echo $teacher['teacher_id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></option><?php endwhile; ?>
                    </select></div>
                    <div class="form-group"><label>Jour :</label><select name="day_of_week" class="form-control" required>
                        <option value="Monday">Lundi</option><option value="Tuesday">Mardi</option><option value="Wednesday">Mercredi</option><option value="Thursday">Jeudi</option><option value="Friday">Vendredi</option><option value="Saturday">Samedi</option>
                    </select></div>
                    <div style="display: flex; gap: 1rem;">
                        <div class="form-group" style="flex:1;"><label>Début :</label><input type="time" name="start_time" class="form-control" required></div>
                        <div class="form-group" style="flex:1;"><label>Fin :</label><input type="time" name="end_time" class="form-control" required></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal="addSessionModal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter la séance</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Le JS est légèrement modifié pour correspondre au nouveau formulaire d'ajout
    const filterData = {
        class: [ <?php mysqli_data_seek($classes_result, 0); while($row = mysqli_fetch_assoc($classes_result)) { echo '{id:'.$row['class_id'].', name:"'.htmlspecialchars($row['class_name'], ENT_QUOTES).'"},'; } ?> ],
        group: [ <?php mysqli_data_seek($groups_result, 0); while($row = mysqli_fetch_assoc($groups_result)) { echo '{id:'.$row['group_id'].', name:"'.htmlspecialchars($row['class_name'].' - '.$row['group_name'], ENT_QUOTES).'"},'; } ?> ],
        teacher: [ <?php mysqli_data_seek($teachers_result, 0); while($row = mysqli_fetch_assoc($teachers_result)) { echo '{id:'.$row['teacher_id'].', name:"'.htmlspecialchars($row['first_name'].' '.$row['last_name'], ENT_QUOTES).'"},'; } ?> ]
    };
    
    const viewByRadios = document.querySelectorAll('input[name="view_by"]');
    const filterSelect = document.getElementById('filter_id_select');
    const gridContainer = document.getElementById('timetable-grid');
    const placeholder = document.getElementById('timetable-placeholder');
    
    function updateFilterSelect() {
        const selectedView = document.querySelector('input[name="view_by"]:checked').value;
        filterSelect.innerHTML = '<option value="">-- Choisissez une option --</option>';
        filterData[selectedView].forEach(item => { filterSelect.innerHTML += `<option value="${item.id}">${item.name}</option>`; });
        gridContainer.innerHTML = placeholder.outerHTML;
    }

    function renderTimetable(data) {
        gridContainer.innerHTML = '';
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const dayNames = {Monday:'Lundi', Tuesday:'Mardi', Wednesday:'Mercredi', Thursday:'Jeudi', Friday:'Vendredi', Saturday:'Samedi'};
        days.forEach(day => {
            const dayColumn = document.createElement('div');
            dayColumn.className = 'day-column';
            dayColumn.innerHTML = `<h3>${dayNames[day]}</h3>`;
            const sessions = data.filter(s => s.day_of_week === day).sort((a,b) => a.start_time.localeCompare(b.start_time));
            if(sessions.length > 0) {
                sessions.forEach(session => {
                    dayColumn.innerHTML += `
                        <div class="session-card">
                            <div class="session-time">${session.start_time.substring(0,5)} - ${session.end_time.substring(0,5)}</div>
                            <div class="session-module">${escapeHTML(session.module_name)}</div>
                            <div class="session-details">${escapeHTML(session.group_name)}</div>
                            <div class="session-teacher"><i class="fas fa-chalkboard-teacher"></i> ${escapeHTML(session.first_name)} ${escapeHTML(session.last_name)}</div>
                        </div>`;
                });
            } else { dayColumn.innerHTML += '<div class="no-session">Aucune séance</div>'; }
            gridContainer.appendChild(dayColumn);
        });
    }

    viewByRadios.forEach(radio => radio.addEventListener('change', updateFilterSelect));
    
    filterSelect.addEventListener('change', function() {
        const viewBy = document.querySelector('input[name="view_by"]:checked').value;
        const id = this.value;
        if (!id) { gridContainer.innerHTML = placeholder.outerHTML; return; }
        gridContainer.innerHTML = '<div class="table-placeholder"><p>Chargement...</p></div>';
        fetch(`get_timetable.php?view_by=${viewBy}&id=${id}`)
            .then(response => response.json())
            .then(data => renderTimetable(data))
            .catch(error => { console.error('Erreur:', error); gridContainer.innerHTML = '<div class="table-placeholder"><p>Erreur de chargement.</p></div>'; });
    });
    
    updateFilterSelect();
    
    const addModal = document.getElementById('addSessionModal');
    const openAddBtn = document.getElementById('open-add-modal-btn');
    const closeBtns = document.querySelectorAll('.close-btn, .btn-secondary');
    openAddBtn.onclick = () => addModal.style.display = 'block';
    closeBtns.forEach(btn => btn.onclick = () => { document.getElementById(btn.dataset.modal).style.display = 'none'; });
    window.onclick = (e) => { if (e.target == addModal) { addModal.style.display = 'none'; } };

    function escapeHTML(str) {
        var p = document.createElement("p");
        p.appendChild(document.createTextNode(str || ""));
        return p.innerHTML;
    }
    </script>
</body>
</html>