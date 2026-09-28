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

    /* Текущая дата в правом верхнем углу */
    .today-box {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #ffffff;
        border-radius: 10px;
        padding: 8px 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,.08);
        font-size: 1rem;
        color: #374151;
    }
    .today-box .bi { color: #0d6efd; }
    .today-day { font-weight: 700; color: #111827; }
    .today-date { color: #4b5563; }

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
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #14532d;
        padding: 10px 18px;
        background: #d1f2db;
        border-top: 1px solid #b7e4c4;
        border-bottom: 1px solid #b7e4c4;
    }
    .day-title { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .day-title .day-total {
        text-transform: none;
        letter-spacing: 0;
        font-size: .92rem;
        font-weight: 800;
        color: #14532d;
        background: rgba(255, 255, 255, .7);
        padding: 2px 10px;
        border-radius: 999px;
        white-space: nowrap;
    }
    .day-title.is-today { color: #052e16; background: #82e89e; border-color: #6fd98c; }

    /* Сворачиваемые дни */
    summary.day-title { cursor: pointer; list-style: none; user-select: none; }
    summary.day-title::-webkit-details-marker { display: none; }
    summary.day-title:hover { filter: brightness(.97); }
    .day-chevron { display: inline-block; margin-right: 6px; font-size: .8rem; transition: transform .2s; }
    details[open] > summary .day-chevron { transform: rotate(90deg); }
    .day-title.is-today .day-total { background: #ffffff; color: #052e16; }
    .day-title .count { font-weight: 600; color: #3f7a52; margin-left: 6px; }
    .day-title.is-today .count { color: #14532d; }

    .intervention {
        padding: 14px 18px;
        border-top: 1px solid #f1f3f5;
    }
    .intervention:hover { background: #fafbfc; }

    /* Шапка карточки: время · клиент · сумма */
    .iv-head {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .rdv-time-badge {
        background-color: #dbeafe;
        color: #1e40af;
        padding: 4px 10px;
        border-radius: 8px;
        font-weight: 700;
        font-size: .95rem;
        min-width: 58px;
        text-align: center;
        flex-shrink: 0;
    }
    .rdv-time-badge.is-empty { background: #f1f3f5; color: #9ca3af; }
    .iv-client {
        flex: 1;
        min-width: 0;
        font-weight: 700;
        font-size: 1.05rem;
        color: #111827;
        overflow-wrap: anywhere;
    }
    .iv-amount {
        text-align: right;
        white-space: nowrap;
        line-height: 1.25;
    }
    .iv-amount .badge { font-size: .7rem; }

    /* Содержимое — под именем клиента */
    .iv-content { padding-left: 70px; margin-top: 6px; }

    .iv-client-note {
        font-size: .85rem;
        color: #b91c1c;
        margin-bottom: 6px;
    }
    .iv-address {
        display: flex;
        gap: 8px;
        font-size: .9rem;
        color: #374151;
    }
    .iv-address > .bi { color: #dc3545; margin-top: 2px; }
    .iv-address-extra { color: #6b7280; font-size: .85rem; }

    .iv-job {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px dashed #e5e7eb;
    }
    .iv-comment { font-weight: 600; color: #111827; }
    .iv-comment .bi { color: #0d6efd; }
    .iv-note {
        display: inline-block;
        margin-top: 4px;
        padding: 3px 8px;
        border-radius: 6px;
        background: #fdf3cf;
        color: #78350f;
        font-size: .85rem;
    }

    .iv-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }
    /* Кнопка Waze в фирменном голубом цвете */
    .btn-waze {
        color: #0a7fa8;
        border: 1px solid #33ccff;
        background: #ffffff;
    }
    .btn-waze:hover, .btn-waze:active {
        color: #ffffff;
        background: #33ccff;
        border-color: #33ccff;
    }

    @media (max-width: 575.98px) {
        .intervention { padding: 12px 14px; }
        .iv-content { padding-left: 0; margin-top: 8px; }
        .iv-actions .btn { flex: 1 1 auto; }
        .iv-actions .btn.ms-auto { flex: 0 0 auto; }
    }

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
        .stat-cards .fs-3 { font-size: 1.35rem !important; }
    }
</style>

<div class="container-fluid mt-3 mt-md-4 px-2 px-md-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 mb-md-4">
        <h3 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2"></i>Tableau de bord (<?= $current_year; ?>)</h3>
        <div class="today-box">
            <i class="bi bi-calendar3 me-2"></i>
            <span class="today-day"><?= $fr_days[(int)date('w')]; ?></span>
            <span class="today-date"><?= (int)date('j') . ' ' . $fr_months[(int)date('n')] . ' ' . date('Y'); ?></span>
        </div>
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
                            <?php $dayTotal = array_sum(array_map(fn($x) => (float)$x['montant'], $orders)); ?>
                            <!-- Сегодня — раскрыт, остальные дни — свёрнуты -->
                            <details class="day-group" <?= $day === $today ? 'open' : ''; ?>>
                            <summary class="day-title <?= $day === $today ? 'is-today' : ''; ?>">
                                <span>
                                    <i class="bi bi-chevron-right day-chevron"></i>
                                    <?= dayLabel($day, $fr_days); ?>
                                    <span class="count">· <?= count($orders); ?> intervention<?= count($orders) > 1 ? 's' : ''; ?></span>
                                </span>
                                <span class="day-total"><?= euro($dayTotal); ?></span>
                            </summary>

                            <?php foreach ($orders as $o): ?>
                                <?php
                                $clientName  = trim($o['client_name']) ?: '—';
                                $clientNote  = trim($o['client_notes'] ?? '');
                                $adresse2    = trim(preg_replace('/\s+/', ' ', $o['adresse_2'] ?? ''));
                                $phoneDigits = preg_replace('/[^\d+]/', '', $o['telephone'] ?? '');
                                ?>
                                <div class="intervention">
                                    <!-- Время · клиент · сумма -->
                                    <div class="iv-head">
                                        <?php if (!empty($o['rdv_time'])): ?>
                                            <span class="rdv-time-badge"><?= substr($o['rdv_time'], 0, 5); ?></span>
                                        <?php else: ?>
                                            <span class="rdv-time-badge is-empty">—:—</span>
                                        <?php endif; ?>

                                        <div class="iv-client"><?= htmlspecialchars($clientName); ?></div>

                                        <div class="iv-amount">
                                            <div class="fw-bold"><?= euro((float)$o['montant']); ?></div>
                                            <span class="badge <?= platformBadge($o['platform_name']); ?>"><?= htmlspecialchars($o['platform_name']); ?></span>
                                        </div>
                                    </div>

                                    <div class="iv-content">
                                        <!-- Заметка о клиенте -->
                                        <?php if ($clientNote !== ''): ?>
                                            <div class="iv-client-note">
                                                <i class="bi bi-exclamation-circle-fill me-1"></i><?= htmlspecialchars($clientNote); ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Адрес -->
                                        <?php if (!empty($o['adresse']) || $adresse2 !== ''): ?>
                                            <div class="iv-address">
                                                <i class="bi bi-geo-alt-fill"></i>
                                                <div>
                                                    <?php if (!empty($o['adresse'])): ?>
                                                        <div><?= htmlspecialchars($o['adresse']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($adresse2 !== ''): ?>
                                                        <div class="iv-address-extra"><?= htmlspecialchars($adresse2); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Что сделать -->
                                        <?php if (!empty($o['commentaire']) || !empty($o['notes'])): ?>
                                            <div class="iv-job">
                                                <?php if (!empty($o['commentaire'])): ?>
                                                    <div class="iv-comment"><i class="bi bi-tools me-1"></i><?= htmlspecialchars($o['commentaire']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($o['notes'])): ?>
                                                    <div class="iv-note"><i class="bi bi-journal-text me-1"></i><?= htmlspecialchars($o['notes']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Действия -->
                                        <div class="iv-actions">
                                            <?php if ($phoneDigits !== ''): ?>
                                                <a href="tel:<?= $phoneDigits; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($o['telephone']); ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($o['adresse'])): ?>
                                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($o['adresse']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-sign-turn-right me-1"></i>Maps
                                                </a>
                                                <a href="https://waze.com/ul?q=<?= urlencode($o['adresse']); ?>&navigate=yes" target="_blank" class="btn btn-sm btn-waze">
                                                    <i class="bi bi-cursor-fill me-1"></i>Waze
                                                </a>
                                            <?php endif; ?>
                                            <a href="edit_commande.php?id=<?= (int)$o['id']; ?>" class="btn btn-sm btn-light ms-auto" title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </details>
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