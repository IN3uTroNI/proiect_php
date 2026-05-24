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
        $error = t('register_error_csrf');
    } else {
        $email = trim($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if ($email === '' || $password === '' || $name === '' || $password !== $password2) {
            $error = t('register_error_fields');
        } else {
            try {
                $id = $controller->registerUser($email, $password, $name);
                $_SESSION['user'] = ['id' => $id, 'email' => $email, 'account_name' => $name];
                header('Location: /dashboard.php');
                exit;
            } catch (Exception $e) {
                $error = t('register_error_failed') . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="<?php echo h(get_language()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo t('register_title'); ?> - <?php echo t('site_title'); ?></title>
    <link rel="stylesheet" href="/resources/css/style.css">
</head>
<body class="<?php echo h(get_theme()); ?> center-page">
<?php include __DIR__ . '/common_scripts/selector.php'; ?>
<main class="card">
    <h1><?php echo t('register_title'); ?></h1>
    <?php if (!empty($error)): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
        <label><?php echo t('register_name'); ?><input type="text" name="name" required></label>
        <label><?php echo t('register_email'); ?><input type="email" name="email" required></label>
        <label><?php echo t('register_password'); ?><input type="password" name="password" required></label>
        <label><?php echo t('register_confirm'); ?><input type="password" name="password2" required></label>
        <div class="actions">
            <button type="submit"><?php echo t('register_button'); ?></button>
            <a class="muted" href="/login.php"><?php echo t('register_back'); ?></a>
        </div>
    </form>
</main>
</body>
</html>
