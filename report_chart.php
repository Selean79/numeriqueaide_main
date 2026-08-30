<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

// 1. Nombre total de toutes les commandes
$stmtTotalCount = $pdo->query("SELECT COUNT(*) FROM commandes");
$totalCount = (int)$stmtTotalCount->fetchColumn();

// 2. Récupération des données pour le graphique
$sql = "
    SELECT 
        DATE_FORMAT(date_commande, '%Y-%m') AS ym,
        DATE_FORMAT(date_commande, '%b %Y') AS month_label,
        SUM(montant) AS total_amount
    FROM commandes
    WHERE LOWER(TRIM(statut)) IN ('payé', 'paye', 'terminee', 'завершен', 'оплачен')
    GROUP BY ym, month_label
    ORDER BY ym ASC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();

$labels = [];
$data = [];

foreach ($rows as $row) {
    $en_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $fr_months = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
    $label = str_replace($en_months, $fr_months, $row['month_label']);

    $labels[] = $label;
    $data[]   = (float)$row['total_amount'];
}

require_once 'header.php';
?>

<title>Graphique du rapport — NumériqueAide</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container mt-4 mb-5" style="max-width: 1100px;">
    <!-- Navigation -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Retour aux rapports</a>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
            </ol>
        </nav>
    </div>

    <!-- Titre -->
    <div class="mb-4">
        <h2 class="fw-bold text-dark"><i class="bi bi-graph-up text-primary me-2"></i>Commandes</h2>
    </div>

    <!-- Carte de quantité -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="text-muted small fw-semibold text-uppercase mb-1">Quantité</div>
            <div class="fs-1 fw-bold text-dark"><?= $totalCount; ?></div>
        </div>
    </div>

    <!-- Graphique -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h5 class="fw-bold text-dark mb-1">Somme des commandes avec statut Payé</h5>
            <p class="text-muted small mb-4">Affiche la somme totale des commandes dont le statut est Payé selon la date de commande</p>

            <div style="position: relative; height: 380px; width: 100%;">
                <canvas id="commandesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('commandesChart').getContext('2d');
    const labels = <?= json_encode($labels); ?>;
    const dataValues = <?= json_encode($data); ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                data: dataValues,
                borderColor: '#e91e63',
                backgroundColor: 'rgba(233, 30, 99, 0.05)',
                borderWidth: 3,
                pointBackgroundColor: '#e91e63',
                pointRadius: 5,
                tension: 0.35,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '€' + v.toLocaleString('fr-FR') } }
            }
        }
    });
</script>
</body>
</html>