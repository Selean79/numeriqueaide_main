<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

// Функция оформления названия магазина в виде цветного бейджа
function renderMagasinBadge($magasinName) {
    $name = trim($magasinName ?? '');
    if (empty($name)) {
        return '<span class="text-muted">—</span>';
    }

    $badgeClass = 'bg-secondary';
    $lower = mb_strtolower($name);

    if (strpos($lower, 'leroy') !== false || strpos($lower, 'merlin') !== false) {
        $badgeClass = 'bg-success';
    } elseif (strpos($lower, 'castorama') !== false) {
        $badgeClass = 'bg-primary';
    } elseif (strpos($lower, 'brico') !== false) {
        $badgeClass = 'bg-warning text-dark';
    } elseif (strpos($lower, 'amazon') !== false) {
        $badgeClass = 'bg-dark';
    } elseif (strpos($lower, 'action') !== false) {
        $badgeClass = 'bg-info text-dark';
    }

    return '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($name) . '</span>';
}

// Удаление закупки
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        header("Location: purchases_list.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "Ошибка при удалении: " . $e->getMessage();
    }
}

// Поиск
$search = trim($_GET['search'] ?? '');

// Динамическое определение наименования колонки в таблице fournisseurs
$fournCols = $pdo->query("SHOW COLUMNS FROM fournisseurs")->fetchAll(PDO::FETCH_COLUMN);
$fournColName = in_array('nom', $fournCols) ? 'nom' : (in_array('name', $fournCols) ? 'name' : $fournCols[1]);
$fournNameCol = "f.`$fournColName`";

// Запрос списка закупок
$sql = "
    SELECT 
        p.*,
        $fournNameCol AS fournisseur_nom
    FROM purchases p
    LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (p.magasin LIKE :search OR p.remarques LIKE :search OR $fournNameCol LIKE :search)";
    $params[':search'] = "%$search%";
}

$sql .= " ORDER BY p.date_achat DESC, p.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $purchases = $stmt->fetchAll();

    // Подсчет общей суммы закупок
    $stmtTotal = $pdo->query("SELECT SUM(montant) FROM purchases");
    $totalAmount = (float)($stmtTotal->fetchColumn() ?? 0);
} catch (PDOException $e) {
    die("Ошибка загрузки закупок: " . htmlspecialchars($e->getMessage()));
}

require_once 'header.php';
?>

<title>Закупки материалов — NumériqueAide</title>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-bag-check me-2"></i>Закупки материалов (Purchases)</h3>
        <a href="add_purchase.php" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i> Добавить закупку
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Запись о закупке успешно удалена.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <!-- Поиск -->
        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <form method="GET" class="row g-2 w-100">
                        <div class="col-md-9">
                            <input type="text" name="search" class="form-control" placeholder="Поиск по магазину или примечанию (например: клей, розетки)..." value="<?= htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Найти</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Итого расходов -->
        <div class="col-md-4">
            <div class="card bg-secondary text-white shadow-sm h-100">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small">Итого расхода на закупки</div>
                        <div class="fs-3 fw-bold"><?= number_format($totalAmount, 2, ',', ' '); ?> €</div>
                    </div>
                    <i class="bi bi-cart3 fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Таблица закупок -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                    <tr>
                        <th style="width: 140px;">Date d'achat <i class="bi bi-arrow-down-short"></i></th>
                        <th style="width: 120px;" class="text-end">Montant</th>
                        <th style="width: 200px;">Magasin</th>
                        <th>Remarques (Примечания)</th>
                        <th style="width: 100px;" class="text-center">Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Закупки не найдены</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $item): ?>
                            <?php
                            $magasinDisplay = !empty($item['fournisseur_nom']) ? $item['fournisseur_nom'] : $item['magasin'];
                            ?>
                            <tr>
                                <!-- Дата -->
                                <td class="text-nowrap"><?= date('M d, Y', strtotime($item['date_achat'])); ?></td>

                                <!-- Сумма -->
                                <td class="text-end fw-bold"><?= number_format((float)$item['montant'], 2, ',', ' '); ?> €</td>

                                <!-- Магазин -->
                                <td><?= renderMagasinBadge($magasinDisplay); ?></td>

                                <!-- Примечания -->
                                <td><?= htmlspecialchars($item['remarques'] ?? '—'); ?></td>

                                <!-- Действия -->
                                <td class="text-center text-nowrap">
                                    <a href="edit_purchase.php?id=<?= $item['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Редактировать">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="purchases_list.php?delete_id=<?= $item['id']; ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Вы уверены, что хотите удалить эту закупку?');"
                                       title="Удалить">
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
</body>
</html>