<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';


// Gestion de la suppression de fichier
$message = '';
if (isset($_GET['delete']) && isset($_GET['matiere'])) {
  $matiere = basename($_GET['matiere']);
  $file = basename($_GET['delete']);
  $targetFile = __DIR__ . '/../cours/' . $matiere . '/' . $file;
  if (is_file($targetFile)) {
    if (unlink($targetFile)) {
      $message = "Fichier supprimé.";
    } else {
      $message = "Erreur lors de la suppression.";
    }
  } else {
    $message = "Fichier introuvable.";
  }
}

// Gestion du renommage de fichier
if (isset($_POST['rename_file']) && isset($_POST['matiere']) && isset($_POST['old_name']) && isset($_POST['new_name'])) {
  $matiere = basename($_POST['matiere']);
  $old = basename($_POST['old_name']);
  $new = basename($_POST['new_name']);
  $oldPath = __DIR__ . '/../cours/' . $matiere . '/' . $old;
  $newPath = __DIR__ . '/../cours/' . $matiere . '/' . $new;
  if (is_file($oldPath)) {
    if (rename($oldPath, $newPath)) {
      $message = "Fichier renommé.";
    } else {
      $message = "Erreur lors du renommage.";
    }
  } else {
    $message = "Fichier introuvable.";
  }
}

// Gestion de l'ajout de fichier
$allowedExtensions = ['pdf','mp4','avi','mov','mp3','wav','jpg','jpeg','png','gif','pptx','ppt','docx','doc','txt','zip','rar'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier'])) {
  $matiere = trim($_POST['matiere'] ?? '');
  if ($matiere && isset($_FILES['fichier']['tmp_name']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
    $filename = basename($_FILES['fichier']['name']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
      $message = "Type de fichier non autorisé. Extensions acceptées : " . implode(', ', $allowedExtensions);
    } elseif ($_FILES['fichier']['size'] > MAX_FILE_SIZE) {
      $message = "Fichier trop volumineux (max " . round(MAX_FILE_SIZE / 1048576) . " Mo).";
    } else {
      $targetDir = __DIR__ . '/../cours/' . $matiere;
      if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
      $targetFile = $targetDir . '/' . $filename;
      if (move_uploaded_file($_FILES['fichier']['tmp_name'], $targetFile)) {
        $message = "Fichier ajouté avec succès.";
      } else {
        $message = "Erreur lors de l'upload.";
      }
    }
  } else {
    $message = "Veuillez choisir une matière et un fichier valide.";
  }
}

// Liste des matières existantes (si la colonne existe)
$matieres = [];
try {
  $matieres = dbAll("SELECT DISTINCT matiere FROM archives ORDER BY matiere");
} catch (Exception $e) {
  $matieres = [];
}
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Gestion des cours | Admin</title>
  <link rel="stylesheet" href="/reussiteplus/assets/css/app.css">
  <style>
    .admin-cours-form { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #0001; padding: 24px; max-width: 400px; margin: 32px auto; }
    .admin-cours-form label { display: block; margin-bottom: 8px; }
    .admin-cours-form input, .admin-cours-form select { width: 100%; margin-bottom: 16px; }
    .admin-cours-form button { background: #007A5E; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; cursor: pointer; }
    .admin-cours-form .msg { margin-bottom: 12px; color: #007A5E; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header_app.php'; ?>
<div class="container">
  <h1>Ajouter un contenu de cours</h1>
  <form class="admin-cours-form" method="post" enctype="multipart/form-data">
    <?php if ($message): ?><div class="msg"><?= e($message) ?></div><?php endif; ?>
    <label for="matiere">Matière</label>
    <?php if (count($matieres) > 0): ?>
      <select name="matiere" id="matiere" required>
        <option value="">-- Choisir --</option>
        <?php foreach ($matieres as $m): ?>
          <option value="<?= e($m['matiere']) ?>"><?= e($m['matiere']) ?></option>
        <?php endforeach; ?>
        <option value="__autre__">Autre (nouvelle matière)</option>
      </select>
      <input type="text" name="matiere_autre" id="matiere_autre" placeholder="Nouvelle matière" style="display:none;margin-top:8px;">
      <script>
      document.getElementById('matiere').addEventListener('change', function() {
        document.getElementById('matiere_autre').style.display = this.value === '__autre__' ? 'block' : 'none';
      });
      </script>
    <?php else: ?>
      <input type="text" name="matiere" id="matiere" placeholder="Nom de la matière" required>
    <?php endif; ?>
    <label for="fichier">Fichier (PDF, vidéo, audio, image, PPT, etc.)</label>
    <input type="file" name="fichier" id="fichier" required>
    <button type="submit">Ajouter</button>
  </form>

  <hr style="margin:40px 0 24px;">
  <h2>Fichiers de cours existants</h2>
  <form method="get" style="margin-bottom:18px;">
    <input type="text" name="q" placeholder="Rechercher un fichier ou une matière..." value="<?= e($_GET['q'] ?? '') ?>" style="padding:7px 14px;border-radius:6px;border:1px solid #ddd;width:260px;">
    <button type="submit" style="padding:7px 18px;border-radius:6px;border:none;background:#007A5E;color:#fff;font-weight:600;margin-left:8px;">Rechercher</button>
  </form>
  <div style="display:flex;flex-wrap:wrap;gap:32px;">
    <?php
    $coursDir = __DIR__ . '/../cours/';
    $query = strtolower(trim($_GET['q'] ?? ''));
    foreach (glob($coursDir . '*', GLOB_ONLYDIR) as $matiereDir) {
      $matiere = basename($matiereDir);
      $files = glob($matiereDir . '/*');
      if (!$files) continue;
      // Tri alphabétique
      usort($files, function($a, $b) { return strcasecmp(basename($a), basename($b)); });
      // Filtrage recherche
      if ($query && stripos($matiere, $query) === false && !array_filter($files, function($f) use ($query) { return stripos(basename($f), $query) !== false; })) continue;
      echo '<div style="background:#fff;border-radius:12px;box-shadow:0 2px 8px #0001;padding:18px 18px 10px;width:340px;">';
      echo '<h3 style="font-size:17px;margin-bottom:10px;">' . e($matiere) . '</h3>';
      echo '<ul style="list-style:none;padding:0;">';
      foreach ($files as $f) {
        $file = basename($f);
        if ($query && stripos($file, $query) === false && stripos($matiere, $query) === false) continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $icon = '';
        switch ($ext) {
          case 'pdf': $icon = '📄'; break;
          case 'mp4': case 'avi': case 'mov': $icon = '🎬'; break;
          case 'mp3': case 'wav': $icon = '🎵'; break;
          case 'jpg': case 'jpeg': case 'png': case 'gif': $icon = '🖼️'; break;
          case 'ppt': case 'pptx': $icon = '📊'; break;
          case 'doc': case 'docx': $icon = '📝'; break;
          case 'txt': $icon = '📄'; break;
          case 'zip': case 'rar': $icon = '🗜️'; break;
          case 'url': case 'link': $icon = '🔗'; break;
          default: $icon = '📁'; break;
        }
        echo '<li style="display:flex;align-items:center;gap:8px;margin-bottom:7px;">';
        if (in_array($ext, ['jpg','jpeg','png','gif'])) {
          echo '<img src="/reussiteplus/cours/' . urlencode($matiere) . '/' . urlencode($file) . '" style="width:28px;height:28px;object-fit:cover;border-radius:5px;border:1px solid #eee;">';
        } else {
          echo '<span style="font-size:16px;">' . $icon . '</span>';
        }
        echo '<a href="/reussiteplus/cours/' . urlencode($matiere) . '/' . urlencode($file) . '" target="_blank" style="flex:1;">' . e($file) . '</a>';
        // Formulaire de renommage inline
        echo '<form method="post" style="display:inline;margin:0 4px;">';
        echo '<input type="hidden" name="matiere" value="' . e($matiere) . '"><input type="hidden" name="old_name" value="' . e($file) . '"><input type="text" name="new_name" value="' . e($file) . '" style="width:90px;font-size:12px;padding:2px 6px;border-radius:4px;border:1px solid #ddd;">';
        echo '<button type="submit" name="rename_file" style="background:#007A5E;color:#fff;border:none;padding:2px 8px;border-radius:4px;font-size:12px;margin-left:2px;">Renommer</button>';
        echo '</form>';
        echo '<a href="?delete=' . urlencode($file) . '&matiere=' . urlencode($matiere) . '" onclick="return confirm(\'Supprimer ce fichier ?\')" style="color:#C9342A;font-size:13px;font-weight:700;margin-left:6px;">Supprimer</a>';
        echo '</li>';
      }
      echo '</ul></div>';
    }
    ?>
  </div>
</div>
</body>
</html>
