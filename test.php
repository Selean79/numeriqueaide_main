<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Удаление заказа
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM commandes WHERE id = :id OR id_commande = :id");
        $stmt->execute([':id' => $delete_id]);

        $queryString = http_build_query(array_diff_key($_GET, ['delete_id' => '']));
        header("Location: commandes_list.php?deleted=1&" . $queryString);
        exit;
    } catch (PDOException $e) {
        $error_message = "Ошибка при удалении: " . $e->getMessage();
    }
}

// Сброс фильтров
if (isset($_GET['clear_filter'])) {
    unset($_SESSION['cmd_search'], $_SESSION['cmd_status'], $_SESSION['cmd_date_start'], $_SESSION['cmd_date_end'], $_SESSION['cmd_month'], $_SESSION['cmd_year'], $_SESSION['cmd_sort_col'], $_SESSION['cmd_sort_dir']);
    header("Location: commandes_list.php");
    exit;
}

// Сохранение фильтров в сессию (или установка текущего месяца по умолчанию, если фильтры еще не заданы)
if (isset($_GET['search']) || isset($_GET['status']) || isset($_GET['month']) || isset($_GET['year'])) {
    $_SESSION['cmd_search'] = trim($_GET['search'] ?? '');
    $_SESSION['cmd_status'] = trim($_GET['status'] ?? '');

    $month = (isset($_GET['month']) && $_GET['month'] !== '') ? (int)$_GET['month'] : null;
    $year  = (isset($_GET['year'])  && $_GET['year']  !== '') ? (int)$_GET['year']  : null;

    $_SESSION['cmd_month'] = $month;
    $_SESSION['cmd_year']  = $year;

    if ($month && $year) {
        $_SESSION['cmd_date_start'] = sprintf('%04d-%02d-01', $year, $month);
        $_SESSION['cmd_date_end']   = date('Y-m-t', strtotime($_SESSION['cmd_date_start']));
    } else {
        $_SESSION['cmd_date_start'] = '';
        $_SESSION['cmd_date_end']   = '';
    }
} elseif (!array_key_exists('cmd_date_start', $_SESSION)) {
    $_SESSION['cmd_month']      = (int)date('n');
    $_SESSION['cmd_year']       = (int)date('Y');
    $_SESSION['cmd_date_start'] = date('Y-m-01');
    $_SESSION['cmd_date_end']   = date('Y-m-t');
}

$search        = $_SESSION['cmd_search'] ?? '';
$status_filter = $_SESSION['cmd_status'] ?? '';
$date_start    = $_SESSION['cmd_date_start'] ?? '';
$date_end      = $_SESSION['cmd_date_end'] ?? '';
$selected_month = $_SESSION['cmd_month'] ?? (int)date('n');
$selected_year  = $_SESSION['cmd_year']  ?? (int)date('Y');

// Управление сортировкой по клику на заголовки
if (isset($_GET['sort_col'])) {
    $requested_col = $_GET['sort_col'];
    if (($_SESSION['cmd_sort_col'] ?? '') === $requested_col) {
        $_SESSION['cmd_sort_dir'] = (($_SESSION['cmd_sort_dir'] ?? 'DESC') === 'ASC') ? 'DESC' : 'ASC';
    } else {
        $_SESSION['cmd_sort_col'] = $requested_col;
        $_SESSION['cmd_sort_dir'] = 'ASC';
    }
}

$sort_col = $_SESSION['cmd_sort_col'] ?? 'date_commande';
$sort_dir = $_SESSION['cmd_sort_dir'] ?? 'DESC';

// Динамическое определение наименований столбцов
$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : (in_array('name', $platCols) ? 'name' : $platCols[1]);
$platNameCol = "p.`$platColName`";

$pmCols = $pdo->query("SHOW COLUMNS FROM modes_de_paiement")->fetchAll(PDO::FETCH_COLUMN);
$pmColName = in_array('nom', $pmCols) ? 'nom' : (in_array('name', $pmCols) ? 'name' : $pmCols[1]);
$pmNameCol = "pm.`$pmColName`";

// SQL запрос с добавлением телефона и адреса клиента
$sql = "
    SELECT  
        c.*,
        CONCAT(COALESCE(cl.nom, ''), ' ', COALESCE(cl.prenom, '')) AS client_name,
        cl.telephone AS client_telephone,
        cl.adresse AS client_adresse,
        $platNameCol AS platform_name,
        $pmNameCol AS payment_method_name,
        f.facture_number
    FROM commandes c
    LEFT JOIN clients cl ON c.client_id = cl.id
    LEFT JOIN platforms p ON c.platform_id = p.id
    LEFT JOIN modes_de_paiement pm ON c.payment_method_id = pm.id
    LEFT JOIN factures f ON c.facture_id = f.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (c.id_commande LIKE :search OR cl.nom LIKE :search OR cl.prenom LIKE :search OR cl.telephone LIKE :search OR cl.adresse LIKE :search OR c.notes LIKE :search OR c.commentaire LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status_filter)) {
    $sql .= " AND c.statut = :status";
    $params[':status'] = $status_filter;
}

if (!empty($date_start)) {
    $sql .= " AND c.date_commande >= :date_start";
    $params[':date_start'] = $date_start . ' 00:00:00';
}
if (!empty($date_end)) {
    $sql .= " AND c.date_commande <= :date_end";
    $params[':date_end'] = $date_end . ' 23:59:59';
}

// Безопасное сопоставление колонок для сортировки
$allowed_sorts = [
        'id_commande'         => 'c.id_commande',
        'date_commande'       => 'c.date_commande',
        'client_name'         => 'client_name',
        'platform_name'       => 'platform_name',
        'facture_number'      => 'f.facture_number',
        'payment_method_name' => 'payment_method_name',
        'montant'             => 'c.montant',
        'statut'              => 'c.statut'
];

$sql_sort_field = $allowed_sorts[$sort_col] ?? 'c.date_commande';
$sql .= " ORDER BY $sql_sort_field $sort_dir, c.id_commande DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $commandes = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Ошибка загрузки заказов: " . htmlspecialchars($e->getMessage()));
}

$total_montant = 0;
$total_impot   = 0;
$total_epargne = 0;

foreach ($commandes as $order) {
    $orderStatusRaw = mb_strtolower(trim($order['statut'] ?? ''));
    $orderIsCancelled = in_array($orderStatusRaw, ['annulee', 'annulée', 'annulée', 'отменен']);

    if ($orderIsCancelled) {
        continue;
    }

    $m = (float)($order['montant'] ?? 0);
    $total_montant += $m;

    $impVal = (float)($order['calcul_impot'] ?? 0);
    $impAmount = ($impVal == 1) ? ($m * 0.212) : $impVal;
    $total_impot += $impAmount;

    $epVal = (float)($order['calcul_epargne'] ?? 0);
    $epAmount = ($epVal == 1) ? ($m * 0.10) : $epVal;
    $total_epargne += $epAmount;
}

// Функция для вывода заголовка со стрелочкой сортировки
function renderTh($colKey, $label, $alignClass = '') {
    global $sort_col, $sort_dir;
    $icon = '';
    if ($sort_col === $colKey) {
        $icon = ($sort_dir === 'ASC') ? ' <i class="bi bi-arrow-up-short"></i>' : ' <i class="bi bi-arrow-down-short"></i>';
    }
    echo "<th class=\"$alignClass\"><a href=\"?sort_col=$colKey\" class=\"text-white text-decoration-none\">$label$icon</a></th>";
}

require_once 'header.php';
?>

<title>Liste des commandes — NumériqueAide</title>

<style>
    .order-divider {
        border-bottom: 3px solid #94a3b8 !important;
    }
    .note-comment {
        background-color: #d9f2df;
    }
    .note-info {
        background-color: #fdf3cf;
    }
    .order-group-even {
        background-color: #f8f9fb;
    }
    .order-group-odd {
        background-color: #ffffff;
    }
    .table-header-custom th,
    .table-header-custom td {
        background-color: #82e89e !important; /* нужный цвет */
        color: #020202 !important;
    }
    .table-header-custom th a {
        color: #020202 !important;
    }
    .totals-badge {
        background-color: #000000 !important;
        color: #ffffff !important;
        font-weight: bold !important;
        white-space: nowrap !important;
        padding: 2px 8px;
        border-radius: 4px;
        display: inline-block;
    }
</style>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-cart-check me-2"></i>Liste des commandes</h3>
        <a href="add_commande.php" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i> Créer une commande
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            La commande a été supprimée avec succès!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Фильтры и поиск -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Recherche</label>
                    <input type="text" name="search" class="form-control" placeholder="numéro de commande, client..." value="<?= htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Mois</label>
                    <select name="month" class="form-select">
                        <option value="">Tout</option>
                        <?php
                        $monthNames = [
                                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
                        ];
                        foreach ($monthNames as $mNum => $mName):
                            ?>
                            <option value="<?= $mNum; ?>" <?= ((int)$selected_month === $mNum) ? 'selected' : ''; ?>><?= $mName; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Année</label>
                    <select name="year" class="form-select">
                        <option value="">Все</option>
                        <?php
                        $currentYear = (int)date('Y');
                        for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++):
                            ?>
                            <option value="<?= $y; ?>" <?= ((int)$selected_year === $y) ? 'selected' : ''; ?>><?= $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Statut</label>
                    <select name="status" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="Prévu" <?= $status_filter === 'Prévu' ? 'selected' : ''; ?>>Prévu</option>
                        <option value="En cours" <?= $status_filter === 'En cours' ? 'selected' : ''; ?>>En cours</option>
                        <option value="Payé" <?= $status_filter === 'Payé' ? 'selected' : ''; ?>>Payé</option>
                        <option value="Annulée" <?= $status_filter === 'Annulée' ? 'selected' : ''; ?>>Annulée</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary flex-grow-1" title="Применить"><i class="bi bi-search me-1"></i> Trouver</button>
                    <a href="commandes_list.php?clear_filter=1" class="btn btn-outline-secondary" title="Сбросить фильтр"><i class="bi bi-x-circle"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица заказов -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 1200px;">
                    <thead class="table-header-custom">
                    <tr>
                        <?php renderTh('id_commande', '№ de сommande', 'style="width: 110px;"'); ?>
                        <?php renderTh('date_commande', 'Date', 'style="width: 110px;"'); ?>
                        <?php renderTh('client_name', 'Client', 'style="width: 240px;"'); ?>
                        <?php renderTh('platform_name', 'Plateforme', 'style="width: 120px;"'); ?>
                        <?php renderTh('facture_number', 'Facture', 'style="width: 100px;"'); ?>
                        <?php renderTh('payment_method_name', 'Paiement', 'style="width: 130px;"'); ?>
                        <?php renderTh('montant', 'Montant', 'text-end style="width: 120px;"'); ?>
                        <th style="width: 150px;" class="text-center">Taxe</th>
                        <th style="width: 150px;" class="text-center">Cumul</th>
                        <?php renderTh('statut', 'Statut', 'text-center style="width: 120px;"'); ?>
                        <th style="width: 100px;" class="text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($commandes)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">Aucune commande trouvée</td>
                        </tr>
                    <?php else: ?>
                        <?php $rowIndex = 0; ?>
                        <?php foreach ($commandes as $order): ?>
                            <?php
                            $rowIndex++;
                            $groupClass = ($rowIndex % 2 === 0) ? 'order-group-even' : 'order-group-odd';

                            $montant = (float)($order['montant'] ?? 0);

                            $impotVal = (float)($order['calcul_impot'] ?? 0);
                            $impotAmount = ($impotVal == 1) ? ($montant * 0.212) : $impotVal;

                            $epargneVal = (float)($order['calcul_epargne'] ?? 0);
                            $epargneAmount = ($epargneVal == 1) ? ($montant * 0.10) : $epargneVal;

                            $platformName = trim($order['platform_name'] ?? 'Privé');
                            $platBadgeClass = 'bg-danger';
                            if (strcasecmp($platformName, 'Yoojo') === 0) {
                                $platBadgeClass = 'bg-primary';
                            } elseif (strcasecmp($platformName, 'NeedHelp') === 0) {
                                $platBadgeClass = 'bg-success';
                            }

                            $statusRaw = trim($order['statut'] ?? '');
                            $statusBadge = 'bg-secondary';
                            $statusLabel = $statusRaw;
                            $isCancelled = false;
                            switch (mb_strtolower($statusRaw)) {
                                case 'prévu': case 'prevu': case 'запланирован':
                                $statusBadge = 'bg-success'; $statusLabel = 'Prévu'; break;
                                case 'en cours': case 'en_cours': case 'в работе':
                                $statusBadge = 'bg-warning text-dark'; $statusLabel = 'En cours'; break;
                                case 'payé': case 'paye': case 'terminee': case 'оплачен':
                                $statusBadge = 'bg-secondary'; $statusLabel = 'Payé'; break;
                                case 'annulee': case 'annulée': case 'отменен':
                                $isCancelled = true; $statusLabel = 'Annulée'; break;
                            }

                            if ($isCancelled) {
                                $montant = 0;
                                $impotAmount = 0;
                                $epargneAmount = 0;
                            }

                            $hasNotes = !empty($order['notes']) || !empty($order['commentaire']);
                            ?>
                            <tr class="<?= $groupClass; ?>">
                                <td class="fw-bold text-nowrap"><?= htmlspecialchars($order['id_commande']); ?></td>
                                <td class="text-nowrap"><?= date('d.m.Y', strtotime($order['date_commande'])); ?></td>
                                <td class="fw-semibold">
                                    <?= !empty(trim($order['client_name'])) ? htmlspecialchars(trim($order['client_name'])) : '<span class="text-muted">—</span>'; ?>

                                    <?php if (!empty($order['client_telephone']) || !empty($order['client_adresse'])): ?>
                                        <div class="small text-muted fw-normal mt-1">
                                            <?php if (!empty($order['client_telephone'])): ?>
                                                <div><i class="bi bi-telephone me-1 text-primary"></i><a href="tel:<?= htmlspecialchars($order['client_telephone']); ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($order['client_telephone']); ?></a></div>
                                            <?php endif; ?>
                                            <?php if (!empty($order['client_adresse'])): ?>
                                                <div><i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars($order['client_adresse']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $platBadgeClass; ?>"><?= htmlspecialchars($platformName); ?></span></td>
                                <td><?= !empty($order['facture_number']) ? htmlspecialchars($order['facture_number']) : '<span class="text-muted">—</span>'; ?></td>
                                <td><?= !empty($order['payment_method_name']) ? htmlspecialchars($order['payment_method_name']) : '<span class="text-muted">—</span>'; ?></td>

                                <td class="text-end fw-bold"><?= number_format($montant, 2, ',', ' '); ?> €</td>

                                <td class="text-center text-nowrap">
                                    <?php if ($impotAmount > 0): ?>
                                        <?php if (!empty($order['impot_paye'])): ?>
                                            <span class="badge bg-success" title="Налог оплачен"><i class="bi bi-check-all me-1"></i><?= number_format($impotAmount, 2, ',', ' '); ?> €</span>
                                        <?php else: ?>
                                            <span class="text-primary fw-semibold"><i class="bi bi-clock me-1"></i><?= number_format($impotAmount, 2, ',', ' '); ?> €</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">0,00 €</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center text-nowrap">
                                    <?php if ($epargneAmount > 0): ?>
                                        <?php if (!empty($order['epargne_paye'])): ?>
                                            <span class="badge bg-success" title="Накопления переведены"><i class="bi bi-check-all me-1"></i><?= number_format($epargneAmount, 2, ',', ' '); ?> €</span>
                                        <?php else: ?>
                                            <span class="text-primary fw-semibold"><i class="bi bi-clock me-1"></i><?= number_format($epargneAmount, 2, ',', ' '); ?> €</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">0,00 €</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <span class="badge <?= $isCancelled ? 'bg-danger' : $statusBadge; ?>"><?= htmlspecialchars($statusLabel); ?></span>
                                    <?php if (!empty($order['date_paiement'])): ?>
                                        <div class="small text-success mt-1 text-nowrap">
                                            <i class="bi bi-calendar-check me-1"></i><?= date('d.m.Y', strtotime($order['date_paiement'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center text-nowrap">
                                    <a href="edit_commande.php?id=<?= $order['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Редактировать">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="commandes_list.php?delete_id=<?= $order['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>

                            <?php if ($hasNotes): ?>
                                <tr class="order-divider <?= $groupClass; ?>">
                                    <td colspan="11" class="pt-0 pb-2 ps-4 small" style="color: #2b2b2b;">
                                        <?php if (!empty($order['commentaire'])): ?>
                                            <span class="note-comment me-2 d-inline-block px-2 py-1 rounded"><i class="bi bi-chat-left-text text-primary me-1"></i><strong class="text-dark">Commentaire:</strong> <?= htmlspecialchars($order['commentaire']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($order['notes'])): ?>
                                            <span class="note-info d-inline-block px-2 py-1 rounded"><i class="bi bi-journal-text text-success me-1"></i><strong class="text-dark">Notes:</strong> <?= htmlspecialchars($order['notes']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr class="order-divider <?= $groupClass; ?>">
                                    <td colspan="11" style="display: none;"></td>
                                </tr>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>

                    <!-- Итоговая строка -->
                    <?php if (!empty($commandes)): ?>
                        <tfoot class="table-header-custom">
                        <tr class="totals-row">
                            <td colspan="6" class="text-end">Total:</td>
                            <td class="text-end fs-6"><span class="totals-badge"><?= number_format($total_montant, 2, ',', ' '); ?> €</span></td>
                            <td class="text-center"><span class="totals-badge"><?= number_format($total_impot, 2, ',', ' '); ?> €</span></td>
                            <td class="text-center"><span class="totals-badge"><?= number_format($total_epargne, 2, ',', ' '); ?> €</span></td>
                            <td colspan="2"></td>
                        </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Кнопка прокрутки вверх -->
<button type="button" class="btn btn-primary btn-lg rounded-circle shadow" id="btn-back-to-top"
        style="position: fixed; bottom: 20px; right: 20px; display: none; z-index: 9999;">
    <i class="bi bi-arrow-up"></i>
</button>

<script>
    let mybutton = document.getElementById("btn-back-to-top");
    window.onscroll = function () {
        if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
            mybutton.style.display = "block";
        } else {
            mybutton.style.display = "none";
        }
    };
    mybutton.addEventListener("click", function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
</script>
</body>
</html>