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
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h5 class="fw-bold text-dark mb-1">Comparaison mensuelle</h5>
            <p class="text-muted small mb-4">Somme des commandes payées vs Revenu net (hors taxes et coûts des matériaux)</p>

            <div style="position: relative; height: 380px; width: 100%;">
                <canvas id="commandesChart"></canvas>
            </div>
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
</script>
</body>
</html>