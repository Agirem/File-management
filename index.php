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
        <title>Connexion - AgiremHub</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
    <body class="login-page">
        <div class="login-wrapper">
            <div class="login-left">
                <div class="login-header">
                    <div class="brand">
                        <h1>AgiremHub</h1>
                    </div>
                    <p class="welcome-text">Bienvenue sur votre espace de partage de fichiers</p>
                </div>
                <div class="login-illustration">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
            </div>
            <div class="login-right">
                <div class="login-box">
                    <h2>Connexion</h2>
                    <?php if (isset($error)) : ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" class="login-form">
                        <div class="form-group">
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" name="username" placeholder="Nom d'utilisateur" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" placeholder="Mot de passe" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-login">
                            <span>Se connecter</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
            </form>
                </div>
            </div>
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
if ($role === 'admin' && isset($_FILES['files']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'error' => ''];
    
    try {
        if (!isset($_FILES['files']['name'][0])) {
            throw new Exception('Aucun fichier n\'a été envoyé');
        }

        $uploadedFiles = [];
        $fileCount = count($_FILES['files']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $_FILES['files']['name'][$i];
            $tmpName = $_FILES['files']['tmp_name'][$i];
            $error = $_FILES['files']['error'][$i];
            
            if ($error !== UPLOAD_ERR_OK) {
                throw new Exception('Erreur lors de l\'upload du fichier ' . $fileName);
            }

            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $config['allowed_extensions'])) {
                throw new Exception('Extension de fichier non autorisée: ' . $ext);
            }

            // Détermine le dossier cible en fonction de la vue actuelle
            $target_folder = isset($_GET['view']) && $_GET['view'] === 'shared' 
                ? $config['folders']['shared'] 
                : $config['folders']['private'];
                
            $target = $target_folder . '/' . ($current_path ? $current_path . '/' : '') . basename($fileName);
            
            // Crée le dossier parent si nécessaire
            $parent_dir = dirname($target);
            if (!file_exists($parent_dir)) {
                mkdir($parent_dir, 0755, true);
            }
            
            if (!move_uploaded_file($tmpName, $target)) {
                throw new Exception('Impossible de déplacer le fichier ' . $fileName);
            }

            $uploadedFiles[] = $fileName;
        }

        $response['success'] = true;
        $response['files'] = $uploadedFiles;
        
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }
    
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

// Gestion des miniatures PDF
if (isset($_GET['pdf_thumbnail']) && isset($_GET['file'])) {
    $file = $base_folder . '/' . $_GET['file'];
    if (file_exists($file) && is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            // On laisse le frontend générer la miniature avec PDF.js
            header('Content-Type: application/pdf');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
    }
}

// Gestion des miniatures vidéo
if (isset($_GET['video_thumbnail']) && isset($_GET['file'])) {
    $file = $base_folder . '/' . $_GET['file'];
    if (file_exists($file) && is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'mp4') {
            // On utilise FFmpeg pour générer la miniature si disponible
            $thumbnail = sys_get_temp_dir() . '/' . md5($file) . '.jpg';
            $cmd = "ffmpeg -i " . escapeshellarg($file) . " -ss 00:00:01 -vframes 1 " . escapeshellarg($thumbnail) . " 2>&1";
            exec($cmd, $output, $return_var);
            
            if ($return_var === 0 && file_exists($thumbnail)) {
                header('Content-Type: image/jpeg');
                readfile($thumbnail);
                unlink($thumbnail);
                exit;
            }
        }
    }
}

// Gestion de la suppression de fichiers (admin uniquement)
if ($role === 'admin' && isset($_POST['delete']) && isset($_POST['file'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'error' => ''];
    
    try {
        $file_to_delete = isset($_GET['view']) && $_GET['view'] === 'shared' 
            ? $config['folders']['shared'] . '/' . $_POST['file']
            : $config['folders']['private'] . '/' . $_POST['file'];

        if (file_exists($file_to_delete) && is_file($file_to_delete)) {
            if (unlink($file_to_delete)) {
                $response['success'] = true;
            } else {
                throw new Exception('Impossible de supprimer le fichier');
            }
        } else {
            throw new Exception('Fichier non trouvé');
        }
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
</head>
<body>
    <!-- Menu Hamburger -->
    <button class="menu-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Overlay -->
    <div class="sidebar-overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">
            AgiremHub
        </div>
        <nav class="sidebar-nav">
            <?php if ($role === 'admin') : ?>
                <a href="?view=private" class="nav-item <?= !isset($_GET['view']) || $_GET['view'] !== 'shared' ? 'active' : '' ?>">
                    <i class="fas fa-folder"></i>
                    <span>Mes Fichiers</span>
                </a>
                <a href="?view=shared" class="nav-item <?= isset($_GET['view']) && $_GET['view'] === 'shared' ? 'active' : '' ?>">
                    <i class="fas fa-share-alt"></i>
                    <span>Fichiers Partagés</span>
                </a>
            <?php else : ?>
                <a href="?category=all" class="nav-item <?= !isset($_GET['category']) || $_GET['category'] === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i>
                    <span>Tous les fichiers</span>
                </a>
                <a href="?category=documents" class="nav-item <?= isset($_GET['category']) && $_GET['category'] === 'documents' ? 'active' : '' ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Documents</span>
                </a>
                <a href="?category=media" class="nav-item <?= isset($_GET['category']) && $_GET['category'] === 'media' ? 'active' : '' ?>">
                    <i class="fas fa-film"></i>
                    <span>Médias</span>
                </a>
                <a href="?category=images" class="nav-item <?= isset($_GET['category']) && $_GET['category'] === 'images' ? 'active' : '' ?>">
                    <i class="fas fa-images"></i>
                    <span>Images</span>
                </a>
                <a href="?category=recent" class="nav-item <?= isset($_GET['category']) && $_GET['category'] === 'recent' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i>
                    <span>Récents</span>
                </a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="search-bar">
                <input type="text" class="search-input" placeholder="Rechercher des fichiers...">
            </div>
            <div class="user-menu">
                <span class="username"><?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="?logout" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </div>

        <?php if ($role === 'admin') : ?>
            <!-- Floating Upload Button -->
            <div class="floating-upload-btn">
                <form id="upload-form" enctype="multipart/form-data">
                    <input type="file" id="file-input" name="files[]" multiple style="display: none;">
                </form>
                <button class="btn-float" onclick="document.getElementById('file-input').click()">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div class="upload-progress-container"></div>
        <?php endif; ?>

        <!-- Files Grid -->
        <div class="files-grid">
            <?php
            $filtered_items = $items;
            if ($role === 'guest') {
                // Filtrage pour l'interface guest
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
                            return (time() - filemtime($file_path)) < (7 * 24 * 60 * 60);
                        default:
                            return true;
                    }
                });
            }
            
            if (empty($filtered_items)) : ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>Aucun fichier disponible dans cette section</p>
                    <?php if ($role === 'admin') : ?>
                        <p>Glissez et déposez des fichiers pour commencer</p>
                    <?php endif; ?>
                </div>
            <?php else :
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
                            <?php elseif ($is_pdf) : ?>
                                <canvas class="pdf-thumbnail" data-pdf="?pdf_thumbnail=1&file=<?= urlencode($item['path']) ?>" data-page="1"></canvas>
                            <?php elseif ($is_media) : ?>
                                <div class="document-preview" style="background-color: #783DFF;">
                                    <div class="document-icon" style="color: white;">
                                        <i class="fas <?= strpos($item['name'], '.mp4') !== false ? 'fa-video' : 'fa-music' ?>"></i>
                                    </div>
                                    <div class="document-name" style="color: white;"><?= pathinfo($item['name'], PATHINFO_FILENAME) ?></div>
                                </div>
                            <?php else : ?>
                                <div class="document-preview">
                                    <div class="document-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="document-name"><?= pathinfo($item['name'], PATHINFO_FILENAME) ?></div>
                                </div>
                            <?php endif; ?>
        </div>
                        <div class="file-info">
                            <div class="file-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="file-actions">
                                <?php if ($is_media && strpos($item['name'], '.mp4') !== false) : ?>
                                    <button onclick="openMediaPreview('?stream&download=<?= urlencode($item['path']) ?>')" class="btn btn-primary" title="Lire">
                                        <i class="fas fa-play"></i>
                                    </button>
            <?php endif; ?>
                                <button onclick="window.location.href='?download=<?= urlencode($item['path']) ?>'" class="btn btn-secondary" title="Télécharger">
                                    <i class="fas fa-download"></i>
                                </button>
                                <?php if ($role === 'admin') : ?>
                                    <form method="post" class="share-form">
                                        <input type="hidden" name="file" value="<?= htmlspecialchars($item['path']) ?>">
                                        <?php if (!$item['is_shared']) : ?>
                                            <button type="submit" name="share" class="btn btn-primary" title="Partager">
                                                <i class="fas fa-share-alt"></i>
                                            </button>
                    <?php else : ?>
                                            <button type="submit" name="unshare" class="btn btn-secondary" title="Ne plus partager">
                                                <i class="fas fa-ban"></i>
                                            </button>
                        <?php endif; ?>
                                    </form>
                                    <button onclick="deleteFile('<?= htmlspecialchars($item['path']) ?>', '<?= htmlspecialchars($item['name']) ?>')" class="btn btn-danger" title="Supprimer">
    <i class="fas fa-trash"></i>
</button>
                    <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach;
            endif; ?>
        </div>
    </div>

    <script src="src/js/app.js"></script>
</body>
</html>