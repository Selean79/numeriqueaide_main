<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: commandes_list.php');
    exit;
}

// Загрузка заказа строго по ID
$stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    die("Заказ не найден.");
}

$error = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_commande         = trim($_POST['id_commande'] ?? $order['id_commande']);
    $client_id           = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
    $platform_id         = !empty($_POST['platform_id']) ? (int)$_POST['platform_id'] : null;
    $payment_method_id   = !empty($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : null;
    $facture_id          = !empty($_POST['facture_id']) ? (int)$_POST['facture_id'] : null;
    $date_commande       = $_POST['date_commande'] ?? date('Y-m-d');
    $date_paiement       = !empty($_POST['date_paiement']) ? $_POST['date_paiement'] : null;
    $montant             = (float)str_replace(',', '.', $_POST['montant'] ?? 0);
    $statut              = $_POST['statut'] ?? 'Prévu';
    $commentaire         = trim($_POST['commentaire'] ?? '');
    $notes               = trim($_POST['notes'] ?? '');

    // Расчет сумм
    $calcul_impot_flag   = isset($_POST['calcul_impot']) ? 1 : 0;
    $calcul_epargne_flag = isset($_POST['calcul_epargne']) ? 1 : 0;

    $calcul_impot   = $calcul_impot_flag ? ($montant * 0.212) : 0;
    $calcul_epargne = $calcul_epargne_flag ? ($montant * 0.10) : 0;

    // Чекбоксы уплаты налога и накоплений
    $impot_paye   = isset($_POST['impot_paye']) ? 1 : 0;
    $epargne_paye = isset($_POST['epargne_paye']) ? 1 : 0;

    try {
        $updateSql = "
            UPDATE commandes 
            SET id_commande         = :id_commande,
                client_id           = :client_id,
                platform_id         = :platform_id,
                payment_method_id   = :payment_method_id,
                facture_id          = :facture_id,
                date_commande       = :date_commande,
                date_paiement       = :date_paiement,
                montant             = :montant,
                statut              = :statut,
                calcul_impot        = :calcul_impot,
                calcul_epargne      = :calcul_epargne,
                impot_paye          = :impot_paye,
                epargne_paye        = :epargne_paye,
                commentaire         = :commentaire,
                notes               = :notes
            WHERE id = :id
        ";

        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
                ':id_commande'       => $id_commande,
                ':client_id'         => $client_id,
                ':platform_id'       => $platform_id,
                ':payment_method_id' => $payment_method_id,
                ':facture_id'        => $facture_id,
                ':date_commande'     => $date_commande,
                ':date_paiement'     => $date_paiement,
                ':montant'           => $montant,
                ':statut'            => $statut,
                ':calcul_impot'      => $calcul_impot,
                ':calcul_epargne'    => $calcul_epargne,
                ':impot_paye'        => $impot_paye,
                ':epargne_paye'      => $epargne_paye,
                ':commentaire'       => $commentaire,
                ':notes'             => $notes,
                ':id'                => $order['id']
        ]);

        //header("Location: commandes_list.php?updated=1");
        //exit;
//        header("Location: commandes_list.php?updated=1#order-" . $order['id']);
//        exit;

        // Перенаправляем туда, откуда пришли (с сохранением якоря на заказ, если возвращаемся в список)
        if (!empty($_POST['return'])) {
            header("Location: " . $_POST['return']);
        } else {
            header("Location: commandes_list.php?updated=1#order-" . $order['id']);
        }
        exit;

    } catch (PDOException $e) {
        $error = "Ошибка обновления заказа: " . $e->getMessage();
    }
}

// Загрузка справочников
$clients = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom ASC, prenom ASC")->fetchAll();

$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : (in_array('name', $platCols) ? 'name' : $platCols[1]);
$platforms = $pdo->query("SELECT id, `$platColName` AS name FROM platforms ORDER BY `$platColName` ASC")->fetchAll();

$pmCols = $pdo->query("SHOW COLUMNS FROM modes_de_paiement")->fetchAll(PDO::FETCH_COLUMN);
$pmColName = in_array('nom', $pmCols) ? 'nom' : (in_array('name', $pmCols) ? 'name' : $pmCols[1]);
$payment_methods = $pdo->query("SELECT id, `$pmColName` AS name FROM modes_de_paiement ORDER BY `$pmColName` ASC")->fetchAll();

// Загрузка списка счетов (factures)
$facturesCols = $pdo->query("SHOW COLUMNS FROM factures")->fetchAll(PDO::FETCH_COLUMN);
$factureNumCol = in_array('facture_number', $facturesCols) ? 'facture_number' : (in_array('numero', $facturesCols) ? 'numero' : $facturesCols[1]);
$factures = $pdo->query("SELECT id, `$factureNumCol` AS facture_num FROM factures ORDER BY id DESC")->fetchAll();

require_once 'header.php';
?>

<title>Редактировать заказ #<?= htmlspecialchars($order['id_commande']); ?> — NumériqueAide</title>

<div class="container mt-4 mb-5" style="max-width: 800px;">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-warning bg-gradient text-dark d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Редактировать заказ #<?= htmlspecialchars($order['id_commande']); ?></h5>
            <a href="commandes_list.php" class="btn btn-sm btn-outline-dark">К списку заказов</a>
        </div>
        <div class="card-body p-4">

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Номер заказа (ID) *</label>
                        <input type="text" name="id_commande" class="form-control" value="<?= htmlspecialchars($order['id_commande']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Клиент</label>
                        <select name="client_id" class="form-select">
                            <option value="">-- Выберите клиента --</option>
                            <?php foreach ($clients as $client): ?>
                                <?php
                                $clientName = trim(($client['nom'] ?? '') . ' ' . ($client['prenom'] ?? ''));
                                $selected = ($client['id'] == $order['client_id']) ? 'selected' : '';
                                ?>
                                <option value="<?= $client['id']; ?>" <?= $selected; ?>><?= htmlspecialchars($clientName); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Дата заказа</label>
                        <input type="date" name="date_commande" class="form-control" value="<?= htmlspecialchars($order['date_commande']); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Сумма (€) *</label>
                        <input type="number" step="0.01" name="montant" class="form-control" value="<?= htmlspecialchars($order['montant']); ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Статус</label>
                        <?php $st = mb_strtolower(trim($order['statut'] ?? '')); ?>
                        <select name="statut" class="form-select">
                            <option value="Prévu" <?= ($st === 'prévu' || $st === 'prevu') ? 'selected' : ''; ?>>Запланирован (Prévu)</option>
                            <option value="En cours" <?= ($st === 'en cours' || $st === 'en_cours' || $st === 'en travail' || $st === 'в работе') ? 'selected' : ''; ?>>В работе (En cours)</option>
                            <option value="Payé" <?= ($st === 'payé' || $st === 'paye' || $st === 'terminee') ? 'selected' : ''; ?>>Оплачен (Payé)</option>
                            <option value="Annulée" <?= ($st === 'annulée' || $st === 'annulee') ? 'selected' : ''; ?>>Отменен (Annulée)</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Платформа / Сервис</label>
                        <select name="platform_id" class="form-select">
                            <option value="">-- Не указана --</option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?= $p['id']; ?>" <?= ($p['id'] == $order['platform_id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Способ оплаты</label>
                        <select name="payment_method_id" class="form-select">
                            <option value="">-- Не указан --</option>
                            <?php foreach ($payment_methods as $pm): ?>
                                <option value="<?= $pm['id']; ?>" <?= ($pm['id'] == $order['payment_method_id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($pm['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Привязка к счету -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-primary"><i class="bi bi-file-earmark-text me-1"></i>Счет (Facture)</label>
                        <select name="facture_id" class="form-select border-primary">
                            <option value="">-- Без счета --</option>
                            <?php foreach ($factures as $fac): ?>
                                <option value="<?= $fac['id']; ?>" <?= ($fac['id'] == $order['facture_id']) ? 'selected' : ''; ?>>
                                    Счет #<?= htmlspecialchars($fac['facture_num']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-success">Дата оплаты</label>
                        <input type="date" name="date_paiement" class="form-control" value="<?= htmlspecialchars($order['date_paiement'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Блок расчета и статуса уплаты -->
                <div class="card bg-light border-0 mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-calculator me-1"></i>Расчет и оплата налогов / отчислений</h6>

                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <?php $hasImpot = (float)($order['calcul_impot'] ?? 0) > 0; ?>
                                    <input class="form-check-input" type="checkbox" name="calcul_impot" id="calculImpot" value="1" <?= $hasImpot ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="calculImpot">Рассчитывать налог URSSAF (21.2%)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <?php $hasEpargne = (float)($order['calcul_epargne'] ?? 0) > 0; ?>
                                    <input class="form-check-input" type="checkbox" name="calcul_epargne" id="calculEpargne" value="1" <?= $hasEpargne ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="calculEpargne">Рассчитывать накопления (10%)</label>
                                </div>
                            </div>
                        </div>

                        <hr class="my-2">

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="impot_paye" id="impotPaye" value="1" <?= (!empty($order['impot_paye'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-success fw-bold" for="impotPaye"><i class="bi bi-check-circle me-1"></i>Налог URSSAF ОПЛАЧЕН</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="epargne_paye" id="epargnePaye" value="1" <?= (!empty($order['epargne_paye'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-success fw-bold" for="epargnePaye"><i class="bi bi-check-circle me-1"></i>Накопление ОТЧИСЛЕНО</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Комментарий к заказу</label>
                    <textarea name="commentaire" class="form-control" rows="2" placeholder="Краткое описание работы..."><?= htmlspecialchars($order['commentaire'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Внутренние заметки (Notes)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Заметки для себя..."><?= htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Сохранить изменения
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>