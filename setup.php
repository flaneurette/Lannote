<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>L A N N O T E - Setup</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php
require("constants.php");
session_start();

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

$authFile = SECURE_PATH . 'notes/data/auth.json';
$authDir = SECURE_PATH . 'notes/data';
$message = '';
$existing = file_exists($authFile) ? json_decode(file_get_contents($authFile), true) : null;

// Ensure directory exists
mkdir(SECURE_PATH, 0775, true);
mkdir($authDir, 0775, true);

if (!is_dir($authDir)) {
    die("Error: Cannot create directory " . htmlspecialchars($authDir) . ". Check permissions.");
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $message = 'Invalid or expired form submission. Please reload and try again.';
    } else {
        $newPassword = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';
        $oldPassword = $_POST['old_password'] ?? '';

        if ($existing && !password_verify($oldPassword, $existing['hash'])) {
            $message = 'Current password incorrect.';
        } elseif (strlen($newPassword) < 6) {
            $message = 'Password must be at least 6 characters.';
        } elseif ($newPassword !== $confirm) {
            $message = 'Passwords do not match.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            // $send = file_put_contents($authFile, json_encode(['hash' => $hash])) or die('Could not write config... check if PHP is allowed to write below /www/ folder.');
            // Atomic write with error handling
	    $bytes = file_put_contents($authFile, json_encode(['hash' => $hash]), LOCK_EX);
		if ($bytes === false) {
		    die("Could not write config to " . htmlspecialchars($authFile) . ". Check if PHP has write permissions.");
		}

            $message = 'Password saved.';
            $existing = ['hash' => $hash];
            header("Location:index.php");
        }
    }
}
?>
  <div class="auth-wrap">
    <div class="auth-panel">
      <h2 class="auth-title"><?= $existing ? 'Change password' : 'Set a password' ?></h2>
      <?php if ($message): ?>
        <p class="auth-message"><?= htmlspecialchars($message) ?></p>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <?php if ($existing): ?>
          <label class="auth-label">Current password</label>
          <input type="password" name="old_password" class="auth-input">
        <?php endif; ?>
        <label class="auth-label">New password</label>
        <input type="password" name="password" class="auth-input">
        <label class="auth-label">Confirm</label>
        <input type="password" name="confirm" class="auth-input">
        <button class="auth-submit">Save</button>
      </form>
    </div>
  </div>
</body>
</html>
