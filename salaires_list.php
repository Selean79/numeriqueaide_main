<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['type']) && $_SESSION['type'] === 'User') {
    header("Location: index.php?error=access_denied");
    exit;
}

// 1. Обработка удаления записи о зарплате (ДО хедера!)
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM salaires WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);

        header("Location: salaires_list.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// 2. Обработка сохранения (Добавление или Редактирование) (ДО хедера!)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_salaire']) || isset($_POST['edit_salaire']))) {
    $salaire_id     = isset($_POST['salaire_id']) ? (int)$_POST['salaire_id'] : 0;
    $user_id        = (int)($_POST['user_id'] ?? 0);
    $date_versement = trim($_POST['date_versement'] ?? ''); // Приходит в формате YYYY-MM-DD из <input type="date">
    $montant        = trim($_POST['montant'] ?? '');
    $commentaire    = trim($_POST['commentaire'] ?? '');

    if ($user_id > 0 && !empty($date_versement) && !empty($montant)) {
        try {
            if ($salaire_id > 0) {
                // Обновление существующей записи
                $stmt = $pdo->prepare("
                    UPDATE salaires 
                    SET user_id = :user_id, 
                        date_versement = :date_versement, 
                        montant = :montant, 
                        commentaire = :commentaire 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':user_id'        => $user_id,
                    ':date_versement' => $date_versement,
                    ':montant'        => str_replace(',', '.', $montant),
                    ':commentaire'    => $commentaire,
                    ':id'             => $salaire_id
                ]);
                header("Location: salaires_list.php?updated=1");
                exit;
            } else {
                // Добавление новой записи
                $stmt = $pdo->prepare("
                    INSERT INTO salaires (user_id, date_versement, montant, commentaire) 
                    VALUES (:user_id, :date_versement, :montant, :commentaire)
                ");
                $stmt->execute([
                    ':user_id'        => $user_id,
                    ':date_versement' => $date_versement,
                    ':montant'        => str_replace(',', '.', $montant),
                    ':commentaire'    => $commentaire
                ]);
                header("Location: salaires_list.php?added=1");
                exit;
            }
        } catch (PDOException $e) {
            $error_message = "Erreur d'enregistrement : " . $e->getMessage();
        }
    } else {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
    }
}

// Сброс фильтров
if (isset($_GET['clear_filter'])) {
    header("Location: salaires_list.php");
    exit;
}

// 3. Теперь подключаем хедер
require_once 'header.php';

// Загружаем список пользователей для выпадающего списка
try {
    $usersStmt = $pdo->query("SELECT id, username, nom, prenom FROM users ORDER BY nom ASC, prenom ASC");
    $users = $usersStmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

// Загружаем список выплат зарплат
try {
    $sql = "
        SELECT s.*, u.username, u.nom, u.prenom 
        FROM salaires s
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.date_versement DESC, s.id DESC
    ";
    $stmt = $pdo->query($sql);
    $salaires = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur de chargement : " . htmlspecialchars($e->getMessage()));
}
?>

<title>Gestion des salaires — NumériqueAide</title>

<style>
    body {
        background-color: #d3d1d1 !important;
    }
    .table-header-custom th {
        background-color: #82e89e !important;
        color: #020202 !important;
    }
</style>

<div class="container mt-4 mb-5" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Gestion des salaires</h3>
        <button type="button" class="btn btn-success" onclick="openAddModal()">
            <i class="bi bi-plus-circle me-1"></i> Ajouter un salaire
        </button>
    </div>

    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Salaire ajouté avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Salaire modifié avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Enregistrement supprimé avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Таблица зарплат -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-header-custom">
                        <tr>
                            <th style="width: 130px;" class="text-nowrap">Date</th>
                            <th>Utilisateur (Nom & Prénom)</th>
                            <th style="width: 150px;" class="text-end">Montant</th>
                            <th>Commentaire</th>
                            <th style="width: 120px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($salaires)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Aucun salaire enregistré</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($salaires as $item): 
                                // Конвертируем YYYY-MM-DD из базы в DD/MM/YYYY для отображения в таблице
                                $formatted_date = date('d/m/Y', strtotime($item['date_versement']));
                            ?>
                                <tr>
                                    <td class="fw-bold text-nowrap"><?= $formatted_date; ?></td>
                                    <td class="fw-semibold">
                                        <?= htmlspecialchars(trim(($item['nom'] ?? '') . ' ' . ($item['prenom'] ?? ''))); ?>
                                        <small class="text-muted">(<?= htmlspecialchars($item['username']); ?>)</small>
                                    </td>
                                    <td class="text-end fw-bold text-success"><?= number_format((float)$item['montant'], 2, ',', ' '); ?> €</td>
                                    <td><?= !empty($item['commentaire']) ? htmlspecialchars($item['commentaire']) : '<span class="text-muted">—</span>'; ?></td>
                                    <td class="text-center text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                                onclick="openEditModal(
                                                    '<?= (int)$item['id']; ?>', 
                                                    '<?= (int)$item['user_id']; ?>', 
                                                    '<?= htmlspecialchars($item['date_versement'], ENT_QUOTES); ?>', 
                                                    '<?= htmlspecialchars($item['montant'], ENT_QUOTES); ?>', 
                                                    '<?= htmlspecialchars($item['commentaire'], ENT_QUOTES); ?>'
                                                )" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="salaires_list.php?delete_id=<?= (int)$item['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet enregistrement ?');" title="Supprimer">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Универсальное модальное окно для добавления и редактирования -->
<div class="modal fade" id="salaireModal" tabindex="-1" aria-labelledby="salaireModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white" id="modalHeader">
                    <h5 class="modal-title" id="salaireModalLabel"><i class="bi bi-plus-circle me-1"></i> <span id="modalTitleText">Ajouter un salaire</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="edit_salaire" id="editSalaireFlag" value="1">
                    <input type="hidden" name="salaire_id" id="salaireId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Utilisateur <span class="text-danger">*</span></label>
                        <select name="user_id" id="userId" class="form-select" required>
                            <option value="">Sélectionner un utilisateur</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id']; ?>">
                                    <?= htmlspecialchars(trim(($u['nom'] ?? '') . ' ' . ($u['prenom'] ?? '')) . ' (' . $u['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date de versement <span class="text-danger">*</span></label>
                        <!-- Используем type="date" для удобного выбора через календарь браузера -->
                        <input type="date" name="date_versement" id="dateVersement" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Montant (€) <span class="text-danger">*</span></label>
                        <input type="text" name="montant" id="montant" class="form-control" placeholder="ex: 1500.00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Commentaire</label>
                        <textarea name="commentaire" id="commentaire" class="form-control" rows="3" placeholder="Précisions sur le versement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success" id="modalSubmitBtn">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const salaireModal = new bootstrap.Modal(document.getElementById('salaireModal'));

    function openAddModal() {
        document.getElementById('salaireId').value = '';
        document.getElementById('userId').value = '';
        
        // Устанавливаем текущую дату в формате YYYY-MM-DD для инпута типа date
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('dateVersement').value = today;

        document.getElementById('montant').value = '';
        document.getElementById('commentaire').value = '';

        document.getElementById('modalTitleText').innerText = 'Ajouter un salaire';
        document.getElementById('modalHeader').className = 'modal-header bg-success text-white';
        document.getElementById('modalSubmitBtn').className = 'btn btn-success';

        salaireModal.show();
    }

    function openEditModal(id, userId, dateVersement, montant, commentaire) {
        document.getElementById('salaireId').value = id;
        document.getElementById('userId').value = userId;
        document.getElementById('dateVersement').value = dateVersement; // Ожидает формат YYYY-MM-DD
        document.getElementById('montant').value = montant;
        document.getElementById('commentaire').value = commentaire;

        document.getElementById('modalTitleText').innerText = 'Modifier le salaire';
        document.getElementById('modalHeader').className = 'modal-header bg-primary text-white';
        document.getElementById('modalSubmitBtn').className = 'btn btn-primary';

        salaireModal.show();
    }

    setTimeout(function () {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function (alertElement) {
            const alert = new bootstrap.Alert(alertElement);
            alert.close();
        });
    }, 5000);
</script>
</body>
</html>