<?php


// Если функции нет, объявляем её здесь, чтобы избежать ошибки
if (!function_exists('renderPlatformBadge')) {
    function renderPlatformBadge($name)
    {
        return htmlspecialchars($name);
    }
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';

$message = '';

// 1. УВЕДОМЛЕНИЯ
if (isset($_GET['updated'])) {
    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Успешно!</strong> Данные платформы сохранены.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
}

// 2. ОБРАБОТКА УДАЛЕНИЯ ПЛАТФОРМЫ
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM platforms WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        Платформа успешно удалена!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Ошибка удаления: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// 3. ПОИСК И СОРТИРОВКА
$search = trim($_GET['search'] ?? '');
$sort_column = $_GET['sort'] ?? 'name';
$allowed_columns = [
    'id' => 'id',
    'name' => 'name',
    'impot' => 'default_impot_rate',
    'epargne' => 'default_epargne_rate'
];

if (!array_key_exists($sort_column, $allowed_columns)) {
    $sort_column = 'name';
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
    $conditions[] = "LOWER(name) LIKE :search";
    $params[':search'] = "%" . mb_strtolower($search, 'UTF-8') . "%";
}

$sql = "SELECT * FROM platforms";
if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$db_sort_col = $allowed_columns[$sort_column];
$sql .= " ORDER BY {$db_sort_col} {$sort_order}";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $platforms = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Ошибка выполнения запроса: " . htmlspecialchars($e->getMessage()));
}
?>

<title>Список платформ — NumériqueAide</title>

<div class="container mt-4 mb-5" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-diagram-3 me-2"></i>Список платформ</h2>
        <a href="add_platform.php" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i> Добавить платформу
        </a>
    </div>

    <?= $message; ?>

    <!-- Поиск -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_column); ?>">
                <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order); ?>">

                <div class="col-md-9">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Поиск по названию платформы..." 
                           value="<?= htmlspecialchars($search); ?>">
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Найти
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="<?= basename($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary" title="Сбросить">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица платформ -->
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
                                <a href="<?= getSortUrl('name', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    Название платформы
                                    <?php if ($sort_column === 'name'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('impot', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    Ставка налога (URSSAF)
                                    <?php if ($sort_column === 'impot'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortUrl('epargne', $sort_column, $sort_order); ?>" class="text-white text-decoration-none">
                                    Ставка отчислений
                                    <?php if ($sort_column === 'epargne'): ?>
                                        <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="text-end" style="width: 120px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($platforms) > 0): ?>
                            <?php foreach ($platforms as $p): ?>
                                <tr>
                                    <td><strong><?= $p['id']; ?></strong></td>
                                    <td><?= renderPlatformBadge($p['name']); ?></td>
                                    <td><span class="badge bg-warning text-dark"><?= number_format($p['default_impot_rate'] ?? 0, 2, ',', ' '); ?> %</span></td>
                                    <td><span class="badge bg-info text-white"><?= number_format($p['default_epargne_rate'] ?? 0, 2, ',', ' '); ?> %</span></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="edit_platform.php?id=<?= $p['id']; ?>" class="btn btn-outline-primary" title="Изменить">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?= basename($_SERVER['PHP_SELF']); ?>?delete_id=<?= $p['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               title="Удалить"
                                               onclick="return confirm('Вы уверены, что хотите удалить платформу «<?= htmlspecialchars($p['name']); ?>»?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Платформы не найдены.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
