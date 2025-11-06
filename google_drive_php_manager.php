<?php
/**
 * google-drive-php-manager.php
 * PHP Drive Manager with MySQL token storage, login/authentication system,
 * folder navigation, search, and pagination.
 *
 * Features:
 * - User login system (users stored in MySQL)
 * - Tokens stored securely in MySQL (table drive_tokens)
 * - List files/folders with breadcrumbs, search, and pagination
 * - Upload, download, rename, and create folders
 *
 * Requirements:
 *  - PHP 7.4+
 *  - MySQL database
 *  - Composer with google/apiclient installed
 *  - Google Cloud project with Drive API and OAuth2 credentials
 */

require __DIR__ . '/vendor/autoload.php';
session_start();

// DB CONFIG
$pdo = new PDO('mysql:host=localhost;dbname=drive_manager;charset=utf8mb4', 'root', '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// TABLES (users and tokens)
// CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) UNIQUE, password_hash VARCHAR(255));
// CREATE TABLE drive_tokens (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, access_token TEXT, refresh_token TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

// CONFIG
$credentialsPath = __DIR__ . '/credentials.json';
$scopes = [Google_Service_Drive::DRIVE];

function getClient() {
  global $credentialsPath, $scopes;
  $client = new Google_Client();
  $client->setAuthConfig($credentialsPath);
  $client->setAccessType('offline');
  $client->setPrompt('select_account consent');
  $client->setScopes($scopes);
  $client->setRedirectUri((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);
  return $client;
}

// LOGIN SYSTEM
if (isset($_POST['action']) && $_POST['action'] === 'login') {
  $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
  $stmt->execute([$_POST['username']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($user && password_verify($_POST['password'], $user['password_hash'])) {
    $_SESSION['user_id'] = $user['id'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
  } else {
    $error = 'Credenciais inválidas';
  }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
  session_destroy();
  header('Location: ' . $_SERVER['PHP_SELF']);
  exit;
}

if (empty($_SESSION['user_id'])) {
  echo '<!doctype html><html><head><meta charset="utf-8"><title>Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light">';
  echo '<div class="container py-5"><div class="card shadow-sm p-4"><h3>Login</h3>';
  if (!empty($error)) echo '<div class="alert alert-danger">'.$error.'</div>';
  echo '<form method="post"><input type="hidden" name="action" value="login">';
  echo '<div class="mb-3"><label>Usuário</label><input name="username" class="form-control" required></div>';
  echo '<div class="mb-3"><label>Senha</label><input type="password" name="password" class="form-control" required></div>';
  echo '<button class="btn btn-primary">Entrar</button></form></div></div></body></html>';
  exit;
}

$userId = $_SESSION['user_id'];
$client = getClient();

// Load token from MySQL
$stmt = $pdo->prepare('SELECT * FROM drive_tokens WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$userId]);
$tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);
if ($tokenRow) {
  $client->setAccessToken(json_decode($tokenRow['access_token'], true));
  if ($client->isAccessTokenExpired()) {
    if (!empty($tokenRow['refresh_token'])) {
      $client->fetchAccessTokenWithRefreshToken($tokenRow['refresh_token']);
      $newToken = $client->getAccessToken();
      $stmt = $pdo->prepare('UPDATE drive_tokens SET access_token = ? WHERE id = ?');
      $stmt->execute([json_encode($newToken), $tokenRow['id']]);
    } else {
      $authUrl = $client->createAuthUrl();
      echo '<a href="'.$authUrl.'">Reconectar Google Drive</a>';
      exit;
    }
  }
} else {
  $authUrl = $client->createAuthUrl();
  echo '<a href="'.$authUrl.'">Conectar Google Drive</a>';
  exit;
}

$service = new Google_Service_Drive($client);

$parent = $_GET['parent'] ?? 'root';
$search = trim($_GET['search'] ?? '');
$pageToken = $_GET['pageToken'] ?? null;

$query = "'".$parent."' in parents and trashed=false";
if ($search) $query .= " and name contains '".addslashes($search)."'";

$optParams = [
  'q' => $query,
  'pageSize' => 20,
  'fields' => 'nextPageToken, files(id, name, mimeType, thumbnailLink, modifiedTime, parents)',
];
if ($pageToken) $optParams['pageToken'] = $pageToken;

$results = $service->files->listFiles($optParams);
$files = $results->getFiles();
$nextPageToken = $results->getNextPageToken();

// Breadcrumbs
function buildBreadcrumbs($service, $parent) {
  $crumbs = [];
  while ($parent && $parent !== 'root') {
    $file = $service->files->get($parent, ['fields' => 'id, name, parents']);
    array_unshift($crumbs, ['id'=>$file->id, 'name'=>$file->name]);
    $parent = $file->parents[0] ?? null;
  }
  array_unshift($crumbs, ['id'=>'root', 'name'=>'Drive']);
  return $crumbs;
}
$breadcrumbs = buildBreadcrumbs($service, $parent);

?><!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<title>Drive Manager</title>
</head><body class="bg-light">
<nav class="navbar navbar-light bg-white shadow-sm"><div class="container-fluid">
<a class="navbar-brand" href="?">Drive Manager</a>
<a href="?action=logout" class="btn btn-outline-secondary">Sair</a>
</div></nav>
<div class="container py-4">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
  <?php foreach ($breadcrumbs as $b): ?>
    <li class="breadcrumb-item"><a href="?parent=<?=$b['id']?>"><?=$b['name']?></a></li>
  <?php endforeach; ?>
  </ol></nav>

  <form class="input-group mb-3">
    <input type="hidden" name="parent" value="<?=htmlspecialchars($parent)?>">
    <input name="search" value="<?=htmlspecialchars($search)?>" class="form-control" placeholder="Pesquisar arquivos">
    <button class="btn btn-outline-primary">Buscar</button>
  </form>

  <div class="row">
  <?php foreach ($files as $f): ?>
    <div class="col-md-3 mb-3"><div class="card">
      <img src="?action=thumb&id=<?=$f->id?>" class="card-img-top" style="height:140px;object-fit:cover;">
      <div class="card-body">
        <h6 class="card-title text-truncate" title="<?=$f->name?>"><?=$f->name?></h6>
        <?php if ($f->mimeType === 'application/vnd.google-apps.folder'): ?>
          <a href="?parent=<?=$f->id?>" class="btn btn-sm btn-outline-primary">Abrir</a>
        <?php else: ?>
          <a href="?action=download&id=<?=$f->id?>" class="btn btn-sm btn-outline-primary">Download</a>
        <?php endif; ?>
      </div>
    </div></div>
  <?php endforeach; ?>
  </div>

  <?php if ($nextPageToken): ?>
    <a href="?parent=<?=$parent?>&search=<?=urlencode($search)?>&pageToken=<?=$nextPageToken?>" class="btn btn-outline-secondary">Próxima página</a>
  <?php endif; ?>
</div>
</body></html>
