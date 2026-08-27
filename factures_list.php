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
                    <strong>Успешно!</strong> Данные счета обновлены.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
}

// 2. ОБРАБОТКА УДАЛЕНИЯ СЧЕТА
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM factures WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        Счет успешно удален!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Ошибка удаления счета: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// 3. ФИЛЬТРЫ, ПОИСК И СОРТИРОВКА
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['filter_status'] ?? 'all';

$sort_column = $_GET['sort'] ?? 'issue_date';
$allowed_columns = [
    'id' => 'f.id',
    'number' => 'f.facture_number',
    'date' => 'f.issue_date',
    'due_date' => 'f.due_date',
    'payment_date' => 'f.payment_date',
    'client' => 'c.nom',
    'montant' => 'f.total_amount',
    'status' => 'f.status'
];

if (!array_key_exists($sort_column, $allowed_columns)) {
    $sort_column = 'date';
}

$sort_order = strtoupper($_GET['order'] ?? 'DESC');
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'DESC';
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
    $conditions[] = "(LOWER(f.facture_number) LIKE :search1 
                     OR LOWER(c.nom) LIKE :search2 
                     OR LOWER(COALESCE(c.prenom, '')) LIKE :search3)";
    $params[':search1'] = $searchParam;
    $params[':search2'] = $searchParam;
    $params[':search3'] = $searchParam;
}

if ($filter_status !== 'all') {
    $conditions[] = "f.status = :status";
    $params[':status'] = $filter_status;
}

$sql = "SELECT f.*, 
               CONCAT_WS(' ', c.nom, c.prenom) AS client_nom 
        FROM factures f 
        LEFT JOIN clients c ON f.client_id = c.id";

if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$db_sort_col = $allowed_columns[$sort_column];
$sql .= " ORDER BY {$db_sort_col} {$sort_order}";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $factures = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Ошибка выполнения запроса: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список счетов — NumériqueAide</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        .table-custom-sm th, 
        .table-custom-sm td {
            padding-top: 0.35rem !important;
            padding-bottom: 0.35rem !important;
            font-size: 0.88rem;
        }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid px-4 mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Список счетов</h2>
        <a href="add_facture.php" class="btn btn-success">
            <i class="bi bi-file-earmark-plus me-1"></i> Выставить счет
        </a>
    </div>

    <?= $message; ?>

    <!-- Панель поиска и фильтров -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_column); ?>">
                <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order); ?>">

                <div class="col-md-7">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Поиск по номеру счета или имени клиента..." 
                           value="<?= htmlspecialchars($search); ?>">
                </div>

                <div class="col-md-3">
                    <select name="filter_status" class="form-select">
                        <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>Все статусы</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Ожидает оплаты</option>
                        <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>Оплачен</option>
                        <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Отменен</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i> Найти
                    </button>
                    <?php if (!empty($search) || $filter_status !== 'all'): ?>
                        <a href="<?= basename($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary" title="Сброс">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица счетов -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm table-custom-sm align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>
                                <a href="<?= getSortUrl('number', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    № Счета
                                    <?php if ($sort_column === 'number'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('client', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    Клиент
                                    <?php if ($sort_column === 'client'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('date', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    Дата выст.
                                    <?php if ($sort_column === 'date'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('due_date', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    Срок оплаты
                                    <?php if ($sort_column === 'due_date'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('payment_date', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    Дата оплаты
                                    <?php if ($sort_column === 'payment_date'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('montant', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    Сумма
                                    <?php if ($sort_column === 'montant'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Статус</th>
                            <th class="text-end">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($factures) > 0): ?>
                            <?php foreach ($factures as $f): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($f['facture_number'] ?? '#'.$f['id']); ?></strong></td>
                                    <td><?= htmlspecialchars($f['client_nom'] ?? 'Не указан'); ?></td>
                                    <td><?= !empty($f['issue_date']) ? date('d.m.Y', strtotime($f['issue_date'])) : '—'; ?></td>
                                    <td><?= !empty($f['due_date']) ? date('d.m.Y', strtotime($f['due_date'])) : '—'; ?></td>
                                    <td>
                                        <?php if (!empty($f['payment_date'])): ?>
                                            <span class="text-success fw-bold"><?= date('d.m.Y', strtotime($f['payment_date'])); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= number_format($f['total_amount'] ?? 0, 2, ',', ' '); ?> €</strong></td>
                                    <td>
                                        <?php 
                                            $st = strtolower($f['status'] ?? '');
                                            if ($st === 'payee' || $st === 'paid') {
                                                echo '<span class="badge bg-success">Оплачен</span>';
                                            } elseif ($st === 'en_attente' || $st === 'pending') {
                                                echo '<span class="badge bg-warning text-dark">Ожидает оплаты</span>';
                                            } elseif ($st === 'annulee' || $st === 'cancelled') {
                                                echo '<span class="badge bg-danger">Отменен</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">' . htmlspecialchars($f['status'] ?? '—') . '</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="edit_facture.php?id=<?= $f['id']; ?>" class="btn btn-outline-primary" title="Изменить">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if (!empty($f['pdf_path'])): ?>
                                                <a href="<?= htmlspecialchars($f['pdf_path']); ?>" class="btn btn-outline-dark" title="Скачать PDF" target="_blank">
                                                    <i class="bi bi-file-pdf"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="facture_pdf.php?id=<?= $f['id']; ?>" class="btn btn-outline-dark" title="Сгенерировать PDF" target="_blank">
                                                    <i class="bi bi-file-pdf"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?= basename($_SERVER['PHP_SELF']); ?>?delete_id=<?= $f['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               title="Удалить"
                                               onclick="return confirm('Вы уверены, что хотите удалить счет № <?= htmlspecialchars($f['facture_number']); ?>?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Счета не найдены.</td>
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
