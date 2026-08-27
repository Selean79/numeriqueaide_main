<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';

$message = '';
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: purchases_list.php');
    exit;
}

// 1. ЗАГРУЗКА ДАННЫХ РЕДАКТИРУЕМОЙ ЗАКУПКИ
try {
    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $purchase = $stmt->fetch();

    if (!$purchase) {
        die("Запись о закупке не найдена.");
    }
} catch (PDOException $e) {
    die("Ошибка загрузки закупки: " . htmlspecialchars($e->getMessage()));
}

// 2. ЗАГРУЗКА СПИСКА МАГАЗИНОВ / ПОСТАВЩИКОВ
try {
    $fournisseurs = $pdo->query("SELECT id, nom_magasin FROM fournisseurs ORDER BY nom_magasin ASC")->fetchAll();
} catch (PDOException $e) {
    die("Ошибка загрузки списка магазинов: " . htmlspecialchars($e->getMessage()));
}

// 3. ОБРАБОТКА СОХРАНЕНИЯ ИЗМЕНЕНИЙ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_achat = $_POST['date_achat'] ?? date('Y-m-d');
    $fournisseur_id = !empty($_POST['fournisseur_id']) ? (int)$_POST['fournisseur_id'] : null;
    $montant = (float)str_replace(',', '.', $_POST['montant'] ?? 0);
    $remarques = trim($_POST['remarques'] ?? '');

    if (empty($date_achat)) {
        $message = '<div class="alert alert-danger">Укажите дату закупки!</div>';
    } elseif ($montant == 0) {
        $message = '<div class="alert alert-danger">Сумма закупки не может быть равна 0!</div>';
    } else {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE purchases 
                SET date_achat = :date_achat, 
                    fournisseur_id = :fournisseur_id, 
                    montant = :montant, 
                    remarques = :remarques 
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':date_achat' => $date_achat,
                ':fournisseur_id' => $fournisseur_id,
                ':montant' => $montant,
                ':remarques' => $remarques,
                ':id' => $id
            ]);

            header("Location: purchases_list.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Ошибка обновления закупки: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

<title>Редактировать закупку — NumériqueAide</title>

<div class="container mt-5" style="max-width: 650px;">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Редактировать закупку #<?= $purchase['id']; ?></h4>
            <a href="purchases_list.php" class="btn btn-sm btn-outline-dark">К списку закупок</a>
        </div>
        <div class="card-body">
            <?= $message; ?>

            <form action="" method="POST">
                <div class="row">
                    <!-- Дата закупки -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Дата закупки (Date d'achat) *</label>
                        <input type="date" name="date_achat" class="form-control" required
                               value="<?= htmlspecialchars($_POST['date_achat'] ?? $purchase['date_achat']); ?>">
                    </div>

                    <!-- Магазин / Поставщик -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Магазин (Magasin)</label>
                        <select name="fournisseur_id" class="form-select">
                            <option value="">-- Выберите магазин --</option>
                            <?php foreach ($fournisseurs as $f): ?>
                                <?php
                                $selected = (($_POST['fournisseur_id'] ?? $purchase['fournisseur_id']) == $f['id']) ? 'selected' : '';
                                ?>
                                <option value="<?= $f['id']; ?>" <?= $selected; ?>>
                                    <?= htmlspecialchars($f['nom_magasin']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Сумма закупки -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Сумма (€) *</label>
                    <div class="input-group">
                        <input type="number" step="0.01" name="montant" class="form-control" placeholder="15.80" required
                               value="<?= htmlspecialchars($_POST['montant'] ?? $purchase['montant']); ?>">
                        <span class="input-group-text">€</span>
                    </div>
                    <div class="form-text">Для возврата товара укажите сумму со знаком минус (например: -14.54).</div>
                </div>

                <!-- Примечания / Название материалов -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Примечания / Состав закупки (Remarques)</label>
                    <textarea name="remarques" class="form-control" rows="3"
                              placeholder="например: Клей универсальный, перчатки, провод, розетка..."><?= htmlspecialchars($_POST['remarques'] ?? $purchase['remarques'] ?? ''); ?></textarea>
                </div>

                <div class="d-grid gap-2 mt-4">
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