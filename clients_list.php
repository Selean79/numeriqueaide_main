<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

/** @var PDO $pdo */
global $pdo;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Удаление клиента
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    try {
        $stmt =$pdo->prepare("DELETE FROM clients WHERE id = :id");
        $stmt->execute([':id' =>$delete_id]);

        header("Location: clients_list.php?deleted=1");
        exit;
    } catch (PDOException $e) {$error_message = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Сброс фильтров
|--------------------------------------------------------------------------
*/
if (isset($_GET['clear_filter'])) {
    unset($_SESSION['client_search']);
    header("Location: clients_list.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Поиск клиентов
|--------------------------------------------------------------------------
*/
if (isset($_GET['search'])) {
    $_SESSION['client_search'] = trim($_GET['search']);
}

$search =$_SESSION['client_search'] ?? '';

$sql = "SELECT * FROM clients WHERE 1=1";
$params = [];

if (!empty($search)) {$sql .= " AND (nom LIKE :search OR prenom LIKE :search OR telephone LIKE :search OR email LIKE :search OR adresse LIKE :search OR notes LIKE :search)";
    $params[':search'] = "\%$search%";
}

$sql .= " ORDER BY id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients =$stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur de chargement : " . htmlspecialchars($e->getMessage()));
}

require_once 'header.php';
?>

<title>Liste des clients — NumériqueAide</title>

<style>
    body { background-color: #d3d1d1 !important; }
    .table-header-custom th { 
        background-color: #82e89e !important; 
        color: #020202 !important; 
    }
    .table-header-custom th a { color: #020202 !important; }
    /* Винный цвет для бэйджа Société */
    .badge-wine {
        background-color: #722F37 !important;
        color: #ffffff !important;
    }
</style>

<div class="container-fluid mt-4 px-4" style="max-width: 1400px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-people me-2"></i>Liste des clients</h3>
        <button type="button" class="btn btn-success" onclick="openClientModal('add_client.php?modal=1', 'Ajouter un client', 'bg-success')">
            <i class="bi bi-person-plus me-1"></i> Ajouter un client
        </button>
    </div>

    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Client ajouté avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Client modifié avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Client supprimé avec succès !
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Поиск -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-10">
                    <label class="form-label small text-muted mb-1">Recherche</label>
                    <input type="text" name="search" class="form-control" placeholder="Nom, prénom, téléphone, email, notes..." value="<?= htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i> Trouver</button>
                    <a href="clients_list.php?clear_filter=1" class="btn btn-outline-secondary" title="Сбросить фильтр"><i class="bi bi-x-circle"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица клиентов -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-header-custom">
                        <tr>
                            <th style="width: 50px;" class="text-center">#</th>
                            <th>Nom & Prénom</th>
                            <th style="width: 120px;" class="text-center">Type</th>
                            <th style="width: 160px;" class="text-nowrap">Téléphone</th>
                            <th>Email</th>
                            <th>Adresse</th>
                            <th style="width: 80px;" class="text-center">Notes</th>
                            <th style="width: 100px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Aucun client trouvé</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clients as$client): ?>
                                <tr id="client-<?= (int)$client['id']; ?>">
                                    <td class="text-center fw-bold text-secondary"><?= $client['id']; ?></td>
                                    <td class="fw-bold">
                                        <?= htmlspecialchars(trim(($client['nom'] ?? '') . ' ' . ($client['prenom'] ?? ''))); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($client['societe'])): ?>
                                            <span class="badge badge-wine">Société</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Particulier</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php if (!empty($client['telephone'])): ?>
                                            <a href="tel:<?= htmlspecialchars($client['telephone']); ?>" class="text-decoration-none">
                                                <i class="bi bi-telephone text-primary me-1"></i><?= htmlspecialchars($client['telephone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($client['email'])): ?>
                                            <a href="mailto:<?= htmlspecialchars($client['email']); ?>" class="text-decoration-none">
                                                <i class="bi bi-envelope text-secondary me-1"></i><?= htmlspecialchars($client['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($client['adresse'])): ?>
                                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($client['adresse']); ?>" target="_blank" class="text-decoration-none text-muted">
                                                <i class="bi bi-geo-alt text-danger me-1"></i><?= htmlspecialchars($client['adresse']); ?>
                                            </a>
                                            <?php if (!empty($client['adresse_2'])): ?>
                                                <div class="small text-muted"><?= htmlspecialchars($client['adresse_2']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty(trim($client['notes'] ?? ''))): ?>
                                            <i class="bi bi-exclamation-circle-fill text-danger" style="cursor: pointer; font-size: 1.1rem;" title="<?= htmlspecialchars($client['notes']); ?>"></i>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="openClientModal('edit_client.php?id=<?= (int)$client['id']; ?>&modal=1', 'Modifier le client', 'bg-primary')" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="clients_list.php?delete_id=<?= (int)$client['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce client ?');" title="Supprimer">
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

<!-- Модальное окно для создания/редактирования клиента -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white" id="clientModalHeader">
                <h5 class="modal-title" id="clientModalLabel">
                    <i class="bi bi-person-plus me-1"></i> <span id="clientModalTitleText">Ajouter un client</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="clientModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const clientModal = new bootstrap.Modal(document.getElementById('clientModal'));

    function openClientModal(url, title, headerClass) {
        document.getElementById('clientModalTitleText').innerText = title;
        document.getElementById('clientModalHeader').className = 'modal-header ' + headerClass + ' text-white';
        
        document.getElementById('clientModalBody').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-success" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
            </div>
        `;
        
        clientModal.show();

        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const content = doc.querySelector('.container, .container-fluid, form') || doc.body;
                
                document.getElementById('clientModalBody').innerHTML = content.outerHTML;
                
                const modalForm = document.getElementById('clientModalBody').querySelector('form');
                if (modalForm) {
                    const cleanUrl = url.split('&modal=1')[0].replace('?modal=1', '');
                    modalForm.action = cleanUrl;
                }
            })
            .catch(error => {
                document.getElementById('clientModalBody').innerHTML = '<div class="alert alert-danger">Erreur de chargement du formulaire.</div>';
            });
    }

    // Универсальный обработчик маски телефона для модальных и обычных окон
    document.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'telephoneInput') {
            let input = e.target;
            let digits = input.value.replace(/\D/g, '');
            
            if (digits.startsWith('33')) {
                digits = digits.slice(2);
            } else if (digits.startsWith('0')) {
                digits = digits.slice(1);
            }
            
            digits = digits.slice(0, 9);

            let formatted = '+33';
            if (digits.length > 0) {
                formatted += ' ' + digits.substring(0, 1);
            }
            if (digits.length > 1) {
                formatted += ' ' + digits.substring(1, 3);
            }
            if (digits.length > 3) {
                formatted += ' ' + digits.substring(3, 5);
            }
            if (digits.length > 5) {
                formatted += ' ' + digits.substring(5, 7);
            }
            if (digits.length > 7) {
                formatted += ' ' + digits.substring(7, 9);
            }
            
            input.value = formatted;
        }
    });

    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.id === 'telephoneInput') {
            if (!e.target.value || e.target.value.trim() === '' || e.target.value.trim() === '+') {
                e.target.value = '+33 ';
            }
        }
    });

    document.addEventListener("DOMContentLoaded", function () {
        if (window.location.hash) {
            const targetElement = document.querySelector(window.location.hash);
            if (targetElement) {
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                targetElement.classList.add('table-active');
                setTimeout(() => {
                    targetElement.classList.remove('table-active');
                }, 2000);
            }
        }
    });

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