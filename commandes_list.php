<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

/** @var PDO $pdo */
global $pdo;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Удаление заказа (одиночное)
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    try {
        $stmt = $pdo->prepare("
            DELETE FROM commandes
            WHERE id = :id OR id_commande = :id
        ");

        $stmt->execute([
            ':id' => $delete_id
        ]);

        $queryString = http_build_query(
            array_diff_key($_GET, ['delete_id' => ''])
        );

        header(
            "Location: commandes_list.php?deleted=1" .
            ($queryString ? '&' . $queryString : '')
        );

        exit;

    } catch (PDOException $e) {
        $error_message = "Ошибка при удалении: " . $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| Массовое удаление выбранных заказов
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['bulk_delete']) &&
    !empty($_POST['delete_ids'])
) {
    $ids = array_map('intval', $_POST['delete_ids']);
    $ids = array_values(array_filter($ids));

    if (count($ids) > 0) {
        try {
            $placeholders = implode(
                ',',
                array_fill(0, count($ids), '?')
            );

            $stmt = $pdo->prepare("
                DELETE FROM commandes
                WHERE id IN ($placeholders)
            ");

            $stmt->execute($ids);

            header("Location: commandes_list.php?deleted=multi");
            exit;

        } catch (PDOException $e) {
            $error_message = "Ошибка при массовом удалении: " . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Копирование выбранного заказа
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['bulk_copy']) &&
    !empty($_POST['delete_ids'])
) {
    $ids = array_map('intval', $_POST['delete_ids']);
    $ids = array_values(array_filter($ids));

    if (count($ids) === 1) {
        $sourceId = $ids[0];

        try {
            $stmtSrc = $pdo->prepare("
                SELECT *
                FROM commandes
                WHERE id = :id
                LIMIT 1
            ");

            $stmtSrc->execute([
                ':id' => $sourceId
            ]);

            $srcOrder = $stmtSrc->fetch(PDO::FETCH_ASSOC);

            if ($srcOrder) {
                $current_year = date('Y');
                $next_order_id = 'CMD-' . $current_year . '-1';

                $stmtMax = $pdo->query("
                    SELECT MAX(
                        CAST(
                            SUBSTRING_INDEX(id_commande, '-', -1)
                            AS UNSIGNED
                        )
                    )
                    FROM commandes
                    WHERE id_commande LIKE 'CMD-%'
                ");

                $max_id = $stmtMax->fetchColumn();

                if ($max_id) {
                    $next_num = (int)$max_id + 1;
                    $next_order_id = 'CMD-' . $current_year . '-' . $next_num;
                }

                $insertSql = "
                    INSERT INTO commandes (
                        id_commande,
                        date_commande,
                        rdv_time,
                        client_id,
                        platform_id,
                        payment_method_id,
                        facture_id,
                        montant,
                        statut,
                        date_paiement,
                        notes,
                        commentaire,
                        calcul_impot,
                        calcul_epargne,
                        impot_paye,
                        epargne_paye
                    )
                    VALUES (
                        :id_commande,
                        :date_commande,
                        :rdv_time,
                        :client_id,
                        :platform_id,
                        :payment_method_id,
                        :facture_id,
                        :montant,
                        :statut,
                        :date_paiement,
                        :notes,
                        :commentaire,
                        :calcul_impot,
                        :calcul_epargne,
                        :impot_paye,
                        :epargne_paye
                    )
                ";

                $insertStmt = $pdo->prepare($insertSql);

                $insertStmt->execute([
                    ':id_commande'         => $next_order_id,
                    ':date_commande'      => $srcOrder['date_commande'],
                    ':rdv_time'           => $srcOrder['rdv_time'] ?? null,
                    ':client_id'          => $srcOrder['client_id'],
                    ':platform_id'        => $srcOrder['platform_id'],
                    ':payment_method_id'  => $srcOrder['payment_method_id'],
                    ':facture_id'         => $srcOrder['facture_id'],
                    ':montant'            => $srcOrder['montant'],
                    ':statut'             => 'Prévu',
                    ':date_paiement'      => null,
                    ':notes'              => $srcOrder['notes'],
                    ':commentaire'        => $srcOrder['commentaire'],
                    ':calcul_impot'       => $srcOrder['calcul_impot'],
                    ':calcul_epargne'     => $srcOrder['calcul_epargne'],
                    ':impot_paye'         => 0,
                    ':epargne_paye'       => 0
                ]);

                $new_order_id = $pdo->lastInsertId();

                header("Location: commandes_list.php?copied=1&scroll=" . $new_order_id);
                exit;
            }

        } catch (PDOException $e) {
            $error_message = "Ошибка при копировании: " . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Сброс фильтров
|--------------------------------------------------------------------------
*/
if (isset($_GET['clear_filter'])) {
    unset(
        $_SESSION['cmd_search'],
        $_SESSION['cmd_status'],
        $_SESSION['cmd_date_start'],
        $_SESSION['cmd_date_end'],
        $_SESSION['cmd_month'],
        $_SESSION['cmd_year'],
        $_SESSION['cmd_sort_col'],
        $_SESSION['cmd_sort_dir']
    );

    header("Location: commandes_list.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Сохранение фильтров в сессию
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['search']) ||
    isset($_GET['status']) ||
    isset($_GET['month']) ||
    isset($_GET['year'])
) {
    $_SESSION['cmd_search'] = trim($_GET['search'] ?? '');
    $_SESSION['cmd_status'] = trim($_GET['status'] ?? '');

    $month = (isset($_GET['month']) && $_GET['month'] !== '') ? (int)$_GET['month'] : null;
    $year = (isset($_GET['year']) && $_GET['year'] !== '') ? (int)$_GET['year'] : null;

    $_SESSION['cmd_month'] = $month;
    $_SESSION['cmd_year'] = $year;

    if ($month && $year) {
        $_SESSION['cmd_date_start'] = sprintf('%04d-%02d-01', $year, $month);
        $_SESSION['cmd_date_end'] = date('Y-m-t', strtotime($_SESSION['cmd_date_start']));
    } else {
        $_SESSION['cmd_date_start'] = '';
        $_SESSION['cmd_date_end'] = '';
    }
} elseif (!array_key_exists('cmd_date_start', $_SESSION)) {
    $_SESSION['cmd_month'] = (int)date('n');
    $_SESSION['cmd_year'] = (int)date('Y');
    $_SESSION['cmd_date_start'] = date('Y-m-01');
    $_SESSION['cmd_date_end'] = date('Y-m-t');
}


$search = $_SESSION['cmd_search'] ?? '';
$status_filter = $_SESSION['cmd_status'] ?? '';
$date_start = $_SESSION['cmd_date_start'] ?? '';
$date_end = $_SESSION['cmd_date_end'] ?? '';
$selected_month = $_SESSION['cmd_month'] ?? (int)date('n');
$selected_year = $_SESSION['cmd_year'] ?? (int)date('Y');


/*
|--------------------------------------------------------------------------
| Управление сортировкой
|--------------------------------------------------------------------------
*/
if (isset($_GET['sort_col'])) {
    $requested_col = $_GET['sort_col'];

    if (($_SESSION['cmd_sort_col'] ?? '') === $requested_col) {
        $_SESSION['cmd_sort_dir'] = (($_SESSION['cmd_sort_dir'] ?? 'ASC') === 'ASC') ? 'DESC' : 'ASC';
    } else {
        $_SESSION['cmd_sort_col'] = $requested_col;
        $_SESSION['cmd_sort_dir'] = 'ASC';
    }
}

$sort_col = $_SESSION['cmd_sort_col'] ?? 'date_commande';
$sort_dir = $_SESSION['cmd_sort_dir'] ?? 'ASC';


/*
|--------------------------------------------------------------------------
| Динамические колонки
|--------------------------------------------------------------------------
*/
$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : (in_array('name', $platCols) ? 'name' : $platCols[1]);
$platNameCol = "p.`$platColName`";

$pmCols = $pdo->query("SHOW COLUMNS FROM modes_de_paiement")->fetchAll(PDO::FETCH_COLUMN);
$pmColName = in_array('nom', $pmCols) ? 'nom' : (in_array('name', $pmCols) ? 'name' : $pmCols[1]);
$pmNameCol = "pm.`$pmColName`";


/*
|--------------------------------------------------------------------------
| SQL запрос
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        c.*,
        CONCAT(
            COALESCE(cl.nom, ''),
            ' ',
            COALESCE(cl.prenom, '')
        ) AS client_name,
        cl.telephone AS client_telephone,
        cl.adresse AS client_adresse,
        cl.notes AS client_notes,
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
    $sql .= "
        AND (
            c.id_commande LIKE :search
            OR cl.nom LIKE :search
            OR cl.prenom LIKE :search
            OR cl.telephone LIKE :search
            OR cl.adresse LIKE :search
            OR c.notes LIKE :search
            OR c.commentaire LIKE :search
        )
    ";
    $params[':search'] = "%$search%";
}

if (!empty($status_filter)) {
    $sql .= " AND c.statut = :status ";
    $params[':status'] = $status_filter;
}

if (!empty($date_start)) {
    $sql .= " AND c.date_commande >= :date_start ";
    $params[':date_start'] = $date_start . ' 00:00:00';
}

if (!empty($date_end)) {
    $sql .= " AND c.date_commande <= :date_end ";
    $params[':date_end'] = $date_end . ' 23:59:59';
}

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

$sql .= "
    ORDER BY
        $sql_sort_field $sort_dir,
        c.rdv_time ASC,
        c.id_commande DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $commandes = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Ошибка загрузки заказов: " . htmlspecialchars($e->getMessage()));
}


/*
|--------------------------------------------------------------------------
| Итоги
|--------------------------------------------------------------------------
*/
$total_montant = 0;
$total_impot   = 0;
$total_epargne = 0;

foreach ($commandes as $order) {
    $orderStatusRaw = mb_strtolower(trim($order['statut'] ?? ''));
    $orderIsCancelled = in_array($orderStatusRaw, ['annulee', 'annulée', 'отменен']);

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


/*
|--------------------------------------------------------------------------
| Функция заголовка таблицы
|--------------------------------------------------------------------------
*/
function renderTh($colKey, $label, $alignClass = '') {
    global $sort_col, $sort_dir;
    $icon = '';

    if ($sort_col === $colKey) {
        $icon = ($sort_dir === 'ASC') ? ' <i class="bi bi-arrow-up-short"></i>' : ' <i class="bi bi-arrow-down-short"></i>';
    }

    echo '
        <th class="' . $alignClass . '">
            <a href="?sort_col=' . urlencode($colKey) . '" class="text-decoration-none">
                ' . $label . $icon . '
            </a>
        </th>
    ';
}

/*
|--------------------------------------------------------------------------
| Сортировка для мобильной версии (выпадающий список вместо заголовков)
|--------------------------------------------------------------------------
*/
$mobileSortOptions = [
        'date_commande'       => 'Date',
        'id_commande'         => '№ de commande',
        'client_name'         => 'Client',
        'platform_name'       => 'Plateforme',
        'montant'             => 'Montant',
        'statut'              => 'Statut'
];

require_once 'header.php';
?>

<title>Liste des commandes — NumériqueAide</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .order-divider { border-bottom: 3px solid #94a3b8 !important; }
    .note-comment { background-color: #d9f2df; }
    .note-info { background-color: #fdf3cf; }

    /* Оплаченные и отменённые заказы: комментарии и заметки без цветного фона, как основная строка */
    tr.row-status-paye .note-comment,
    tr.row-status-paye .note-info,
    tr.row-status-annulee .note-comment,
    tr.row-status-annulee .note-info {
        background-color: transparent !important;
        padding-left: 0 !important;
    }
    tr.row-status-paye .note-comment i,
    tr.row-status-paye .note-info i,
    tr.row-status-annulee .note-comment i,
    tr.row-status-annulee .note-info i {
        color: #6b7280 !important;
    }
    .order-group-even { background-color: #f8f9fb !important; }
    .order-group-odd { background-color: #ffffff !important; }

    tr.row-status-en-cours, tr.row-status-en-cours > td { background-color: #fffad6 !important; }
    tr.row-status-annulee, tr.row-status-annulee > td { background-color: #fee3e3 !important; }
    tr.row-status-paye, tr.row-status-paye > td { background-color: #e1e7eb !important; }

    .table-header-custom th, .table-header-custom td {
        background-color: #82e89e !important;
        color: #020202 !important;
    }
    .table-header-custom th a { color: #020202 !important; }

    .totals-badge { background-color: #ffffff !important; color: #14532d !important; font-weight: 700 !important; white-space: nowrap !important; padding: 3px 10px; border-radius: 6px; display: inline-block; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
    .rdv-time-badge { background-color: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 4px; font-weight: 600; display: inline-block; }

    #bulkActionButtons {
        position: sticky;
        top: 55px;
        z-index: 1050;
        background-color: transparent;
        padding: 8px 0;
        margin-bottom: 10px;
    }

    .orders-table { min-width: 1200px; }

    /* Колонка действий: показывается, когда выбран хотя бы один заказ */
    .actions-column { display: none; }
    .show-actions .actions-column { display: table-cell; }

    tr.row-selected, tr.row-selected > td { background-color: #eef7ff !important; }
    .order-actions .btn { min-width: 30px; padding: 1px 6px; }
    body { background-color: #d3d1d1 !important; }

    .flatpickr-calendar { z-index: 99999 !important; }

    /* Подсветка только что сохранённого заказа */
    tr.row-just-saved > td {
        background-color: #fff3a0 !important;
        transition: background-color .5s;
    }

    /* =========================================================
       МОБИЛЬНАЯ ВЕРСИЯ (экраны до 768px): таблица → карточки
       ========================================================= */
    @media (max-width: 767.98px) {
        h3 { font-size: 1.25rem; }

        .orders-card { background: transparent !important; box-shadow: none !important; border: none !important; }
        .orders-card .card-body { padding: 0 !important; }
        .orders-table { min-width: 0 !important; background: transparent; }
        .orders-table thead { display: none; }

        .orders-table,
        .orders-table tbody,
        .orders-table tfoot { display: block; width: 100%; }

        /* Основная строка заказа = верх карточки */
        .orders-table tr.order-main {
            display: block;
            position: relative;
            margin-top: 12px;
            border-radius: 10px 10px 0 0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,.12);
        }
        .orders-table tr.order-main > td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 6px 12px;
            border: none;
            text-align: right !important;
            white-space: normal !important;
            background-color: #ffffff;
        }
        .orders-table tr.order-main > td[data-label]::before {
            content: attr(data-label);
            font-weight: 600;
            color: #6b7280;
            text-align: left;
            flex-shrink: 0;
        }

        /* Чекбокс — в правом верхнем углу карточки */
        .orders-table tr.order-main > td.cell-check {
            position: absolute;
            top: 6px;
            right: 4px;
            padding: 4px 8px;
            background: transparent !important;
            z-index: 2;
        }
        .orders-table tr.order-main > td.cell-check .form-check-input { width: 1.4em; height: 1.4em; }

        /* Номер заказа — заголовок карточки */
        .orders-table tr.order-main > td.cell-id {
            font-size: 1.05rem;
            padding: 10px 50px 8px 12px;
            border-bottom: 1px solid rgba(0,0,0,.08);
            justify-content: flex-start;
        }

        /* Клиент — подпись сверху, данные под ней */
        .orders-table tr.order-main > td.cell-client {
            flex-direction: column;
            align-items: flex-start;
            text-align: left !important;
            gap: 2px;
        }

        /* Кнопки действий на мобильном */
        .orders-table tr.order-main > td.actions-column { display: none; }
        .show-actions .orders-table tr.order-main > td.actions-column {
            display: flex;
            justify-content: flex-end;
            border-top: 1px solid rgba(0,0,0,.08);
        }

        /* Строка заметок = низ карточки */
        .orders-table tr.order-notes {
            display: block;
            border-radius: 0 0 10px 10px;
            overflow: hidden;
            border-bottom: none !important;
            box-shadow: 0 1px 3px rgba(0,0,0,.12);
        }
        .orders-table tr.order-notes > td {
            display: block;
            padding: 8px 12px !important;
            border: none;
        }
        .orders-table tr.order-notes > td .d-inline-block { display: block !important; margin: 0 0 6px 0 !important; }

        /* Пустой список */
        .orders-table tr.row-empty, .orders-table tr.row-empty > td { display: block; background: #fff; border-radius: 10px; }

        /* Итоги — отдельная карточка внизу */
        .orders-table tfoot tr {
            display: block;
            margin-top: 16px;
            border-radius: 10px;
            overflow: hidden;
        }
        .orders-table tfoot td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 12px;
            border: none;
        }
        .orders-table tfoot td[data-label]::before {
            content: attr(data-label);
            font-weight: 600;
        }
        .orders-table tfoot td.cell-total-title { justify-content: flex-start; font-weight: 700; font-size: 1.05rem; }
        .orders-table tfoot td.cell-empty { display: none; }

        /* Панель кнопок */
        #bulkActionButtons { top: 56px; flex-wrap: wrap; }
        #bulkActionButtons .btn { flex: 1 1 auto; }

        /* Плавающие кнопки прокрутки поменьше */
        #btn-back-to-top, #btn-back-to-bottom { width: 44px; height: 44px; padding: 0; font-size: 1.1rem; right: 12px !important; }
    }
</style>

<div class="container-fluid mt-3 mt-md-4 px-2 px-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-cart-check me-2"></i>Liste des commandes</h3>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <?php if ($_GET['deleted'] === 'multi'): ?>
                Sélection de commandes supprimée avec succès!
            <?php else: ?>
                La commande a été supprimée avec succès!
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            La commande a été ajoutée avec succès!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            La commande a été modifiée avec succès!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['copied'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            La commande a été copiée avec succès!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3 mb-md-4">
        <div class="card-body p-2 p-md-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1" for="search-input">Recherche</label>
                    <input type="text" id="search-input" name="search" class="form-control" placeholder="numéro de commande, client..." value="<?= htmlspecialchars($search); ?>">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1" for="month-select">Mois</label>
                    <select id="month-select" name="month" class="form-select">
                        <option value="">Tout</option>
                        <?php
                        $monthNames = [
                                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
                        ];
                        foreach ($monthNames as $mNum => $mName):
                            ?>
                            <option value="<?= $mNum; ?>" <?= ((int)$selected_month === $mNum) ? 'selected' : ''; ?>>
                                <?= $mName; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1" for="year-select">Année</label>
                    <select id="year-select" name="year" class="form-select">
                        <option value="">Все</option>
                        <?php
                        $currentYear = (int)date('Y');
                        for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++):
                            ?>
                            <option value="<?= $y; ?>" <?= ((int)$selected_year === $y) ? 'selected' : ''; ?>>
                                <?= $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1" for="status-select">Statut</label>
                    <select id="status-select" name="status" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="Prévu" <?= $status_filter === 'Prévu' ? 'selected' : ''; ?>>Prévu</option>
                        <option value="En cours" <?= $status_filter === 'En cours' ? 'selected' : ''; ?>>En cours</option>
                        <option value="Payé" <?= $status_filter === 'Payé' ? 'selected' : ''; ?>>Payé</option>
                        <option value="Annulée" <?= $status_filter === 'Annulée' ? 'selected' : ''; ?>>Annulée</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary flex-grow-1" title="Применить">
                        <i class="bi bi-search me-1"></i>Trouver
                    </button>
                    <a href="commandes_list.php?clear_filter=1" class="btn btn-outline-secondary" title="Сбросить фильтр">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>

            <!-- Сортировка для телефона (на компьютере сортировка по заголовкам таблицы) -->
            <div class="d-md-none mt-2">
                <label class="form-label small text-muted mb-1" for="mobile-sort">Trier par</label>
                <div class="d-flex gap-1">
                    <select id="mobile-sort" class="form-select" onchange="if (this.value) window.location.href='?sort_col=' + encodeURIComponent(this.value);">
                        <?php foreach ($mobileSortOptions as $key => $label): ?>
                            <option value="<?= $key; ?>" <?= $sort_col === $key ? 'selected' : ''; ?>><?= $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <a href="?sort_col=<?= urlencode($sort_col); ?>" class="btn btn-outline-secondary" title="Changer l'ordre">
                        <i class="bi <?= $sort_dir === 'ASC' ? 'bi-sort-up' : 'bi-sort-down'; ?>"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" id="bulkActionForm">
        <div id="bulkActionButtons" class="d-flex justify-content-end gap-2 mb-2">
            <button type="button" class="btn btn-success btn-sm shadow-sm" onclick="openCommandeModal('add_commande.php?modal=1', 'Créer une commande', 'bg-success')">
                <i class="bi bi-plus-circle me-1"></i>Créer une commande
            </button>
            <button type="submit" name="bulk_copy" id="bulkCopyBtn" class="btn btn-success btn-sm d-none">
                <i class="bi bi-files me-1"></i>Copy
            </button>
            <button type="submit" name="bulk_delete" id="bulkDeleteBtn" class="btn btn-danger btn-sm d-none" onclick="return confirm('Êtes-vous sûr de vouloir supprimer les commandes sélectionnées?');">
                <i class="bi bi-trash me-1"></i>Supprimer la sélection
            </button>
        </div>

        <div class="card shadow-sm orders-card">
            <div class="card-body p-0">
                <div class="table-responsive" id="ordersWrapper">
                    <table class="table table-hover align-middle mb-0 orders-table">
                        <thead class="table-header-custom">
                        <tr>
                            <th style="width: 50px;" class="text-center">
                                <input type="checkbox" class="form-check-input" id="selectAll" aria-label="Tout sélectionner">
                            </th>
                            <th style="width: 90px;" class="text-center actions-column" id="actionsHeader">Actions</th>
                            <?php renderTh('id_commande', '№ de сommande', 'text-nowrap'); ?>
                            <?php renderTh('date_commande', 'Date', 'text-nowrap'); ?>
                            <?php renderTh('client_name', 'Client', 'text-nowrap'); ?>
                            <?php renderTh('platform_name', 'Plateforme', 'text-nowrap'); ?>
                            <?php renderTh('facture_number', 'Facture', 'text-nowrap'); ?>
                            <?php renderTh('payment_method_name', 'Paiement', 'text-nowrap'); ?>
                            <?php renderTh('montant', 'Montant', 'text-end text-nowrap'); ?>
                            <th style="width: 150px;" class="text-center">Taxe</th>
                            <th style="width: 150px;" class="text-center">Cumul</th>
                            <?php renderTh('statut', 'Statut', 'text-center text-nowrap'); ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($commandes)): ?>
                            <tr class="row-empty">
                                <td colspan="12" class="text-center py-4 text-muted">Aucune commande trouvée</td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $rowIndex = 0;
                            foreach ($commandes as $order):
                                $rowIndex++;
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
                                $rowStatusClass = ($rowIndex % 2 === 0) ? 'order-group-even' : 'order-group-odd';

                                switch (mb_strtolower($statusRaw)) {
                                    case 'prévu':
                                    case 'prevu':
                                    case 'запланирован':
                                        $statusBadge = 'bg-success';
                                        $statusLabel = 'Prévu';
                                        break;
                                    case 'en cours':
                                    case 'en_cours':
                                    case 'в работе':
                                        $statusBadge = 'bg-warning text-dark';
                                        $statusLabel = 'En cours';
                                        $rowStatusClass = 'row-status-en-cours';
                                        break;
                                    case 'payé':
                                    case 'paye':
                                    case 'terminee':
                                    case 'оплачен':
                                        $statusBadge = 'bg-secondary';
                                        $statusLabel = 'Payé';
                                        $rowStatusClass = 'row-status-paye';
                                        break;
                                    case 'annulee':
                                    case 'annulée':
                                    case 'отменен':
                                        $isCancelled = true;
                                        $statusLabel = 'Annulée';
                                        $rowStatusClass = 'row-status-annulee';
                                        break;
                                }

                                if ($isCancelled) {
                                    $montant = 0;
                                    $impotAmount = 0;
                                    $epargneAmount = 0;
                                }

                                $hasNotes = !empty($order['notes']) || !empty($order['commentaire']);
                                ?>
                                <tr id="order-<?= (int)$order['id']; ?>" class="order-main <?= $rowStatusClass; ?>">
                                    <td class="text-center cell-check">
                                        <input type="checkbox" name="delete_ids[]" value="<?= (int)$order['id']; ?>" class="form-check-input order-checkbox" aria-label="Sélectionner la commande">
                                    </td>
                                    <td class="text-center text-nowrap actions-column">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="openCommandeModal('edit_commande.php?id=<?= (int)$order['id']; ?>&modal=1', 'Modifier la commande', 'bg-primary')" title="Редактировать">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="commandes_list.php?delete_id=<?= (int)$order['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande?');" title="Удалить">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                    <td class="fw-bold text-nowrap cell-id"><?= htmlspecialchars($order['id_commande']); ?></td>
                                    <td class="text-nowrap" data-label="Date">
                                        <div>
                                            <?= date('d.m.Y', strtotime($order['date_commande'])); ?>
                                            <?php if (!empty($order['rdv_time'])): ?>
                                                <br><small class="text-muted"><span class="rdv-time-badge mt-1"><i class="bi bi-clock me-1"></i><?= substr($order['rdv_time'], 0, 5); ?></span></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="fw-semibold cell-client" data-label="Client">
                                        <div>
                                            <?php if (!empty(trim($order['client_name']))): ?>
                                                <a href="#" onclick="openClientModal('edit_client.php?id=<?= (int)$order['client_id']; ?>&modal=1', 'Modifier le client', 'bg-primary'); return false;" class="text-decoration-none text-dark" title="Modifier le client">
                                                    <?= htmlspecialchars(trim($order['client_name'])); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>

                                            <?php if (!empty(trim($order['client_notes'] ?? ''))): ?>
                                                <i class="bi bi-exclamation-circle-fill text-danger ms-1" style="cursor: pointer;" title="Client note: <?= htmlspecialchars($order['client_notes']); ?>"></i>
                                            <?php endif; ?>

                                            <?php if (!empty($order['client_telephone']) || !empty($order['client_adresse'])): ?>
                                                <div class="small text-muted fw-normal mt-1">
                                                    <?php if (!empty($order['client_telephone'])): ?>
                                                        <div>
                                                            <i class="bi bi-telephone me-1 text-primary"></i>
                                                            <a href="tel:<?= htmlspecialchars($order['client_telephone']); ?>" class="text-decoration-none text-muted">
                                                                <?= htmlspecialchars($order['client_telephone']); ?>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($order['client_adresse'])): ?>
                                                        <div>
                                                            <i class="bi bi-geo-alt me-1 text-danger"></i>
                                                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($order['client_adresse']); ?>" target="_blank" class="text-decoration-none text-muted">
                                                                <?= htmlspecialchars($order['client_adresse']); ?>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Plateforme"><span class="badge <?= $platBadgeClass; ?>"><?= htmlspecialchars($platformName); ?></span></td>
                                    <td data-label="Facture"><?= !empty($order['facture_number']) ? htmlspecialchars($order['facture_number']) : '<span class="text-muted">—</span>'; ?></td>
                                    <td data-label="Paiement"><?= !empty($order['payment_method_name']) ? htmlspecialchars($order['payment_method_name']) : '<span class="text-muted">—</span>'; ?></td>
                                    <td class="text-end fw-bold" data-label="Montant"><?= number_format($montant, 2, ',', ' '); ?> €</td>
                                    <td class="text-center text-nowrap" data-label="Taxe">
                                        <?php if ($impotAmount > 0): ?>
                                            <?php if (!empty($order['impot_paye'])): ?>
                                                <span class="badge bg-success" title="Налог оплачен">
                                                    <i class="bi bi-check-all me-1"></i><?= number_format($impotAmount, 2, ',', ' '); ?> €
                                                </span>
                                            <?php else: ?>
                                                <span class="text-primary fw-semibold" title="Налог не оплачен">
                                                    <i class="bi bi-clock me-1"></i><?= number_format($impotAmount, 2, ',', ' '); ?> €
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">0,00 €</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-nowrap" data-label="Cumul">
                                        <?php if ($epargneAmount > 0): ?>
                                            <?php if (!empty($order['epargne_paye'])): ?>
                                                <span class="badge bg-success" title="Накопления переведены">
                                                    <i class="bi bi-check-all me-1"></i><?= number_format($epargneAmount, 2, ',', ' '); ?> €
                                                </span>
                                            <?php else: ?>
                                                <span class="text-primary fw-semibold" title="Накопления не переведены">
                                                    <i class="bi bi-clock me-1"></i><?= number_format($epargneAmount, 2, ',', ' '); ?> €
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">0,00 €</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" data-label="Statut">
                                        <div>
                                            <span class="badge <?= $isCancelled ? 'bg-danger' : $statusBadge; ?>">
                                                <?= htmlspecialchars($statusLabel); ?>
                                            </span>
                                            <?php if (!empty($order['date_paiement'])): ?>
                                                <div class="small text-success mt-1 text-nowrap">
                                                    <i class="bi bi-calendar-check me-1"></i><?= date('d.m.Y', strtotime($order['date_paiement'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <tr class="order-divider order-notes <?= $rowStatusClass; ?>">
                                    <td colspan="12" class="pt-0 pb-2 ps-4 small" style="color: #2b2b2b; <?= !$hasNotes ? 'display: none;' : ''; ?>">
                                        <?php if (!empty($order['commentaire'])): ?>
                                            <span class="note-comment me-2 d-inline-block px-2 py-1 rounded">
                                                <i class="bi bi-chat-left-text text-primary me-1"></i>
                                                <strong class="text-dark">Commentaire:</strong>
                                                <?= htmlspecialchars($order['commentaire']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($order['notes'])): ?>
                                            <span class="note-info d-inline-block px-2 py-1 rounded">
                                                <i class="bi bi-journal-text text-success me-1"></i>
                                                <strong class="text-dark">Notes:</strong>
                                                <?= htmlspecialchars($order['notes']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>

                        <?php if (!empty($commandes)): ?>
                            <tfoot class="table-header-custom">
                            <tr class="totals-row">
                                <td colspan="7" class="text-end cell-total-title">Total:</td>
                                <td class="text-end fs-6" data-label="Montant">
                                    <span class="totals-badge"><?= number_format($total_montant, 2, ',', ' '); ?> €</span>
                                </td>
                                <td class="text-center" data-label="Taxe">
                                    <span class="totals-badge"><?= number_format($total_impot, 2, ',', ' '); ?> €</span>
                                </td>
                                <td class="text-center" data-label="Cumul">
                                    <span class="totals-badge"><?= number_format($total_epargne, 2, ',', ' '); ?> €</span>
                                </td>
                                <td colspan="2" class="cell-empty"></td>
                            </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Модальное окно для создания/редактирования заказа -->
<div class="modal fade" id="commandeModal" tabindex="-1" aria-labelledby="commandeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-success text-white" id="cmdModalHeader">
                <h5 class="modal-title" id="commandeModalLabel">
                    <i class="bi bi-cart-plus me-1"></i> <span id="cmdModalTitleText">Créer une commande</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="cmdModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для редактирования клиента -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-success text-white" id="clientModalHeader">
                <h5 class="modal-title" id="clientModalLabel">
                    <i class="bi bi-person-gear me-1"></i> <span id="clientModalTitleText">Modifier le client</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="clientModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>

<button type="button" class="btn btn-success btn-lg rounded-circle shadow" id="btn-back-to-bottom" style="position: fixed; bottom: 80px; right: 20px; display: none; z-index: 9999;" title="Прокрутить вниз">
    <i class="bi bi-arrow-down"></i>
</button>

<button type="button" class="btn btn-success btn-lg rounded-circle shadow" id="btn-back-to-top" style="position: fixed; bottom: 20px; right: 20px; display: none; z-index: 9999;" title="Прокрутить наверх">
    <i class="bi bi-arrow-up"></i>
</button>

<script>
    // Если статус «Payé» — дата оплаты обязательна
    function syncPaymentDateRequired(form) {
        const statut = form.querySelector('[name="statut"]');
        const dateInput = form.querySelector('[name="date_paiement"]');
        if (!statut || !dateInput) return;

        const isPaid = statut.value === 'Payé';
        // у календаря flatpickr видимое поле — altInput
        const visible = (dateInput._flatpickr && dateInput._flatpickr.altInput) ? dateInput._flatpickr.altInput : dateInput;
        visible.required = isPaid;

        const star = form.querySelector('#datePaiementStar');
        if (star) star.classList.toggle('d-none', !isPaid);
    }

    // Реакция на смену статуса в форме
    document.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'statut') {
            const form = e.target.closest('form');
            if (form) syncPaymentDateRequired(form);
        }
    });

    // Календарь для полей дат в формах (класс js-date)
    function initDatePickers(root) {
        if (typeof flatpickr !== 'undefined') {
            root.querySelectorAll('.js-date').forEach(function (el) {
                flatpickr(el, {
                    locale: 'fr',        // французские месяцы, неделя с понедельника
                    dateFormat: 'Y-m-d', // формат для сервера
                    altInput: true,
                    altFormat: 'd.m.Y'   // формат на экране: 27.09.2026
                });
            });
        }
        const form = root.querySelector('form');
        if (form) syncPaymentDateRequired(form);
    }

    const commandeModal = new bootstrap.Modal(document.getElementById('commandeModal'));
    const clientModal = new bootstrap.Modal(document.getElementById('clientModal'));

    function openCommandeModal(url, title, headerClass) {
        document.getElementById('cmdModalTitleText').innerText = title;
        document.getElementById('cmdModalHeader').className = 'modal-header ' + headerClass + ' text-white';

        document.getElementById('cmdModalBody').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-success" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
            </div>
        `;

        commandeModal.show();

        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const content = doc.querySelector('.container, .container-fluid, form') || doc.body;

                document.getElementById('cmdModalBody').innerHTML = content.outerHTML;
                initDatePickers(document.getElementById('cmdModalBody'));

                const modalForm = document.getElementById('cmdModalBody').querySelector('form');
                if (modalForm) {
                    modalForm.addEventListener('submit', function(e) {
                        e.preventDefault();

                        syncPaymentDateRequired(modalForm);

                        // проверка обязательных полей
                        if (!modalForm.checkValidity()) {
                            modalForm.classList.add('was-validated');
                            const firstInvalid = modalForm.querySelector(':invalid');
                            if (firstInvalid) firstInvalid.focus();
                            return;
                        }

                        const formData = new FormData(modalForm);

                        // Гарантируем, что форма отправляется с параметром modal=1
                        let targetUrl = modalForm.action || url;
                        if (!targetUrl.includes('modal=1')) {
                            targetUrl += (targetUrl.includes('?') ? '&' : '?') + 'modal=1';
                        }

                        fetch(targetUrl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(async res => {
                            if (res.redirected) {
                                window.location.href = res.url;
                            } else {
                                const responseText = await res.text();
                                // Если сервер вернул форму с ошибками, показываем их
                                if (responseText.includes('alert-danger')) {
                                    const tempDoc = new DOMParser().parseFromString(responseText, 'text/html');
                                    const newContent = tempDoc.querySelector('.container, .container-fluid, form') || tempDoc.body;
                                    document.getElementById('cmdModalBody').innerHTML = newContent.outerHTML;
                                    initDatePickers(document.getElementById('cmdModalBody'));
                                } else {
                                    window.location.reload();
                                }
                            }
                        })
                        .catch(err => {
                            alert('Erreur lors de l\'enregistrement');
                        });
                    });
                }
            })
            .catch(error => {
                document.getElementById('cmdModalBody').innerHTML = '<div class="alert alert-danger">Erreur de chargement du formulaire.</div>';
            });
    }

    function openClientModal(url, title, headerClass) {
        document.getElementById('clientModalTitleText').innerText = title;
        document.getElementById('clientModalHeader').className = 'modal-header ' + headerClass + ' text-white';

        document.getElementById('clientModalBody').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-success" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
            </div>
        `;

        clientModal.show();

        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const content = doc.querySelector('.container, .container-fluid, form') || doc.body;

                document.getElementById('clientModalBody').innerHTML = content.outerHTML;

                const modalForm = document.getElementById('clientModalBody').querySelector('form');
                if (modalForm) {
                    const cleanUrl = url.split('&modal=1')[0].replace('?modal=1', '');
                    modalForm.action = cleanUrl;

                    modalForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(modalForm);

                        fetch(cleanUrl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => {
                            if (res.redirected) {
                                window.location.href = res.url;
                            } else {
                                window.location.reload();
                            }
                        })
                        .catch(err => {
                            modalForm.submit();
                        });
                    });
                }
            })
            .catch(error => {
                document.getElementById('clientModalBody').innerHTML = '<div class="alert alert-danger">Erreur de chargement du formulaire.</div>';
            });
    }

    // Универсальный обработчик маски телефона для модальных окон
    document.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'telephoneInput') {
            let input = e.target;
            let digits = input.value.replace(/\D/g, '');

            if (digits.startsWith('33')) {
                digits = digits.slice(2);
            } else if (digits.startsWith('0')) {
                digits = digits.slice(1);
            }

            digits = digits.slice(0, 9);

            let formatted = '+33';
            if (digits.length > 0) {
                formatted += ' ' + digits.substring(0, 1);
            }
            if (digits.length > 1) {
                formatted += ' ' + digits.substring(1, 3);
            }
            if (digits.length > 3) {
                formatted += ' ' + digits.substring(3, 5);
            }
            if (digits.length > 5) {
                formatted += ' ' + digits.substring(5, 7);
            }
            if (digits.length > 7) {
                formatted += ' ' + digits.substring(7, 9);
            }

            input.value = formatted;
        }
    });

    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.id === 'telephoneInput') {
            if (!e.target.value || e.target.value.trim() === '' || e.target.value.trim() === '+') {
                e.target.value = '+33 ';
            }
        }
    });

    // Прокрутка к только что сохранённому / скопированному заказу
    document.addEventListener("DOMContentLoaded", function () {
        const params = new URLSearchParams(window.location.search);
        let targetId = params.get('scroll');

        // якорь #order-ID тоже поддерживаем
        if (!targetId && window.location.hash.startsWith('#order-')) {
            targetId = window.location.hash.replace('#order-', '');
        }
        if (!targetId) return;

        const row = document.getElementById('order-' + targetId);
        if (row) {
            const noteRow = row.nextElementSibling; // строка с заметками
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            [row, noteRow].forEach(r => r && r.classList.add('row-just-saved'));
            setTimeout(() => {
                [row, noteRow].forEach(r => r && r.classList.remove('row-just-saved'));
            }, 2500);
        }

        // чистим адрес, чтобы при F5 не прокручивало повторно
        ['scroll', 'added', 'updated', 'copied'].forEach(p => params.delete(p));
        const qs = params.toString();
        history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
    });

    setTimeout(function () {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function (alertElement) {
            const alert = new bootstrap.Alert(alertElement);
            alert.close();
        });
    }, 5000);

    const selectAllCheckbox = document.getElementById('selectAll');
    const orderCheckboxes = document.querySelectorAll('.order-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkCopyBtn = document.getElementById('bulkCopyBtn');
    const ordersWrapper = document.getElementById('ordersWrapper');

    function updateActionButtonsVisibility() {
        const checkedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
        const checkedCount = checkedCheckboxes.length;

        bulkDeleteBtn.classList.toggle('d-none', checkedCount === 0);
        bulkCopyBtn.classList.toggle('d-none', checkedCount !== 1);

        // колонка «Actions» (на мобильном — строка с кнопками в карточке)
        if (ordersWrapper) {
            ordersWrapper.classList.toggle('show-actions', checkedCount > 0);
        }

        orderCheckboxes.forEach(function (checkbox) {
            const mainRow = checkbox.closest('tr');
            mainRow.classList.toggle('row-selected', checkbox.checked);
        });

        if (selectAllCheckbox && orderCheckboxes.length > 0) {
            selectAllCheckbox.checked = (checkedCount === orderCheckboxes.length);
        }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            const isChecked = selectAllCheckbox.checked;
            orderCheckboxes.forEach(checkbox => checkbox.checked = isChecked);
            updateActionButtonsVisibility();
        });
    }

    orderCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateActionButtonsVisibility);
    });

    updateActionButtonsVisibility();

    const topButton = document.getElementById("btn-back-to-top");
    const bottomButton = document.getElementById("btn-back-to-bottom");

    window.onscroll = function () {
        const scrollTop = document.body.scrollTop || document.documentElement.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight;
        const clientHeight = document.documentElement.clientHeight;

        topButton.style.display = (scrollTop > 300) ? "block" : "none";
        bottomButton.style.display = (scrollTop + clientHeight < scrollHeight - 300) ? "block" : "none";
    };

    topButton.addEventListener("click", function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    bottomButton.addEventListener("click", function () {
        window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
    });
</script>
</body>
</html>