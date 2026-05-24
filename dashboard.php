<?php
require_once __DIR__ . '/common_scripts/init.php';
require_login();

// Handle language change
if (isset($_GET['lang'])) {
    set_language($_GET['lang']);
}

// Handle theme change
if (isset($_GET['theme'])) {
    set_theme($_GET['theme']);
}

$user = $_SESSION['user'];
$chars = $controller->getCharactersByUser((int)$user['id']);
?>
<!doctype html>
<html lang="<?php echo h(get_language()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo t('dashboard_title'); ?> - <?php echo t('site_title'); ?></title>
    <link rel="stylesheet" href="/resources/css/style.css">
</head>
<body class="<?php echo h(get_theme()); ?>">
<?php include __DIR__ . '/common_scripts/selector.php'; ?>
<header class="topbar" style="justify-content:space-between; ">
    <div class="container" style="justify-content:space-between;">
        <h2 style="background:#7c3aed;padding:0.3rem;border-radius:4px;align-self:center;margin: 1.5em;"><?php echo t('site_title'); ?></h2>
        <nav>
            <span style="text-decoration:underline;"><?php echo t('hello'); ?>, <?php echo h($user['account_name'] ?? $user['email']); ?></span>
            <a href="/items.php" class="button secondary"><?php echo t('nav_items'); ?></a>
            <a href="/character_create.php" class="button secondary"><?php echo t('nav_new_character'); ?></a>
            <a href="/logout.php" class="button secondary"><?php echo t('nav_logout'); ?></a>
        </nav>
    </div>
</header>
<main class="container">
    <section class="card">
        <h3><?php echo t('dashboard_characters'); ?></h3>
        <?php if (empty($chars)): ?>
            <p class="muted"><?php echo t('dashboard_no_chars'); ?></p>
        <?php else: ?>
            <div class="character-list">
                <?php foreach ($chars as $c): ?>
                    <article class="character-card">
                        <div>
                            <h3><?php echo h($c['name']); ?></h3>
                            <div class="summary-row">
                                <span class="summary-pill"><?php echo t('dashboard_level'); ?> <?php echo h($c['total_level']); ?></span>
                                <span class="summary-pill"><?php echo t('dashboard_hp'); ?> <?php echo h($c['hp_current']); ?>/<?php echo h($c['hp_max']); ?></span>
                            </div>
                        </div>
                        <div class="card-actions">
                            <a class="button" href="/character_view.php?id=<?php echo h($c['id']); ?>"><?php echo t('view_button'); ?></a>
                            <a class="button secondary" href="/character_edit.php?id=<?php echo h($c['id']); ?>"><?php echo t('edit_button'); ?></a>
                            <a class="button danger" href="/character_delete.php?id=<?php echo h($c['id']); ?>"><?php echo t('delete_button'); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
