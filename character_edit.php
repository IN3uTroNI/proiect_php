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

$stats = $controller->getCharacterStats($characterId);
$skills = $controller->getCharacterSkills($characterId);
$items = $controller->getCharacterItems($characterId);
$races = $controller->getRaces();
$classes = $controller->getClasses();
$allSkills = $controller->getSkills();
$allItems = $controller->getItems();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!verify_csrf($token)) {
        $error = t('char_create_error_csrf');
    } else {
        $name = trim($_POST['name'] ?? '');
        $raceId = (int)($_POST['race_id'] ?? 0);
        $classId = (int)($_POST['class_id'] ?? 0);
        $hp = max(1, (int)($_POST['hp_max'] ?? 1));
        $background = trim($_POST['background'] ?? '');
        $alignment = trim($_POST['alignment'] ?? '');
        if ($name === '' || $raceId <= 0 || $classId <= 0) {
            $error = t('char_create_error');
        } else {
            try {
                $controller->updateCharacter($characterId, $raceId, $name, $hp, $background, $alignment);
                $controller->setCharacterStats($characterId, $_POST['stats'] ?? []);
                $selectedSkills = array_map('intval', $_POST['skills'] ?? []);
                $selectedSkills = array_flip($selectedSkills);
                $skillsMap = [];
                foreach ($allSkills as $sk) {
                    $skillId = (int)$sk['id'];
                    $skillsMap[$skillId] = [
                        'proficient' => !empty($selectedSkills[$skillId]) ? 1 : 0,
                        'expertise' => 0
                    ];
                }
                $controller->setCharacterSkills($characterId, $skillsMap);
                $controller->setCharacterItems($characterId, $_POST['items'] ?? []);
                header('Location: /character_view.php?id=' . $characterId);
                exit;
            } catch (Exception $e) {
                $error = t('char_create_error_db') . $e->getMessage();
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
    <title><?php echo t('char_edit_title'); ?> - <?php echo t('site_title'); ?></title>
    <link rel="stylesheet" href="/resources/css/style.css">
</head>
<body class="<?php echo h(get_theme()); ?>">
<?php include __DIR__ . '/common_scripts/selector.php'; ?>
<main class="builder-page container">
    <section class="builder-hero">
        <div>
            <p class="eyebrow"><?php echo t('char_edit_title'); ?></p>
            <h1><?php echo t('char_edit_title'); ?></h1>
        </div>
        <div class="hero-actions">
            <a href="/dashboard.php" class="button secondary"><?php echo t('view_back'); ?></a>
            <a href="/character_view.php?id=<?php echo $characterId; ?>" class="button secondary"><?php echo t('char_edit_cancel'); ?></a>
        </div>
    </section>
    <section class="card builder-panel">
        <h2><?php echo t('char_edit_title'); ?></h2>
        <?php if (!empty($error)): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
            <label><?php echo t('char_create_name'); ?><input type="text" name="name" value="<?php echo h($character['name']); ?>" required></label>
            <label><?php echo t('char_create_race'); ?>
                <select name="race_id" required>
                    <?php foreach ($races as $r): ?>
                        <option value="<?php echo (int)$r['id']; ?>"<?php if ($character['race_id'] == $r['id']) echo ' selected'; ?>><?php echo h($r['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?php echo t('char_create_class'); ?>
                <select name="class_id" required>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"<?php if (!empty($character['class_id']) && $character['class_id'] == $c['id']) echo ' selected'; ?>><?php echo h($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?php echo t('char_create_hp'); ?><input type="number" name="hp_max" value="<?php echo h($character['hp_max']); ?>" min="1"></label>
            <label><?php echo t('char_create_background'); ?><input type="text" name="background" value="<?php echo h($character['background']); ?>"></label>
            <label><?php echo t('char_create_alignment'); ?><input type="text" name="alignment" value="<?php echo h($character['alignment']); ?>"></label>
            <h4><?php echo t('char_create_step_stats'); ?></h4>
            <div class="stat-grid">
                <?php foreach ($stats as $s): ?>
                    <div class="stat-card">
                        <label><?php echo h($s['code']); ?> <input type="number" min="1" max="30" name="stats[<?php echo (int)$s['stat_id']; ?>]" value="<?php echo (int)$s['value']; ?>"></label>
                        <div><small class="muted-small"><?php echo h($s['name']); ?></small></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <h4><?php echo t('char_create_skills_select'); ?></h4>
            <div class="skill-grid">
                <?php foreach ($allSkills as $sk): ?>
                    <label class="skill-row">
                        <input type="checkbox" name="skills[]" value="<?php echo (int)$sk['id']; ?>"<?php foreach ($skills as $osk) if ($osk['skill_id'] == $sk['id'] && $osk['proficient']) echo ' checked'; ?>>
                        <span class="skill-name"><?php echo h($sk['name']); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <h4><?php echo t('char_create_starting_items'); ?></h4>
            <div class="item-grid">
                <?php foreach ($allItems as $it): ?>
                    <label class="item-card">
                        <input type="checkbox" name="items[]" value="<?php echo (int)$it['id']; ?>"<?php foreach ($items as $oi) if ($oi['name'] == $it['name']) echo ' checked'; ?>>
                        <div>
                            <strong><?php echo h($it['name']); ?></strong>
                            <small><?php echo h($it['item_type']); ?> · <?php echo h($it['rarity']); ?></small>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="actions">
                <button type="submit" class="button"><?php echo t('char_edit_save'); ?></button>
                <a href="/character_view.php?id=<?php echo $characterId; ?>" class="button secondary"><?php echo t('char_edit_cancel'); ?></a>
            </div>
        </form>
    </section>
</main>
</body>
</html>
