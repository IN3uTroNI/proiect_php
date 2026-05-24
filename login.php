<?php
require_once __DIR__ . '/common_scripts/init.php';

// Handle language change
if (isset($_GET['lang'])) {
    set_language($_GET['lang']);
}

// Handle theme change
if (isset($_GET['theme'])) {
    set_theme($_GET['theme']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!verify_csrf($token)) {
        $error = t('login_csrf_error');
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email === '' || $password === '') {
            $error = t('login_invalid');
        } else {
            $user = $controller->authenticateUser($email, $password);
            if ($user) {
                $_SESSION['user'] = $user;
                header('Location: /dashboard.php');
                exit;
            }
            $error = t('login_invalid');
        }
    }
}
?>
<!doctype html>
<html lang="<?php echo h(get_language()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo t('login_title'); ?> - <?php echo t('site_title'); ?></title>
    <link rel="stylesheet" href="/resources/css/style.css">
</head>
<body class="<?php echo h(get_theme()); ?> center-page">
<?php include __DIR__ . '/common_scripts/selector.php'; ?>
<main class="card">
    <h1><?php echo t('login_title'); ?></h1>
    <?php if (!empty($error)): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
        <label><?php echo t('login_email'); ?><input type="email" name="email" required></label>
        <label><?php echo t('login_password'); ?><input type="password" name="password" required></label>
        <div class="actions">
            <button type="submit"><?php echo t('login_button'); ?></button>
            <a class="muted" href="/register.php"><?php echo t('login_no_account'); ?></a>
        </div>
    </form>
</main>
</body>
</html>
