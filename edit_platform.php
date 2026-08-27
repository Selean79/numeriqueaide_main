<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';

$message = '';
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: platforms_list.php');
    exit;
}

// 1. ЗАГРУЗКА ДАННЫХ ПЛАТФОРМЫ
try {
    $stmt = $pdo->prepare("SELECT * FROM platforms WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $platform = $stmt->fetch();

    if (!$platform) {
        die("Платформа не найдена.");
    }
} catch (PDOException $e) {
    die("Ошибка загрузки: " . htmlspecialchars($e->getMessage()));
}

// 2. ОБРАБОТКА СОХРАНЕНИЯ ИЗМЕНЕНИЙ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $default_impot_rate = (float)str_replace(',', '.', $_POST['default_impot_rate'] ?? 0);
    $default_epargne_rate = (float)str_replace(',', '.', $_POST['default_epargne_rate'] ?? 0);

    if (empty($name)) {
        $message = '<div class="alert alert-danger">Название платформы обязательно!</div>';
    } else {
        try {
            $updateStmt = $pdo->prepare("UPDATE platforms SET name = :name, default_impot_rate = :impot, default_epargne_rate = :epargne WHERE id = :id");
            $updateStmt->execute([
                ':name' => $name,
                ':impot' => $default_impot_rate,
                ':epargne' => $default_epargne_rate,
                ':id' => $id
            ]);

            header("Location: platforms_list.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Ошибка обновления: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

<title>Редактировать платформу — NumériqueAide</title>

<div class="container mt-5" style="max-width: 550px;">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Редактировать платформу #<?= $platform['id']; ?></h4>
            <a href="platforms_list.php" class="btn btn-sm btn-outline-dark">К списку</a>
        </div>
        <div class="card-body">
            <?= $message; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Название платформы *</label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['name'] ?? $platform['name']); ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ставка налога (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" name="default_impot_rate" class="form-control"
                                   value="<?= htmlspecialchars($_POST['default_impot_rate'] ?? $platform['default_impot_rate']); ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ставка отчислений (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" name="default_epargne_rate" class="form-control"
                                   value="<?= htmlspecialchars($_POST['default_epargne_rate'] ?? $platform['default_epargne_rate']); ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-3">
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
