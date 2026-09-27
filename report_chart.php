<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

// 1. Общее количество всех заказов
$stmtTotalCount = $pdo->query("SELECT COUNT(*) FROM commandes");
$totalCount = (int)$stmtTotalCount->fetchColumn();

// 2. Получаем данные по месяцам для Оплаченных заказов (сумма всех платежей)
$stmtPaiements = $pdo->query("
    SELECT 
        DATE_FORMAT(COALESCE(date_paiement, date_commande), '%Y-%m') AS ym,
        DATE_FORMAT(COALESCE(date_paiement, date_commande), '%b %Y') AS month_label,
        SUM(montant) AS total_paiements
    FROM commandes
    WHERE LOWER(TRIM(statut)) IN ('payé', 'paye', 'terminee', 'завершен', 'оплачен')
    GROUP BY ym, month_label
");
$paiements_by_month = [];
$labels_meta = [];
while ($row = $stmtPaiements->fetch()) {
    $paiements_by_month[$row['ym']] = (float)$row['total_paiements'];
    $labels_meta[$row['ym']] = $row['month_label'];
}

// 3. Получаем налоги по месяцам для Оплаченных заказов
$stmtImpots = $pdo->query("
    SELECT 
        DATE_FORMAT(COALESCE(date_paiement, date_commande), '%Y-%m') AS ym,
        SUM(
            CASE 
                WHEN calcul_impot = 1 THEN montant * 0.212 
                ELSE calcul_impot 
            END
        ) AS total_impot
    FROM commandes
    WHERE LOWER(TRIM(statut)) IN ('payé', 'paye', 'terminee', 'завершен', 'оплачен')
      AND calcul_impot > 0
    GROUP BY ym
");
$impots_by_month = [];
while ($row = $stmtImpots->fetch()) {
    $impots_by_month[$row['ym']] = (float)$row['total_impot'];
}

// 4. Получаем закупки материалов по месяцам
$stmtPurchases = $pdo->query("
    SELECT 
        DATE_FORMAT(date_achat, '%Y-%m') AS ym,
        DATE_FORMAT(date_achat, '%b %Y') AS month_label,
        SUM(montant) AS total_purchases
    FROM purchases
    GROUP BY ym, month_label
");
$purchases_by_month = [];
while ($row = $stmtPurchases->fetch()) {
    $purchases_by_month[$row['ym']] = (float)$row['total_purchases'];
    if (!isset($labels_meta[$row['ym']])) {
        $labels_meta[$row['ym']] = $row['month_label'];
    }
}

// Собираем все уникальные месяцы и сортируем по возрастанию
$all_yms = array_unique(array_merge(array_keys($paiements_by_month), array_keys($purchases_by_month), array_keys($impots_by_month)));
sort($all_yms);

$labels = [];
$data_paiements = [];
$data_net = [];

$en_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$fr_months = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];

foreach ($all_yms as $ym) {
    $paiements = $paiements_by_month[$ym] ?? 0;
    $impot     = $impots_by_month[$ym] ?? 0;
    $purchases = $purchases_by_month[$ym] ?? 0;

    // Чистый доход за месяц: платежи - налоги - закупки материалов
    $net_amount = $paiements - $impot - $purchases;

    // Метка месяца
    $raw_label = $labels_meta[$ym] ?? date('M Y', strtotime($ym . '-01'));
    $label = str_replace($en_months, $fr_months, $raw_label);

    $labels[] = $label;
    $data_paiements[] = $paiements;
    $data_net[]       = $net_amount;
}

// 5. Количество ОПЛАЧЕННЫХ заказов по платформам, с выбором месяца (месяц = дата оплаты)
$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : (in_array('name', $platCols) ? 'name' : $platCols[1]);

// Список месяцев, в которых есть заказы (для выпадающего списка)
$fr_months_full = [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$stmtMonths = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(COALESCE(c.date_paiement, c.date_commande), '%Y-%m') AS ym
    FROM commandes c
    WHERE LOWER(TRIM(c.statut)) IN ('payé', 'paye', 'terminee', 'завершен', 'оплачен')
      AND COALESCE(c.date_paiement, c.date_commande) IS NOT NULL
    ORDER BY ym DESC
");
$platform_months = [];
while ($row = $stmtMonths->fetch()) {
    [$y, $m] = explode('-', $row['ym']);
    $platform_months[$row['ym']] = $fr_months_full[(int)$m] . ' ' . $y;
}

// Выбранный месяц: YYYY-MM или пусто = за всё время
$pf_month = $_GET['pf_month'] ?? '';
if (!preg_match('/^\d{4}-\d{2}$/', $pf_month)) {
    $pf_month = '';
}

$pfSql = "
    SELECT 
        COALESCE(NULLIF(TRIM(p.`$platColName`), ''), 'Privé') AS platform_name,
        COUNT(*) AS nb_commandes,
        SUM(c.montant) AS total_montant
    FROM commandes c
    LEFT JOIN platforms p ON c.platform_id = p.id
    WHERE LOWER(TRIM(c.statut)) IN ('payé', 'paye', 'terminee', 'завершен', 'оплачен')
";
$pfParams = [];
if ($pf_month !== '') {
    $pfSql .= " AND DATE_FORMAT(COALESCE(c.date_paiement, c.date_commande), '%Y-%m') = :pf_month ";
    $pfParams[':pf_month'] = $pf_month;
}
$pfSql .= "
    GROUP BY platform_name
    ORDER BY nb_commandes DESC
";

$stmtPlatforms = $pdo->prepare($pfSql);
$stmtPlatforms->execute($pfParams);

$platform_total = 0;
$platform_labels  = [];
$platform_counts  = [];
$platform_amounts = [];
$platform_colors  = [];

while ($row = $stmtPlatforms->fetch()) {
    $name = $row['platform_name'];
    $platform_labels[]  = $name;
    $platform_counts[]  = (int)$row['nb_commandes'];
    $platform_total    += (int)$row['nb_commandes'];
    $platform_amounts[] = round((float)$row['total_montant'], 2);

    // Те же цвета, что у бейджей платформ в списке заказов
    if (strcasecmp($name, 'Yoojo') === 0) {
        $platform_colors[] = '#0d6efd';
    } elseif (strcasecmp($name, 'NeedHelp') === 0) {
        $platform_colors[] = '#198754';
    } elseif (strcasecmp($name, 'Privé') === 0) {
        $platform_colors[] = '#dc3545';
    } else {
        $platform_colors[] = '#6c757d';
    }
}

// 6. Динамика по месяцам: количество ОПЛАЧЕННЫХ заказов по каждой платформе
$stmtPfMonthly = $pdo->query("
    SELECT
        DATE_FORMAT(COALESCE(c.date_paiement, c.date_commande), '%Y-%m') AS ym,
        COALESCE(NULLIF(TRIM(p.`$platColName`), ''), 'Privé') AS platform_name,
        COUNT(*) AS nb_commandes
    FROM commandes c
    LEFT JOIN platforms p ON c.platform_id = p.id
    WHERE LOWER(TRIM(c.statut)) IN ('payé', 'paye', 'terminee', 'завершен', 'оплачен')
      AND COALESCE(c.date_paiement, c.date_commande) IS NOT NULL
    GROUP BY ym, platform_name
    ORDER BY ym ASC
");

$pf_monthly = [];      // [platform][ym] => count
$pf_monthly_yms = [];  // все месяцы
$pf_monthly_totals = []; // для сортировки платформ по количеству
while ($row = $stmtPfMonthly->fetch()) {
    $pf_monthly[$row['platform_name']][$row['ym']] = (int)$row['nb_commandes'];
    $pf_monthly_yms[$row['ym']] = true;
    $pf_monthly_totals[$row['platform_name']] = ($pf_monthly_totals[$row['platform_name']] ?? 0) + (int)$row['nb_commandes'];
}
ksort($pf_monthly_yms);
arsort($pf_monthly_totals);

$fr_months_short = [1 => 'Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
$pf_monthly_labels = [];
foreach (array_keys($pf_monthly_yms) as $ym) {
    [$y, $m] = explode('-', $ym);
    $pf_monthly_labels[] = $fr_months_short[(int)$m] . ' ' . $y;
}

$pf_monthly_datasets = [];
foreach (array_keys($pf_monthly_totals) as $name) {
    if (strcasecmp($name, 'Yoojo') === 0) {
        $color = '#0d6efd';
    } elseif (strcasecmp($name, 'NeedHelp') === 0) {
        $color = '#198754';
    } elseif (strcasecmp($name, 'Privé') === 0) {
        $color = '#dc3545';
    } else {
        $color = '#6c757d';
    }

    $values = [];
    foreach (array_keys($pf_monthly_yms) as $ym) {
        $values[] = $pf_monthly[$name][$ym] ?? 0;
    }

    $pf_monthly_datasets[] = [
        'label'                => $name,
        'data'                 => $values,
        'borderColor'          => $color,
        'backgroundColor'      => $color,
        'pointBackgroundColor' => $color,
        'borderWidth'          => 3,
        'pointRadius'          => 4,
        'tension'              => 0.35,
        'fill'                 => false,
    ];
}

require_once 'header.php';
?>

<title>Graphique des rapports — NumériqueAide</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container mt-4 mb-5" style="max-width: 1100px;">
    <!-- Navigation -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Retour aux rapports</a>
    </div>

    <!-- Titre -->
    <div class="mb-4">
        <h2 class="fw-bold text-dark"><i class="bi bi-graph-up text-primary me-2"></i>Graphique financier</h2>
    </div>

    <!-- Carte de quantité -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Quantité totale de commandes</div>
            <div class="fs-1 fw-bold text-dark"><?= $totalCount; ?></div>
        </div>
    </div>

    <!-- Graphique avec 2 courbes -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold text-dark mb-1">Comparaison mensuelle</h5>
            <p class="text-muted small mb-4">Somme des commandes payées vs Revenu net (hors taxes et coûts des matériaux)</p>

            <div style="position: relative; height: 380px; width: 100%;">
                <canvas id="commandesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Graphique en barres par plateforme -->
    <div class="card shadow-sm border-0" id="platforms">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
                <div>
                    <h5 class="fw-bold text-dark mb-1">Commandes par plateforme</h5>
                    <p class="text-muted small mb-0">
                        Nombre de commandes payées par plateforme
                        — <?= $pf_month !== '' ? htmlspecialchars($platform_months[$pf_month] ?? $pf_month) : 'toute la période'; ?> :
                        <strong><?= $platform_total; ?></strong>
                    </p>
                </div>

                <form method="GET" action="report_chart.php#platforms" class="d-flex gap-1" style="min-width: 240px;">
                    <select name="pf_month" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Mois">
                        <option value="">Toute la période</option>
                        <?php foreach ($platform_months as $ym => $monthLabel): ?>
                            <option value="<?= $ym; ?>" <?= $pf_month === $ym ? 'selected' : ''; ?>><?= htmlspecialchars($monthLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($pf_month !== ''): ?>
                        <a href="report_chart.php#platforms" class="btn btn-sm btn-outline-secondary" title="Toute la période"><i class="bi bi-x-circle"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($platform_labels)): ?>
                <div class="text-muted text-center py-4">Aucune commande pour cette période</div>
            <?php else: ?>
                <div style="position: relative; height: 320px; width: 100%;">
                    <canvas id="platformsChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Graphique mensuel par plateforme -->
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-body p-4">
            <h5 class="fw-bold text-dark mb-1">Évolution mensuelle par plateforme</h5>
            <p class="text-muted small mb-4">Nombre de commandes payées par mois et par plateforme</p>

            <?php if (empty($pf_monthly_datasets)): ?>
                <div class="text-muted text-center py-4">Aucune donnée</div>
            <?php else: ?>
                <div style="position: relative; height: 380px; width: 100%;">
                    <canvas id="platformsMonthlyChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('commandesChart').getContext('2d');
    const labels = <?= json_encode($labels); ?>;
    const dataPaiements = <?= json_encode($data_paiements); ?>;
    const dataNet = <?= json_encode($data_net); ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Somme des commandes (Payé)',
                    data: dataPaiements,
                    borderColor: '#e91e63',
                    backgroundColor: 'rgba(233, 30, 99, 0.05)',
                    borderWidth: 3,
                    pointBackgroundColor: '#e91e63',
                    pointRadius: 4,
                    tension: 0.35,
                    fill: false
                },
                {
                    label: 'Total (hors taxes et matériaux)',
                    data: dataNet,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.05)',
                    borderWidth: 3,
                    pointBackgroundColor: '#198754',
                    pointRadius: 4,
                    tension: 0.35,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '€' + v.toLocaleString('fr-FR') }
                }
            }
        }
    });

    // Столбчатый график: количество заказов по платформам
    const platformsCanvas = document.getElementById('platformsChart');
    if (platformsCanvas) {
        const platformLabels  = <?= json_encode($platform_labels); ?>;
        const platformCounts  = <?= json_encode($platform_counts); ?>;
        const platformAmounts = <?= json_encode($platform_amounts); ?>;
        const platformColors  = <?= json_encode($platform_colors); ?>;

        new Chart(platformsCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: platformLabels,
                datasets: [{
                    label: 'Commandes',
                    data: platformCounts,
                    backgroundColor: platformColors,
                    borderRadius: 6,
                    maxBarThickness: 80
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 22 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (item) {
                                const amount = platformAmounts[item.dataIndex] || 0;
                                return [
                                    'Commandes : ' + item.raw,
                                    'Montant : ' + amount.toLocaleString('fr-FR', { minimumFractionDigits: 2 }) + ' €'
                                ];
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            },
            // Число заказов над каждым столбиком
            plugins: [{
                id: 'barValues',
                afterDatasetsDraw(chart) {
                    const c = chart.ctx;
                    c.save();
                    c.font = 'bold 13px sans-serif';
                    c.fillStyle = '#1f2937';
                    c.textAlign = 'center';
                    chart.getDatasetMeta(0).data.forEach((bar, i) => {
                        c.fillText(chart.data.datasets[0].data[i], bar.x, bar.y - 6);
                    });
                    c.restore();
                }
            }]
        });
    }

    // Линейный график: заказы по месяцам для каждой платформы
    const pfMonthlyCanvas = document.getElementById('platformsMonthlyChart');
    if (pfMonthlyCanvas) {
        new Chart(pfMonthlyCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($pf_monthly_labels); ?>,
                datasets: <?= json_encode($pf_monthly_datasets); ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: item => item.dataset.label + ' : ' + item.raw + ' commande(s)'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }
</script>
</body>
</html>