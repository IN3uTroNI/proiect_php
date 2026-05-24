<?php
require_once __DIR__ . '/common_scripts/init.php';
require_login();

if (isset($_GET['lang'])) { set_language($_GET['lang']); }
if (isset($_GET['theme'])) { set_theme($_GET['theme']); }

$search = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$itemTypes = $controller->getItemTypes();
$items = $controller->getItems();

$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!verify_csrf($token)) {
        $error = t('items_created_error') . t('char_create_error_csrf');
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $itemType = trim($_POST['item_type'] ?? '');
        $rarity = trim($_POST['rarity'] ?? '');
        if ($name === '' || $description === '' || $itemType === '' || $rarity === '') {
            $error = t('items_created_error') . t('char_create_error');
        } else {
            try {
                $controller->createItem($name, $description, $itemType, $rarity);
                $success = t('items_created_success');
                $itemTypes = $controller->getItemTypes();
                $items = $controller->getItems();
            } catch (Exception $e) {
                $error = t('items_created_error') . $e->getMessage();
            }
        }
    }
}

$filteredItems = array_filter($items, function ($item) use ($search, $typeFilter) {
    $matchesName = $search === '' || stripos($item['name'], $search) !== false;
    $matchesType = $typeFilter === '' || $item['item_type'] === $typeFilter;
    return $matchesName && $matchesType;
});
?>
<!doctype html>
<html lang="<?php echo h(get_language()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo t('items_title'); ?> - <?php echo t('site_title'); ?></title>
    <link rel="stylesheet" href="/resources/css/style.css">
</head>
<body class="<?php echo h(get_theme()); ?>">
<?php include __DIR__ . '/common_scripts/selector.php'; ?>
<header class="topbar">
    <div class="container">
        <h2 class="view-title"><?php echo t('items_title'); ?></h2>
        <nav>
            <a href="/dashboard.php" class="button secondary"><?php echo t('dashboard_title'); ?></a>
            <a href="/character_create.php" class="button secondary"><?php echo t('nav_new_character'); ?></a>
            <a href="/logout.php" class="button secondary"><?php echo t('nav_logout'); ?></a>
        </nav>
    </div>
</header>
<main class="container">
    <section class="card">
        <h3><?php echo t('items_list_title'); ?></h3>
        <?php if ($success): ?><div class="success-msg"><?php echo h($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
        <form method="get" class="item-controls">
            <label><?php echo t('items_search_label'); ?><input type="search" name="search" value="<?php echo h($search); ?>" placeholder="<?php echo t('items_search_placeholder'); ?>"></label>
            <label><?php echo t('items_filter_type'); ?><select name="type" onchange="this.form.submit()">
                <option value=""><?php echo t('items_all_types'); ?></option>
                <?php foreach ($itemTypes as $type): ?>
                    <option value="<?php echo h($type); ?>"<?php if ($type === $typeFilter) echo ' selected'; ?>><?php echo h($type); ?></option>
                <?php endforeach; ?>
            </select></label>
        </form>
        <div class="item-grid">
            <?php if (empty($filteredItems)): ?>
                <p class="muted"><?php echo t('items_no_records'); ?></p>
            <?php else: ?>
                <?php foreach ($filteredItems as $item): ?>
                    <article class="item-card">
                        <div>
                            <strong><?php echo h($item['name']); ?></strong>
                            <div class="meta"><?php echo h($item['item_type']); ?> • <?php echo h($item['rarity']); ?></div>
                            <?php if (!empty($item['description'])): ?>
                                <p class="muted-small"><?php echo h($item['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <h3><?php echo t('items_create_title'); ?></h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
            <label><?php echo t('items_name'); ?><input type="text" name="name" required></label>
            <label><?php echo t('items_description'); ?><textarea name="description" rows="3" required></textarea></label>
            <label><?php echo t('items_type'); ?><input type="text" name="item_type" required></label>
            <label><?php echo t('items_rarity'); ?><input type="text" name="rarity" required></label>
            <label><?php echo t('items_value'); ?><input type="number" name="value" required></label>
            <label><?php echo t('items_damage'); ?><input type="text" name="damage_dice"></label>
            <label><?php echo t('items_weight'); ?><input type="text" name="weight"></label>
            <label><?php echo t('items_ac'); ?><input type="text" name="ac_base"></label>
            <div class="actions"><button type="submit" class="button"><?php echo t('items_create_button'); ?></button></div>
        </form>
    </section>
</main>
</body>
</html>
