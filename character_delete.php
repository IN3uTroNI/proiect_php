<?php
require_once __DIR__ . '/common_scripts/init.php';
require_login();

$user = $_SESSION['user'];
$characterId = (int)($_GET['id'] ?? 0);
if ($characterId <= 0) {
    header('Location: /dashboard.php');
    exit;
}

$character = $controller->getCharacterBase($characterId, (int)$user['id']);
if (!$character) {
    header('Location: /dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!verify_csrf($token)) {
        $error = t('char_create_error_csrf');
    } else {
        try {
            $controller->deleteCharacter($characterId);
            header('Location: /dashboard.php');
            exit;
        } catch (Exception $e) {
            $error = t('char_delete_error') . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="<?php echo h(get_language()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo t('char_delete_title'); ?> - <?php echo t('site_title'); ?></title>
    <link rel="stylesheet" href="/resources/css/style.css">
</head>
<body class="<?php echo h(get_theme()); ?>">
<?php include __DIR__ . '/common_scripts/selector.php'; ?>
<main class="container">
    <section class="card">
        <h1><?php echo t('char_delete_title'); ?></h1>
        <?php if (!empty($error)): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
        <p><?php echo t('char_delete_confirm'); ?> <strong><?php echo h($character['name']); ?></strong>?</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
            <div class="actions">
                <button type="submit" class="button danger"><?php echo t('char_delete_confirm_button'); ?></button>
                <a href="/character_view.php?id=<?php echo $characterId; ?>" class="button secondary"><?php echo t('char_delete_cancel'); ?></a>
            </div>
        </form>
    </section>
</main>
</body>
</html>
