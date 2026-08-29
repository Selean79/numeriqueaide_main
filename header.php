<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        /* Фиксация шапки поверх контента */
        .navbar-sticky {
            position: sticky;
            top: 0;
            z-index: 1030;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark navbar-sticky shadow-sm mb-3">
    <div class="container-fluid px-4">
        <!-- Логотип -->
        <a class="navbar-brand fw-bold text-success d-flex align-items-center" href="index.php">
            <i class="bi bi-cpu me-2 fs-4"></i>NumériqueAide
        </a>

        <!-- Кнопка гамбургера для мобильных -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Пункты меню -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="bi bi-speedometer2 me-1"></i>Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="commandes_list.php"><i class="bi bi-cart me-1"></i>Commandes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="factures_list.php"><i class="bi bi-file-earmark-text me-1"></i>Factures</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="clients_list.php"><i class="bi bi-people me-1"></i>Clients</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="fournisseurs_list.php"><i class="bi bi-shop me-1"></i>Fournisseurs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="purchases_list.php"><i class="bi bi-bag me-1"></i>Achats</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="platforms_list.php"><i class="bi bi-diagram-3 me-1"></i>Plateformes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-bar-graph me-1"></i>Rapports</a>
                </li>
            </ul>

            <!-- Кнопки прокрутки: Вверх (справа) и Вниз (слева) -->
            <button type="button" class="btn btn-primary btn-lg rounded-circle shadow" id="btn-back-to-top"
                    style="position: fixed; bottom: 20px; right: 20px; display: none; z-index: 9999;">
                <i class="bi bi-arrow-up"></i>
            </button>

            <button type="button" class="btn btn-secondary btn-lg rounded-circle shadow" id="btn-scroll-to-bottom"
                    style="position: fixed; bottom: 20px; left: 20px; z-index: 9999;">
                <i class="bi bi-arrow-down"></i>
            </button>

            <script>
                // Логика кнопки "Вверх"
                let topBtn = document.getElementById("btn-back-to-top");
                window.onscroll = function () {
                    if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
                        topBtn.style.display = "block";
                    } else {
                        topBtn.style.display = "none";
                    }
                };
                topBtn.addEventListener("click", function() {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });

                // Логика кнопки "Вниз"
                let bottomBtn = document.getElementById("btn-scroll-to-bottom");
                bottomBtn.addEventListener("click", function() {
                    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                });
            </script>
        </div>
    </div>
</nav>