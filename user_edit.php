<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'header.php';

$error = '';

// Получаем ID пользователя для редактирования
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: users_list.php");
    exit;
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $nom      = trim($_POST['nom'] ?? '');
    $prenom   = trim($_POST['prenom'] ?? '');
    $password = $_POST['password'] ?? '';
    $type     = $_POST['type'] ?? 'User';
    $status   = $_POST['status'] ?? 'Active';

    if (empty($username)) {
        $error = "Le nom d'utilisateur est obligatoire.";
    } else {
        try {
            // Проверяем, не занят ли логин другим пользователем
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1");
            $stmt->execute([':username' => $username, ':id' => $id]);

            if ($stmt->fetch()) {
                $error = "Ce nom d'utilisateur est déjà pris par un autre utilisateur.";
            } else {
                // Если введен новый пароль, обновляем и его
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET username = :username, nom = :nom, prenom = :prenom, password = :password, type = :type, status = :status WHERE id = :id");
                    $updateStmt->execute([
                        ':username' => $username,
                        ':nom'      => $nom,
                        ':prenom'   => $prenom,
                        ':password' => $hashed_password,
                        ':type'     => $type,
                        ':status'   => $status,
                        ':id'       => $id
                    ]);
                } else {
                    // Обновляем без изменения пароля
                    $updateStmt = $pdo->prepare("UPDATE users SET username = :username, nom = :nom, prenom = :prenom, type = :type, status = :status WHERE id = :id");
                    $updateStmt->execute([
                        ':username' => $username,
                        ':nom'      => $nom,
                        ':prenom'   => $prenom,
                        ':type'     => $type,
                        ':status'   => $status,
                        ':id'       => $id
                    ]);
                }

                header("Location: users_list.php?updated=1");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Erreur de base de données : " . $e->getMessage();
        }
    }
}

// Загружаем данные текущего пользователя
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: users_list.php");
        exit;
    }
} catch (PDOException $e) {
    die("Erreur : " . htmlspecialchars($e->getMessage()));
}
?>

<title>Modifier l'utilisateur — NumériqueAide</title>

<div class="container mt-4" style="max-width: 600px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-person-gear me-2"></i>Modifier l'utilisateur</h3>
        <a href="users_list.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Retour à la liste
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" name="username" class="form-control" autocomplete="off" required value="<?= htmlspecialchars($_POST['username'] ?? $user['username']); ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Nom</label>
                        <input type="text" name="nom" class="form-control" autocomplete="off" value="<?= htmlspecialchars($_POST['nom'] ?? $user['nom']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Prénom</label>
                        <input type="text" name="prenom" class="form-control" autocomplete="off" value="<?= htmlspecialchars($_POST['prenom'] ?? $user['prenom']); ?>">
                    </div>
                </div>

                <!-- Поле пароля с кнопкой-глазиком -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nouveau mot de passe</label>
                    <div class="input-group">
                        <input type="password" name="password" id="passwordField" class="form-control" autocomplete="new-password" placeholder="Laisser vide pour ne pas changer">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                    <div class="form-text">Ne remplissez ce champ que si vous souhaitez modifier votre mot de passe actuel.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Type d'utilisateur</label>
                    <?php $currentType = $_POST['type'] ?? $user['type']; ?>
                    <select name="type" class="form-select">
                        <option value="User" <?= ($currentType === 'User') ? 'selected' : ''; ?>>User</option>
                        <option value="PowerUser" <?= ($currentType === 'PowerUser') ? 'selected' : ''; ?>>PowerUser</option>
                        <option value="Admin" <?= ($currentType === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Statut</label>
                    <?php $currentStatus = $_POST['status'] ?? $user['status']; ?>
                    <select name="status" class="form-select">
                        <option value="Active" <?= ($currentStatus === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?= ($currentStatus === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Скрипт для переключения видимости пароля -->
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const passwordField = document.getElementById('passwordField');
        const toggleIcon = document.getElementById('toggleIcon');

        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.classList.remove('bi-eye');
            toggleIcon.classList.add('bi-eye-slash');
        } else {
            passwordField.type = 'password';
            toggleIcon.classList.remove('bi-eye-slash');
            toggleIcon.classList.add('bi-eye');
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>