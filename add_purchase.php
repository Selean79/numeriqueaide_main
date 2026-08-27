<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$message = '';

// 1. ЗАГРУЗКА СПИСКА МАГАЗИНОВ / ПОСТАВЩИКОВ
try {
    $fournisseurs = $pdo->query("SELECT id, nom_magasin FROM fournisseurs ORDER BY nom_magasin ASC")->fetchAll();
} catch (PDOException $e) {
    die("Ошибка загрузки списка магазинов: " . htmlspecialchars($e->getMessage()));
}

// 2. ОБРАБОТКА ОТПРАВКИ ФОРМЫ (перенаправление происходит ДО вывода HTML)
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
            $stmt = $pdo->prepare("INSERT INTO purchases (date_achat, fournisseur_id, montant, remarques) VALUES (:date_achat, :fournisseur_id, :montant, :remarques)");
            $stmt->execute([
                    ':date_achat' => $date_achat,
                    ':fournisseur_id' => $fournisseur_id,
                    ':montant' => $montant,
                    ':remarques' => $remarques
            ]);

            header("Location: purchases_list.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Ошибка сохранения закупки: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// 3. Подключаем шапку ТОЛЬКО ПОСЛЕ того, как все возможные редиректы отработали
require_once 'header.php';
?>

<title>Добавить закупку — NumériqueAide</title>

<div class="container mt-5" style="max-width: 650px;">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-bag-plus me-2"></i>Добавить закупку материалов</h4>
            <a href="purchases_list.php" class="btn btn-sm btn-outline-light">К списку закупок</a>
        </div>
        <div class="card-body">
            <?= $message; ?>

            <form action="" method="POST">
                <div class="row">
                    <!-- Дата закупки -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Дата закупки (Date d'achat) *</label>
                        <input type="date" name="date_achat" class="form-control" required
                               value="<?= htmlspecialchars($_POST['date_achat'] ?? date('Y-m-d')); ?>">
                    </div>

                    <!-- Магазин / Поставщик -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Магазин (Magasin)</label>
                        <select name="fournisseur_id" class="form-select">
                            <option value="">-- Выберите магазин --</option>
                            <?php foreach ($fournisseurs as $f): ?>
                                <?php $selected = (($_POST['fournisseur_id'] ?? '') == $f['id']) ? 'selected' : ''; ?>
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
                               value="<?= htmlspecialchars($_POST['montant'] ?? ''); ?>">
                        <span class="input-group-text">€</span>
                    </div>
                    <div class="form-text">Для возврата товара укажите сумму со знаком минус (например: -14.54).</div>
                </div>

                <!-- Примечания / Название материалов -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Примечания / Состав закупки (Remarques)</label>
                    <textarea name="remarques" class="form-control" rows="3"
                              placeholder="например: Клей универсальный, перчатки, провод, розетка..."><?= htmlspecialchars($_POST['remarques'] ?? ''); ?></textarea>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Сохранить закупку
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>