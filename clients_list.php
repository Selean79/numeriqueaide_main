<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Подключаем базу данных
require_once 'db.php';

// 1. Сначала обрабатываем удаление клиента (до отправки любых HTML-данных и шапки!)
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);

        // Перенаправление через JavaScript для полной защиты от ошибки headers already sent
        echo '<script>window.location.href = "clients_list.php?deleted=1";</script>';
        exit;
    } catch (PDOException $e) {
        $error_message = "Ошибка при удалении: " . $e->getMessage();
    }
}

// 2. Загружаем список клиентов из базы данных
try {
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY id DESC");
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Ошибка загрузки клиентов: " . htmlspecialchars($e->getMessage()));
}

// 3. Только теперь подключаем HTML-шапку
require_once 'header.php';
?>

<title>Список клиентов — NumériqueAide</title>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="bi bi-people me-2"></i>Список клиентов</h3>
        <a href="add_client.php" class="btn btn-success">
            <i class="bi bi-person-plus me-1"></i> Добавить клиента
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Клиент успешно удален.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 1000px;">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 60px;" class="text-center">#</th>
                        <th style="width: 220px;">ФИО / Название</th>
                        <th style="width: 130px;">Тип</th>
                        <th style="width: 170px;" class="text-nowrap">Телефон</th>
                        <th style="width: 200px;">Email</th>
                        <th>Адрес</th>
                        <th>Заметки</th>
                        <th style="width: 100px;" class="text-center">Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Клиенты пока не добавлены</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td class="text-center fw-bold text-secondary"><?= $client['id']; ?></td>

                                <td class="fw-bold">
                                    <?= htmlspecialchars(trim(($client['nom'] ?? '') . ' ' . ($client['prenom'] ?? ''))); ?>
                                </td>

                                <td>
                                    <?php if (!empty($client['societe'])): ?>
                                        <span class="badge bg-primary">Компания</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Частное лицо</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-nowrap fw-semibold text-primary">
                                    <?php if (!empty($client['telephone'])): ?>
                                        <?= str_replace(' ', '&nbsp;', htmlspecialchars($client['telephone'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (!empty($client['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($client['email']); ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($client['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= !empty($client['adresse']) ? htmlspecialchars($client['adresse']) : '<span class="text-muted">—</span>'; ?></td>

                                <td><small class="text-muted"><?= !empty($client['notes']) ? htmlspecialchars($client['notes']) : '—'; ?></small></td>

                                <td class="text-center text-nowrap">
                                    <a href="edit_client.php?id=<?= $client['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Редактировать">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="clients_list.php?delete_id=<?= $client['id']; ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Вы уверены, что хотите удалить этого клиента?');"
                                       title="Удалить">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>