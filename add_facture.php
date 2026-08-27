<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';

$message = '';

// 1. ЗАГРУЗКА СПИСКА КЛИЕНТОВ
try {
    $clientsStmt = $pdo->query("SELECT id, nom, prenom, societe FROM clients ORDER BY nom ASC, prenom ASC");
    $clients = $clientsStmt->fetchAll();
} catch (PDOException $e) {
    die("Ошибка загрузки клиентов: " . htmlspecialchars($e->getMessage()));
}

// 2. ГЕНЕРАЦИЯ НОМЕРА СЧЕТА
$yearMonth = date('Ym');
$nextNumStmt = $pdo->query("SELECT COUNT(*) + 1 AS next_num FROM factures WHERE DATE_FORMAT(created_at, '%Y%m') = '$yearMonth'");
$nextNum = $nextNumStmt->fetch()['next_num'] ?? 1;
$defaultFactureNumber = 'FAC-' . $yearMonth . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

// 3. ОБРАБОТКА СОХРАНЕНИЯ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $facture_number = trim($_POST['facture_number'] ?? '');
    $client_id = (int)($_POST['client_id'] ?? 0);
    $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
    $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
    $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : null;
    $total_amount = (float)str_replace(',', '.', $_POST['total_amount'] ?? 0);
    $status = $_POST['status'] ?? 'pending';

    if (empty($facture_number)) {
        $message = '<div class="alert alert-danger">Поле "Номер счета" обязательно!</div>';
    } elseif ($client_id <= 0) {
        $message = '<div class="alert alert-danger">Выберите клиента!</div>';
    } elseif ($total_amount <= 0) {
        $message = '<div class="alert alert-danger">Укажите корректную сумму!</div>';
    } else {
        try {
            $sql = "INSERT INTO factures (facture_number, client_id, issue_date, due_date, payment_date, total_amount, status, created_at) 
                    VALUES (:facture_number, :client_id, :issue_date, :due_date, :payment_date, :total_amount, :status, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':facture_number' => $facture_number,
                ':client_id' => $client_id,
                ':issue_date' => $issue_date,
                ':due_date' => $due_date,
                ':payment_date' => $payment_date,
                ':total_amount' => $total_amount,
                ':status' => $status
            ]);

            header("Location: factures_list.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Ошибка сохранения счета: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выставить счет — NumériqueAide</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="container mt-5 mb-5" style="max-width: 650px;">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-file-earmark-plus me-2"></i>Выставить новый счет</h4>
            <a href="factures_list.php" class="btn btn-sm btn-outline-light">К списку счетов</a>
        </div>
        <div class="card-body">
            <?= $message; ?>

            <form action="" method="POST">
                <!-- Номер счета -->
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Номер счета *</label>
                    <input type="text" name="facture_number" class="form-control" required
                           value="<?= htmlspecialchars($_POST['facture_number'] ?? $defaultFactureNumber); ?>">
                </div>

                <!-- Выбор клиента -->
                <div class="mb-3">
                    <label class="form-label">Клиент *</label>
                    <select name="client_id" class="form-select" required>
                        <option value="">-- Выберите клиента из базы --</option>
                        <?php foreach ($clients as $c): ?>
                            <?php 
                                $selected = (($_POST['client_id'] ?? '') == $c['id']) ? 'selected' : '';
                                $clientLabel = htmlspecialchars($c['nom'] . ' ' . $c['prenom']);
                                if (!empty($c['societe'])) {
                                    $clientLabel .= ' (Компания)';
                                }
                            ?>
                            <option value="<?= $c['id']; ?>" <?= $selected; ?>><?= $clientLabel; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Даты счета -->
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Дата выставления</label>
                        <input type="date" name="issue_date" class="form-control" 
                               value="<?= htmlspecialchars($_POST['issue_date'] ?? date('Y-m-d')); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Срок оплаты</label>
                        <input type="date" name="due_date" class="form-control" 
                               value="<?= htmlspecialchars($_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'))); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-success">Дата оплаты</label>
                        <input type="date" name="payment_date" class="form-control" 
                               value="<?= htmlspecialchars($_POST['payment_date'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Сумма и Статус -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Сумма (€) *</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" name="total_amount" class="form-control" placeholder="0.00" required
                                   value="<?= htmlspecialchars($_POST['total_amount'] ?? ''); ?>">
                            <span class="input-group-text">€</span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Статус оплаты</label>
                        <select name="status" class="form-select">
                            <option value="pending" <?= (($_POST['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>Ожидает оплаты</option>
                            <option value="paid" <?= (($_POST['status'] ?? '') === 'paid') ? 'selected' : ''; ?>>Оплачен</option>
                            <option value="cancelled" <?= (($_POST['status'] ?? '') === 'cancelled') ? 'selected' : ''; ?>>Отменен</option>
                        </select>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-3">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Сохранить и выставить счет
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.querySelectorAll('input, textarea').forEach(el => {
    const orig = el.placeholder;
    el.addEventListener('focus', () => { el.placeholder = ''; });
    el.addEventListener('blur', () => { el.placeholder = orig; });
  });
</script>
</body>
</html>
