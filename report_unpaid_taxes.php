<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$export = $_GET['export'] ?? '';
$currentFilename = basename($_SERVER['PHP_SELF']); // Определяем имя текущего файла отчета автоматически

// Динамическое определение колонок платформ
$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : (in_array('name', $platCols) ? 'name' : $platCols[1]);

// Выборка оплаченных заказов с неуплаченными налогами / накоплениями
$sql = "
    SELECT 
        c.*,
        CONCAT(COALESCE(cl.nom, ''), ' ', COALESCE(cl.prenom, '')) AS client_name,
        p.`$platColName` AS platform_name
    FROM commandes c
    LEFT JOIN clients cl ON c.client_id = cl.id
    LEFT JOIN platforms p ON c.platform_id = p.id
    WHERE (LOWER(TRIM(c.statut)) = 'payé' OR LOWER(TRIM(c.statut)) = 'paye' OR LOWER(TRIM(c.statut)) = 'terminee' OR LOWER(TRIM(c.statut)) = 'завершен')
      AND (
          (c.calcul_impot > 0 AND (c.impot_paye = 0 OR c.impot_paye IS NULL))
          OR 
          (c.calcul_epargne > 0 AND (c.epargne_paye = 0 OR c.epargne_paye IS NULL))
      )
    ORDER BY c.date_commande DESC, c.id_commande DESC
";

$stmt = $pdo->query($sql);
$orders = $stmt->fetchAll();

// Расчет итоговых сумм к уплате и общей суммы к оплате
$total_pending_impot = 0;
$total_pending_epargne = 0;

foreach ($orders as $ord) {
    $m = (float)($ord['montant'] ?? 0);

    // Если налог должен рассчитываться, но еще НЕ уплачен
    $impVal = (float)($ord['calcul_impot'] ?? 0);
    $impAmount = ($impVal == 1) ? ($m * 0.212) : $impVal;
    if ($impAmount > 0 && empty($ord['impot_paye'])) {
        $total_pending_impot += $impAmount;
    }

    // Если накопления должны рассчитываться, но еще НЕ переведены
    $epVal = (float)($ord['calcul_epargne'] ?? 0);
    $epAmount = ($epVal == 1) ? ($m * 0.10) : $epVal;
    if ($epAmount > 0 && empty($ord['epargne_paye'])) {
        $total_pending_epargne += $epAmount;
    }
}

// Общая сумма к оплате (налог + отчисления)
$total_combined_payment = $total_pending_impot + $total_pending_epargne;

// Экспорт в Excel / CSV
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="unpaid_taxes_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");

    fputcsv($output, ['ID Заказа', 'Дата', 'Клиент', 'Платформа', 'Сумма (€)', 'Налог URSSAF (€)', 'Статус Налога', 'Накопления (€)', 'Статус Накоплений'], ';');

    foreach ($orders as $ord) {
        $m = (float)($ord['montant'] ?? 0);

        $impVal = (float)($ord['calcul_impot'] ?? 0);
        $impAmount = ($impVal == 1) ? ($m * 0.212) : $impVal;

        $epVal = (float)($ord['calcul_epargne'] ?? 0);
        $epAmount = ($epVal == 1) ? ($m * 0.10) : $epVal;

        fputcsv($output, [
                $ord['id_commande'],
                $ord['date_commande'],
                trim($ord['client_name']),
                $ord['platform_name'] ?? 'Privé',
                number_format($m, 2, '.', ''),
                number_format($impAmount, 2, '.', ''),
                !empty($ord['impot_paye']) ? 'Оплачен' : 'К уплате',
                number_format($epAmount, 2, '.', ''),
                !empty($ord['epargne_paye']) ? 'Отчислено' : 'К отчислению'
        ], ';');
    }

    fputcsv($output, ['ИТОГО', '', '', '', '', number_format($total_pending_impot, 2, '.', ''), '', number_format($total_pending_epargne, 2, '.', ''), ''], ';');

    fclose($output);
    exit;
}

require_once 'header.php';
?>

<title>Контроль уплаты налогов — NumériqueAide</title>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> К отчетам</a>
            <h3 class="mb-0"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Заказы с неоплаченными налогами / накоплениями</h3>
        </div>
        <div>
            <a href="?export=csv" class="btn btn-outline-success me-2">
                <i class="bi bi-file-earmark-excel me-1"></i> Скачать в Excel
            </a>
            <button onclick="window.print();" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Печать
            </button>
        </div>
    </div>

    <!-- Карточки итогов -->
    <div class="row g-3 mb-4">
        <!-- 1. Требуют обработки -->
        <div class="col-md-3">
            <div class="card border-0 bg-danger text-white shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="fw-semibold text-white mb-1" style="font-size: 0.95rem;">Требуют обработки (заказов)</div>
                    <div class="fs-2 fw-bold"><?= count($orders); ?></div>
                </div>
            </div>
        </div>
        <!-- 2. Общая сумма к оплате (налог + отчисления) -->
        <div class="col-md-3">
            <div class="card border-0 bg-success text-white shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="fw-semibold text-white mb-1" style="font-size: 0.95rem;">Общая сумма к оплате</div>
                    <div class="fs-2 fw-bold"><?= number_format($total_combined_payment, 2, ',', ' '); ?> €</div>
                </div>
            </div>
        </div>
        <!-- 3. Итого к уплате URSSAF -->
        <div class="col-md-3">
            <div class="card border-0 bg-warning text-dark shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">Итого к уплате URSSAF (21.2%)</div>
                    <div class="fs-2 fw-bold text-dark"><?= number_format($total_pending_impot, 2, ',', ' '); ?> €</div>
                </div>
            </div>
        </div>
        <!-- 4. Итого к отчислению накоплений -->
        <div class="col-md-3">
            <div class="card border-0 bg-info text-white shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="fw-semibold text-white mb-1" style="font-size: 0.95rem;">Итого к отчислению (10%)</div>
                    <div class="fs-2 fw-bold"><?= number_format($total_pending_epargne, 2, ',', ' '); ?> €</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Таблица -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                    <tr>
                        <th style="width: 90px;">ID Заказа</th>
                        <th style="width: 110px;">Дата</th>
                        <th>Клиент</th>
                        <th>Платформа</th>
                        <th class="text-end">Сумма заказа</th>
                        <th class="text-center">Налог URSSAF</th>
                        <th class="text-center">Отчисления</th>
                        <th style="width: 100px;" class="text-center">Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-success fw-bold">
                                <i class="bi bi-check-circle-fill me-1"></i> Все налоги и накопления по оплаченным заказам уплачены!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $ord): ?>
                            <?php
                            $m = (float)($ord['montant'] ?? 0);

                            $impVal = (float)($ord['calcul_impot'] ?? 0);
                            $impAmount = ($impVal == 1) ? ($m * 0.212) : $impVal;

                            $epVal = (float)($ord['calcul_epargne'] ?? 0);
                            $epAmount = ($epVal == 1) ? ($m * 0.10) : $epVal;

                            $platformName = trim($ord['platform_name'] ?? 'Privé');
                            $platBadgeClass = 'bg-danger';
                            if (strcasecmp($platformName, 'Yoojo') === 0) {
                                $platBadgeClass = 'bg-primary';
                            } elseif (strcasecmp($platformName, 'NeedHelp') === 0) {
                                $platBadgeClass = 'bg-success';
                            }
                            ?>
                            <tr>
                                <td class="fw-bold">#<?= htmlspecialchars($ord['id_commande']); ?></td>
                                <td><?= date('d.m.Y', strtotime($ord['date_commande'])); ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars(trim($ord['client_name']) ?: '—'); ?></td>
                                <td><span class="badge <?= $platBadgeClass; ?>"><?= htmlspecialchars($platformName); ?></span></td>
                                <td class="text-end fw-bold"><?= number_format($m, 2, ',', ' '); ?> €</td>

                                <!-- Налог -->
                                <td class="text-center">
                                    <?php if ($impAmount > 0): ?>
                                        <?php if (!empty($ord['impot_paye'])): ?>
                                            <span class="badge bg-success" title="Оплачен"><i class="bi bi-check-all me-1"></i><?= number_format($impAmount, 2, ',', ' '); ?> €</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger p-2"><i class="bi bi-clock me-1"></i><?= number_format($impAmount, 2, ',', ' '); ?> € (Не уплачен)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">0,00 €</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Накопления -->
                                <td class="text-center">
                                    <?php if ($epAmount > 0): ?>
                                        <?php if (!empty($ord['epargne_paye'])): ?>
                                            <span class="badge bg-success" title="Отчислено"><i class="bi bi-check-all me-1"></i><?= number_format($epAmount, 2, ',', ' '); ?> €</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark p-2"><i class="bi bi-clock me-1"></i><?= number_format($epAmount, 2, ',', ' '); ?> € (Не отчислено)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">0,00 €</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Редактировать (теперь с возвратом на этот же отчет) -->
                                <td class="text-center">
                                    <a href="edit_commande.php?id=<?= $ord['id']; ?>&return=<?= $currentFilename; ?>" class="btn btn-sm btn-outline-primary" title="Отметить уплату">
                                        <i class="bi bi-pencil"></i>
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