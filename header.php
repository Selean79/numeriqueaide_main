<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Если пользователь не авторизован, перенаправляем на страницу входа
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Отмечаем активность пользователя (для индикатора «en ligne» в списке пользователей).
// Пишем в базу не чаще раза в минуту, чтобы не нагружать её на каждой странице.
if (isset($pdo) && (time() - ($_SESSION['last_activity_saved'] ?? 0)) >= 60) {
    try {
        $stmtActivity = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = :id");
        $stmtActivity->execute([':id' => (int)$_SESSION['user_id']]);
        $_SESSION['last_activity_saved'] = time();
    } catch (PDOException $e) {
        // колонки last_activity ещё нет — просто пропускаем
    }
}

// Получаем тип текущего пользователя (по умолчанию User, если не задан)
$userType = $_SESSION['type'] ?? 'User';

// Текущая страница — для подсветки пункта меню
$currentPage = basename($_SERVER['PHP_SELF']);

// Какие страницы относятся к какому пункту меню
$menuGroups = [
    'index'       => ['index.php'],
    'commandes'   => ['commandes_list.php', 'add_commande.php', 'edit_commande.php'],
    'factures'    => ['factures_list.php', 'add_facture.php', 'edit_facture.php'],
    'clients'     => ['clients_list.php', 'add_client.php', 'edit_client.php'],
    'achats'      => ['purchases_list.php', 'add_purchase.php', 'edit_purchase.php'],
    'salaires'    => ['salaires_list.php'],
    'rapports'    => ['reports.php', 'report_chart.php', 'report_daily_clients.php', 'report_mensuel.php', 'report_status_sum.php', 'report_unpaid_taxes.php'],
    'plateformes' => ['platforms_list.php', 'add_platform.php', 'edit_platform.php'],
    'magasins'    => ['fournisseurs_list.php', 'add_fournisseur.php', 'edit_fournisseur.php'],
    'users'       => ['users_list.php', 'user_edit.php', 'register_user.php'],
];

function navActive(string $group): string {
    global $menuGroups, $currentPage;
    return in_array($currentPage, $menuGroups[$group] ?? [], true) ? ' active' : '';
}

$partenairesActive = navActive('plateformes') !== '' || navActive('magasins') !== '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="img/favicon.png">
    <style>
        /* ---------- Компьютер ---------- */
        .navbar-nav .nav-link {
            transition: color 0.2s ease, background-color 0.2s ease;
            border-radius: 6px;
        }
        .navbar-nav .nav-link:hover {
            color: #ffffff !important;
        }
        /* Активный пункт меню */
        .navbar-dark .navbar-nav .nav-link.active {
            color: #ffffff !important;
            background-color: rgba(130, 232, 158, .18);
        }
        .navbar-dark .navbar-nav .nav-link.active i { color: #82e89e; }

        /* Темный фон и стили для выпадающего меню */
        .navbar-dark .dropdown-menu {
            background-color: #212529;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .navbar-dark .dropdown-menu .dropdown-item {
            color: rgba(255, 255, 255, 0.75);
        }
        .navbar-dark .dropdown-menu .dropdown-item:hover,
        .navbar-dark .dropdown-menu .dropdown-item.active {
            background-color: #343a40;
            color: #ffffff;
        }

        .nav-user { color: #e5e7eb; }
        .nav-user .role { color: #9ca3af; }

        /* ---------- Телефон и планшет (меню свёрнуто) ---------- */
        @media (max-width: 991.98px) {
            .navbar.main-nav { padding-left: 12px !important; padding-right: 12px !important; }

            /* Пункты меню — плитки 3 в ряд */
            .main-nav .navbar-nav {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
                margin: 12px 0 !important;
            }
            .main-nav .navbar-nav .nav-link {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 4px;
                height: 72px;
                padding: 8px 4px;
                background-color: rgba(255, 255, 255, .06);
                border: 1px solid rgba(255, 255, 255, .08);
                border-radius: 10px;
                font-size: .82rem;
                text-align: center;
                color: rgba(255, 255, 255, .8);
            }
            .main-nav .navbar-nav .nav-link i {
                font-size: 1.35rem;
                margin: 0 !important;
            }
            .main-nav .navbar-nav .nav-link:active { background-color: rgba(255, 255, 255, .14); }
            .main-nav .navbar-nav .nav-link.active {
                background-color: rgba(130, 232, 158, .18);
                border-color: rgba(130, 232, 158, .5);
            }

            /* Блок пользователя внизу меню */
            .nav-user-block {
                display: flex;
                justify-content: space-between;
                align-items: center;
                width: 100%;
                padding: 12px 0 6px;
                border-top: 1px solid rgba(255, 255, 255, .12);
            }
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-4 shadow-sm mb-4 sticky-top main-nav">
    <div class="container-fluid px-0 px-lg-2">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-cpu me-2"></i>NumériqueAide
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?= navActive('index'); ?>" href="index.php"><i class="bi bi-speedometer2 me-1"></i> Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= navActive('commandes'); ?>" href="commandes_list.php"><i class="bi bi-cart-check me-1"></i> Commandes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= navActive('factures'); ?>" href="factures_list.php"><i class="bi bi-receipt me-1"></i> Factures</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= navActive('clients'); ?>" href="clients_list.php"><i class="bi bi-people me-1"></i> Clients</a>
                </li>

                <!-- Пункты ниже видны ТОЛЬКО для Admin и PowerUser -->
                <?php if ($userType !== 'User'): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= navActive('achats'); ?>" href="purchases_list.php"><i class="bi bi-bag-check me-1"></i> Achats</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= navActive('salaires'); ?>" href="salaires_list.php"><i class="bi bi-wallet2 me-1"></i> Salaires</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= navActive('rapports'); ?>" href="reports.php"><i class="bi bi-file-earmark-bar-graph me-1"></i> Rapports</a>
                    </li>

                    <!-- Компьютер: выпадающее меню «Partenaires» -->
                    <li class="nav-item dropdown d-none d-lg-block">
                        <a class="nav-link dropdown-toggle<?= $partenairesActive ? ' active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear me-1"></i> Partenaires
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item<?= navActive('plateformes'); ?>" href="platforms_list.php">Plateformes</a></li>
                            <li><a class="dropdown-item<?= navActive('magasins'); ?>" href="fournisseurs_list.php">Fournisseurs / Magasins</a></li>
                        </ul>
                    </li>
                    <!-- Телефон: те же пункты отдельными плитками -->
                    <li class="nav-item d-lg-none">
                        <a class="nav-link<?= navActive('plateformes'); ?>" href="platforms_list.php"><i class="bi bi-diagram-3"></i> Plateformes</a>
                    </li>
                    <li class="nav-item d-lg-none">
                        <a class="nav-link<?= navActive('magasins'); ?>" href="fournisseurs_list.php"><i class="bi bi-shop"></i> Magasins</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link<?= navActive('users'); ?>" href="users_list.php"><i class="bi bi-person-badge me-1"></i> Users</a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Имя пользователя и кнопка выхода -->
            <div class="ms-lg-auto d-flex align-items-center nav-user-block">
                <span class="nav-user me-3 small">
                    <i class="bi bi-person-circle me-1 text-info"></i>
                    <strong><?= htmlspecialchars($_SESSION['username'] ?? ''); ?></strong>
                    <span class="role ms-1">(<?= htmlspecialchars($userType); ?>)</span>
                </span>
                <a href="logout.php" class="btn btn-sm btn-outline-danger" title="Se déconnecter">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
    // Мобильное меню: закрывается при нажатии в любом месте страницы вне меню
    document.addEventListener('DOMContentLoaded', function () {
        const menu = document.getElementById('navbarNav');
        const nav  = document.querySelector('.main-nav');
        if (!menu || !nav) return;

        function closeMenu() {
            if (!menu.classList.contains('show')) return;
            if (window.bootstrap && bootstrap.Collapse) {
                bootstrap.Collapse.getOrCreateInstance(menu, { toggle: false }).hide();
            } else {
                menu.classList.remove('show');
            }
        }

        document.addEventListener('click', function (e) {
            if (!nav.contains(e.target)) closeMenu();
        });
        document.addEventListener('touchstart', function (e) {
            if (!nav.contains(e.target)) closeMenu();
        }, { passive: true });
    });
</script>