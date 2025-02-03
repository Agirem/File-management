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

// Ajout de la gestion des vues PDF
if (isset($_GET['pdf_preview']) && isset($_GET['file'])) {
    $file = $base_folder . '/' . $_GET['file'];
    if (file_exists($file) && is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($file) . '"');
            readfile($file);
            exit;
        }
    }
}

// Gestion des prévisualisations d'images
if (isset($_GET['preview']) && isset($_GET['file'])) {
    $file = $base_folder . '/' . $_GET['file'];
    if (file_exists($file) && is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $mime = $config['mime_types'][$ext];
            header('Content-Type: ' . $mime);
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
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
</head>
<body>
    <?php if ($role === 'admin') : ?>
        <!-- Interface Admin -->
        <div class="container">
            <div class="header">
                <div class="header-left">
                    <h1>Administration</h1>
                    <div class="view-toggle">
                        <a href="?view=private" class="<?= !isset($_GET['view']) || $_GET['view'] !== 'shared' ? 'active' : '' ?>">Mes Fichiers</a>
                        <a href="?view=shared" class="<?= isset($_GET['view']) && $_GET['view'] === 'shared' ? 'active' : '' ?>">Fichiers Partagés</a>
                    </div>
                </div>
                <div class="header-right">
                    <span class="username"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <a href="?logout" class="logout-btn">Déconnexion</a>
                </div>
            </div>

            <div class="search-container">
                <input type="text" class="search-input" placeholder="Rechercher des fichiers...">
            </div>

            <div class="dropzone">
                <p>Glissez et déposez vos fichiers ici</p>
                <p>ou</p>
                <label class="btn btn-primary">
                    Choisir des fichiers
                    <input type="file" multiple style="display: none">
                </label>
            </div>

            <div class="upload-progress-container"></div>

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
                            <a href="?download=<?= urlencode($item['path']) . $view_param ?>">
                                <span class="<?= isset($item['is_media']) && $item['is_media'] ? 'media-icon' : 'file-icon' ?>"></span>
                                <span class="file-name"><?= htmlspecialchars($item['name']) ?></span>
                            </a>
                            <div class="file-actions">
                                <?php if (isset($item['is_media']) && $item['is_media']) : ?>
                                    <a href="?stream&download=<?= urlencode($item['path']) . $view_param ?>" class="btn btn-primary">Lire</a>
                                <?php endif; ?>
                                <?php if (strtolower(pathinfo($item['name'], PATHINFO_EXTENSION)) === 'pdf') : ?>
                                    <button onclick="previewPDF('<?= htmlspecialchars($item['path']) ?>')" class="btn btn-primary">Voir le PDF</button>
                                <?php endif; ?>
                                <a href="?download=<?= urlencode($item['path']) . $view_param ?>" class="btn">Télécharger</a>
                                <form method="post" class="share-form" style="display: inline;">
                                    <input type="hidden" name="file" value="<?= htmlspecialchars($item['path']) ?>">
                                    <?php if (!$item['is_shared']) : ?>
                                        <button type="submit" name="share" class="btn btn-secondary">Partager</button>
                                    <?php else : ?>
                                        <button type="submit" name="unshare" class="btn btn-danger">Ne plus partager</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else : ?>
        <!-- Interface Guest -->
        <div class="container">
            <div class="header">
                <div class="header-left">
                    <h1>Fichiers Partagés</h1>
                </div>
                <div class="header-right">
                    <span class="username"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <a href="?logout" class="logout-btn">Déconnexion</a>
                </div>
            </div>

            <div class="guest-interface">
                <div class="categories-nav">
                    <a href="?category=all" class="category-item <?= !isset($_GET['category']) || $_GET['category'] === 'all' ? 'active' : '' ?>">
                        <span class="category-icon">📑</span>
                        Tout
                    </a>
                    <a href="?category=documents" class="category-item <?= isset($_GET['category']) && $_GET['category'] === 'documents' ? 'active' : '' ?>">
                        <span class="category-icon">📄</span>
                        Documents
                    </a>
                    <a href="?category=media" class="category-item <?= isset($_GET['category']) && $_GET['category'] === 'media' ? 'active' : '' ?>">
                        <span class="category-icon">🎬</span>
                        Médias
                    </a>
                    <a href="?category=images" class="category-item <?= isset($_GET['category']) && $_GET['category'] === 'images' ? 'active' : '' ?>">
                        <span class="category-icon">🖼️</span>
                        Images
                    </a>
                    <a href="?category=recent" class="category-item <?= isset($_GET['category']) && $_GET['category'] === 'recent' ? 'active' : '' ?>">
                        <span class="category-icon">🕒</span>
                        Récents
                    </a>
                </div>

                <div class="search-container">
                    <input type="text" class="search-input" placeholder="Rechercher des fichiers...">
                </div>

                <div class="files-grid">
                    <?php
                    // Filtrer les fichiers selon la catégorie
                    $category = isset($_GET['category']) ? $_GET['category'] : 'all';
                    $filtered_items = array_filter($items, function($item) use ($category, $config) {
                        if ($item['is_dir']) return false;
                        
                        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                        
                        switch ($category) {
                            case 'documents':
                                return in_array($ext, ['pdf', 'txt']);
                            case 'media':
                                return in_array($ext, ['mp3', 'mp4']);
                            case 'images':
                                return in_array($ext, ['jpg', 'jpeg', 'png']);
                            case 'recent':
                                $file_path = $config['folders']['shared'] . '/' . $item['path'];
                                return (time() - filemtime($file_path)) < (7 * 24 * 60 * 60); // 7 jours
                            default:
                                return true;
                        }
                    });
                    
                    foreach ($filtered_items as $item) : 
                        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                        $is_image = in_array($ext, ['jpg', 'jpeg', 'png']);
                        $is_media = in_array($ext, ['mp3', 'mp4']);
                        $is_pdf = $ext === 'pdf';
                    ?>
                        <div class="file-card">
                            <div class="file-preview">
                                <?php if ($is_image) : ?>
                                    <img src="?preview=1&file=<?= urlencode($item['path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
                                <?php else : ?>
                                    <span class="file-type-icon <?= $is_media ? 'media' : ($is_pdf ? 'pdf' : 'document') ?>"></span>
                                <?php endif; ?>
                            </div>
                            <div class="file-info">
                                <div class="file-name"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="file-actions">
                                    <?php if ($is_media) : ?>
                                        <button onclick="openMediaPreview('?stream&download=<?= urlencode($item['path']) ?>')" class="btn btn-icon" title="Lire">▶️</button>
                                    <?php endif; ?>
                                    <?php if ($is_pdf) : ?>
                                        <button onclick="previewPDF('<?= htmlspecialchars($item['path']) ?>')" class="btn btn-icon" title="Voir">👁️</button>
                                    <?php endif; ?>
                                    <button onclick="window.location.href='?download=<?= urlencode($item['path']) ?>'" class="btn btn-icon" title="Télécharger">⬇️</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="src/js/app.js"></script>
</body>
</html>