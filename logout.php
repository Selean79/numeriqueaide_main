<?php
session_start();

// Отмечаем, что пользователь вышел — красная точка «en ligne» сразу пропадёт
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'db.php';
        $stmt = $pdo->prepare("UPDATE users SET last_activity = NULL WHERE id = :id");
        $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    } catch (Throwable $e) {
        // не мешаем выходу, даже если колонки ещё нет
    }
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
header("Location: login.php");
exit;