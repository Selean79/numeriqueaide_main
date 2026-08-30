<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Если пользователь уже авторизован, перенаправляем его на главную (или дашборд)
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            // Проверяем пользователя и хэш пароля
            if ($user && password_verify($password, $user['password'])) {
                // Успешный вход: сохраняем данные в сессию
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                header("Location: index.php");
                exit;
            } else {
                $error = "Nom d'utilisateur ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Erreur de base de données : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — NumériqueAide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #cbd5e1; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { width: 100%; max-width: 400px; border: none; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
    </style>
</head>
<body>

<div class="card p-4">
    <div class="text-center mb-4">
        <h3 class="fw-bold text-success"><i class="bi bi-shield-lock me-2"></i>NumériqueAide</h3>
        <p class="text-muted small">Vकृपया connectez-vous pour continuer</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-semibold">Nom d'utilisateur</label>
            <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Mot de passe</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="d-grid mt-4">
            <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-box-arrow-in-right me-1"></i> Se connecter
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>