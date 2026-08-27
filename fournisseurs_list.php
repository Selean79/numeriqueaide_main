<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';

$message = '';

// 1. УВЕДОМЛЕНИЯ
if (isset($_GET['updated'])) {
    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Успешно!</strong> Данные поставщика сохранены.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
}

// 2. ОБРАБОТКА УДАЛЕНИЯ ПОСТАВЩИКА
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM fournisseurs WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        Поставщик успешно удален!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Ошибка удаления: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// 3. ПОИСК И СОРТИРОВКА
$search = trim($_GET['search'] ?? '');
$sort_column = $_GET['sort'] ?? 'nom_magasin';
$allowed_columns = [
    'id' => 'id',
    'nom' => 'nom_magasin',
    'adresse' => 'adresse',
    'created_at' => 'created_at'
];

if (!array_key_exists($sort_column, $allowed_columns)) {
    $sort_column = 'nom';
}

$sort_order = strtoupper($_GET['order'] ?? 'ASC');
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'ASC';
}

function getSortUrl($col, $current_col, $current_order) {
    $queryParams = $_GET;
    $queryParams['sort'] = $col;
    $queryParams['order'] = ($col === $current_col && $current_order === 'ASC') ? 'DESC' : 'ASC';
    return basename($_SERVER['PHP_SELF']) . '?' . http_build_query($queryParams);
}

// 4. ДИНАМИЧЕСКИЙ SQL-ЗАПРОС
$conditions = [];
$params = [];

if (!empty($search)) {
    $searchParam = "%" . mb_strtolower($search, 'UTF-8') . "%";
    $conditions[] = "(LOWER(nom_magasin) LIKE :search1 OR LOWER(COALESCE(adresse, '')) LIKE :search2)";
    $params[':search1'] = $searchParam;
    $params[':search2'] = $searchParam;
}

$sql = "SELECT * FROM fournisseurs";
if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$db_sort_col = $allowed_columns[$sort_column];
$sql .= " ORDER BY {$db_sort_col} {$sort_order}";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $fournisseurs = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Ошибка выполнения запроса: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список поставщиков — NumériqueAide</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        .table-custom-sm th, 
        .table-custom-sm td {
            padding-top: 0.35rem !important;
            padding-bottom: 0.35rem !important;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Список поставщиков</h2>
        <a href="add_fournisseur.php" class="btn btn-success">
            <i class="bi bi-shop me-1"></i> Добавить магазин
        </a>
    </div>

    <?= $message; ?>

    <!-- Панель поиска -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_column); ?>">
                <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order); ?>">

                <div class="col-md-10">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Поиск по названию магазина или адресу..." 
                           value="<?= htmlspecialchars($search); ?>">
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i> Найти
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="<?= basename($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary" title="Сброс">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица поставщиков -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm table-custom-sm align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 80px;">
                                <a href="<?= getSortUrl('id', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    ID 
                                    <?php if ($sort_column === 'id'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('nom', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    Название магазина
                                    <?php if ($sort_column === 'nom'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('adresse', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    Адрес
                                    <?php if ($sort_column === 'adresse'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="text-end" style="width: 120px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($fournisseurs) > 0): ?>
                            <?php foreach ($fournisseurs as $f): ?>
                                <tr>
                                    <td><strong><?= $f['id']; ?></strong></td>
                                    <td><strong><?= htmlspecialchars($f['nom_magasin']); ?></strong></td>
                                    <td><?= !empty($f['adresse']) ? htmlspecialchars($f['adresse']) : '<span class="text-muted">—</span>'; ?></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="edit_fournisseur.php?id=<?= $f['id']; ?>" class="btn btn-outline-primary" title="Изменить">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?= basename($_SERVER['PHP_SELF']); ?>?delete_id=<?= $f['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               title="Удалить"
                                               onclick="return confirm('Вы уверены, что хотите удалить магазин «<?= htmlspecialchars($f['nom_magasin']); ?>»?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Поставщики не найдены.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
      setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      }, 3000);
    });
  });
</script>
</body>
</html>
