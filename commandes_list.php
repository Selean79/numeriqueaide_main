<?php
#sdfh
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once 'db.php';

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
| Работает только если выбран ровно 1 заказ
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

                /*
                |--------------------------------------------------------------------------
                | Генерация следующего номера заказа
                |--------------------------------------------------------------------------
                */
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

                    $next_order_id =
                            'CMD-' . $current_year . '-' . $next_num;
                }


                /*
                |--------------------------------------------------------------------------
                | Вставка копии заказа
                |--------------------------------------------------------------------------
                */
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
                        ':id_commande'       => $next_order_id,
                        ':date_commande'     => $srcOrder['date_commande'],
                        ':rdv_time'          => $srcOrder['rdv_time'] ?? null,
                        ':client_id'         => $srcOrder['client_id'],
                        ':platform_id'       => $srcOrder['platform_id'],
                        ':payment_method_id' => $srcOrder['payment_method_id'],
                        ':facture_id'        => $srcOrder['facture_id'],
                        ':montant'           => $srcOrder['montant'],
                        ':statut'            => 'Prévu',
                        ':date_paiement'     => null,
                        ':notes'             => $srcOrder['notes'],
                        ':commentaire'       => $srcOrder['commentaire'],
                        ':calcul_impot'      => $srcOrder['calcul_impot'],
                        ':calcul_epargne'    => $srcOrder['calcul_epargne'],
                        ':impot_paye'        => 0,
                        ':epargne_paye'      => 0
                ]);

                header("Location: commandes_list.php?copied=1");
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

    $_SESSION['cmd_search'] =
            trim($_GET['search'] ?? '');

    $_SESSION['cmd_status'] =
            trim($_GET['status'] ?? '');

    $month =
            (
                    isset($_GET['month']) &&
                    $_GET['month'] !== ''
            )
                    ? (int)$_GET['month']
                    : null;

    $year =
            (
                    isset($_GET['year']) &&
                    $_GET['year'] !== ''
            )
                    ? (int)$_GET['year']
                    : null;

    $_SESSION['cmd_month'] = $month;
    $_SESSION['cmd_year'] = $year;


    if ($month && $year) {

        $_SESSION['cmd_date_start'] =
                sprintf(
                        '%04d-%02d-01',
                        $year,
                        $month
                );

        $_SESSION['cmd_date_end'] =
                date(
                        'Y-m-t',
                        strtotime($_SESSION['cmd_date_start'])
                );

    } else {

        $_SESSION['cmd_date_start'] = '';
        $_SESSION['cmd_date_end'] = '';
    }

} elseif (!array_key_exists('cmd_date_start', $_SESSION)) {

    $_SESSION['cmd_month'] =
            (int)date('n');

    $_SESSION['cmd_year'] =
            (int)date('Y');

    $_SESSION['cmd_date_start'] =
            date('Y-m-01');

    $_SESSION['cmd_date_end'] =
            date('Y-m-t');
}


/*
|--------------------------------------------------------------------------
| Получение фильтров
|--------------------------------------------------------------------------
*/
$search =
        $_SESSION['cmd_search'] ?? '';

$status_filter =
        $_SESSION['cmd_status'] ?? '';

$date_start =
        $_SESSION['cmd_date_start'] ?? '';

$date_end =
        $_SESSION['cmd_date_end'] ?? '';

$selected_month =
        $_SESSION['cmd_month'] ??
        (int)date('n');

$selected_year =
        $_SESSION['cmd_year'] ??
        (int)date('Y');


/*
|--------------------------------------------------------------------------
| Управление сортировкой
|--------------------------------------------------------------------------
*/
if (isset($_GET['sort_col'])) {

    $requested_col =
            $_GET['sort_col'];

    if (
            ($_SESSION['cmd_sort_col'] ?? '') ===
            $requested_col
    ) {

        $_SESSION['cmd_sort_dir'] =
                (
                        ($_SESSION['cmd_sort_dir'] ?? 'ASC') === 'ASC'
                )
                        ? 'DESC'
                        : 'ASC';

    } else {

        $_SESSION['cmd_sort_col'] =
                $requested_col;

        $_SESSION['cmd_sort_dir'] =
                'ASC';
    }
}


$sort_col =
        $_SESSION['cmd_sort_col'] ??
        'date_commande';

$sort_dir =
        $_SESSION['cmd_sort_dir'] ??
        'ASC';


/*
|--------------------------------------------------------------------------
| Определение названия колонки платформы
|--------------------------------------------------------------------------
*/
$platCols =
        $pdo
                ->query("SHOW COLUMNS FROM platforms")
                ->fetchAll(PDO::FETCH_COLUMN);

$platColName =
        in_array('nom', $platCols)
                ? 'nom'
                : (
        in_array('name', $platCols)
                ? 'name'
                : $platCols[1]
        );

$platNameCol =
        "p.`$platColName`";


/*
|--------------------------------------------------------------------------
| Определение названия колонки оплаты
|--------------------------------------------------------------------------
*/
$pmCols =
        $pdo
                ->query("SHOW COLUMNS FROM modes_de_paiement")
                ->fetchAll(PDO::FETCH_COLUMN);

$pmColName =
        in_array('nom', $pmCols)
                ? 'nom'
                : (
        in_array('name', $pmCols)
                ? 'name'
                : $pmCols[1]
        );

$pmNameCol =
        "pm.`$pmColName`";


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

    LEFT JOIN clients cl
        ON c.client_id = cl.id

    LEFT JOIN platforms p
        ON c.platform_id = p.id

    LEFT JOIN modes_de_paiement pm
        ON c.payment_method_id = pm.id

    LEFT JOIN factures f
        ON c.facture_id = f.id

    WHERE 1=1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Поиск
|--------------------------------------------------------------------------
*/
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

    $params[':search'] =
            "%$search%";
}


/*
|--------------------------------------------------------------------------
| Фильтр статуса
|--------------------------------------------------------------------------
*/
if (!empty($status_filter)) {

    $sql .= "
        AND c.statut = :status
    ";

    $params[':status'] =
            $status_filter;
}


/*
|--------------------------------------------------------------------------
| Фильтр даты
|--------------------------------------------------------------------------
*/
if (!empty($date_start)) {

    $sql .= "
        AND c.date_commande >= :date_start
    ";

    $params[':date_start'] =
            $date_start . ' 00:00:00';
}

if (!empty($date_end)) {

    $sql .= "
        AND c.date_commande <= :date_end
    ";

    $params[':date_end'] =
            $date_end . ' 23:59:59';
}


/*
|--------------------------------------------------------------------------
| Разрешенные поля сортировки
|--------------------------------------------------------------------------
*/
$allowed_sorts = [

        'id_commande'
        => 'c.id_commande',

        'date_commande'
        => 'c.date_commande',

        'client_name'
        => 'client_name',

        'platform_name'
        => 'platform_name',

        'facture_number'
        => 'f.facture_number',

        'payment_method_name'
        => 'payment_method_name',

        'montant'
        => 'c.montant',

        'statut'
        => 'c.statut'
];


$sql_sort_field =
        $allowed_sorts[$sort_col]
        ?? 'c.date_commande';


$sql .= "
    ORDER BY
        $sql_sort_field $sort_dir,
        c.rdv_time ASC,
        c.id_commande DESC
";


/*
|--------------------------------------------------------------------------
| Выполнение запроса
|--------------------------------------------------------------------------
*/
try {

    $stmt =
            $pdo->prepare($sql);

    $stmt->execute($params);

    $commandes =
            $stmt->fetchAll();

} catch (PDOException $e) {

    die(
            "Ошибка загрузки заказов: " .
            htmlspecialchars($e->getMessage())
    );
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

    $orderStatusRaw =
            mb_strtolower(
                    trim($order['statut'] ?? '')
            );

    $orderIsCancelled =
            in_array(
                    $orderStatusRaw,
                    [
                            'annulee',
                            'annulée',
                            'отменен'
                    ]
            );

    if ($orderIsCancelled) {
        continue;
    }


    $m =
            (float)($order['montant'] ?? 0);

    $total_montant += $m;


    $impVal =
            (float)($order['calcul_impot'] ?? 0);

    $impAmount =
            ($impVal == 1)
                    ? ($m * 0.212)
                    : $impVal;

    $total_impot += $impAmount;


    $epVal =
            (float)($order['calcul_epargne'] ?? 0);

    $epAmount =
            ($epVal == 1)
                    ? ($m * 0.10)
                    : $epVal;

    $total_epargne += $epAmount;
}


/*
|--------------------------------------------------------------------------
| Функция заголовка таблицы
|--------------------------------------------------------------------------
*/
function renderTh(
        $colKey,
        $label,
        $alignClass = ''
) {
    global $sort_col, $sort_dir;

    $icon = '';

    if ($sort_col === $colKey) {

        $icon =
                ($sort_dir === 'ASC')
                        ? ' <i class="bi bi-arrow-up-short"></i>'
                        : ' <i class="bi bi-arrow-down-short"></i>';
    }

    echo '
        <th class="' . $alignClass . '">
            <a
                href="?sort_col=' . urlencode($colKey) . '"
                class="text-decoration-none"
            >
                ' . $label . $icon . '
            </a>
        </th>
    ';
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
        background-color: #82e89e !important;
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

    .rdv-time-badge {
        background-color: #e2e8f0;
        color: #1e293b;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 600;
        display: inline-block;
    }

    #bulkActionButtons {
        position: sticky;
        top: 10px;
        z-index: 1050;
    }

    .actions-column {
        vertical-align: middle;
    }

    .order-actions {
        opacity: 0;
        visibility: hidden;
        transition:
                opacity 0.2s ease-in-out,
                visibility 0.2s ease-in-out;
    }

    tr.row-selected .order-actions {
        opacity: 1;
        visibility: visible;
    }

    tr.row-selected {
        background-color: #eef7ff !important;
    }

    .order-actions .btn {
        min-width: 34px;
    }

    body {
        background-color: #d3d1d1 !important;
    }
</style>


<div class="container-fluid mt-4 px-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">
            <i class="bi bi-cart-check me-2"></i>
            Liste des commandes
        </h3>

        <a
                href="add_commande.php"
                class="btn btn-success"
        >
            <i class="bi bi-plus-circle me-1"></i>
            Créer une commande
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div
                class="alert alert-warning alert-dismissible fade show"
                role="alert"
        >
            <?php if ($_GET['deleted'] === 'multi'): ?>
                Sélection de commandes supprimée avec succès!
            <?php else: ?>
                La commande a été supprimée avec succès!
            <?php endif; ?>

            <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
            ></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['copied'])): ?>
        <div
                class="alert alert-success alert-dismissible fade show"
                role="alert"
        >
            La commande a été copiée avec succès!

            <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
            ></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
        >
            <?= htmlspecialchars($error_message); ?>

            <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
            ></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form
                    method="GET"
                    class="row g-2 align-items-end"
            >

                <div class="col-md-3">
                    <label
                            class="form-label small text-muted mb-1"
                    >
                        Recherche
                    </label>

                    <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="numéro de commande, client..."
                            value="<?= htmlspecialchars($search); ?>"
                    >
                </div>

                <div class="col-md-2">
                    <label
                            class="form-label small text-muted mb-1"
                    >
                        Mois
                    </label>

                    <select
                            name="month"
                            class="form-select"
                    >
                        <option value="">
                            Tout
                        </option>

                        <?php
                        $monthNames = [
                                1  => 'Janvier',
                                2  => 'Février',
                                3  => 'Mars',
                                4  => 'Avril',
                                5  => 'Mai',
                                6  => 'Juin',
                                7  => 'Juillet',
                                8  => 'Août',
                                9  => 'Septembre',
                                10 => 'Octobre',
                                11 => 'Novembre',
                                12 => 'Décembre'
                        ];

                        foreach (
                                $monthNames
                                as $mNum => $mName
                        ):
                            ?>
                            <option
                                    value="<?= $mNum; ?>"
                                    <?= (
                                            (int)$selected_month === $mNum
                                    )
                                            ? 'selected'
                                            : ''
                                    ?>
                            >
                                <?= $mName; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label
                            class="form-label small text-muted mb-1"
                    >
                        Année
                    </label>

                    <select
                            name="year"
                            class="form-select"
                    >
                        <option value="">
                            Все
                        </option>

                        <?php
                        $currentYear =
                                (int)date('Y');

                        for (
                                $y = $currentYear - 2;
                                $y <= $currentYear + 1;
                                $y++
                        ):
                            ?>
                            <option
                                    value="<?= $y; ?>"
                                    <?= (
                                            (int)$selected_year === $y
                                    )
                                            ? 'selected'
                                            : ''
                                    ?>
                            >
                                <?= $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label
                            class="form-label small text-muted mb-1"
                    >
                        Statut
                    </label>

                    <select
                            name="status"
                            class="form-select"
                    >
                        <option value="">
                            Tous les statuts
                        </option>

                        <option
                                value="Prévu"
                                <?= $status_filter === 'Prévu'
                                        ? 'selected'
                                        : ''
                                ?>
                        >
                            Prévu
                        </option>

                        <option
                                value="En cours"
                                <?= $status_filter === 'En cours'
                                        ? 'selected'
                                        : ''
                                ?>
                        >
                            En cours
                        </option>

                        <option
                                value="Payé"
                                <?= $status_filter === 'Payé'
                                        ? 'selected'
                                        : ''
                                ?>
                        >
                            Payé
                        </option>

                        <option
                                value="Annulée"
                                <?= $status_filter === 'Annulée'
                                        ? 'selected'
                                        : ''
                                ?>
                        >
                            Annulée
                        </option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-1">
                    <button
                            type="submit"
                            class="btn btn-primary flex-grow-1"
                            title="Применить"
                    >
                        <i class="bi bi-search me-1"></i>
                        Trouver
                    </button>

                    <a
                            href="commandes_list.php?clear_filter=1"
                            class="btn btn-outline-secondary"
                            title="Сбросить фильтр"
                    >
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>

            </form>
        </div>
    </div>

    <form
            method="POST"
            id="bulkActionForm"
    >

        <div id="bulkActionButtons" class="d-flex justify-content-end gap-2 mb-2">

            <button
                    type="submit"
                    name="bulk_copy"
                    id="bulkCopyBtn"
                    class="btn btn-success btn-sm d-none"
            >
                <i class="bi bi-files me-1"></i>
                Copy
            </button>

            <button
                    type="submit"
                    name="bulk_delete"
                    id="bulkDeleteBtn"
                    class="btn btn-danger btn-sm d-none"
                    onclick="
                    return confirm(
                        'Êtes-vous sûr de vouloir supprimer les commandes sélectionnées?'
                    );
                "
            >
                <i class="bi bi-trash me-1"></i>
                Supprimer la sélection
            </button>

        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table
                            class="table table-hover align-middle mb-0"
                            style="min-width: 1200px;"
                    >

                        <thead class="table-header-custom">
                        <tr>

                            <th
                                    style="width: 40px;"
                                    class="text-center"
                            >
                                <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="selectAll"
                                >
                            </th>

                            <?php
                            renderTh(
                                    'id_commande',
                                    '№ de сommande',
                                    'text-nowrap'
                            );
                            ?>

                            <?php
                            renderTh(
                                    'date_commande',
                                    'Date',
                                    'text-nowrap'
                            );
                            ?>

                            <?php
                            renderTh(
                                    'client_name',
                                    'Client',
                                    'text-nowrap'
                            );
                            ?>

                            <?php
                            renderTh(
                                    'platform_name',
                                    'Plateforme',
                                    'text-nowrap'
                            );
                            ?>

                            <?php
                            renderTh(
                                    'facture_number',
                                    'Facture',
                                    'text-nowrap'
                            );
                            ?>

                            <?php
                            renderTh(
                                    'payment_method_name',
                                    'Paiement',
                                    'text-nowrap'
                            );
                            ?>

                            <?php
                            renderTh(
                                    'montant',
                                    'Montant',
                                    'text-end text-nowrap'
                            );
                            ?>

                            <th
                                    style="width: 150px;"
                                    class="text-center"
                            >
                                Taxe
                            </th>

                            <th
                                    style="width: 150px;"
                                    class="text-center"
                            >
                                Cumul
                            </th>

                            <?php
                            renderTh(
                                    'statut',
                                    'Statut',
                                    'text-center text-nowrap'
                            );
                            ?>

                            <th
                                    id="actionsHeader"
                                    style="width: 100px;"
                                    class="text-center actions-column d-none"
                            >
                                Actions
                            </th>

                        </tr>
                        </thead>

                        <tbody>

                        <?php if (empty($commandes)): ?>
                            <tr>
                                <td
                                        colspan="12"
                                        class="text-center py-4 text-muted"
                                >
                                    Aucune commande trouvée
                                </td>
                            </tr>
                        <?php else: ?>

                            <?php
                            $rowIndex = 0;
                            ?>

                            <?php foreach ($commandes as $order): ?>

                                <?php
                                $rowIndex++;

                                $groupClass =
                                        (
                                                $rowIndex % 2 === 0
                                        )
                                                ? 'order-group-even'
                                                : 'order-group-odd';

                                $montant =
                                        (float)(
                                                $order['montant'] ?? 0
                                        );

                                $impotVal =
                                        (float)(
                                                $order['calcul_impot'] ?? 0
                                        );

                                $impotAmount =
                                        ($impotVal == 1)
                                                ? ($montant * 0.212)
                                                : $impotVal;

                                $epargneVal =
                                        (float)(
                                                $order['calcul_epargne'] ?? 0
                                        );

                                $epargneAmount =
                                        ($epargneVal == 1)
                                                ? ($montant * 0.10)
                                                : $epargneVal;

                                $platformName =
                                        trim(
                                                $order['platform_name'] ??
                                                'Privé'
                                        );

                                $platBadgeClass =
                                        'bg-danger';

                                if (
                                        strcasecmp(
                                                $platformName,
                                                'Yoojo'
                                        ) === 0
                                ) {
                                    $platBadgeClass =
                                            'bg-primary';
                                } elseif (
                                        strcasecmp(
                                                $platformName,
                                                'NeedHelp'
                                        ) === 0
                                ) {
                                    $platBadgeClass =
                                            'bg-success';
                                }

                                $statusRaw =
                                        trim(
                                                $order['statut'] ?? ''
                                        );

                                $statusBadge =
                                        'bg-secondary';

                                $statusLabel =
                                        $statusRaw;

                                $isCancelled =
                                        false;

                                switch (
                                mb_strtolower(
                                        $statusRaw
                                )
                                ) {
                                    case 'prévu':
                                    case 'prevu':
                                    case 'запланирован':
                                        $statusBadge =
                                                'bg-success';
                                        $statusLabel =
                                                'Prévu';
                                        break;

                                    case 'en cours':
                                    case 'en_cours':
                                    case 'в работе':
                                        $statusBadge =
                                                'bg-warning text-dark';
                                        $statusLabel =
                                                'En cours';
                                        break;

                                    case 'payé':
                                    case 'paye':
                                    case 'terminee':
                                    case 'оплачен':
                                        $statusBadge =
                                                'bg-secondary';
                                        $statusLabel =
                                                'Payé';
                                        break;

                                    case 'annulee':
                                    case 'annulée':
                                    case 'отменен':
                                        $isCancelled =
                                                true;
                                        $statusLabel =
                                                'Annulée';
                                        break;
                                }

                                if ($isCancelled) {
                                    $montant = 0;
                                    $impotAmount = 0;
                                    $epargneAmount = 0;
                                }

                                $hasNotes =
                                        !empty($order['notes']) ||
                                        !empty($order['commentaire']);
                                ?>

                                <tr
                                        id="order-<?= (int)$order['id']; ?>"
                                        class="<?= $groupClass; ?>"
                                >

                                    <td class="text-center">
                                        <input
                                                type="checkbox"
                                                name="delete_ids[]"
                                                value="<?= (int)$order['id']; ?>"
                                                class="form-check-input order-checkbox"
                                        >
                                    </td>

                                    <td class="fw-bold text-nowrap">
                                        <?= htmlspecialchars(
                                                $order['id_commande']
                                        ); ?>
                                    </td>

                                    <!-- Date + Время рандеву (rdv_time) в виде бэджа -->
                                    <td class="text-nowrap">
                                        <?= date(
                                                'd.m.Y',
                                                strtotime(
                                                        $order['date_commande']
                                                )
                                        ); ?>
                                        <?php if (!empty($order['rdv_time'])): ?>
                                            <br><small class="text-muted"><span class="rdv-time-badge mt-1"><i class="bi bi-clock me-1"></i><?= substr($order['rdv_time'], 0, 5); ?></span></small>
                                        <?php endif; ?>
                                    </td>

                                    <td class="fw-semibold">
                                        <?php if (!empty(trim($order['client_name']))): ?>
                                            <a href="edit_client.php?id=<?= (int)$order['client_id']; ?>&return_order=<?= (int)$order['id']; ?>"
                                               class="text-decoration-none text-dark"
                                               title="Modifier le client">
                                                <?= htmlspecialchars(trim($order['client_name'])); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>

                                        <?php if (!empty(trim($order['client_notes'] ?? ''))): ?>
                                            <i class="bi bi-exclamation-circle-fill text-danger ms-1"
                                               style="cursor: pointer;"
                                               title="Client note: <?= htmlspecialchars($order['client_notes']); ?>">
                                            </i>
                                        <?php endif; ?>

                                        <?php
                                        if (
                                                !empty(
                                                $order['client_telephone']
                                                ) ||
                                                !empty(
                                                $order['client_adresse']
                                                )
                                        ):
                                            ?>
                                            <div
                                                    class="small text-muted fw-normal mt-1"
                                            >

                                                <?php
                                                if (
                                                        !empty(
                                                        $order['client_telephone']
                                                        )
                                                ):
                                                    ?>
                                                    <div>
                                                        <i
                                                                class="bi bi-telephone me-1 text-primary"
                                                        ></i>

                                                        <a
                                                                href="tel:<?= htmlspecialchars($order['client_telephone']); ?>"
                                                                class="text-decoration-none text-muted"
                                                        >
                                                            <?= htmlspecialchars(
                                                                    $order['client_telephone']
                                                            ); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>

                                                <?php
                                                if (
                                                        !empty(
                                                        $order['client_adresse']
                                                        )
                                                ):
                                                    ?>
                                                    <div>
                                                        <i
                                                                class="bi bi-geo-alt me-1 text-danger"
                                                        ></i>

                                                        <a
                                                                href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($order['client_adresse']); ?>"
                                                                target="_blank"
                                                                class="text-decoration-none text-muted"
                                                        >
                                                            <?= htmlspecialchars(
                                                                    $order['client_adresse']
                                                            ); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>

                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span
                                                class="badge <?= $platBadgeClass; ?>"
                                        >
                                            <?= htmlspecialchars(
                                                    $platformName
                                            ); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?=
                                        !empty(
                                        $order['facture_number']
                                        )

                                                ? htmlspecialchars(
                                                $order['facture_number']
                                        )

                                                : '<span class="text-muted">—</span>';
                                        ?>
                                    </td>

                                    <td>
                                        <?=
                                        !empty(
                                        $order['payment_method_name']
                                        )

                                                ? htmlspecialchars(
                                                $order['payment_method_name']
                                        )

                                                : '<span class="text-muted">—</span>';
                                        ?>
                                    </td>

                                    <td
                                            class="text-end fw-bold"
                                    >
                                        <?= number_format(
                                                $montant,
                                                2,
                                                ',',
                                                ' '
                                        ); ?>
                                        €
                                    </td>

                                    <td
                                            class="text-center text-nowrap"
                                    >
                                        <?php
                                        if ($impotAmount > 0):
                                            ?>

                                            <?php
                                            if (
                                                    !empty(
                                                    $order['impot_paye']
                                                    )
                                            ):
                                                ?>
                                                <span
                                                        class="badge bg-success"
                                                        title="Налог оплачен"
                                                >
                                                    <i
                                                            class="bi bi-check-all me-1"
                                                    ></i>

                                                    <?= number_format(
                                                            $impotAmount,
                                                            2,
                                                            ',',
                                                            ' '
                                                    ); ?>

                                                    €
                                                </span>
                                            <?php else: ?>
                                                <span
                                                        class="text-primary fw-semibold"
                                                >
                                                    <i
                                                            class="bi bi-clock me-1"
                                                    ></i>

                                                    <?= number_format(
                                                            $impotAmount,
                                                            2,
                                                            ',',
                                                            ' '
                                                    ); ?>

                                                    €
                                                </span>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <span
                                                    class="text-muted"
                                            >
                                                0,00 €
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td
                                            class="text-center text-nowrap"
                                    >
                                        <?php
                                        if ($epargneAmount > 0):
                                            ?>

                                            <?php
                                            if (
                                                    !empty(
                                                    $order['epargne_paye']
                                                    )
                                            ):
                                                ?>
                                                <span
                                                        class="badge bg-success"
                                                        title="Накопления переведены"
                                                >
                                                    <i
                                                            class="bi bi-check-all me-1"
                                                    ></i>

                                                    <?= number_format(
                                                            $epargneAmount,
                                                            2,
                                                            ',',
                                                            ' '
                                                    ); ?>

                                                    €
                                                </span>
                                            <?php else: ?>
                                                <span
                                                        class="text-primary fw-semibold"
                                                >
                                                    <i
                                                            class="bi bi-clock me-1"
                                                    ></i>

                                                    <?= number_format(
                                                            $epargneAmount,
                                                            2,
                                                            ',',
                                                            ' '
                                                    ); ?>

                                                    €
                                                </span>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <span
                                                    class="text-muted"
                                            >
                                                0,00 €
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <span
                                                class="badge <?= $isCancelled
                                                        ? 'bg-danger'
                                                        : $statusBadge; ?>"
                                        >
                                            <?= htmlspecialchars(
                                                    $statusLabel
                                            ); ?>
                                        </span>

                                        <?php
                                        if (
                                                !empty(
                                                $order['date_paiement']
                                                )
                                        ):
                                            ?>
                                            <div
                                                    class="small text-success mt-1 text-nowrap"
                                            >
                                                <i
                                                        class="bi bi-calendar-check me-1"
                                                ></i>

                                                <?= date(
                                                        'd.m.Y',
                                                        strtotime(
                                                                $order['date_paiement']
                                                        )
                                                ); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td
                                            class="text-center text-nowrap actions-column d-none"
                                    >
                                        <div class="order-actions">

                                            <a
                                                    href="edit_commande.php?id=<?= (int)$order['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary me-1"
                                                    title="Редактировать"
                                            >
                                                <i
                                                        class="bi bi-pencil"
                                                ></i>
                                            </a>

                                            <a
                                                    href="commandes_list.php?delete_id=<?= (int)$order['id']; ?>"
                                                    class="btn btn-sm btn-outline-danger"
                                                    onclick="
                                                    return confirm(
                                                        'Êtes-vous sûr de vouloir supprimer cette commande?'
                                                    );
                                                "
                                                    title="Удалить"
                                            >
                                                <i
                                                        class="bi bi-trash"
                                                ></i>
                                            </a>

                                        </div>
                                    </td>

                                </tr>

                                <?php if ($hasNotes): ?>
                                    <tr
                                            class="order-divider <?= $groupClass; ?>"
                                    >
                                        <td
                                                colspan="12"
                                                class="pt-0 pb-2 ps-4 small"
                                                style="color: #2b2b2b;"
                                        >

                                            <?php
                                            if (
                                                    !empty(
                                                    $order['commentaire']
                                                    )
                                            ):
                                                ?>
                                                <span
                                                        class="note-comment me-2 d-inline-block px-2 py-1 rounded"
                                                >
                                                    <i
                                                            class="bi bi-chat-left-text text-primary me-1"
                                                    ></i>

                                                    <strong class="text-dark">
                                                        Commentaire:
                                                    </strong>

                                                    <?= htmlspecialchars(
                                                            $order['commentaire']
                                                    ); ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php
                                            if (
                                                    !empty(
                                                    $order['notes']
                                                    )
                                            ):
                                                ?>
                                                <span
                                                        class="note-info d-inline-block px-2 py-1 rounded"
                                                >
                                                    <i
                                                            class="bi bi-journal-text text-success me-1"
                                                    ></i>

                                                    <strong class="text-dark">
                                                        Notes:
                                                    </strong>

                                                    <?= htmlspecialchars(
                                                            $order['notes']
                                                    ); ?>
                                                </span>
                                            <?php endif; ?>

                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr
                                            class="order-divider <?= $groupClass; ?>"
                                    >
                                        <td
                                                colspan="12"
                                                style="display: none;"
                                        ></td>
                                    </tr>
                                <?php endif; ?>

                            <?php endforeach; ?>

                        <?php endif; ?>
                        </tbody>

                        <?php if (!empty($commandes)): ?>
                            <tfoot class="table-header-custom">
                            <tr class="totals-row">

                                <td
                                        colspan="7"
                                        class="text-end"
                                >
                                    Total:
                                </td>

                                <td
                                        class="text-end fs-6"
                                >
                                    <span
                                            class="totals-badge"
                                    >
                                        <?= number_format(
                                                $total_montant,
                                                2,
                                                ',',
                                                ' '
                                        ); ?>
                                        €
                                    </span>
                                </td>

                                <td
                                        class="text-center"
                                >
                                    <span
                                            class="totals-badge"
                                    >
                                        <?= number_format(
                                                $total_impot,
                                                2,
                                                ',',
                                                ' '
                                        ); ?>
                                        €
                                    </span>
                                </td>

                                <td
                                        class="text-center"
                                >
                                    <span
                                            class="totals-badge"
                                    >
                                        <?= number_format(
                                                $total_epargne,
                                                2,
                                                ',',
                                                ' '
                                        ); ?>
                                        €
                                    </span>
                                </td>

                                <td colspan="2"></td>

                            </tr>
                            </tfoot>
                        <?php endif; ?>

                    </table>
                </div>
            </div>
        </div>

    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<button
        type="button"
        class="btn btn-primary btn-lg rounded-circle shadow"
        id="btn-back-to-top"
        style="
        position: fixed;
        bottom: 20px;
        right: 20px;
        display: none;
        z-index: 9999;
    "
>
    <i class="bi bi-arrow-up"></i>
</button>

<script>
    setTimeout(function () {
        const alerts =
            document.querySelectorAll('.alert');

        alerts.forEach(function (alertElement) {
            const alert =
                new bootstrap.Alert(alertElement);

            alert.close();
        });
    }, 5000);

    const selectAllCheckbox =
        document.getElementById('selectAll');

    const orderCheckboxes =
        document.querySelectorAll('.order-checkbox');

    const bulkDeleteBtn =
        document.getElementById('bulkDeleteBtn');

    const bulkCopyBtn =
        document.getElementById('bulkCopyBtn');

    const actionsHeader =
        document.getElementById('actionsHeader');

    const actionsCells =
        document.querySelectorAll('.actions-column');

    function updateActionButtonsVisibility() {
        const checkedCheckboxes =
            document.querySelectorAll(
                '.order-checkbox:checked'
            );

        const checkedCount =
            checkedCheckboxes.length;

        if (checkedCount > 0) {
            bulkDeleteBtn.classList.remove(
                'd-none'
            );
        } else {
            bulkDeleteBtn.classList.add(
                'd-none'
            );
        }

        if (checkedCount === 1) {
            bulkCopyBtn.classList.remove(
                'd-none'
            );
        } else {
            bulkCopyBtn.classList.add(
                'd-none'
            );
        }

        if (checkedCount > 0) {
            if (actionsHeader) {
                actionsHeader.classList.remove(
                    'd-none'
                );
            }

            actionsCells.forEach(function (cell) {
                cell.classList.remove(
                    'd-none'
                );
            });
        } else {
            if (actionsHeader) {
                actionsHeader.classList.add(
                    'd-none'
                );
            }

            actionsCells.forEach(function (cell) {
                cell.classList.add(
                    'd-none'
                );
            });
        }

        orderCheckboxes.forEach(function (checkbox) {
            const mainRow =
                checkbox.closest('tr');

            if (checkbox.checked) {
                mainRow.classList.add(
                    'row-selected'
                );
            } else {
                mainRow.classList.remove(
                    'row-selected'
                );
            }
        });

        if (selectAllCheckbox) {
            if (
                orderCheckboxes.length > 0 &&
                checkedCount === orderCheckboxes.length
            ) {
                selectAllCheckbox.checked =
                    true;
            } else {
                selectAllCheckbox.checked =
                    false;
            }
        }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener(
            'change',
            function () {
                const isChecked =
                    selectAllCheckbox.checked;

                orderCheckboxes.forEach(
                    function (checkbox) {
                        checkbox.checked =
                            isChecked;
                    }
                );

                updateActionButtonsVisibility();
            }
        );
    }

    orderCheckboxes.forEach(
        function (checkbox) {
            checkbox.addEventListener(
                'change',
                function () {
                    updateActionButtonsVisibility();
                }
            );
        }
    );

    updateActionButtonsVisibility();

    const mybutton =
        document.getElementById(
            "btn-back-to-top"
        );

    window.onscroll =
        function () {
            if (
                document.body.scrollTop > 300 ||
                document.documentElement.scrollTop > 300
            ) {
                mybutton.style.display =
                    "block";
            } else {
                mybutton.style.display =
                    "none";
            }
        };

    mybutton.addEventListener(
        "click",
        function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    );
</script>

</body>
</html>