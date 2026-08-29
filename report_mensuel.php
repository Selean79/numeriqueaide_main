<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

// Получение месяца и года
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

// Границы месяца
$start_date = sprintf('%04d-%02d-01', $selected_year, $selected_month);
$end_date   = date('Y-m-t', strtotime($start_date));

// 1. Total des commandes — сумма всех заказов, созданных в текущем месяце (исключая отмененные)
$stmtCommandes = $pdo->prepare("
    SELECT SUM(montant) FROM commandes 
    WHERE date_commande BETWEEN :start_date AND :end_date
    AND (LOWER(TRIM(statut)) NOT IN ('annulee', 'annulée', 'отменен'))
");
$stmtCommandes->execute([':start_date' => $start_date, ':end_date' => $end_date]);
$total_commandes = (float)($stmtCommandes->fetchColumn() ?? 0);

// 2. Сумма заказов, созданных в текущем месяце и имеющих статус оплаты
$stmtPaiementsCurrentMonth = $pdo->prepare("
    SELECT SUM(montant) FROM commandes 
    WHERE date_commande BETWEEN :start_date AND :end_date
    AND (LOWER(TRIM(statut)) IN ('payé', 'paye', 'terminee', 'завершен', 'оплачен'))
");
$stmtPaiementsCurrentMonth->execute([':start_date' => $start_date, ':end_date' => $end_date]);
$total_paiements_current = (float)($stmtPaiementsCurrentMonth->fetchColumn() ?? 0);

// 3. Impôt 21,2% (Сумма налога по заказам текущего месяца с флагом calcul_impot > 0)
$stmtImpot = $pdo->prepare("
    SELECT SUM(
        CASE 
            WHEN calcul_impot = 1 THEN montant * 0.212 
            ELSE calcul_impot 
        END
    ) FROM commandes 
    WHERE date_commande BETWEEN :start_date AND :end_date
    AND (LOWER(TRIM(statut)) NOT IN ('annulee', 'annulée', 'отменен'))
    AND calcul_impot > 0
");
$stmtImpot->execute([':start_date' => $start_date, ':end_date' => $end_date]);
$total_impot = (float)($stmtImpot->fetchColumn() ?? 0);

// 4. Épargne 10% (Сумма отчислений по заказам текущего месяца с флагом calcul_epargne > 0)
$stmtEpargne = $pdo->prepare("
    SELECT SUM(
        CASE 
            WHEN calcul_epargne = 1 THEN montant * 0.10 
            ELSE calcul_epargne 
        END
    ) FROM commandes 
    WHERE date_commande BETWEEN :start_date AND :end_date
    AND (LOWER(TRIM(statut)) NOT IN ('annulee', 'annulée', 'отменен'))
    AND calcul_epargne > 0
");
$stmtEpargne->execute([':start_date' => $start_date, ':end_date' => $end_date]);
$total_epargne = (float)($stmtEpargne->fetchColumn() ?? 0);

// 5. Coûts des matériaux (Закупки материалов за текущий месяц)
$stmtPurchases = $pdo->prepare("
    SELECT SUM(montant) FROM purchases 
    WHERE date_achat BETWEEN :start_date AND :end_date
");
$stmtPurchases->execute([':start_date' => $start_date, ':end_date' => $end_date]);
$total_purchases = (float)($stmtPurchases->fetchColumn() ?? 0);

// 6. Чистый доход (Поступления - Закупки - Налог)
$net_total = $total_paiements_current - $total_purchases - $total_impot;

// 7. Разбивка по способам оплаты для заказов, созданных в текущем месяце
$pmCols = $pdo->query("SHOW COLUMNS FROM modes_de_paiement")->fetchAll(PDO::FETCH_COLUMN);
$pmColName = in_array('nom', $pmCols) ? 'nom' : (in_array('name', $pmCols) ? 'name' : $pmCols[1]);

$stmtByMethod = $pdo->prepare("
    SELECT 
        COALESCE(pm.`$pmColName`, 'Espèces') AS method_name,
        SUM(c.montant) AS amount
    FROM commandes c
    LEFT JOIN modes_de_paiement pm ON c.payment_method_id = pm.id
    WHERE c.date_commande BETWEEN :start_date AND :end_date
    AND (LOWER(TRIM(c.statut)) NOT IN ('annulee', 'annulée', 'отменен'))
    GROUP BY pm.id, method_name
");
$stmtByMethod->execute([':start_date' => $start_date, ':end_date' => $end_date]);
$payments_by_method = $stmtByMethod->fetchAll();

$french_months = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

require_once 'header.php';
?>

<title>Rapport mensuel — NumériqueAide</title>
<style>
    body {
        background-color: #f0f2f5;
    }
    .comments{
        font-size: 103%;
    }
    .comments i.comments1{
        color: #0b37ed;
        font-size: 120%
    }
    /* Делаем фон блоков методов оплаты темнее (вместо bg-light / белого) */
    .payment-method-box {
        background-color: #e4e7eb !important;
    }
</style>

<div class="container mt-4 mb-5" style="max-width: 950px;">
    <!-- Кнопка возврата к отчетам -->
    <div class="mb-3">
        <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Vers les rapports</a>
    </div>

    <!-- Заголовок и селектор -->
    <div class="text-center mb-4">
        <h3 class="fw-bold mb-3"><i class="bi bi-calendar2-check text-primary me-2"></i>Rapport mensuel</h3>

        <form method="GET" id="reportForm" class="d-flex flex-column align-items-center gap-3">
            <div class="d-flex gap-2">
                <select name="month" class="form-select fw-bold shadow-sm" style="width: 140px;" onchange="this.form.submit()">
                    <?php foreach ($french_months as $m_num => $m_name): ?>
                        <option value="<?= $m_num; ?>" <?= $selected_month === $m_num ? 'selected' : ''; ?>>
                            <?= $m_name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="year" class="form-select fw-bold shadow-sm" style="width: 110px;" onchange="this.form.submit()">
                    <?php for ($y = 2024; $y <= 2028; $y++): ?>
                        <option value="<?= $y; ?>" <?= $selected_year === $y ? 'selected' : ''; ?>><?= $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- ТРИ ГЛАВНЫХ МОДУЛЯ (Слева направо) -->
    <div class="row g-3 mb-4">
        <!-- 1. Total des commandes -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger me-3">
                        <i class="bi bi-cart-check fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted extra-small fw-semibold">Total des commandes</div>
                        <div class="fs-4 fw-bold text-danger"><?= number_format($total_commandes, 2, ',', ' '); ?> €</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Commandes payées du mois -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                        <i class="bi bi-credit-card fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted extra-small fw-semibold">Commandes payées</div>
                        <div class="fs-4 fw-bold text-primary"><?= number_format($total_paiements_current, 2, ',', ' '); ?> €</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Revenu net -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success me-3">
                        <i class="bi bi-wallet2 fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted extra-small fw-semibold">Revenu net</div>
                        <div class="fs-4 fw-bold text-success"><?= number_format($net_total, 2, ',', ' '); ?> €</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Вторичные показатели: Налоги, отчисления и закупки -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-2 text-warning me-3">
                        <i class="bi bi-percent fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted extra-small fw-semibold">Impôt 21,2%</div>
                        <div class="fs-5 fw-bold text-dark"><?= number_format($total_impot, 2, ',', ' '); ?> €</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-circle bg-info bg-opacity-10 p-2 text-info me-3">
                        <i class="bi bi-piggy-bank fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted extra-small fw-semibold">Épargne 10%</div>
                        <div class="fs-5 fw-bold text-dark"><?= number_format($total_epargne, 2, ',', ' '); ?> €</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-2 text-warning me-3">
                        <i class="bi bi-cart3 fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted extra-small fw-semibold">Coûts des matériaux</div>
                        <div class="fs-5 fw-bold text-dark"><?= number_format($total_purchases, 2, ',', ' '); ?> €</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Способы оплаты -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-3">Montant par méthode de paiement</h6>
            <div class="row g-3">
                <?php if (empty($payments_by_method)): ?>
                    <div class="col-12 text-muted small">Aucun mode de paiement n'a été trouvé pour ce mois-ci.</div>
                <?php else: ?>
                    <?php foreach ($payments_by_method as $pm): ?>
                        <?php
                        $m_name = trim($pm['method_name']);
                        $icon = 'bi-credit-card';
                        if (strcasecmp($m_name, 'Banque Postal') === 0 || strcasecmp($m_name, 'Virement') === 0) {
                            $icon = 'bi-bank';
                        } elseif (strcasecmp($m_name, 'PayPal') === 0) {
                            $icon = 'bi-currency-dollar';
                        } elseif (strcasecmp($m_name, 'Espèces') === 0) {
                            $icon = 'bi-cash-stack';
                        }
                        ?>
                        <div class="col-md-4">
                            <!-- Заменили класс bg-light на payment-method-box с более темным оттенком -->
                            <div class="p-3 payment-method-box rounded-3 d-flex align-items-center">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-2 text-primary me-3">
                                    <i class="bi <?= $icon; ?> fs-4"></i>
                                </div>
                                <div>
                                    <div class="text-muted extra-small"><?= htmlspecialchars($m_name); ?></div>
                                    <div class="fw-bold fs-5 text-dark"><?= number_format((float)$pm['amount'], 2, ',', ' '); ?> €</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div>
        <ul class="comments">
            <ol><i class="comments1">Total des commandes</i> - Montant total des commandes du mois en cours</ol>
            <ol><i class="comments1">Commandes payées</i> - Paiement de toutes les commandes d'un mois civil</ol>
            <ol><i class="comments1">Revenu net</i> - Commandes payées moins Impôt 21,2 % moins Coûts des matériaux</ol>
            <ol><i class="comments1">Total des commandes</i> - Montant total des commandes du mois en cours</ol>
            <ol><i class="comments1">Total des commandes</i> - Montant total des commandes du mois en cours</ol>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>