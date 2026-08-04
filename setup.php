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
require("assets/php/ip.php");

session_start();

$ip_ok = in_array($_SERVER['REMOTE_ADDR'], $allowed_ips);

if(!$ip_ok) {
   echo 'IP not allowed. Edit ip.php to set your IP address.';
   exit;
}

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

// One-time-use CAPTCHA check: valid for 5 minutes, consumed whether it
// passes or fails so a captured code can't be replayed.
function captcha_check(): bool {
    $submitted = trim($_POST['captcha'] ?? '');
    $expected = $_SESSION['setup_captcha'] ?? null;
    $issuedAt = $_SESSION['setup_captcha_time'] ?? 0;

    unset($_SESSION['setup_captcha'], $_SESSION['setup_captcha_time']);

    if ($expected === null || $submitted === '') {
        return false;
    }
    if (time() - $issuedAt > 300) {
        return false; // expired
    }
    return hash_equals($expected, $submitted);
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
    } elseif (!captcha_check()) {
        $message = 'Incorrect or expired code. Please try again.';
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
            $bytes = file_put_contents($authFile, json_encode(['hash' => $hash]), LOCK_EX);
            if ($bytes === false) {
                die("Could not write config to " . htmlspecialchars($authFile) . ". Check if PHP has write permissions.");
            }

            $message = 'Password saved.';
            $existing = ['hash' => $hash];
            header("Location:index.php");
            exit;
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

        <label class="auth-label">Enter the code shown below</label>
        <img src="captcha.php?t=<?= time() ?>" alt="verification code" id="captcha-img"
             style="display:block;margin-bottom:10px;border-radius:6px;cursor:pointer;"
             title="Click to get a new code"
             onclick="this.src='captcha.php?t=' + Date.now()">
        <input type="text" name="captcha" class="auth-input" autocomplete="off"
               inputmode="numeric" pattern="[0-9]*" maxlength="4" placeholder="4-digit code">

        <button class="auth-submit">Save</button>
      </form>
    </div>
  </div>
</body>
</html>
