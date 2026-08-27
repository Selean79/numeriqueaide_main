<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$error = '';

// 1. Обработка отправки формы создания заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_commande       = trim($_POST['id_commande'] ?? '');
    $date_commande     = trim($_POST['date_commande'] ?? '');
    $client_id         = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
    $platform_id       = !empty($_POST['platform_id']) ? (int)$_POST['platform_id'] : null;
    $payment_method_id = !empty($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : null;
    $facture_id        = !empty($_POST['facture_id']) ? (int)$_POST['facture_id'] : null;
    $montant           = !empty($_POST['montant']) ? str_replace(',', '.', $_POST['montant']) : 0;
    $statut            = trim($_POST['statut'] ?? 'Prévu');
    $date_paiement     = !empty($_POST['date_paiement']) ? $_POST['date_paiement'] : null;
    $notes             = trim($_POST['notes'] ?? '');
    $commentaire       = trim($_POST['commentaire'] ?? '');

    $calcul_impot      = isset($_POST['calcul_impot']) ? 1 : 0;
    $calcul_epargne    = isset($_POST['calcul_epargne']) ? 1 : 0;
    $impot_paye        = isset($_POST['impot_paye']) ? 1 : 0;
    $epargne_paye      = isset($_POST['epargne_paye']) ? 1 : 0;

    if (!empty($montant) && !empty($date_commande)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO commandes (
                    id_commande, date_commande, client_id, platform_id, payment_method_id, 
                    facture_id, montant, statut, date_paiement, notes, commentaire, 
                    calcul_impot, calcul_epargne, impot_paye, epargne_paye
                ) VALUES (
                    :id_commande, :date_commande, :client_id, :platform_id, :payment_method_id, 
                    :facture_id, :montant, :statut, :date_paiement, :notes, :commentaire, 
                    :calcul_impot, :calcul_epargne, :impot_paye, :epargne_paye
                )
            ");
            $stmt->execute([
                    ':id_commande'       => $id_commande,
                    ':date_commande'     => $date_commande,
                    ':client_id'         => $client_id,
                    ':platform_id'       => $platform_id,
                    ':payment_method_id' => $payment_method_id,
                    ':facture_id'        => $facture_id,
                    ':montant'           => $montant,
                    ':statut'            => $statut,
                    ':date_paiement'     => $date_paiement,
                    ':notes'             => $notes,
                    ':commentaire'       => $commentaire,
                    ':calcul_impot'      => $calcul_impot,
                    ':calcul_epargne'    => $calcul_epargne,
                    ':impot_paye'        => $impot_paye,
                    ':epargne_paye'      => $epargne_paye
            ]);

            header("Location: commandes_list.php");
            exit;
        } catch (PDOException $e) {
            $error = "Ошибка базы данных: " . $e->getMessage();
        }
    } else {
        $error = "Пожалуйста, заполните обязательные поля (Сумма, Дата).";
    }
}

// Генерация номера в формате CMD-ГОД-НОМЕР
$current_year = date('Y');
$next_order_id = 'CMD-' . $current_year . '-1';
try {
    $stmtMax = $pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(id_commande, '-', -1) AS UNSIGNED)) FROM commandes WHERE id_commande LIKE 'CMD-%'");
    $max_id = $stmtMax->fetchColumn();
    if ($max_id) {
        $next_num = $max_id + 1;
        $next_order_id = 'CMD-' . $current_year . '-' . $next_num;
    }
} catch (Exception $e) {}

// Динамическое определение колонок для таблиц
$platCols = $pdo->query("SHOW COLUMNS FROM platforms")->fetchAll(PDO::FETCH_COLUMN);
$platColName = in_array('nom', $platCols) ? 'nom' : 'name';

$pmCols = $pdo->query("SHOW COLUMNS FROM modes_de_paiement")->fetchAll(PDO::FETCH_COLUMN);
$pmColName = in_array('nom', $pmCols) ? 'nom' : 'name';

// Получаем списки для выпадающих списков
$clients = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom ASC")->fetchAll();
$platforms = $pdo->query("SELECT id, `$platColName` AS name FROM platforms ORDER BY name ASC")->fetchAll();
$payment_methods = $pdo->query("SELECT id, `$pmColName` AS name FROM modes_de_paiement ORDER BY name ASC")->fetchAll();
$factures = [];
try {
    $factures = $pdo->query("SELECT id, facture_number FROM factures ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

require_once 'header.php';
?>

<title>Создание заказа — NumériqueAide</title>

<div class="container mt-4" style="max-width: 800px;">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-cart-plus me-2"></i>Создать новый заказ</h5>
            <a href="commandes_list.php" class="btn btn-sm btn-light text-success fw-semibold">К списку заказов</a>
        </div>
        <div class="card-body p-4">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small text-muted">Номер заказа (ID) *</label>
                        <input type="text" name="id_commande" class="form-control" value="<?= htmlspecialchars($next_order_id); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold small text-muted">Клиент</label>
                        <select name="client_id" class="form-select">
                            <option value="">-- Выберите клиента --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id']; ?>"><?= htmlspecialchars(trim($client['nom'] . ' ' . $client['prenom'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Дата заказа</label>
                        <input type="date" name="date_commande" class="form-control" required value="<?= date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Сумма (€) *</label>
                        <div class="input-group">
                            <input type="text" name="montant" class="form-control" placeholder="0.00" required>
                            <span class="input-group-text">€</span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Статус</label>
                        <select name="statut" class="form-select">
                            <option value="Prévu">Prévu (Запланирован)</option>
                            <option value="En cours">En cours (В работе)</option>
                            <option value="Payé">Payé (Оплачен)</option>
                            <option value="Annulée">Annulée (Отменен)</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Платформа / Сервис</label>
                        <select name="platform_id" class="form-select">
                            <option value="">-- Выберите платформу --</option>
                            <?php foreach ($platforms as $plat): ?>
                                <option value="<?= $plat['id']; ?>"><?= htmlspecialchars($plat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Способ оплаты</label>
                        <select name="payment_method_id" class="form-select">
                            <option value="">-- Выберите --</option>
                            <?php foreach ($payment_methods as $pm): ?>
                                <option value="<?= $pm['id']; ?>"><?= htmlspecialchars($pm['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-semibold small text-muted">Привязанный счет</label>
                        <select name="facture_id" class="form-select">
                            <option value="">- Нет счета -</option>
                            <?php foreach ($factures as $fac): ?>
                                <option value="<?= $fac['id']; ?>"><?= htmlspecialchars($fac['facture_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row align-items-center bg-light p-3 rounded mb-3">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="form-label fw-semibold small text-success mb-1">Дата оплаты</label>
                        <input type="date" name="date_paiement" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="form-label fw-semibold small text-muted mb-1">Расчет налога (€)</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" name="calcul_impot" id="calcul_impot" value="1" checked>
                            <label class="form-check-label small" for="calcul_impot">Рассчитывать</label>
                        </div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="impot_paye" id="impot_paye" value="1">
                            <label class="form-check-label small text-success fw-semibold" for="impot_paye">Налог уплачен</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-muted mb-1">Накопления (€)</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" name="calcul_epargne" id="calcul_epargne" value="1" checked>
                            <label class="form-check-label small" for="calcul_epargne">Рассчитывать</label>
                        </div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="epargne_paye" id="epargne_paye" value="1">
                            <label class="form-check-label small text-success fw-semibold" for="epargne_paye">Накопления переведены</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Комментарий к заказу</label>
                    <textarea name="commentaire" class="form-control" rows="2" placeholder="Краткое описание работы..."></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small text-muted">Внутренние заметки (Notes)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Служебные заметки..."></textarea>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Сохранить и создать заказ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>