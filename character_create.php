<?php
require_once __DIR__ . '/common_scripts/init.php';
require_login();
$user = $_SESSION['user'];

// Handle language/theme quick changes
if (isset($_GET['lang'])) { set_language($_GET['lang']); }
if (isset($_GET['theme'])) { set_theme($_GET['theme']); }

$races = $controller->getRaces();
$classes = $controller->getClasses();
$skills = $controller->getSkills();
$items = $controller->getItems();
$itemTypes = array_values(array_unique(array_column($items, 'item_type')));
sort($itemTypes);
$stats = $controller->getStats();

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
                $cid = $controller->createCharacter((int)$user['id'], $raceId, $name, $hp, $background, $alignment);
                $controller->addOrIncrementClassLevel($cid, $classId);

                // Collect stats posted as stats[<stat_id>] => value
                $postedStats = $_POST['stats'] ?? [];
                $statsMap = [];
                foreach ($postedStats as $sid => $val) {
                    $statsMap[(int)$sid] = (int)$val;
                }
                if (!empty($statsMap)) {
                    $controller->setCharacterStats($cid, $statsMap);
                }

                // Save all skills and mark selected ones as proficient
                $selectedSkills = array_map('intval', $_POST['skills'] ?? []);
                $selectedSkills = array_flip($selectedSkills);
                $skillsMap = [];
                foreach ($skills as $sk) {
                    $skillId = (int)$sk['id'];
                    $skillsMap[$skillId] = [
                        'proficient' => !empty($selectedSkills[$skillId]) ? 1 : 0,
                        'expertise' => 0
                    ];
                }
                $controller->setCharacterSkills($cid, $skillsMap);

                // Starting items as items[]
                $postedItems = $_POST['items'] ?? [];
                foreach ($postedItems as $it) {
                    $controller->addItemToInventory($cid, (int)$it, 1);
                }

                header('Location: /dashboard.php');
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
    <title><?php echo t('char_create_title'); ?> - <?php echo t('site_title'); ?></title>
    <link rel="stylesheet" href="/resources/css/style.css">
</head>
<body class="<?php echo h(get_theme()); ?>">
<?php include __DIR__ . '/common_scripts/selector.php'; ?>
<main class="card builder-page">
    <h1 class="section-title"><?php echo t('char_create_title'); ?></h1>
    <?php if (!empty($error)): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>

    <div class="steps" id="steps">
        <div class="step active" data-step="1"><?php echo t('char_create_step_basic'); ?></div>
        <div class="step" data-step="2"><?php echo t('char_create_step_stats'); ?></div>
        <div class="step" data-step="3"><?php echo t('char_create_step_skills'); ?></div>
        <div class="step" data-step="4"><?php echo t('char_create_preview'); ?></div>
    </div>
    <div class="progress"><span id="progressText"><?php echo t('char_create_step_basic'); ?></span></div>

    <form method="post" id="charForm">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">

        <!-- Step 1: Basic Info -->
        <div class="step-panel" data-panel="1">
            <div class="builder-hero">
                <div>
                    <div class="eyebrow"><?php echo t('char_create_step_basic'); ?></div>
                    <h2 class="section-title"><?php echo t('char_create_title'); ?></h2>
                    <p class="muted section-text"><?php echo t('char_create_desc'); ?></p>
                </div>
            </div>
            <div class="builder-grid">
                <div class="builder-panel">
                    <div class="description"><?php echo t('char_create_desc'); ?></div>
                    <div class="ability-section">
                        <label class="choice-card"><?php echo t('char_create_name'); ?><input type="text" name="name" id="name" required></label>
                        <label class="choice-card"><?php echo t('char_create_race'); ?>
                            <select name="race_id" id="race_id" required>
                                <option value=""><?php echo t('char_create_choose'); ?></option>
                                <?php foreach ($races as $r): ?>
                                    <option value="<?php echo (int)$r['id']; ?>"><?php echo h($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="choice-card"><?php echo t('char_create_class'); ?>
                            <select name="class_id" id="class_id" required>
                                <option value=""><?php echo t('char_create_choose'); ?></option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>"><?php echo h($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="choice-card"><?php echo t('char_create_hp'); ?><input type="number" name="hp_max" id="hp_max" value="10" min="1"></label>
                        <label class="choice-card"><?php echo t('char_create_background'); ?><input type="text" name="background" id="background"></label>
                        <label class="choice-card"><?php echo t('char_create_alignment'); ?><input type="text" name="alignment" id="alignment"></label>
                    </div>
                </div>
            </div>
            <div class="step-buttons"><button type="button" onclick="nextStep()" class="button">Next</button></div>
        </div>

        <!-- Step 2: Stats -->
        <div class="step-panel hidden" data-panel="2">
            <div class="builder-hero">
                <div>
                    <div class="eyebrow"><?php echo t('char_create_step_stats'); ?></div>
                    <h2 class="section-title"><?php echo t('char_create_stats_title'); ?></h2>
                    <p class="muted section-text"><?php echo t('char_create_stats_desc'); ?></p>
                </div>
            </div>
            <div class="builder-grid">
                <div class="builder-panel">
                    <div class="roll-section">
                        <h3><?php echo t('char_create_stats_title'); ?></h3>
                        <p><?php echo t('char_create_stats_desc'); ?></p>
                        <div class="form-row">
                            <div><?php echo t('char_create_roll_method'); ?>:
                                <select id="roll_method">
                                    <option value="4d6"><?php echo t('char_create_roll_4d6'); ?></option>
                                    <option value="array"><?php echo t('char_create_array'); ?></option>
                                </select>
                            </div>
                            <button type="button" onclick="generateStats()" class="btn-roll"><?php echo t('char_create_generate'); ?></button>
                            <button type="button" onclick="clearRolls()" class="btn-ghost"><?php echo t('char_create_clear_rolls', 'Clear Rolls'); ?></button>
                        </div>
                        <div id="rollContainer" class="roll-container hidden"></div>
                        <div id="rollSuccess" class="success-msg hidden"></div>
                    </div>
                    <div class="ability-section">
                        <?php foreach ($stats as $s): ?>
                            <div class="ability-card">
                                <h4><?php echo h($s['code']); ?></h4>
                                <label><?php echo h($s['name']); ?>
                                    <input type="number" min="1" max="30" data-stat-id="<?php echo (int)$s['id']; ?>" data-stat-code="<?php echo h($s['code']); ?>" class="stat-input" name="stats[<?php echo (int)$s['id']; ?>]" value="10">
                                </label>
                                <div class="modifier-bonus" id="mod-<?php echo (int)$s['id']; ?>">+0</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="summary-row">
                        <span class="summary-pill"><?php echo t('char_create_proficiency_bonus'); ?> +2</span>
                        <span class="summary-pill"><?php echo t('char_create_skill_bonus_info'); ?></span>
                    </div>
                </div>
            </div>
            <div class="step-buttons">
                <button type="button" onclick="prevStep()" class="button text-button">Back</button>
                <button type="button" onclick="nextStep()" class="button">Next</button>
            </div>
        </div>

        <!-- Step 3: Skills & Items -->
        <div class="step-panel hidden" data-panel="3">
            <div class="builder-hero">
                <div>
                    <div class="eyebrow"><?php echo t('char_create_step_skills'); ?></div>
                    <h2 class="section-title"><?php echo t('char_create_skills_title'); ?></h2>
                    <p class="muted section-text"><?php echo t('char_create_skills_desc'); ?></p>
                </div>
            </div>
            <div class="builder-grid">
                <div class="builder-panel">
                    <div class="description"><?php echo t('char_create_skills_desc'); ?></div>
                    <h4><?php echo t('char_create_skills_select'); ?></h4>
                    <div class="skill-grid section-gap" id="skillGrid">
                        <?php foreach ($skills as $sk): ?>
                            <label class="skill-row">
                                <input type="checkbox" name="skills[]" value="<?php echo (int)$sk['id']; ?>" data-related-stat-id="<?php echo (int)$sk['related_stat_id']; ?>">
                                <div>
                                    <span class="skill-name"><?php echo h($sk['name']); ?></span>
                                    <span class="skill-info"><?php echo t('char_create_skill_of'); ?> <strong class="skill-stat-code">?</strong></span>
                                </div>
                                <div class="skill-bonus">+0</div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="section-divider"></div>
                    <h4><?php echo t('char_create_starting_items'); ?></h4>
                    <div class="item-controls">
                        <label><?php echo t('items_search_label'); ?><input type="search" id="itemSearch" placeholder="<?php echo t('items_search_placeholder'); ?>"></label>
                        <label><?php echo t('items_filter_type'); ?><select id="itemTypeFilter"><option value=""><?php echo t('items_all_types'); ?></option><?php foreach ($itemTypes as $type): ?><option value="<?php echo h($type); ?>"><?php echo h($type); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="item-grid scrollable" id="itemGrid">
                        <?php foreach ($items as $it): ?>
                            <label class="choice-card item-choice" data-item-name="<?php echo h(strtolower($it['name'])); ?>" data-item-type="<?php echo h($it['item_type']); ?>">
                                <input type="checkbox" name="items[]" value="<?php echo (int)$it['id']; ?>"> <?php echo h($it['name']); ?> <small class="muted-small"><?php echo h($it['item_type']); ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="step-buttons">
                <button type="button" onclick="prevStep()" class="button text-button">Back</button>
                <button type="button" onclick="nextStep()" class="button"><?php echo t('char_create_preview'); ?></button>
            </div>
        </div>

        <div class="step-panel hidden" data-panel="4">
            <h3>Preview</h3>
            <div class="description"><?php echo t('char_create_preview_desc', 'Review your choices before saving.'); ?></div>
            <div class="preview" id="previewBox"></div>
            <div class="step-buttons">
                <button type="button" onclick="prevStep()" class="button text-button">Back</button>
                <button type="submit" class="button"><?php echo t('char_create_button'); ?></button>
            </div>
        </div>

    </form>

    <script>
        let step = 1;
        const stepNames = {
            1: '<?php echo t('char_create_step_basic'); ?>',
            2: '<?php echo t('char_create_step_stats'); ?>',
            3: '<?php echo t('char_create_step_skills'); ?>',
            4: '<?php echo t('char_create_preview'); ?>'
        };

        function showStep(n){
            document.querySelectorAll('.step').forEach(el=>el.classList.toggle('active', Number(el.dataset.step)===n));
            document.querySelectorAll('.step-panel').forEach(p => p.classList.toggle('hidden', p.dataset.panel != n));
            const progressText = document.getElementById('progressText');
            if(progressText) progressText.textContent = stepNames[n] || '';
            step = n;
            if(n===3){
                refreshSkillLabels();
                updateSkillBonuses();
            }
            if(n===4) buildPreview();
        }
        function nextStep(){ showStep(Math.min(4, step+1)); }
        function prevStep(){ showStep(Math.max(1, step-1)); }

        function roll4d6(){
            let rolls = [];
            for(let i=0;i<4;i++) rolls.push(Math.floor(Math.random()*6)+1);
            rolls.sort((a,b)=>a-b);
            return rolls[1]+rolls[2]+rolls[3];
        }

        function generateStats(){
            const method = document.getElementById('roll_method').value;
            const inputs = document.querySelectorAll('.stat-input');
            const rolls = [];

            if(method==='4d6'){
                inputs.forEach(inp => {
                    const value = roll4d6();
                    inp.value = value;
                    rolls.push(value);
                });
                renderRolls(rolls, '<?php echo t('char_create_rolls_generated', 'Generated scores from 4d6 drop lowest.'); ?>');
            } else if(method==='array'){
                const arr = [15,14,13,12,10,8];
                let i=0;
                inputs.forEach(inp => {
                    inp.value = arr[i++];
                    rolls.push(inp.value);
                });
                renderRolls(rolls, '<?php echo t('char_create_rolls_array', 'Standard array assigned.'); ?>');
            }
            updateStatModifiers();
            updateSkillBonuses();
        }

        function renderRolls(rolls, message){
            const container = document.getElementById('rollContainer');
            const messageBox = document.getElementById('rollSuccess');
            if(!container || !messageBox) return;
            container.innerHTML = '';
            rolls.forEach((score, index) => {
                const item = document.createElement('div');
                item.className = 'roll-item';
                item.innerHTML = '<div>' + ('<?php echo t('char_create_roll_score', 'Score'); ?>') + ' ' + (index + 1) + '</div>' +
                    '<div class="roll-value">' + score + '</div>';
                container.appendChild(item);
            });
            container.classList.toggle('hidden', rolls.length === 0);
            messageBox.textContent = message;
            messageBox.classList.remove('hidden');
        }

        function clearRolls(){
            const container = document.getElementById('rollContainer');
            const messageBox = document.getElementById('rollSuccess');
            if(container){
                container.innerHTML = '';
                container.classList.add('hidden');
            }
            if(messageBox){
                messageBox.textContent = '';
                messageBox.classList.add('hidden');
            }
        }

        function statModifier(score){
            return Math.floor((score - 10) / 2);
        }

        function updateStatModifiers(){
            document.querySelectorAll('.stat-input').forEach(input => {
                const score = Number(input.value) || 0;
                const mod = statModifier(score);
                const label = document.getElementById('mod-' + input.dataset.statId);
                if(label){
                    label.textContent = (mod >= 0 ? '+' + mod : mod);
                }
            });
        }

        function updateSkillBonuses(){
            const proficiency = 2;
            document.querySelectorAll('.skill-row').forEach(row => {
                const input = row.querySelector('input[name="skills[]"]');
                const statId = input?.dataset.relatedStatId;
                const skillBonusNode = row.querySelector('.skill-bonus');
                const statInput = document.querySelector('.stat-input[data-stat-id="' + statId + '"]');
                const statMod = statInput ? statModifier(Number(statInput.value) || 0) : 0;
                const isProf = input?.checked;
                const bonus = statMod + (isProf ? proficiency : 0);
                if(skillBonusNode){
                    skillBonusNode.textContent = (bonus >= 0 ? '+' + bonus : bonus);
                }
            });
        }

        function refreshSkillLabels(){
            document.querySelectorAll('.skill-row').forEach(row => {
                const input = row.querySelector('input[name="skills[]"]');
                const statId = input?.dataset.relatedStatId;
                const statInput = document.querySelector('.stat-input[data-stat-id="' + statId + '"]');
                const statCode = statInput?.dataset.statCode || 'STAT';
                const label = row.querySelector('.skill-stat-code');
                if(label){
                    label.textContent = statCode;
                }
            });
        }

        function bindCharacterCreationEvents(){
            document.querySelectorAll('.stat-input').forEach(input => {
                input.addEventListener('input', () => {
                    updateStatModifiers();
                    refreshSkillLabels();
                    updateSkillBonuses();
                });
            });
            document.querySelectorAll('input[name="skills[]"]').forEach(input => {
                input.addEventListener('change', updateSkillBonuses);
            });
        }

        function buildPreview(){
            const form = document.getElementById('charForm');
            const data = new FormData(form);
            let out = '<strong>' + (data.get('name')||'Unnamed') + '</strong><br/>';
            out += 'Race: ' + (document.querySelector('select[name=race_id] option:checked')?.text || '') + '<br/>';
            out += 'Class: ' + (document.querySelector('select[name=class_id] option:checked')?.text || '') + '<br/>';
            out += 'Background: ' + (data.get('background')||'') + '<br/>';
            out += 'Alignment: ' + (data.get('alignment')||'') + '<br/>';
            out += '<h4>Stats</h4>';
            out += '<ul>';
            document.querySelectorAll('.stat-input').forEach(input => {
                const code = input.dataset.statCode || 'STAT';
                const mod = statModifier(Number(input.value) || 0);
                out += '<li>' + code + ': ' + input.value + ' (' + (mod >= 0 ? '+' + mod : mod) + ')</li>';
            });
            out += '</ul>';
            out += '<h4>Skills</h4>';
            const skills = data.getAll('skills[]');
            if(skills.length){
                out += '<ul>' + skills.map(s => {
                    const skillNode = document.querySelector('input[name="skills[]"][value="'+s+'"]');
                    const bonus = skillNode ? skillNode.closest('.skill-row').querySelector('.skill-bonus').textContent : '+0';
                    return '<li>' + (skillNode ? skillNode.closest('.skill-row').querySelector('.skill-name').textContent.trim() : s) + ' ' + bonus + '</li>';
                }).join('') + '</ul>';
            } else {
                out += '<p class="muted-small"><?php echo t('dashboard_no_chars'); ?></p>';
            }
            out += '<h4>Starting Items</h4>';
            const items = data.getAll('items[]');
            if(items.length){
                out += '<ul>' + items.map(i => '<li>' + (document.querySelector('input[name="items[]"][value="'+i+'"]')?.parentNode.textContent.trim() || i) + '</li>').join('') + '</ul>';
            } else {
                out += '<p class="muted-small"><?php echo t('char_create_no_items'); ?></p>';
            }
            document.getElementById('previewBox').innerHTML = out;
        }

        function filterItems(){
            const query = document.getElementById('itemSearch');
            const type = document.getElementById('itemTypeFilter');
            if(!query || !type) return;
            const needle = query.value.trim().toLowerCase();
            const filterType = type.value;
            document.querySelectorAll('.item-choice').forEach(choice => {
                const name = choice.dataset.itemName || '';
                const itemType = choice.dataset.itemType || '';
                const matchesName = !needle || name.includes(needle);
                const matchesType = !filterType || itemType === filterType;
                choice.style.display = matchesName && matchesType ? '' : 'none';
            });
        }

        function bindItemFilters(){
            const query = document.getElementById('itemSearch');
            const type = document.getElementById('itemTypeFilter');
            if(query) query.addEventListener('input', filterItems);
            if(type) type.addEventListener('change', filterItems);
        }

        // Initialize
        bindCharacterCreationEvents();
        bindItemFilters();
        updateStatModifiers();
        refreshSkillLabels();
        updateSkillBonuses();
        showStep(1);
    </script>

</main>
</body>
</html>
