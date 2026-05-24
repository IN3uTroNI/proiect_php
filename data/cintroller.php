<?php

declare(strict_types=1);

class DnDSQLiteController
{
    private PDO $pdo;


    public function __construct(string $databaseFilePath)
    {
        try {
            $this->pdo = new PDO("sqlite:" . $databaseFilePath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // CRITICAL FOR SQLITE: Foreign key enforcement must be turned on explicitly per session!
            $this->pdo->exec("PRAGMA foreign_keys = ON;");
        } catch (PDOException $e) {
            throw new RuntimeException("SQLite connection failed: " . $e->getMessage());
        }
    }

   
    public function registerUser(string $email, string $rawPassword, string $accountName): int
    {
        // Safe standard bcrypt hash generation
        $passwordHash = password_hash($rawPassword, PASSWORD_BCRYPT);

        $sql = "INSERT INTO users (email, password_hash, account_name) VALUES (:email, :password_hash, :account_name)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':email'         => $email,
            ':password_hash' => $passwordHash,
            ':account_name'  => $accountName
        ]);

        return (int)$this->pdo->lastInsertId();
    }


    public function authenticateUser(string $email, string $rawPassword): ?array
    {
        $sql = "SELECT id, email, password_hash, account_name FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($rawPassword, $user['password_hash'])) {
            unset($user['password_hash']); // Wipe signature from returning arrays
            return $user;
        }

        return null;
    }

    public function createCharacter(int $userId, int $raceId, string $name, int $hpMax, string $background, string $alignment): int
    {
        $sql = "INSERT INTO characters (user_id, race_id, name, hp_current, hp_max, background, alignment)
        VALUES (:user_id, :race_id, :name, :hp_max, :hp_max, :background, :alignment)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id'    => $userId,
            ':race_id'    => $raceId,
            ':name'       => $name,
            ':hp_max'     => $hpMax,
            ':background' => $background,
            ':alignment'  => $alignment
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // Update character main info
    public function updateCharacter(int $characterId, int $raceId, string $name, int $hpMax, string $background, string $alignment): void
    {
        $sql = "UPDATE characters SET race_id = :race_id, name = :name, hp_max = :hp_max, background = :background, alignment = :alignment WHERE id = :cid";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':race_id' => $raceId,
            ':name' => $name,
            ':hp_max' => $hpMax,
            ':background' => $background,
            ':alignment' => $alignment,
            ':cid' => $characterId
        ]);
    }

    // Replace all items for a character
    public function setCharacterItems(int $characterId, array $itemIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM character_items WHERE character_id = :cid');
            $del->execute([':cid' => $characterId]);
            $slotSql = "SELECT id FROM equipment_slots_list WHERE slot_name = 'Inventory' LIMIT 1";
            $slotId = (int)$this->pdo->query($slotSql)->fetchColumn();
            $ins = $this->pdo->prepare('INSERT INTO character_items (character_id, item_id, quantity, equipped, is_attuned, equipment_slot_id) VALUES (:cid, :item_id, 1, 0, 0, :slot_id)');
            foreach ($itemIds as $itemId) {
                $ins->execute([':cid' => $characterId, ':item_id' => (int)$itemId, ':slot_id' => $slotId]);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Delete character and all related data
    public function deleteCharacter(int $characterId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM character_stats WHERE character_id = :cid')->execute([':cid' => $characterId]);
            $this->pdo->prepare('DELETE FROM character_skills WHERE character_id = :cid')->execute([':cid' => $characterId]);
            $this->pdo->prepare('DELETE FROM character_items WHERE character_id = :cid')->execute([':cid' => $characterId]);
            $this->pdo->prepare('DELETE FROM character_classes WHERE character_id = :cid')->execute([':cid' => $characterId]);
            $this->pdo->prepare('DELETE FROM characters WHERE id = :cid')->execute([':cid' => $characterId]);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function addOrIncrementClassLevel(int $characterId, int $classId): void
    {
        $this->pdo->beginTransaction();
        try {
            // Check if class allocation is already established
            $checkSql = "SELECT class_level FROM character_classes WHERE character_id = :cid AND class_id = :class_id";
            $chk = $this->pdo->prepare($checkSql);
            $chk->execute([':cid' => $characterId, ':class_id' => $classId]);
            $row = $chk->fetch();

            if ($row) {
                $sql = "UPDATE character_classes SET class_level = class_level + 1 WHERE character_id = :cid AND class_id = :class_id";
                $this->pdo->prepare($sql)->execute([':cid' => $characterId, ':class_id' => $classId]);
            } else {
                $sql = "INSERT INTO character_classes (character_id, class_id, class_level) VALUES (:cid, :class_id, 1)";
                $this->pdo->prepare($sql)->execute([':cid' => $characterId, ':class_id' => $classId]);
            }

            // Sync structural total level cache
            $syncSql = "UPDATE characters
            SET total_level = (SELECT SUM(class_level) FROM character_classes WHERE character_id = :cid)
            WHERE id = :cid";
            $this->pdo->prepare($syncSql)->execute([':cid' => $characterId]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function assignSubclass(int $characterId, int $classId, int $subclassId): void
    {
        $sql = "INSERT INTO character_subclasses (character_id, class_id, subclass_id)
        VALUES (:cid, :class_id, :subclass_id)
        ON CONFLICT(character_id, class_id) DO UPDATE SET subclass_id = :subclass_id";

        $this->pdo->prepare($sql)->execute([
            ':cid'         => $characterId,
            ':class_id'    => $classId,
            ':subclass_id' => $subclassId
        ]);
    }

    public function addItemToInventory(int $characterId, int $itemId, int $quantity = 1): void
    {
        $itemSql = "SELECT item_type FROM items WHERE id = :item_id";
        $itemStmt = $this->pdo->prepare($itemSql);
        $itemStmt->execute([':item_id' => $itemId]);
        $item = $itemStmt->fetch();

        if (!$item) {
            throw new InvalidArgumentException("Item does not exist.");
        }

        // Fetch index ID for standard baseline Inventory slots
        $slotSql = "SELECT id FROM equipment_slots_list WHERE slot_name = 'Inventory' LIMIT 1";
        $slotId = (int)$this->pdo->query($slotSql)->fetchColumn();

        $isStackable = in_array($item['item_type'], ['Potion', 'Gear']);

        if ($isStackable) {
            $checkSql = "SELECT id FROM character_items
            WHERE character_id = :cid AND item_id = :item_id AND equipment_slot_id = :slot_id LIMIT 1";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([':cid' => $characterId, ':item_id' => $itemId, ':slot_id' => $slotId]);
            $existingRow = $checkStmt->fetch();

            if ($existingRow) {
                $updateSql = "UPDATE character_items SET quantity = quantity + :qty WHERE id = :id";
                $this->pdo->prepare($updateSql)->execute([':qty' => $quantity, ':id' => $existingRow['id']]);
                return;
            }
        }

        $insertSql = "INSERT INTO character_items (character_id, item_id, quantity, equipped, is_attuned, equipment_slot_id)
        VALUES (:cid, :item_id, :qty, 0, 0, :slot_id)";
        $this->pdo->prepare($insertSql)->execute([
            ':cid'     => $characterId,
            ':item_id' => $itemId,
            ':qty'     => $isStackable ? $quantity : 1,
            ':slot_id' => $slotId
        ]);
    }


    public function getCharacterSummary(int $characterId): array
    {
        $data = [];

        // Base profiling stats
        $sqlChar = "SELECT c.*, r.name AS race_name, r.base_speed
        FROM characters c
        JOIN races r ON c.race_id = r.id
        WHERE c.id = :id";
        $stmt = $this->pdo->prepare($sqlChar);
        $stmt->execute([':id' => $characterId]);
        $data['base'] = $stmt->fetch();

        if (!$data['base']) {
            throw new RuntimeException("Character context matching criteria not located.");
        }

        // Active classes sorted by highest level count allocation first
        $sqlClasses = "SELECT cc.class_level, cl.name as class_name, sc.name as subclass_name
        FROM character_classes cc
        JOIN classes cl ON cc.class_id = cl.id
        LEFT JOIN character_subclasses csc ON csc.character_id = cc.character_id AND csc.class_id = cc.class_id
        LEFT JOIN subclasses sc ON csc.subclass_id = sc.id
        WHERE cc.character_id = :id
        ORDER BY cc.class_level DESC, cl.name ASC";
        $stmt = $this->pdo->prepare($sqlClasses);
        $stmt->execute([':id' => $characterId]);
        $data['classes'] = $stmt->fetchAll();

        // Complete asset inventory inventory sorted alphabetically by item name
        $sqlItems = "SELECT ci.id as character_item_id, ci.quantity, ci.equipped, ci.is_attuned, esl.slot_name, i.*
        FROM character_items ci
        JOIN items i ON ci.item_id = i.id
        JOIN equipment_slots_list esl ON ci.equipment_slot_id = esl.id
        WHERE ci.character_id = :id
        ORDER BY i.name ASC";
        $stmt = $this->pdo->prepare($sqlItems);
        $stmt->execute([':id' => $characterId]);
        $data['inventory'] = $stmt->fetchAll();

        return $data;
    }


    public function getActiveModifiers(int $characterId): array
    {
        $sql = "
        SELECT rm.target_type, rm.target_identifier, rm.modifier_mode, rm.modifier_value, rm.priority
        FROM rule_modifiers rm
        JOIN character_items ci ON rm.item_id = ci.item_id
        WHERE ci.character_id = :id AND ci.equipped = 1

        UNION ALL

        SELECT rm.target_type, rm.target_identifier, rm.modifier_mode, rm.modifier_value, rm.priority
        FROM rule_modifiers rm
        JOIN character_features cf ON rm.feature_id = cf.feature_id
        WHERE cf.character_id = :id

        UNION ALL

        SELECT rm.target_type, rm.target_identifier, rm.modifier_mode, rm.modifier_value, rm.priority
        FROM rule_modifiers rm
        JOIN character_conditions cc ON rm.condition_id = cc.condition_id
        WHERE cc.character_id = :id

        ORDER BY priority ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $characterId]);
        return $stmt->fetchAll();
    }


    public function getCharactersByUser(int $userId): array
    {
        $sql = "SELECT id, name, total_level, hp_current, hp_max, created_at FROM characters WHERE user_id = :uid ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getCharacterBase(int $characterId, int $userId): ?array
    {
        $sql = "SELECT c.*, r.name AS race_name, r.base_speed FROM characters c JOIN races r ON c.race_id = r.id WHERE c.id = :cid AND c.user_id = :uid LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cid' => $characterId, ':uid' => $userId]);
        $character = $stmt->fetch();
        return $character ?: null;
    }

    public function getCharacterClasses(int $characterId): array
    {
        $sql = "SELECT cc.class_level, cl.name AS class_name FROM character_classes cc JOIN classes cl ON cc.class_id = cl.id WHERE cc.character_id = :cid ORDER BY cc.class_level DESC, cl.name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cid' => $characterId]);
        return $stmt->fetchAll();
    }

    public function getCharacterStats(int $characterId): array
    {
        $sql = "SELECT cs.stat_id, s.code, s.name, cs.value FROM character_stats cs JOIN stats s ON cs.stat_id = s.id WHERE cs.character_id = :cid ORDER BY s.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cid' => $characterId]);
        return $stmt->fetchAll();
    }

    public function getCharacterSkills(int $characterId): array
    {
        $sql = "SELECT cs.skill_id, sk.name, sk.related_stat_id, s.code AS stat_code, cs.proficient, cs.expertise FROM character_skills cs JOIN skills sk ON cs.skill_id = sk.id JOIN stats s ON sk.related_stat_id = s.id WHERE cs.character_id = :cid ORDER BY sk.name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cid' => $characterId]);
        return $stmt->fetchAll();
    }

    public function getCharacterItems(int $characterId): array
    {
        $sql = "SELECT ci.quantity, i.name, i.description, i.damage_dice, i.item_type, i.rarity, esl.slot_name, ci.equipped FROM character_items ci JOIN items i ON ci.item_id = i.id JOIN equipment_slots_list esl ON ci.equipment_slot_id = esl.id WHERE ci.character_id = :cid ORDER BY i.name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cid' => $characterId]);
        return $stmt->fetchAll();
    }

    public function getCharacterSpells(int $characterId): array
    {
        $sql = "SELECT cs.prepared, s.name, s.spell_level, s.school, s.casting_time, s.range_text, s.duration_text, s.description, c.name AS source_class_name
                FROM character_spells cs
                JOIN spells s ON cs.spell_id = s.id
                LEFT JOIN classes c ON cs.source_class_id = c.id
                WHERE cs.character_id = :cid
                ORDER BY s.spell_level ASC, s.name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cid' => $characterId]);
        return $stmt->fetchAll();
    }

    public function getRaces(): array
    {
        $sql = "SELECT id, name, description, base_speed FROM races ORDER BY name ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function getClasses(): array
    {
        $sql = "SELECT id, name, hit_die FROM classes ORDER BY name ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function getItems(): array
    {
        $sql = "SELECT id, name, description, item_type, rarity FROM items ORDER BY name ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function getSpells(): array
    {
        $sql = "SELECT id, name, spell_level, school, casting_time, range_text, duration_text, description FROM spells ORDER BY spell_level ASC, name ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function addSpellToCharacter(int $characterId, int $spellId, bool $prepared = false): void
    {
        if ($prepared) {
            $sql = "INSERT INTO character_spells (character_id, spell_id, prepared) VALUES (:cid, :sid, 1)
                    ON CONFLICT(character_id, spell_id) DO UPDATE SET prepared = 1";
        } else {
            $sql = "INSERT OR IGNORE INTO character_spells (character_id, spell_id, prepared) VALUES (:cid, :sid, 0)";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cid' => $characterId, ':sid' => $spellId]);
    }

    public function getItemTypes(): array
    {
        $sql = "SELECT DISTINCT item_type FROM items ORDER BY item_type ASC";
        return array_column($this->pdo->query($sql)->fetchAll(), 'item_type');
    }

    public function createItem(string $name, string $description, string $itemType, string $rarity): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO items (name, description, item_type, rarity) VALUES (:name, :description, :type, :rarity)');
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':type' => $itemType,
            ':rarity' => $rarity,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getSkills(): array
    {
        $sql = "SELECT id, name, related_stat_id FROM skills ORDER BY name ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function getStats(): array
    {
        $sql = "SELECT id, code, name FROM stats ORDER BY id ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function setCharacterStats(int $characterId, array $stats): void
    {
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM character_stats WHERE character_id = :cid');
            $del->execute([':cid' => $characterId]);

            $ins = $this->pdo->prepare('INSERT INTO character_stats (character_id, stat_id, value) VALUES (:cid, :stat_id, :val)');
            foreach ($stats as $statId => $val) {
                $ins->execute([':cid' => $characterId, ':stat_id' => (int)$statId, ':val' => (int)$val]);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }


    public function setCharacterSkills(int $characterId, array $skills): void
    {
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM character_skills WHERE character_id = :cid');
            $del->execute([':cid' => $characterId]);

            $ins = $this->pdo->prepare('INSERT INTO character_skills (character_id, skill_id, proficient, expertise) VALUES (:cid, :skill_id, :prof, :expert)');
            foreach ($skills as $skillId => $meta) {
                $ins->execute([
                    ':cid' => $characterId,
                    ':skill_id' => (int)$skillId,
                    ':prof' => !empty($meta['proficient']) ? 1 : 0,
                    ':expert' => !empty($meta['expertise']) ? 1 : 0
                ]);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
