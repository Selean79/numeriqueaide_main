<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Подключаем базу данных
require_once 'db.php';

// Сброс фильтра поиска
if (isset($_GET['clear_filter'])) {
    header("Location: clients_list.php");
    exit;
}

// 1. Сначала обрабатываем удаление клиента (до отправки любых HTML-данных и шапки!) ылорлва
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);

        // Перенаправление через JavaScript для полной защиты от ошибки headers already sent
        echo '<script>window.location.href = "clients_list.php?deleted=1";</script>';
        exit;
    } catch (PDOException $e) {
        $error_message = "Ошибка при удалении: " . $e->getMessage();
    }
}

// Получаем поисковый запрос
$search = trim($_GET['search'] ?? '');

// Безопасное извлечение только цифр без использования JIT-компилятора регулярных выражений
$search_clean = '';
for ($i = 0; $i < strlen($search); $i++) {
    if ($search[$i] >= '0' && $search[$i] <= '9') {
        $search_clean .= $search[$i];
    }
}

// Параметры сортировки
$allowed_sorts = [
        'id' => 'id',
        'nom' => 'nom',
        'societe' => 'societe',
        'telephone' => 'telephone',
        'email' => 'email',
        'adresse' => 'adresse'
];

// Установка сортировки по умолчанию на 'nom' (название/фамилию) вместо 'id'
$sort = $_GET['sort'] ?? 'nom';
if (!array_key_exists($sort, $allowed_sorts)) {
    $sort = 'nom';
}

$order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$next_order = ($order === 'ASC') ? 'desc' : 'asc';

// 2. Загружаем список клиентов из базы данных с учетом поиска и сортировки
try {
    $sql = "SELECT * FROM clients WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (
            nom LIKE :search 
            OR prenom LIKE :search 
            OR adresse LIKE :search 
            OR email LIKE :search";

        if (!empty($search_clean)) {
            $sql .= " OR REPLACE(REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '+', ''), '33', ''), '-', '') LIKE :search_clean";
            $params[':search_clean'] = "%$search_clean%";
        }

        $sql .= ")";
        $params[':search'] = "%$search%";
    }

    $sql .= " ORDER BY " . $allowed_sorts[$sort] . " " . $order;
    if ($sort !== 'id') {
        $sql .= ", id DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Ошибка загрузки клиентов: " . htmlspecialchars($e->getMessage()));
}

// Функция для генерации ссылки сортировки
function sortLink($column, $label, $current_sort, $current_order, $search) {
    global $next_order;
    $new_order = ($current_sort === $column) ? $next_order : 'asc';
    $icon = '';
    if ($current_sort === $column) {
        $icon = ($current_order === 'ASC') ? ' <i class="bi bi-sort-up small"></i>' : ' <i class="bi bi-sort-down small"></i>';
    }
    $query_params = ['sort' => $column, 'order' => $new_order];
    if (!empty($search)) {
        $query_params['search'] = $search;
    }
    $url = 'clients_list.php?' . http_build_query($query_params);
    return '<a href="' . $url . '" class="text-decoration-none text-dark d-block">' . $label . $icon . '</a>';
}

// 3. Только теперь подключаем HTML-шапку
require_once 'header.php';
?>

<title>Liste des clients — NumériqueAide</title>

<style>
    /* Кастомный цвет шапки таблицы */
    .table-header-custom th {
        background-color: #82e89e !important;
        color: #020202 !important;
    }
    body {
        background-color: #d3d1d1 !important;
    }
</style>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-people me-2"></i>Liste des clients</h3>
        <a href="add_client.php" class="btn btn-success">
            <i class="bi bi-person-plus me-1"></i> Ajouter un client
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Le client a été supprimé avec succès.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Блок поиска -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <?php if (!empty($sort)): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort); ?>">
                <?php endif; ?>
                <?php if (!empty($order)): ?>
                    <input type="hidden" name="order" value="<?= htmlspecialchars($order); ?>">
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Recherche</label>
                    <input type="text" name="search" class="form-control" placeholder="nom, téléphone, adresse, email..." value="<?= htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary flex-grow-1" title="Trouver">
                        <i class="bi bi-search me-1"></i> Trouver
                    </button>
                    <a href="clients_list.php?clear_filter=1" class="btn btn-outline-secondary" title="Сбросить фильтр">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 1000px;">
                    <thead class="table-header-custom">
                    <tr>
                        <th style="width: 60px;" class="text-center"><?= sortLink('id', '#', $sort, $order, $search); ?></th>
                        <th style="width: 220px;"><?= sortLink('nom', 'Nom', $sort, $order, $search); ?></th>
                        <th style="width: 130px;"><?= sortLink('societe', 'Type', $sort, $order, $search); ?></th>
                        <th style="width: 170px;" class="text-nowrap"><?= sortLink('telephone', 'Téléphone', $sort, $order, $search); ?></th>
                        <th style="width: 200px;"><?= sortLink('email', 'Email', $sort, $order, $search); ?></th>
                        <th><?= sortLink('adresse', 'Adresse', $sort, $order, $search); ?></th>
                        <th>Notes</th>
                        <th style="width: 100px;" class="text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Aucun client n'a encore été ajouté</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td class="text-center fw-bold text-secondary"><?= $client['id']; ?></td>

                                <td class="fw-bold">
                                    <a href="edit_client.php?id=<?= (int)$client['id']; ?>" class="text-decoration-none text-dark" title="Modifier le client">
                                        <?= htmlspecialchars(trim(($client['nom'] ?? '') . ' ' . ($client['prenom'] ?? ''))); ?>
                                    </a>
                                </td>

                                <td>
                                    <?php if (!empty($client['societe'])): ?>
                                        <span class="badge bg-primary">Entreprise</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Particulier</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-nowrap fw-semibold text-primary">
                                    <?php if (!empty($client['telephone'])): ?>
                                        <?= str_replace(' ', '&nbsp;', htmlspecialchars($client['telephone'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (!empty($client['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($client['email']); ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($client['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= !empty($client['adresse']) ? htmlspecialchars($client['adresse']) : '<span class="text-muted">—</span>'; ?></td>

                                <td><small class="text-muted"><?= !empty($client['notes']) ? htmlspecialchars($client['notes']) : '—'; ?></small></td>

                                <td class="text-center text-nowrap">
                                    <a href="edit_client.php?id=<?= $client['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="clients_list.php?delete_id=<?= $client['id']; ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce client?');"
                                       title="Supprimer">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    /* Автоматическое закрытие уведомлений через 5 секунд */
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