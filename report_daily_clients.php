<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$report_date = $_GET['report_date'] ?? date('Y-m-d');
$export      = $_GET['export'] ?? '';

// Динамическое определение колонки названия платформы
$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : (in_array('name', $platCols) ? 'name' : $platCols[1]);

$sql = "
    SELECT 
        c.id_commande,
        c.date_commande,
        c.commentaire,
        c.notes,
        cl.nom,
        cl.prenom,
        cl.telephone,
        cl.adresse,
        p.`$platColName` AS platform_name
    FROM commandes c
    INNER JOIN clients cl ON c.client_id = cl.id
    LEFT JOIN platforms p ON c.platform_id = p.id
    WHERE c.date_commande = :report_date
    ORDER BY c.id_commande ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':report_date' => $report_date]);
$daily_orders = $stmt->fetchAll();

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders_' . $report_date . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");

    fputcsv($output, ['ID', 'Клиент', 'Телефон', 'Платформа', 'Адрес', 'Описание работ / Заметки'], ';');

    foreach ($daily_orders as $row) {
        $clientName = trim(($row['nom'] ?? '') . ' ' . ($row['prenom'] ?? ''));
        $description = implode(' | ', array_filter([$row['commentaire'], $row['notes']]));

        fputcsv($output, [
            '#' . $row['id_commande'],
            $clientName ?: '—',
            $row['telephone'] ?: '—',
            $row['platform_name'] ?: 'Privé',
            $row['adresse'] ?: '—',
            $description ?: '—'
        ], ';');
    }

    fclose($output);
    exit;
}

require_once 'header.php';
?>

<title>Заказы на <?= date('d.m.Y', strtotime($report_date)); ?> — NumériqueAide</title>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> К отчетам</a>
            <h3 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Заказы на текущий день или выбранный день</h3>
        </div>
        <div>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-outline-success me-2">
                <i class="bi bi-file-earmark-excel me-1"></i> Скачать в Excel
            </a>
            <button onclick="window.print();" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Печать
            </button>
        </div>
    </div>

    <!-- Селектор даты -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Выберите дату</label>
                    <input type="date" name="report_date" class="form-control" value="<?= htmlspecialchars($report_date); ?>" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-calendar-check me-1"></i> Показать на эту дату
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="report_daily_clients.php" class="btn btn-outline-secondary w-100">Сегодня</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица результатов -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
            <span class="fw-bold fs-6">
                <i class="bi bi-calendar3 me-2"></i>Список объектов на <?= date('d.m.Y', strtotime($report_date)); ?>
            </span>
            <span class="badge bg-primary fs-6">Заказов: <?= count($daily_orders); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                    <tr>
                        <th class="text-nowrap" style="width: 70px;">ID</th>
                        <th class="text-nowrap" style="width: 180px;">Клиент</th>
                        <th class="text-nowrap" style="width: 160px;">Телефон</th>
                        <th class="text-nowrap" style="width: 130px;">Платформа</th>
                        <th class="text-nowrap" style="width: 320px;">Адрес</th>
                        <th>Описание работ / Заметки</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($daily_orders)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-info-circle me-1"></i> На выбранную дату заказы не запланированы.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daily_orders as $row): ?>
                            <?php
                            $clientName = trim(($row['nom'] ?? '') . ' ' . ($row['prenom'] ?? ''));

                            $platformName = trim($row['platform_name'] ?? 'Privé');
                            $platBadgeClass = 'bg-danger';
                            if (strcasecmp($platformName, 'Yoojo') === 0) {
                                $platBadgeClass = 'bg-primary';
                            } elseif (strcasecmp($platformName, 'NeedHelp') === 0) {
                                $platBadgeClass = 'bg-success';
                            }
                            ?>
                            <tr>
                                <td class="fw-bold text-nowrap">#<?= $row['id_commande']; ?></td>
                                <td class="fw-semibold text-nowrap"><?= !empty($clientName) ? htmlspecialchars($clientName) : '<span class="text-muted">—</span>'; ?></td>
                                <td class="text-nowrap">
                                    <?php if (!empty($row['telephone'])): ?>
                                        <a href="tel:<?= preg_replace('/[^\d+]/', '', $row['telephone']); ?>" class="text-decoration-none fw-semibold">
                                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['telephone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap"><span class="badge <?= $platBadgeClass; ?>"><?= htmlspecialchars($platformName); ?></span></td>
                                <td class="text-nowrap">
                                    <?php if (!empty($row['adresse'])): ?>
                                        <i class="bi bi-geo-alt text-danger me-1"></i><?= htmlspecialchars($row['adresse']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['commentaire'])): ?>
                                        <div class="fw-semibold text-dark"><i class="bi bi-tools me-1 text-primary"></i><?= htmlspecialchars($row['commentaire']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['notes'])): ?>
                                        <div class="small text-muted"><i class="bi bi-journal-text me-1"></i><?= htmlspecialchars($row['notes']); ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($row['commentaire']) && empty($row['notes'])): ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
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