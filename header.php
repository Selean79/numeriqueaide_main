<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Если пользователь не авторизован, перенаправляем на страницу входа
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-4 shadow-sm mb-4 sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-cpu me-2"></i>NumériqueAide
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="bi bi-speedometer2 me-1"></i> Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="commandes_list.php"><i class="bi bi-cart-check me-1"></i> Commandes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="factures_list.php"><i class="bi bi-receipt me-1"></i> Factures</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="clients_list.php"><i class="bi bi-people me-1"></i> Clients</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="purchases_list.php"><i class="bi bi-bag-check me-1"></i> Achats</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-1"></i> Partenaires
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="platforms_list.php">Plateformes</a></li>
                        <li><a class="dropdown-item" href="fournisseurs_list.php">Fournisseurs / Magasins</a></li>
                    </ul>
                </li>
            </ul>

            <!-- Кнопка выхода в самом правом краю -->
            <div class="ms-auto">
                <a href="logout.php" class="btn btn-sm btn-outline-danger" title="Se déconnecter">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </div>
    </div>
</nav>