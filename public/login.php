<?php
/**
 * Login-Seite
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/RateLimiter.php';

$auth = new Auth();
$rateLimiter = new RateLimiter();

// Wenn bereits eingeloggt, zur Hauptseite weiterleiten
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$isBlocked = $rateLimiter->isBlocked();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Rate Limiting prüfen
    if ($isBlocked) {
        $remainingTime = $rateLimiter->getRemainingLockoutTime();
        $minutes = ceil($remainingTime / 60);
        $error = "Zu viele fehlgeschlagene Versuche. Bitte warten Sie $minutes Minute(n).";
    } elseif (empty($username) || empty($password)) {
        $error = 'Bitte Benutzername und Passwort eingeben';
    } elseif ($auth->login($username, $password)) {
        $rateLimiter->recordSuccessfulLogin($username);
        header('Location: index.php');
        exit;
    } else {
        $rateLimiter->recordFailedAttempt($username);
        $remaining = $rateLimiter->getRemainingAttempts();

        if ($remaining > 0) {
            $error = "Ungültiger Benutzername oder Passwort. Noch $remaining Versuch(e).";
        } else {
            $error = 'Zu viele fehlgeschlagene Versuche. Bitte warten Sie 15 Minuten.';
        }
    }
}

// Periodische Bereinigung (bei 1% der Requests)
if (rand(1, 100) === 1) {
    $rateLimiter->cleanup();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - YAML/MD Editor</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
    <link rel="manifest" href="/favicon/site.webmanifest">
    <link rel="shortcut icon" href="/favicon/favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
        }

        h1 {
            text-align: center;
            color: #1a1a2e;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #4a90d9;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background: #4a90d9;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        button:hover {
            background: #357abd;
        }

        .error {
            background: #fee;
            color: #c00;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .info {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f0f7ff;
            border-radius: 5px;
            font-size: 0.85rem;
            color: #666;
        }

        .info strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>YAML/MD Editor</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Benutzername</label>
                <input type="text" id="username" name="username" required autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" <?= $isBlocked ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>Anmelden</button>
        </form>
    </div>
</body>
</html>
