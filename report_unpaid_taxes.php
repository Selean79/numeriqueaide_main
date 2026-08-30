<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$export = $_GET['export'] ?? '';
$currentFilename = basename($_SERVER['PHP_SELF']); // Détermination automatique du nom du fichier de rapport actuel

// Détermination dynamique des colonnes des plateformes
$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : (in_array('name', $platCols) ? 'name' : $platCols[1]);

// Sélection des commandes payées ayant des taxes / cotisations impayées
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

// Calcul des montants totaux à payer et de la somme globale à régler
$total_pending_impot = 0;
$total_pending_epargne = 0;

foreach ($orders as $ord) {
    $m = (float)($ord['montant'] ?? 0);

    // Si la taxe doit être calculée mais n'est PAS encore payée
    $impVal = (float)($ord['calcul_impot'] ?? 0);
    $impAmount = ($impVal == 1) ? ($m * 0.212) : $impVal;
    if ($impAmount > 0 && empty($ord['impot_paye'])) {
        $total_pending_impot += $impAmount;
    }

    // Si l'épargne doit être calculée mais n'est PAS encore versée
    $epVal = (float)($ord['calcul_epargne'] ?? 0);
    $epAmount = ($epVal == 1) ? ($m * 0.10) : $epVal;
    if ($epAmount > 0 && empty($ord['epargne_paye'])) {
        $total_pending_epargne += $epAmount;
    }
}

// Somme totale à payer (taxe + cotisations)
$total_combined_payment = $total_pending_impot + $total_pending_epargne;

// Exportation en Excel / CSV
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="unpaid_taxes_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");

    fputcsv($output, ['ID Commande', 'Date', 'Client', 'Plateforme', 'Montant (€)', 'Taxe URSSAF (€)', 'Statut de la taxe', 'Épargne (€)', 'Statut de l\'épargne'], ';');

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
                !empty($ord['impot_paye']) ? 'Payée' : 'À payer',
                number_format($epAmount, 2, '.', ''),
                !empty($ord['epargne_paye']) ? 'Versé' : 'À verser'
        ], ';');
    }

    fputcsv($output, ['TOTAL', '', '', '', '', number_format($total_pending_impot, 2, '.', ''), '', number_format($total_pending_epargne, 2, '.', ''), ''], ';');

    fclose($output);
    exit;
}

require_once 'header.php';
?>
<style>
    @media print {
        /* Скрываем навигацию, кнопки и колонку действий */
        .d-flex.justify-content-between,
        .btn,
        a,
        th:last-child,
        td:last-child {
            display: none !important;
        }

        /* Убираем тени и делаем фон белым */
        body {
            background-color: #fff !important;
            font-size: 12px;
        }

        .card {
            border: 1px solid #dee2e6 !important;
            box-shadow: none !important;
        }

        /* Располагаем карточки статистики в ряд по 4 штуки */
        .row.g-3.mb-4 {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            gap: 10px !important;
        }

        .row.g-3.mb-4 > div {
            width: 25% !important;
            flex: 1 !important;
        }

        /* Уменьшаем отступы в карточках и таблице для компактности */
        .card-body {
            padding: 10px !important;
        }

        .table th, .table td {
            padding: 6px !important;
            font-size: 11px !important;
        }

        /* Альбомная ориентация для печати */
        @page {
            size: landscape;
            margin: 10mm;
        }
    }
</style>
<title>Contrôle du paiement des taxes — NumériqueAide</title>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Retour aux rapports</a>
            <h3 class="mb-0"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Commandes avec taxes / cotisations impayées</h3>
        </div>
        <div>
            <a href="?export=csv" class="btn btn-outline-success me-2">
                <i class="bi bi-file-earmark-excel me-1"></i> Télécharger en Excel
            </a>
            <button onclick="window.print();" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Imprimer
            </button>
        </div>
    </div>

    <!-- Cartes de totaux -->
    <div class="row g-3 mb-4">
        <!-- 1. À traiter -->
        <div class="col-md-3">
            <div class="card border-0 bg-danger text-white shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="fw-semibold text-white mb-1" style="font-size: 0.95rem;">À traiter (commandes)</div>
                    <div class="fs-2 fw-bold"><?= count($orders); ?></div>
                </div>
            </div>
        </div>
        <!-- 2. Somme totale à payer -->
        <div class="col-md-3">
            <div class="card border-0 bg-success text-white shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="fw-semibold text-white mb-1" style="font-size: 0.95rem;">Somme totale à payer</div>
                    <div class="fs-2 fw-bold"><?= number_format($total_combined_payment, 2, ',', ' '); ?> €</div>
                </div>
            </div>
        </div>
        <!-- 3. Total URSSAF à payer -->
        <div class="col-md-3">
            <div class="card border-0 bg-warning text-dark shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">Total URSSAF à payer (21.2%)</div>
                    <div class="fs-2 fw-bold text-dark"><?= number_format($total_pending_impot, 2, ',', ' '); ?> €</div>
                </div>
            </div>
        </div>
        <!-- 4. Total épargne à verser -->
        <div class="col-md-3">
            <div class="card border-0 bg-info text-white shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="fw-semibold text-white mb-1" style="font-size: 0.95rem;">Total épargne à verser (10%)</div>
                    <div class="fs-2 fw-bold"><?= number_format($total_pending_epargne, 2, ',', ' '); ?> €</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                    <tr>
                        <th style="width: 110px;">ID Commande</th>
                        <th style="width: 110px;">Date</th>
                        <th>Client</th>
                        <th>Plateforme</th>
                        <th class="text-end">Montant de la commande</th>
                        <th class="text-center">Taxe URSSAF</th>
                        <th class="text-center">Épargne</th>
                        <th style="width: 100px;" class="text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-success fw-bold">
                                <i class="bi bi-check-circle-fill me-1"></i> Toutes les taxes et cotisations des commandes payées ont été réglées !
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
                                <td class="fw-bold text-nowrap">#<?= htmlspecialchars($ord['id_commande']); ?></td>
                                <td><?= date('d.m.Y', strtotime($ord['date_commande'])); ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars(trim($ord['client_name']) ?: '—'); ?></td>
                                <td><span class="badge <?= $platBadgeClass; ?>"><?= htmlspecialchars($platformName); ?></span></td>
                                <td class="text-end fw-bold"><?= number_format($m, 2, ',', ' '); ?> €</td>

                                <!-- Taxe -->
                                <td class="text-center">
                                    <?php if ($impAmount > 0): ?>
                                        <?php if (!empty($ord['impot_paye'])): ?>
                                            <span class="badge bg-success" title="Payée"><i class="bi bi-check-all me-1"></i><?= number_format($impAmount, 2, ',', ' '); ?> €</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger p-2"><i class="bi bi-clock me-1"></i><?= number_format($impAmount, 2, ',', ' '); ?> € (Impayée)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">0,00 €</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Épargne -->
                                <td class="text-center">
                                    <?php if ($epAmount > 0): ?>
                                        <?php if (!empty($ord['epargne_paye'])): ?>
                                            <span class="badge bg-success" title="Versé"><i class="bi bi-check-all me-1"></i><?= number_format($epAmount, 2, ',', ' '); ?> €</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark p-2"><i class="bi bi-clock me-1"></i><?= number_format($epAmount, 2, ',', ' '); ?> € (Non versé)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">0,00 €</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Modifier (retour sur ce même rapport) -->
                                <td class="text-center">
                                    <a href="edit_commande.php?id=<?= $ord['id']; ?>&return=<?= $currentFilename; ?>" class="btn btn-sm btn-outline-primary" title="Marquer comme payé">
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