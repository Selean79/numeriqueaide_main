<?php
// Включаем принудительный вывод всех ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$current_year = (int)date('Y');
$start_date = "$current_year-01-01";
$end_date   = "$current_year-12-31";

$today    = date('Y-m-d');
$in7days  = date('Y-m-d', strtotime('+7 days'));

// Статусы
$PAID_SQL      = "LOWER(TRIM(c.statut)) IN ('payé', 'paye', 'terminee', 'завершен', 'оплачен')";
$CANCELLED_SQL = "LOWER(TRIM(c.statut)) IN ('annulee', 'annulée', 'отменен')";

// Имя колонки платформы
$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : (in_array('name', $platCols) ? 'name' : $platCols[1]);

try {
    /* ---------------- Верхние карточки (за год) ---------------- */

    // 1. Общий оборот за год
    $stmtTurnover = $pdo->prepare("
        SELECT SUM(montant) FROM commandes
        WHERE date_commande BETWEEN :start AND :end
    ");
    $stmtTurnover->execute([':start' => $start_date, ':end' => $end_date]);
    $total_turnover = (float)($stmtTurnover->fetchColumn() ?? 0);

    // 2. Налог URSSAF за год (только оплаченные)
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

    // 3. Накопления за год (только оплаченные)
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

    // 4. Заказы и уникальные клиенты за год
    $stmtOrdersCount = $pdo->prepare("SELECT COUNT(*) FROM commandes WHERE date_commande BETWEEN :start AND :end");
    $stmtOrdersCount->execute([':start' => $start_date, ':end' => $end_date]);
    $orders_count = (int)$stmtOrdersCount->fetchColumn();

    $stmtClientsCount = $pdo->prepare("SELECT COUNT(DISTINCT client_id) FROM commandes WHERE date_commande BETWEEN :start AND :end AND client_id IS NOT NULL");
    $stmtClientsCount->execute([':start' => $start_date, ':end' => $end_date]);
    $clients_count = (int)$stmtClientsCount->fetchColumn();

    /* ---------------- Prochaines interventions (сегодня + 7 дней) ---------------- */
    $stmtNext = $pdo->prepare("
        SELECT
            c.id, c.id_commande, c.date_commande, c.rdv_time, c.montant, c.statut,
            c.commentaire, c.notes,
            CONCAT(COALESCE(cl.nom,''), ' ', COALESCE(cl.prenom,'')) AS client_name,
            cl.telephone, cl.adresse, cl.adresse_2, cl.notes AS client_notes,
            COALESCE(NULLIF(TRIM(p.`$platColName`), ''), 'Privé') AS platform_name
        FROM commandes c
        LEFT JOIN clients cl ON c.client_id = cl.id
        LEFT JOIN platforms p ON c.platform_id = p.id
        WHERE c.date_commande BETWEEN :today AND :in7
          AND NOT ($CANCELLED_SQL)
        ORDER BY c.date_commande ASC, c.rdv_time ASC, c.id ASC
    ");
    $stmtNext->execute([':today' => $today, ':in7' => $in7days]);
    $next_orders = $stmtNext->fetchAll();

    // Группировка по дням
    $next_by_day = [];
    foreach ($next_orders as $o) {
        $day = date('Y-m-d', strtotime($o['date_commande']));
        $next_by_day[$day][] = $o;
    }

    /* ---------------- À régulariser: прошедшие, но не оплаченные ---------------- */
    $stmtLate = $pdo->prepare("
        SELECT
            c.id, c.id_commande, c.date_commande, c.montant, c.statut,
            CONCAT(COALESCE(cl.nom,''), ' ', COALESCE(cl.prenom,'')) AS client_name
        FROM commandes c
        LEFT JOIN clients cl ON c.client_id = cl.id
        WHERE c.date_commande < :today
          AND NOT ($PAID_SQL)
          AND NOT ($CANCELLED_SQL)
        ORDER BY c.date_commande ASC
    ");
    $stmtLate->execute([':today' => $today]);
    $late_orders = $stmtLate->fetchAll();
    $late_total = array_sum(array_map(fn($o) => (float)$o['montant'], $late_orders));

    /* ---------------- Ce mois-ci vs mois dernier ---------------- */
    function monthStats(PDO $pdo, string $from, string $to, string $paidSql, string $cancelledSql): array {
        // Заказы месяца (без отменённых) по дате заказа
        $st = $pdo->prepare("
            SELECT COUNT(*) AS nb, COALESCE(SUM(c.montant), 0) AS total
            FROM commandes c
            WHERE c.date_commande BETWEEN :from AND :to
              AND NOT ($cancelledSql)
        ");
        $st->execute([':from' => $from, ':to' => $to]);
        $r = $st->fetch();

        // Получено денег в месяце — по дате оплаты
        $st2 = $pdo->prepare("
            SELECT COALESCE(SUM(c.montant), 0)
            FROM commandes c
            WHERE $paidSql
              AND COALESCE(c.date_paiement, c.date_commande) BETWEEN :from AND :to
        ");
        $st2->execute([':from' => $from, ':to' => $to]);

        $nb = (int)$r['nb'];
        $total = (float)$r['total'];
        return [
            'nb'       => $nb,
            'total'    => $total,
            'encaisse' => (float)$st2->fetchColumn(),
            'panier'   => $nb > 0 ? $total / $nb : 0,
        ];
    }

    $thisMonth = monthStats($pdo, date('Y-m-01'), date('Y-m-t'), $PAID_SQL, $CANCELLED_SQL);
    $lastMonth = monthStats(
        $pdo,
        date('Y-m-01', strtotime('first day of last month')),
        date('Y-m-t', strtotime('last day of last month')),
        $PAID_SQL,
        $CANCELLED_SQL
    );

} catch (PDOException $e) {
    die("Erreur de base de données : " . htmlspecialchars($e->getMessage()));
}

/* ---------------- Помощники для вывода ---------------- */
$fr_days   = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$fr_months = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function dayLabel(string $ymd, array $fr_days): string {
    $today    = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    if ($ymd === $today)    return "Aujourd'hui";
    if ($ymd === $tomorrow) return 'Demain';
    return $fr_days[(int)date('w', strtotime($ymd))] . ' ' . date('d.m', strtotime($ymd));
}

function platformBadge(string $name): string {
    if (strcasecmp($name, 'Yoojo') === 0)    return 'bg-primary';
    if (strcasecmp($name, 'NeedHelp') === 0) return 'bg-success';
    return 'bg-danger';
}

function euro(float $v): string {
    return number_format($v, 2, ',', ' ') . ' €';
}

// Стрелка сравнения с прошлым месяцем
function trend(float $now, float $before): string {
    if ($before <= 0) {
        return $now > 0 ? '<span class="trend trend-up">nouveau</span>' : '<span class="trend">—</span>';
    }
    $pct = ($now - $before) / $before * 100;
    $cls = $pct >= 0 ? 'trend-up' : 'trend-down';
    $arrow = $pct >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right';
    return '<span class="trend ' . $cls . '"><i class="bi ' . $arrow . '"></i> ' . ($pct >= 0 ? '+' : '') . number_format($pct, 0, ',', ' ') . ' %</span>';
}

$monthName     = $fr_months[(int)date('n')];
$lastMonthName = $fr_months[(int)date('n', strtotime('first day of last month'))];

require_once 'header.php';
?>

<title>Tableau de bord <?= $current_year; ?> — NumériqueAide</title>

<style>
    body { background-color: #f1f3f5 !important; }

    .dash-card { border: none; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .dash-card .card-header {
        background: #ffffff;
        border-bottom: 1px solid #eef0f2;
        border-radius: 12px 12px 0 0 !important;
        padding: 14px 18px;
    }
    .dash-card .card-header h5 { font-size: 1.05rem; }

    /* Prochaines interventions */
    .day-title {
        font-size: .8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6b7280;
        padding: 12px 18px 6px;
        background: #f8f9fa;
        border-top: 1px solid #eef0f2;
    }
    .day-title.is-today { color: #14532d; background: #e8f7ec; }
    .day-title .count { font-weight: 600; color: #9ca3af; margin-left: 6px; }

    .intervention {
        display: grid;
        grid-template-columns: 72px 1fr auto;
        gap: 12px;
        padding: 12px 18px;
        border-top: 1px solid #f1f3f5;
        align-items: start;
    }
    .intervention:hover { background: #fafbfc; }
    .rdv-time-badge { background-color: #dbeafe; color: #1e40af; padding: 3px 8px; border-radius: 6px; font-weight: 700; display: inline-block; font-size: .95rem; }
    .intervention .client { font-weight: 700; font-size: 1.02rem; }
    .intervention .meta a { color: #4b5563; text-decoration: none; }
    .intervention .meta a:hover { text-decoration: underline; }
    .intervention .meta div { margin-top: 2px; }
    .intervention .job { margin-top: 6px; font-size: .92rem; }
    .intervention .job .comment { color: #1f2937; font-weight: 600; }
    .intervention .job .note { color: #92400e; }
    .intervention .right { text-align: right; white-space: nowrap; }

    /* À régulariser */
    .late-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 18px;
        border-top: 1px solid #f1f3f5;
        text-decoration: none;
        color: inherit;
    }
    .late-row:hover { background: #fff7ed; color: inherit; }
    .late-days { font-size: .78rem; color: #b45309; font-weight: 600; }

    /* Ce mois-ci */
    .kpi { padding: 12px 18px; border-top: 1px solid #f1f3f5; display: flex; justify-content: space-between; align-items: center; }
    .kpi:first-child { border-top: none; }
    .kpi .label { color: #6b7280; font-size: .88rem; }
    .kpi .value { font-weight: 700; font-size: 1.1rem; }
    .kpi .before { font-size: .78rem; color: #9ca3af; }
    .trend { font-size: .78rem; font-weight: 700; padding: 2px 6px; border-radius: 6px; background: #f1f3f5; color: #6b7280; }
    .trend-up { background: #e8f7ec; color: #15803d; }
    .trend-down { background: #fdecec; color: #b91c1c; }

    @media (max-width: 575.98px) {
        .intervention { grid-template-columns: 1fr; gap: 6px; }
        .intervention .right { text-align: left; }
        .stat-cards .fs-3 { font-size: 1.35rem !important; }
    }
</style>

<div class="container-fluid mt-3 mt-md-4 px-2 px-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3 mb-md-4">
        <h3 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2"></i>Tableau de bord (<?= $current_year; ?>)</h3>
    </div>

    <!-- Cartes -->
    <div class="row g-3 mb-4 stat-cards">
        <div class="col-6 col-md-3">
            <div class="card border-0 bg-primary bg-gradient text-white shadow-sm h-100">
                <div class="card-body p-3 p-md-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small fw-semibold text-uppercase">Chiffre d'affaires</div>
                        <div class="fs-3 fw-bold mt-1"><?= euro($total_turnover); ?></div>
                    </div>
                    <i class="bi bi-wallet2 fs-1 opacity-50 d-none d-lg-inline"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 bg-warning bg-gradient text-dark shadow-sm h-100">
                <div class="card-body p-3 p-md-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-dark-50 small fw-semibold text-uppercase">Taxes URSSAF</div>
                        <div class="fs-3 fw-bold mt-1"><?= euro($total_impot); ?></div>
                    </div>
                    <i class="bi bi-bank fs-1 opacity-50 d-none d-lg-inline"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 bg-info bg-gradient text-dark shadow-sm h-100">
                <div class="card-body p-3 p-md-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-dark-50 small fw-semibold text-uppercase">Épargne</div>
                        <div class="fs-3 fw-bold mt-1"><?= euro($total_epargne); ?></div>
                    </div>
                    <i class="bi bi-piggy-bank fs-1 opacity-50 d-none d-lg-inline"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 bg-success bg-gradient text-white shadow-sm h-100">
                <div class="card-body p-3 p-md-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small fw-semibold text-uppercase">Commandes / Clients</div>
                        <div class="fs-3 fw-bold mt-1"><?= $orders_count; ?> / <?= $clients_count; ?></div>
                    </div>
                    <i class="bi bi-people fs-1 opacity-50 d-none d-lg-inline"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-5">
        <!-- ============ Prochaines interventions ============ -->
        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-week me-2 text-primary"></i>Prochaines interventions</h5>
                    <span class="text-muted small">7 prochains jours · <strong><?= count($next_orders); ?></strong></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($next_by_day)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                            Aucune intervention prévue dans les 7 prochains jours
                        </div>
                    <?php else: ?>
                        <?php if (!isset($next_by_day[$today])): ?>
                            <div class="day-title is-today">Aujourd'hui <span class="count">· aucune intervention</span></div>
                        <?php endif; ?>

                        <?php foreach ($next_by_day as $day => $orders): ?>
                            <div class="day-title <?= $day === $today ? 'is-today' : ''; ?>">
                                <?= dayLabel($day, $fr_days); ?>
                                <span class="count">· <?= count($orders); ?> intervention<?= count($orders) > 1 ? 's' : ''; ?></span>
                            </div>

                            <?php foreach ($orders as $o): ?>
                                <?php $clientName = trim($o['client_name']) ?: '—'; ?>
                                <div class="intervention">
                                    <div>
                                        <?php if (!empty($o['rdv_time'])): ?>
                                            <span class="rdv-time-badge"><?= substr($o['rdv_time'], 0, 5); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—:—</span>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <div class="client">
                                            <?= htmlspecialchars($clientName); ?>
                                            <?php if (!empty(trim($o['client_notes'] ?? ''))): ?>
                                                <i class="bi bi-exclamation-circle-fill text-danger ms-1" title="<?= htmlspecialchars($o['client_notes']); ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="meta small">
                                            <?php if (!empty($o['telephone'])): ?>
                                                <div><i class="bi bi-telephone text-primary me-1"></i><a href="tel:<?= preg_replace('/[^\d+]/', '', $o['telephone']); ?>"><?= htmlspecialchars($o['telephone']); ?></a></div>
                                            <?php endif; ?>
                                            <?php if (!empty($o['adresse'])): ?>
                                                <div><i class="bi bi-geo-alt text-danger me-1"></i><a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($o['adresse']); ?>" target="_blank"><?= htmlspecialchars($o['adresse']); ?></a></div>
                                            <?php endif; ?>
                                            <?php if (!empty(trim($o['adresse_2'] ?? ''))): ?>
                                                <div class="text-muted"><i class="bi bi-building me-1"></i><?= htmlspecialchars(trim(preg_replace('/\s+/', ' ', $o['adresse_2']))); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($o['commentaire']) || !empty($o['notes'])): ?>
                                            <div class="job">
                                                <?php if (!empty($o['commentaire'])): ?>
                                                    <div class="comment"><i class="bi bi-tools me-1 text-primary"></i><?= htmlspecialchars($o['commentaire']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($o['notes'])): ?>
                                                    <div class="note"><i class="bi bi-journal-text me-1"></i><?= htmlspecialchars($o['notes']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="right">
                                        <div class="fw-bold"><?= euro((float)$o['montant']); ?></div>
                                        <span class="badge <?= platformBadge($o['platform_name']); ?> mt-1"><?= htmlspecialchars($o['platform_name']); ?></span>
                                        <div class="mt-2">
                                            <a href="edit_commande.php?id=<?= (int)$o['id']; ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="bi bi-pencil"></i></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============ Colonne droite ============ -->
        <div class="col-lg-4 d-flex flex-column gap-3">

            <!-- À régulariser -->
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>À régulariser</h5>
                    <?php if (!empty($late_orders)): ?>
                        <span class="badge bg-warning text-dark"><?= count($late_orders); ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($late_orders)): ?>
                        <div class="text-center text-muted py-4 small">
                            <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>
                            Tout est à jour
                        </div>
                    <?php else: ?>
                        <div class="px-3 py-2 small text-muted">
                            Interventions passées non payées : <strong class="text-dark"><?= euro($late_total); ?></strong>
                        </div>
                        <?php foreach (array_slice($late_orders, 0, 8) as $o): ?>
                            <?php $daysLate = (int)floor((strtotime($today) - strtotime(date('Y-m-d', strtotime($o['date_commande'])))) / 86400); ?>
                            <a href="edit_commande.php?id=<?= (int)$o['id']; ?>" class="late-row">
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars(trim($o['client_name']) ?: '—'); ?></div>
                                    <div class="small text-muted"><?= date('d.m.Y', strtotime($o['date_commande'])); ?> · <?= htmlspecialchars($o['statut']); ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?= euro((float)$o['montant']); ?></div>
                                    <div class="late-days">il y a <?= $daysLate; ?> j</div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($late_orders) > 8): ?>
                            <div class="text-center py-2 border-top">
                                <a href="commandes_list.php?month=&year=&status=Prévu" class="small">Voir les <?= count($late_orders); ?> commandes</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ce mois-ci -->
            <div class="card dash-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-bar-chart-line me-2 text-success"></i>Ce mois-ci <span class="text-muted fw-normal small">(<?= $monthName; ?>)</span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="kpi">
                        <div>
                            <div class="label">Commandes</div>
                            <div class="before"><?= $lastMonthName; ?> : <?= $lastMonth['nb']; ?></div>
                        </div>
                        <div class="text-end">
                            <div class="value"><?= $thisMonth['nb']; ?></div>
                            <?= trend($thisMonth['nb'], $lastMonth['nb']); ?>
                        </div>
                    </div>
                    <div class="kpi">
                        <div>
                            <div class="label">Montant des commandes</div>
                            <div class="before"><?= $lastMonthName; ?> : <?= euro($lastMonth['total']); ?></div>
                        </div>
                        <div class="text-end">
                            <div class="value"><?= euro($thisMonth['total']); ?></div>
                            <?= trend($thisMonth['total'], $lastMonth['total']); ?>
                        </div>
                    </div>
                    <div class="kpi">
                        <div>
                            <div class="label">Encaissé</div>
                            <div class="before"><?= $lastMonthName; ?> : <?= euro($lastMonth['encaisse']); ?></div>
                        </div>
                        <div class="text-end">
                            <div class="value"><?= euro($thisMonth['encaisse']); ?></div>
                            <?= trend($thisMonth['encaisse'], $lastMonth['encaisse']); ?>
                        </div>
                    </div>
                    <div class="kpi">
                        <div>
                            <div class="label">Panier moyen</div>
                            <div class="before"><?= $lastMonthName; ?> : <?= euro($lastMonth['panier']); ?></div>
                        </div>
                        <div class="text-end">
                            <div class="value"><?= euro($thisMonth['panier']); ?></div>
                            <?= trend($thisMonth['panier'], $lastMonth['panier']); ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>