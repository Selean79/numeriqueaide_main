<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$client = null;
$error = '';

// Если передан ID, получаем данные клиента для редактирования
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $client = $stmt->fetch();
}

// Обработка отправки формы (сохранение/обновление)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($nom)) {
        if ($id > 0) {
            // Обновление существующего клиента
            $stmt = $pdo->prepare("UPDATE clients SET nom = :nom, prenom = :prenom, telephone = :telephone, email = :email, adresse = :adresse, notes = :notes WHERE id = :id");
            $stmt->execute([
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':telephone' => $telephone,
                    ':email' => $email,
                    ':adresse' => $adresse,
                    ':notes' => $notes,
                    ':id' => $id
            ]);
        } else {
            // Создание нового клиента
            $stmt = $pdo->prepare("INSERT INTO clients (nom, prenom, telephone, email, adresse, notes) VALUES (:nom, :prenom, :telephone, :email, :adresse, :notes)");
            $stmt->execute([
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':telephone' => $telephone,
                    ':email' => $email,
                    ':adresse' => $adresse,
                    ':notes' => $notes
            ]);
        }
        // Безопасный редирект после успешного сохранения
        header("Location: clients_list.php");
        exit;
    } else {
        $error = "Поле «Фамилия / Имя» обязательно для заполнения.";
    }
}

// Подключаем шапку строго ПОСЛЕ возможного редиректа
require_once 'header.php';
?>

<title><?= $id > 0 ? 'Редактирование клиента' : 'Добавление клиента'; ?> — NumériqueAide</title>

<div class="container mt-4" style="max-width: 600px;">
    <div class="d-flex align-items-center mb-4">
        <a href="clients_list.php" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i> Назад</a>
        <h3 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2"></i><?= $id > 0 ? 'Редактировать клиента' : 'Добавить клиента'; ?></h3>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Фамилия / Название <span class="text-danger">*</span></label>
                    <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($client['nom'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Имя</label>
                    <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($client['prenom'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Телефон</label>
                    <input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($client['telephone'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($client['email'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Адрес</label>
                    <textarea name="adresse" class="form-control" rows="2"><?= htmlspecialchars($client['adresse'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Заметки</label>
                    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($client['notes'] ?? ''); ?></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="clients_list.php" class="btn btn-secondary">Отмена</a>
                    <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i> Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>