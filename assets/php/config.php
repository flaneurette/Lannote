<?php

// be sure the ../secure folder exists below /var/www/ !
require("constants.php");

if (!is_dir(SECURE_PATH)) {
    // Attempt to create the directory (recursively if needed)
    $dir = mkdir(SECURE_PATH, 0775, true);

    if (!$dir) {
        echo "The secure path does not exist. First, create a folder called '".htmlspecialchars(SECURE_PATH)."' in the parent directory of your web root (below /www/), then proceed with the installation.";
        exit;
    }

    // Set permissions (chmod instead of chown)
    if (!chown(SECURE_PATH, 'www-data:www-data')) {
        echo "Failed to set permissions on the secure directory.";
    }
}


session_start();

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit;
}

require("ip.php");

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

    $authFile = SECURE_PATH . 'notes/data/auth.json';
    $auth = file_exists($authFile) ? json_decode(file_get_contents($authFile), true) : null;

    if (!$auth) {
    
    ?>
        <!DOCTYPE html>
	<html lang="en">
	<head>
	<meta charset="UTF-8">
	<title>L A N N O T E - Setup</title>
	<link rel="stylesheet" href="style.css">
	</head>
	<body>
        <div id="setup"><div class="auth-panel">No password set yet. Visit <a href="setup.php">setup.php</a> first</div></div>
    	</body>
	</html>
<?php
     exit;
    }

	if (isset($_POST['password'])) {
	
		if (!csrf_check()) {
		    http_response_code(403);
		    exit('Invalid or expired form submission. Please reload and try again.');
		}

		$maxAttempts = 3;
		$lockoutSeconds = 60;

		$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
		$_SESSION['login_locked_until'] = $_SESSION['login_locked_until'] ?? 0;

		if (time() < $_SESSION['login_locked_until']) {
		    $wait = $_SESSION['login_locked_until'] - time();
		    http_response_code(429);
		    exit("Too many attempts. Try again in {$wait}s.");
		}

		if (password_verify($_POST['password'], $auth['hash'])) {
		    $_SESSION['authed'] = true;
		    unset($_SESSION['csrf']); // rotate token after successful login
		    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
		    session_regenerate_id(true); // prevent session fixation
		} else {
		    $_SESSION['login_attempts']++;
		    if ($_SESSION['login_attempts'] >= $maxAttempts) {
		        $_SESSION['login_locked_until'] = time() + $lockoutSeconds;
		        $_SESSION['login_attempts'] = 0;
		    }
		    usleep(500000); // 0.5s
		}
    }

    if (empty($_SESSION['authed'])) {
        ?>
        <!DOCTYPE html>
	<html lang="en">
	<head>
	<meta charset="UTF-8">
	<title>L A N N O T E - Login</title>
	<link rel="stylesheet" href="assets/css/style.css">
	</head>
	<body>
	<div class="auth-wrap">
    	<div class="auth-panel">
        <form method="post">
          <h1 class="brand">L·ANNOTE</h1>
          <p>Enter password to continue:</p>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="password" name="password" style="width:100%;padding:8px;">
          <button style="margin-top:8px;" class="auth-submit">Enter</button>
        </form>
        </div>
        </div>
	</body>
	</html>
        <?php
        exit;
    }
?>
