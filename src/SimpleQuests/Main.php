<?php

declare(strict_types=1);

namespace SimpleQuests;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\TextFormat as TF;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase implements Listener {

    /**
     * @var array<string, array{
     *     name:string,
     *     description:string,
     *     type:string,
     *     block:string,
     *     target:int,
     *     reward:string,
     *     rewardMessage:string,
     *     cooldown:int
     * }>
     */
    private array $quests = [];

    /** @var array<string, array{quest:string,progress:int}> */
    private array $playerQuestProgress = [];

    /**
     * @var array<string, array<string,int>>
     * Stores last completion timestamps: [playerName => [questId => timestamp]]
     */
    private array $lastCompleted = [];

    private ConsoleCommandSender $consoleSender;

    public function onEnable(): void {
        @mkdir($this->getDataFolder(), 0777, true);
        $this->saveDefaultConfig();
    
        // Load quests with default cooldown = 0
        $raw = $this->getConfig()->get("quests", []);
        foreach ($raw as $id => $q) {
            // Ensure cooldown exists or set to 0 if not
            $q['cooldown'] = isset($q['cooldown']) ? $q['cooldown'] : 0;
            
            // Store the quest, making sure $q is always an array
            if (is_array($q)) {
                $this->quests[$id] = $q;
            } else {
                $this->getLogger()->warning("Quest '$id' is not a valid array!");
            }
        }
    

        // Load player progress
        $file = $this->getDataFolder() . "progress.json";
        if (file_exists($file)) {
            $this->playerQuestProgress = json_decode(file_get_contents($file), true) ?? [];
        }
        // Load last completed timestamps
        $lcFile = $this->getDataFolder() . "lastCompleted.json";
        if (file_exists($lcFile)) {
            $this->lastCompleted = json_decode(file_get_contents($lcFile), true) ?? [];
        }

        // Prepare console sender (PMMP 5+)
        $this->consoleSender = new ConsoleCommandSender(
            $this->getServer(),
            $this->getServer()->getLanguage()
        );

        // Register events and commands
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->registerCommand("quest", "Open the Quest Menu", "simplequests.command.quest");
        $this->registerCommand("addquest", "Add a new quest", "simplequests.command.addquest");
        $this->registerCommand("editquest", "Edit an existing quest", "simplequests.command.editquest");
        $this->registerCommand("deletequest", "Delete a quest via menu", "simplequests.command.deletequest");

        // Save progress every second (20 ticks = 1 second)
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            file_put_contents(
                $this->getDataFolder() . "progress.json",
                json_encode($this->playerQuestProgress, JSON_PRETTY_PRINT)
            );
        }), 20);
        // Save lastCompleted every minute (1200 ticks)
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            file_put_contents(
                $this->getDataFolder() . "lastCompleted.json",
                json_encode($this->lastCompleted, JSON_PRETTY_PRINT)
            );
        }), 1200);

        // Show progress bar every second for active quests
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            foreach ($this->playerQuestProgress as $playerName => $data) {
                $player = $this->getServer()->getPlayerExact($playerName);
                if (!$player instanceof Player || !$player->isOnline()) continue;

                $qid     = $data['quest'];
                $current = $data['progress'];
                $quest   = $this->quests[$qid] ?? null;
                if ($quest === null) continue;

                $target  = $quest['target'];
                $percent = min((int)($current * 100 / max($target, 1)), 100);
                $filled  = intdiv($percent, 5);
                $bar     = str_repeat("§a▎", $filled) . str_repeat("§7▎", 20 - $filled);

                // Send as tip above hotbar
                $player->sendTip(
                    TF::YELLOW . "{$quest['name']}: $bar §r $current/$target ($percent%)"
                );
            }
        }), 20);
    }

    public function onDisable(): void {
        // Save progress on disable
        file_put_contents(
            $this->getDataFolder() . "progress.json",
            json_encode($this->playerQuestProgress, JSON_PRETTY_PRINT)
        );
        // Save lastCompleted on disable
        file_put_contents(
            $this->getDataFolder() . "lastCompleted.json",
            json_encode($this->lastCompleted, JSON_PRETTY_PRINT)
        );
    }

    private function sendDeleteQuestMenu(Player $player): void {
    $validQuests = [];
    if (class_exists(\jojoe77777\FormAPI\SimpleForm::class)) {
        $form = new \jojoe77777\FormAPI\SimpleForm(function(Player $player, ?int $data) use (&$validQuests) {
            if ($data === null) return;
            
            // Make sure the array is defined before accessing the key
            $array = []; // Define $array before accessing it. Replace with your actual array if needed.
            $key = 0; // Ensure $key is valid before using it
            $value = $array[$key] ?? null; // Handle undefined array index correctly
            
            // Assuming $qid is the quest ID you want to delete, ensure it's properly set
            $qid = $validQuests[$data] ?? null; // Make sure $qid is assigned a valid value
            if ($qid !== null) {
                $this->deleteQuest($player, $qid);
                }
            });
            $form->setTitle("Select Quest to Delete");
            foreach ($this->quests as $id => $quest) {
                if ($this->isValidQuest($quest)) {
                    $validQuests[] = $id;
                    $form->addButton("§a{$quest['name']}\n§7{$quest['description']}");
                }
            }
            if (empty($validQuests)) {
                $form->addButton(TF::RED . "No quests available to delete");
            }
            $player->sendForm($form);
        } else {
            $player->sendMessage("§cFormAPI is not installed!");
        }
    }    

    private function deleteQuest(Player $player, string $questId): void {
        if (!isset($this->quests[$questId])) {
            $player->sendMessage(TF::RED . "No quest found with ID: $questId");
            return;
        }
        unset($this->quests[$questId]);
        $this->getConfig()->set("quests", $this->quests);
        $this->getConfig()->save();
        $player->sendMessage(TF::GREEN . "Quest with ID '$questId' has been deleted.");
    }

    private function registerCommand(string $name, string $description, string $permission): void {
        $cmd = new PluginCommand($name, $this, $this);
        $cmd->setDescription($description);
        $cmd->setPermission($permission);
        $cmd->setPermissionMessage(TF::RED . "You don't have permission to use /{$name}.");
        $this->getServer()->getCommandMap()->register($this->getName(), $cmd);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . "This command can only be used in-game.");
            return true;
        }
        switch (strtolower($command->getName())) {
            case "quest":
                $this->sendQuestMenu($sender);
                break;
            case "addquest":
                if (!$sender->hasPermission("simplequests.command.addquest")) {
                    $sender->sendMessage(TF::RED . "No permission.");
                    return true;
                }
                $this->sendQuestTypeMenu($sender);
                break;
            case "editquest":
                if (!$sender->hasPermission("simplequests.command.editquest")) {
                    $sender->sendMessage(TF::RED . "No permission.");
                    return true;
                }
                $this->sendEditQuestMenu($sender);
                break;
            case "deletequest":
                if (!$sender->hasPermission("simplequests.command.deletequest")) {
                    $sender->sendMessage(TF::RED . "No permission.");
                    return true;
                }
                $this->sendDeleteQuestMenu($sender);
                break;
        }
        return true;
    }

    private function sendQuestMenu(Player $player): void {
        $validQuests = [];
        if (class_exists(\jojoe77777\FormAPI\SimpleForm::class)) {
            $form = new \jojoe77777\FormAPI\SimpleForm(function(Player $player, ?int $data) use (&$validQuests) {
                if ($data === null || !isset($validQuests[$data])) return;
                $this->startQuest($player, $validQuests[$data]);
            });
            $form->setTitle(TF::GOLD . "Available Quests");
            foreach ($this->quests as $id => $quest) {
                if ($this->isValidQuest($quest)) {
                    $validQuests[] = $id;
                    $form->addButton("§a{$quest['name']}\n§7{$quest['description']}");
                }
            }
            if (empty($validQuests)) {
                $form->addButton(TF::RED . "No quests available");
            }
            $player->sendForm($form);
        } else {
            $player->sendMessage("§cFormAPI is not installed!");
        }
    }    

    private function sendQuestTypeMenu(Player $player): void {
        if (class_exists(\jojoe77777\FormAPI\SimpleForm::class)) {
            $form = new \jojoe77777\FormAPI\SimpleForm(function(Player $player, ?int $data) {
                if ($data === null) return;
                $types = ["BREAK_BLOCK", "PLACE_BLOCK"];
                $type = $types[$data] ?? null;
                if ($type !== null) {
                    $this->sendAddQuestForm($player, $type);
                }
            });
            $form->setTitle("Select Quest Type");
            $form->addButton("Break Block");
            $player->sendForm($form);
        } else {
            $player->sendMessage("§cFormAPI is not installed!");
        }
    }    

    private function sendAddQuestForm(Player $player, string $type): void {
        if (class_exists(\jojoe77777\FormAPI\CustomForm::class)) {
            $form = new \jojoe77777\FormAPI\CustomForm(function(Player $player, ?array $data) use ($type) {
                if ($data === null) return;
                [$name, $desc, $block, $target, $rewardItem, $rewardMessage, $cooldownInput] = $data;
    
                if (!is_numeric($target)) {
                    $player->sendMessage(TF::RED . "Target must be a number.");
                    return;
                }
    
                $id = uniqid("quest_");
                $this->quests[$id] = [
                    "name" => $name,
                    "description" => $desc,
                    "type" => $type,
                    "block" => strtoupper(trim($block)),
                    "target" => (int)$target,
                    "reward" => trim($rewardItem),
                    "rewardMessage" => trim($rewardMessage),
                    "cooldown" => $this->parseDuration($cooldownInput)
                ];
                $this->getConfig()->set("quests", $this->quests);
                $this->getConfig()->save();
                $player->sendMessage(TF::GREEN . "Quest '$name' added! Type: $type");
            });
            $form->setTitle("Add " . ($type === "BREAK_BLOCK" ? "Break" : "Place") . " Quest");
            $form->addInput("Name", "e.g. Mine stone blocks");
            $form->addInput("Description", "e.g. Break stone for reward");
            $form->addInput("Block (id/name)", $type === "BREAK_BLOCK" ? "STONE" : "DIRT");
            $form->addInput("Target Amount", "10");
            $form->addInput("Reward Command (use {player} for player name)", "say Congratulations {player}!");
            $form->addInput("Reward Message", "e.g. You have completed the quest!");
            $form->addInput("Cooldown (e.g. 1s, 5m, 2h)", "e.g. 0");
            $player->sendForm($form);
        } else {
            $player->sendMessage("§cFormAPI is not installed!");
        }
    }    

    // --- START: Edit Quest Functionality, (this took a long time T-T) ---
    private function sendEditQuestMenu(Player $player): void {
        $validQuests = [];
        if (class_exists(\jojoe77777\FormAPI\SimpleForm::class)) {
            $form = new \jojoe77777\FormAPI\SimpleForm(function(Player $player, ?int $data) use (&$validQuests) {
                if ($data === null || !isset($validQuests[$data])) return;
                $this->sendEditQuestForm($player, $validQuests[$data]);
            });
            $form->setTitle(TF::GOLD . "Edit Quests");
            foreach ($this->quests as $id => $quest) {
                if ($this->isValidQuest($quest)) {
                    $validQuests[] = $id;
                    $form->addButton("§e{$quest['name']}\n§7{$quest['description']}");
                }
            }
            if (empty($validQuests)) {
                $form->addButton(TF::RED . "No quests available to edit");
            }
            $player->sendForm($form);
        } else {
            $player->sendMessage("§cFormAPI is not installed!");
        }
    }    

    private function sendEditQuestForm(Player $player, string $qid): void {
        if (class_exists(\jojoe77777\FormAPI\CustomForm::class)) {
            $quest = $this->quests[$qid];
            $form = new \jojoe77777\FormAPI\CustomForm(function(Player $player, ?array $data) use ($qid) {
                if ($data === null) return;
                [$name, $desc, $block, $target, $rewardItem, $rewardMessage, $cooldownInput, $action] = $data;
                if ($action === 1) {
                    $player->sendMessage(TF::YELLOW . "Quest edit canceled.");
                    return;
                }
                if (!is_numeric($target)) {
                    $player->sendMessage(TF::RED . "Target must be a number.");
                    return;
                }
                $this->quests[$qid] = [
                    'name' => $name,
                    'description' => $desc,
                    'type' => $this->quests[$qid]['type'],
                    'block' => strtoupper(trim($block)),
                    'target' => (int)$target,
                    'reward' => trim($rewardItem),
                    'rewardMessage' => trim($rewardMessage),
                    'cooldown' => $this->parseDuration($cooldownInput)
                ];
                $this->getConfig()->set('quests', $this->quests);
                $this->getConfig()->save();
                $player->sendMessage(TF::GREEN . "Quest '$name' updated successfully.");
            });
            $form->setTitle("Edit Quest: {$quest['name']}");
            $form->addInput("Name", "", $quest['name']);
            $form->addInput("Description", "", $quest['description']);
            $form->addInput("Block (id/name)", "", $quest['block']);
            $form->addInput("Target Amount", "", (string)$quest['target']);
            $form->addInput("Reward Command", "", $quest['reward']);
            $form->addInput("Reward Message", "", $quest['rewardMessage']);
            $form->addInput("Cooldown (e.g. 1s, 5m, 2h)", "", (string)$quest['cooldown']);
            $form->addDropdown("Action", ["Save", "Cancel"], 0);
            $player->sendForm($form);
        } else {
            $player->sendMessage("§cFormAPI is not installed!");
        }
    }    
    // --- END: Edit Quest Functionality (took alot of tries but finally :D) ---

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $key = strtolower($player->getName());
        if (!isset($this->playerQuestProgress[$key])) return;
        $data = $this->playerQuestProgress[$key];
        $qid = $data['quest'];
        $current = $data['progress'];
        $quest = $this->quests[$qid] ?? null;
        if ($quest === null) return;
        if ($quest['type'] === 'BREAK_BLOCK') {
            $brokeId = $event->getBlock()->getTypeId();
            $targetId = $this->parseBlockId($quest['block']);
            if ($brokeId !== $targetId) return;
            $this->updateProgress($player, $qid, $current + 1);
        }
    }
    private function formatDuration(int $seconds): string {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        $parts = [];
        if ($h > 0) $parts[] = "{$h}h";
        if ($m > 0) $parts[] = "{$m}m";
        if ($s > 0 || empty($parts)) $parts[] = "{$s}s";
        return implode(" ", $parts);
    }
    private function updateProgress(Player $player, string $qid, int $newProgress): void {
        $key = strtolower($player->getName());
        $this->playerQuestProgress[$key]['progress'] = $newProgress;
        $quest = $this->quests[$qid];
        $target = $quest['target'];
        // Immediately show updated progress bar
        $percent = min((int)($newProgress * 100 / max($target, 1)), 100);
        $filled = intdiv($percent, 5);
        $bar = str_repeat("§a▎", $filled) . str_repeat("§7▎", 20 - $filled);
        $player->sendTip(
            TF::YELLOW . "{$quest['name']}: $bar §r $newProgress/$target ($percent%)"
        );
        // Check completion
        if ($newProgress >= $target) {
            $player->sendMessage(TF::GREEN . "Quest '{$quest['name']}' completed!");
            $this->giveReward($player, $quest['reward'], $quest['rewardMessage']);
            // Record completion time for cooldown
            $this->lastCompleted[$key][$qid] = time();
            unset($this->playerQuestProgress[$key]);
        }
    }

    private function parseBlockId(string $alias): int {
        $item = StringToItemParser::getInstance()->parse($alias);
        if ($item !== null) {
            $block = $item->getBlock();
            return $block !== null ? $block->getTypeId() : 0;
        }
        return 0;
    }

    private function giveReward(Player $player, string $reward, string $rewardMessage): void {
        $parts = preg_split('/\s+/', trim($reward));
        $alias = strtolower($parts[0]);
        $count = isset($parts[1]) ? (int)$parts[1] : 1;
        $item = StringToItemParser::getInstance()->parse($alias);
        if ($item !== null) {
            $item = $item->setCount($count);
            $player->getInventory()->addItem($item);
            $player->sendMessage(TF::AQUA . "You got {$count}x {$parts[0]}!");
        } else {
            $command = str_replace("{player}", $player->getName(), $reward);
            $this->getServer()->dispatchCommand($this->consoleSender, $command);
        }
        $player->sendMessage(TF::AQUA . $rewardMessage);
    }

    private function startQuest(Player $player, string $qid): void {
        $key = strtolower($player->getName());
        $quest = $this->quests[$qid];
        // Check cooldown
        $now  = time();
        $last = $this->lastCompleted[$key][$qid] ?? 0;
        $cd   = $quest['cooldown'];
        if ($cd > 0 && $now < $last + $cd) {
            $remaining = ($last + $cd) - $now;
            $formatted = $this->formatDuration($remaining);
            $player->sendMessage(TF::RED . "You must wait {$formatted} before doing this quest again.");        
            return;
        }
        
        $this->playerQuestProgress[$key] = ['quest' => $qid, 'progress' => 0];
        $player->sendMessage(TF::GREEN . "Started quest: " . $quest['name']);
    }

    private function isValidQuest(array $quest): bool {
        return isset(
            $quest['name'],
            $quest['description'],
            $quest['type'],
            $quest['block'],
            $quest['target'],
            $quest['reward'],
            $quest['rewardMessage'],
            $quest['cooldown']
        );
    }

    /**
     * Parse duration strings like "1s", "5m", "2h" into seconds, way easier to read :V
     */
    private function parseDuration(string $input): int {
        if (preg_match('/^(\d+)\s*([smh])$/i', trim($input), $m)) {
            $value = (int)$m[1];
            switch (strtolower($m[2])) {
                case 's': return $value * 1; 
                case 'm': return $value *60;
                case 'h': return $value * 60 * 60;
            }
        }
        // Fallback: if numeric, treat as seconds
        return is_numeric($input) ? (int)$input : 0;
    }
}
