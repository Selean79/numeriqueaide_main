<?php
// Включаем принудительный вывод всех ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$current_year = 2026;
$start_date = "$current_year-01-01";
$end_date   = "$current_year-12-31";

try {
    // 1. Общий оборот за 2026 год
    $stmtTurnover = $pdo->prepare("
        SELECT SUM(montant) FROM commandes 
        WHERE date_commande BETWEEN :start AND :end
    ");
    $stmtTurnover->execute([':start' => $start_date, ':end' => $end_date]);
    $total_turnover = (float)($stmtTurnover->fetchColumn() ?? 0);

    // 2. Налог URSSAF за 2026 год (только оплаченные)
    $stmtImpot = $pdo->prepare("
        SELECT SUM(
            CASE 
                WHEN calcul_impot = 1 THEN montant * 0.212 
                ELSE calcul_impot 
            END
        ) FROM commandes 
        WHERE date_commande BETWEEN :start AND :end
          AND (LOWER(TRIM(statut)) IN ('payé', 'paye', 'terminee', 'завершен', 'оплачен'))
          AND calcul_impot > 0
    ");
    $stmtImpot->execute([':start' => $start_date, ':end' => $end_date]);
    $total_impot = (float)($stmtImpot->fetchColumn() ?? 0);

    // 3. Накопления за 2026 год (только оплаченные)
    $stmtEpargne = $pdo->prepare("
        SELECT SUM(
            CASE 
                WHEN calcul_epargne = 1 THEN montant * 0.10 
                ELSE calcul_epargne 
            END
        ) FROM commandes 
        WHERE date_commande BETWEEN :start AND :end
          AND (LOWER(TRIM(statut)) IN ('payé', 'paye', 'terminee', 'завершен', 'оплачен'))
          AND calcul_epargne > 0
    ");
    $stmtEpargne->execute([':start' => $start_date, ':end' => $end_date]);
    $total_epargne = (float)($stmtEpargne->fetchColumn() ?? 0);

    // 4. Заказы и уникальные клиенты за 2026 год
    $stmtOrdersCount = $pdo->prepare("SELECT COUNT(*) FROM commandes WHERE date_commande BETWEEN :start AND :end");
    $stmtOrdersCount->execute([':start' => $start_date, ':end' => $end_date]);
    $orders_count = (int)$stmtOrdersCount->fetchColumn();

    $stmtClientsCount = $pdo->prepare("SELECT COUNT(DISTINCT client_id) FROM commandes WHERE date_commande BETWEEN :start AND :end AND client_id IS NOT NULL");
    $stmtClientsCount->execute([':start' => $start_date, ':end' => $end_date]);
    $clients_count = (int)$stmtClientsCount->fetchColumn();

    // Последние заказы
    $stmtLast = $pdo->query("
        SELECT c.*, CONCAT(COALESCE(cl.nom,''), ' ', COALESCE(cl.prenom,'')) AS client_name 
        FROM commandes c 
        LEFT JOIN clients cl ON c.client_id = cl.id 
        ORDER BY c.date_commande DESC, c.id DESC 
        LIMIT 10
    ");
    $last_orders = $stmtLast->fetchAll();

} catch (PDOException $e) {
    die("Erreur de base de données : " . htmlspecialchars($e->getMessage()));
}

require_once 'header.php';
?>

<title>Tableau de bord <?= $current_year; ?> — NumériqueAide</title>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2"></i>Tableau de bord (<?= $current_year; ?>)</h3>
    </div>

    <!-- Cartes -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-primary bg-gradient text-white shadow-sm h-100">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small fw-semibold text-uppercase">Chiffre d'affaires</div>
                        <div class="fs-3 fw-bold mt-1"><?= number_format($total_turnover, 2, ',', ' '); ?> €</div>
                    </div>
                    <i class="bi bi-wallet2 fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-warning bg-gradient text-dark shadow-sm h-100">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-dark-50 small fw-semibold text-uppercase">Taxes URSSAF</div>
                        <div class="fs-3 fw-bold mt-1"><?= number_format($total_impot, 2, ',', ' '); ?> €</div>
                    </div>
                    <i class="bi bi-bank fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-info bg-gradient text-dark shadow-sm h-100">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-dark-50 small fw-semibold text-uppercase">Épargne</div>
                        <div class="fs-3 fw-bold mt-1"><?= number_format($total_epargne, 2, ',', ' '); ?> €</div>
                    </div>
                    <i class="bi bi-piggy-bank fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-success bg-gradient text-white shadow-sm h-100">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small fw-semibold text-uppercase">Commandes / Clients</div>
                        <div class="fs-3 fw-bold mt-1"><?= $orders_count; ?> / <?= $clients_count; ?></div>
                    </div>
                    <i class="bi bi-people fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Dernières commandes -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Dernières commandes</h5>
            <a href="commandes_list.php" class="btn btn-sm btn-outline-primary">Toutes les commandes</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th class="text-end">Montant</th>
                        <th class="text-center">Statut</th>
                        <th class="text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($last_orders)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Aucune commande trouvée</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($last_orders as $ord): ?>
                            <?php
                            $statusRaw = trim($ord['statut'] ?? '');
                            $statusBadge = 'bg-secondary';
                            $statusLabel = $statusRaw;
                            switch (mb_strtolower($statusRaw)) {
                                case 'prévu': case 'prevu': case 'запланирован':
                                $statusBadge = 'bg-success'; $statusLabel = 'Prévu'; break;
                                case 'en cours': case 'en_cours': case 'в работе':
                                $statusBadge = 'bg-warning text-dark'; $statusLabel = 'En cours'; break;
                                case 'payé': case 'paye': case 'terminee': case 'оплачен':
                                $statusBadge = 'bg-secondary'; $statusLabel = 'Payé'; break;
                                case 'annulee': case 'отменен':
                                $statusBadge = 'bg-danger'; $statusLabel = 'Annulée'; break;
                            }
                            ?>
                            <tr>
                                <td class="fw-bold">#<?= $ord['id_commande']; ?></td>
                                <td><?= date('d.m.Y', strtotime($ord['date_commande'])); ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars(trim($ord['client_name']) ?: '—'); ?></td>
                                <td class="text-end fw-bold"><?= number_format((float)$ord['montant'], 2, ',', ' '); ?> €</td>
                                <td class="text-center"><span class="badge <?= $statusBadge; ?>"><?= htmlspecialchars($statusLabel); ?></span></td>
                                <td class="text-center">
                                    <a href="edit_commande.php?id=<?= $ord['id']; ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="bi bi-pencil"></i></a>
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