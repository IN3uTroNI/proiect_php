<?php
require_once __DIR__ . '/common_scripts/init.php';
require_login();

if (isset($_GET['lang'])) {
    set_language($_GET['lang']);
}
if (isset($_GET['theme'])) {
    set_theme($_GET['theme']);
}

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

$classes = $controller->getCharacterClasses($characterId);
$stats = $controller->getCharacterStats($characterId);
$skills = $controller->getCharacterSkills($characterId);
$items = $controller->getCharacterItems($characterId);
$spellOptions = $controller->getSpells();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_spell') {
    $token = $_POST['csrf'] ?? '';
    if (!verify_csrf($token)) {
        $error = t('char_create_error_csrf');
    } else {
        $spellId = (int)($_POST['spell_id'] ?? 0);
        $prepared = !empty($_POST['prepared']);
        if ($spellId <= 0) {
            $error = t('view_spell_invalid');
        } else {
            try {
                $controller->addSpellToCharacter($characterId, $spellId, $prepared);
                header('Location: /character_view.php?id=' . $characterId);
                exit;
            } catch (Exception $e) {
                $error = t('error_generic') . ' ' . $e->getMessage();
            }
        }
    }
}

$spells = $controller->getCharacterSpells($characterId);

function abilityModifier(int $score): int
{
    return (int)floor(($score - 10) / 2);
}

function proficiencyBonus(int $level): int
{
    return 2 + (int)floor(max(1, $level - 1) / 4);
}

$level = max(1, (int)$character['total_level']);
$proficiencyBonus = proficiencyBonus($level);
$classesText = implode(', ', array_map(fn($c) => h($c['class_name']) . ' ' . h($c['class_level']), $classes));
?>
<!doctype html>
<html lang="<?php echo h(get_language()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo h($character['name']); ?> - <?php echo t('site_title'); ?></title>
    <link rel="stylesheet" href="/resources/css/style.css">
</head>
<body class="<?php echo h(get_theme()); ?>">
<?php include __DIR__ . '/common_scripts/selector.php'; ?>
<header class="topbar">
    <div class="container">
        <h2 class="view-title"><?php echo t('view_summary'); ?></h2>
        <nav>
            <a href="/dashboard.php" class="button secondary"><?php echo t('view_back'); ?></a>
            <a href="/character_create.php" class="button secondary"><?php echo t('nav_new_character'); ?></a>
            <a href="/logout.php" class="button secondary"><?php echo t('nav_logout'); ?></a>
        </nav>
    </div>
</header>
<main class="container">
    <section class="card view-card">
        <div class="view-header">
            <div>
                <h1><?php echo h($character['name']); ?></h1>
                <p class="muted"><?php echo t('site_title'); ?> <?php echo t('view_summary'); ?></p>
            </div>
            <div class="view-summary">
                <span class="summary-pill"><?php echo t('dashboard_level'); ?> <?php echo h($level); ?></span>
                <span class="summary-pill"><?php echo t('dashboard_hp'); ?> <?php echo h($character['hp_current']); ?>/<?php echo h($character['hp_max']); ?></span>
                <span class="summary-pill"><?php echo t('char_create_proficiency_bonus'); ?> +<?php echo h($proficiencyBonus); ?></span>
            </div>
        </div>
        <div class="summary-row">
            <span class="summary-pill"><?php echo t('char_create_race'); ?>: <?php echo h($character['race_name']); ?></span>
            <span class="summary-pill"><?php echo t('char_create_class'); ?>: <?php echo h($classesText ?: t('dashboard_no_chars')); ?></span>
            <span class="summary-pill"><?php echo t('char_create_alignment'); ?>: <?php echo h($character['alignment']); ?></span>
        </div>
        <div class="view-actions">
            <a class="button secondary" href="/character_edit.php?id=<?php echo $characterId; ?>"><?php echo t('edit_button'); ?></a>
            <a class="button danger" href="/character_delete.php?id=<?php echo $characterId; ?>"><?php echo t('delete_button'); ?></a>
        </div>
        <?php if (!empty($character['background'])): ?>
            <p class="section-text"><strong><?php echo t('char_create_background'); ?>:</strong> <?php echo h($character['background']); ?></p>
        <?php endif; ?>
    </section>

    <section class="page">
        <article class="card">
            <h3><?php echo t('view_stats'); ?></h3>
            <div class="stat-grid">
                <?php foreach ($stats as $stat): ?>
                    <div class="stat-card">
                        <strong><?php echo h($stat['code']); ?> <?php echo h($stat['value']); ?></strong>
                        <small><?php echo h($stat['name']); ?> • <?php echo h(abilityModifier((int)$stat['value'])) >= 0 ? '+' . h(abilityModifier((int)$stat['value'])) : h(abilityModifier((int)$stat['value'])); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <h3 class="section-subtitle"><?php echo t('view_skills'); ?></h3>
            <?php if (empty($skills)): ?>
                <p class="muted text-center"><?php echo t('view_no_skills'); ?></p>
            <?php else: ?>
                <div class="skill-bars">
                    <?php foreach ($skills as $skill): ?>
                        <?php $statValue = 0; foreach ($stats as $stat) { 
                            if ($stat['stat_id'] == $skill['related_stat_id']) { 
                                $statValue = (int)$stat['value']; break; 
                            } 
                        }
                        $baseMod = abilityModifier($statValue);
                        $bonus = $baseMod + ((int)$skill['proficient'] ? $proficiencyBonus : 0);
                        ?>
                        <div class="skill-bar">
                            <div>
                                <span class="skill-name"><?php echo h($skill['name']); ?></span>
                                <span class="skill-info"><?php echo t('char_create_skill_of'); ?> <?php echo h($skill['stat_code']); ?></span>
                            </div>
                            <div class="skill-value"><?php echo $bonus >= 0 ? '+' . h($bonus) : h($bonus); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <section class="card view-card">
        <h3><?php echo t('view_items'); ?></h3>
        <?php if (empty($items)): ?>
            <p class="muted text-center"><?php echo t('view_no_items'); ?></p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($items as $item): ?>
                    <li>
                        <div>
                            <strong><?php echo h($item['name']); ?></strong>
                            <?php if (!empty($item['description'])): ?>
                                <div class="muted-small"><?php echo h($item['description']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['damage_dice'])): ?>
                                <div class="muted-small"><?php echo t('view_damage_dice'); ?>: <?php echo h($item['damage_dice']); ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="meta"><?php echo h($item['item_type']); ?> • <?php echo h($item['rarity']); ?><?php echo $item['equipped'] ? ' • Equipped' : ''; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card view-card">
        <h3><?php echo t('view_spells'); ?></h3>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($spellOptions)): ?>
            <form method="post" class="form-inline">
                <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="action" value="add_spell">
                <label>
                    <?php echo t('view_add_spell'); ?>
                    <select name="spell_id" required>
                        <option value=""><?php echo t('view_choose_spell'); ?></option>
                        <?php foreach ($spellOptions as $spellOption): ?>
                            <option value="<?php echo (int)$spellOption['id']; ?>"><?php echo h($spellOption['name']); ?> (<?php echo (int)$spellOption['spell_level'] === 0 ? t('view_cantrip') : h($spellOption['spell_level']) . ' ' . t('view_spell_level'); ?>, <?php echo h($spellOption['school']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="prepared" value="1"> <?php echo t('view_prepared'); ?>
                </label>
                <button type="submit" class="button small"><?php echo t('view_add_spell_button'); ?></button>
            </form>
        <?php endif; ?>
        <?php if (empty($spells)): ?>
            <p class="muted text-center"><?php echo t('view_no_spells'); ?></p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($spells as $spell): ?>
                    <li>
                        <div>
                            <strong><?php echo h($spell['name']); ?></strong>
                            <div class="muted-small">
                                <?php if ((int)$spell['spell_level'] === 0): ?>
                                    <?php echo t('view_cantrip'); ?>
                                <?php else: ?>
                                    <?php echo h($spell['spell_level']) . ' ' . t('view_spell_level'); ?>
                                <?php endif; ?>
                                • <?php echo h($spell['school']); ?>
                            </div>
                            <?php if (!empty($spell['description'])): ?>
                                <div class="muted-small"><?php echo h($spell['description']); ?></div>
                            <?php endif; ?>
                            <div class="muted-small"><?php echo h($spell['casting_time']); ?> • <?php echo h($spell['range_text']); ?> • <?php echo h($spell['duration_text']); ?></div>
                        </div>
                        <span class="meta"><?php echo $spell['prepared'] ? t('view_prepared') : t('view_unprepared'); ?><?php echo !empty($spell['source_class_name']) ? ' • ' . h($spell['source_class_name']) : ''; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
