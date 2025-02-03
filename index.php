<?php
session_start();

// Charger la configuration
$config = require_once 'config/app.php';

// Création des dossiers s'ils n'existent pas
foreach ($config['folders'] as $folder) {
    if (!file_exists($folder)) {
        mkdir($folder, 0755, true);
    }
}

// Gestion de la déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Gestion de l'authentification
function checkAuth() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function getUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function login() {
    global $config;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        foreach ($config['auth'] as $user) {
            if ($username === $user['username'] && $password === $user['password']) {
                $_SESSION['logged_in'] = true;
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $username;
                header('Location: index.php');
                exit;
            }
        }
        $error = "Identifiants incorrects";
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Connexion - Serveur de Fichiers</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <div class="login-form">
            <h2>Connexion</h2>
            <?php if (isset($error)) : ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label>Nom d'utilisateur</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Se connecter</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Si non connecté, afficher le formulaire de connexion
if (!checkAuth()) {
    login();
}

// Gestion des chemins selon le rôle
$role = getUserRole();
$base_folder = $config['folders']['shared']; // Par défaut, on utilise le dossier partagé

if ($role === 'admin') {
    // Si l'admin est dans la vue privée, on utilise le dossier privé
    if (!isset($_GET['view']) || $_GET['view'] !== 'shared') {
        $base_folder = $config['folders']['private'];
    }
}

$current_path = isset($_GET['path']) ? $_GET['path'] : '';
$absolute_path = realpath($base_folder . '/' . $current_path);

// Vérification de sécurité du chemin
if ($absolute_path === false || strpos($absolute_path, realpath($base_folder)) !== 0) {
    $current_path = '';
    $absolute_path = realpath($base_folder);
}

// Gestion du partage de fichiers (admin uniquement)
if ($role === 'admin' && isset($_POST['share']) && isset($_POST['file'])) {
    $file = $config['folders']['private'] . '/' . $_POST['file'];
    $target = $config['folders']['shared'] . '/' . basename($_POST['file']);
    if (file_exists($file) && is_file($file)) {
        copy($file, $target);
    }
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Gestion du retrait du partage (admin uniquement)
if ($role === 'admin' && isset($_POST['unshare']) && isset($_POST['file'])) {
    $file = $config['folders']['shared'] . '/' . $_POST['file'];
    if (file_exists($file) && is_file($file)) {
        unlink($file);
    }
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Gestion du téléchargement de fichiers (admin uniquement)
if ($role === 'admin' && isset($_FILES['file']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false];
    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $config['allowed_extensions'])) {
        // Détermine le dossier cible en fonction de la vue actuelle
        $target_folder = isset($_GET['view']) && $_GET['view'] === 'shared' 
            ? $config['folders']['shared'] 
            : $config['folders']['private'];
            
        $target = $target_folder . '/' . ($current_path ? $current_path . '/' : '') . basename($file['name']);
        
        // Crée le dossier parent si nécessaire
        $parent_dir = dirname($target);
        if (!file_exists($parent_dir)) {
            mkdir($parent_dir, 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $response['success'] = true;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Gestion du streaming et téléchargement
if (isset($_GET['download'])) {
    $file = $base_folder . '/' . $_GET['download'];
    if (file_exists($file) && is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $config['allowed_extensions'])) {
            $mime = isset($config['mime_types'][$ext]) ? $config['mime_types'][$ext] : 'application/octet-stream';
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($file));
            if (isset($_GET['stream'])) {
                header('Content-Disposition: inline');
            } else {
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            }
            readfile($file);
            exit;
        }
    }
}

// Obtenir la liste des fichiers et dossiers
$items = [];
if (is_dir($absolute_path)) {
    foreach (scandir($absolute_path) as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $current_path ? $current_path . '/' . $item : $item;
        $full_path = $absolute_path . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($full_path)) {
            $items[] = [
                'name' => $item,
                'path' => $path,
                'is_dir' => true
            ];
        } else {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (in_array($ext, $config['allowed_extensions'])) {
                $is_shared = file_exists($config['folders']['shared'] . '/' . $item);
                $items[] = [
                    'name' => $item,
                    'path' => $path,
                    'is_dir' => false,
                    'is_media' => in_array($ext, ['mp3', 'mp4']),
                    'is_shared' => $is_shared
                ];
            }
        }
    }
}

// Trier les éléments (dossiers d'abord)
usort($items, function($a, $b) {
    if ($a['is_dir'] != $b['is_dir']) {
        return $b['is_dir'] - $a['is_dir'];
    }
    return strcasecmp($a['name'], $b['name']);
});

?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $role === 'admin' ? 'Administration' : 'Fichiers Partagés' ?> - Serveur de Fichiers</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <h1><?= $role === 'admin' ? 'Administration' : 'Fichiers Partagés' ?></h1>
                <?php if ($role === 'admin') : ?>
                    <div class="view-toggle">
                        <a href="?view=private" class="<?= !isset($_GET['view']) || $_GET['view'] !== 'shared' ? 'active' : '' ?>">Mes Fichiers</a>
                        <a href="?view=shared" class="<?= isset($_GET['view']) && $_GET['view'] === 'shared' ? 'active' : '' ?>">Fichiers Partagés</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="header-right">
                <span class="username"><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="?logout" class="logout-btn">Déconnexion</a>
            </div>
        </div>

        <div class="search-container">
            <input type="text" class="search-input" placeholder="Rechercher des fichiers...">
        </div>

        <div class="breadcrumb">
            <?php 
            $view_param = ($role === 'admin' && isset($_GET['view'])) ? '&view=' . $_GET['view'] : '';
            ?>
            <a href="?path=<?= $view_param ?>">Accueil</a>
            <?php
            $parts = explode('/', $current_path);
            $path = '';
            foreach ($parts as $part) {
                if ($part) {
                    $path .= ($path ? '/' : '') . $part;
                    echo ' / <a href="?path=' . urlencode($path) . $view_param . '">' . htmlspecialchars($part) . '</a>';
                }
            }
            ?>
        </div>

        <?php if ($role === 'admin') : ?>
        <div class="dropzone">
            <p>Glissez et déposez vos fichiers ici</p>
            <p>ou</p>
            <label class="btn btn-primary">
                Choisir des fichiers
                <input type="file" multiple style="display: none">
            </label>
        </div>

        <div class="upload-progress-container"></div>
        <?php endif; ?>

        <ul class="file-list">
            <?php if ($current_path) : ?>
                <li class="file-item">
                    <a href="?path=<?= urlencode(dirname($current_path)) . $view_param ?>">
                        <span class="folder-icon"></span>
                        <span class="file-name">..</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php foreach ($items as $item) : ?>
                <li class="file-item">
                    <?php if ($item['is_dir']) : ?>
                        <a href="?path=<?= urlencode($item['path']) . $view_param ?>">
                            <span class="folder-icon"></span>
                            <span class="file-name"><?= htmlspecialchars($item['name']) ?></span>
                        </a>
                    <?php else : ?>
                        <a href="?download=<?= urlencode($item['path']) . $view_param ?>" <?= isset($item['is_media']) && $item['is_media'] ? 'data-type="media"' : '' ?>>
                            <span class="<?= isset($item['is_media']) && $item['is_media'] ? 'media-icon' : 'file-icon' ?>"></span>
                            <span class="file-name"><?= htmlspecialchars($item['name']) ?></span>
                        </a>
                        <div class="file-actions">
                            <?php if (isset($item['is_media']) && $item['is_media']) : ?>
                                <a href="?stream&download=<?= urlencode($item['path']) . $view_param ?>" class="btn btn-primary">Lire</a>
                            <?php endif; ?>
                            <a href="?download=<?= urlencode($item['path']) . $view_param ?>" class="btn">Télécharger</a>
                            <?php if ($role === 'admin') : ?>
                                <form method="post" class="share-form" style="display: inline;">
                                    <input type="hidden" name="file" value="<?= htmlspecialchars($item['path']) ?>">
                                    <?php if (!$item['is_shared']) : ?>
                                        <button type="submit" name="share" class="btn btn-secondary">Partager</button>
                                    <?php else : ?>
                                        <button type="submit" name="unshare" class="btn btn-danger">Ne plus partager</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <script src="src/js/app.js"></script>
</body>
</html>