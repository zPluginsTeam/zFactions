<?php

declare(strict_types=1);

namespace zPlugins\Factions;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\event\Listener;
use pocketmine\event\Cancellable;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

use zPlugins\Factions\Commands\FactionCommand;
use zPlugins\Factions\Manager\FactionManager;
use zPlugins\Factions\Manager\ClaimManager;
use zPlugins\Factions\Manager\EconomyManager;
use zPlugins\Factions\API\FactionsAPI;
use zPlugins\Factions\Utils\Rank;
use zPlugins\Factions\Data\Faction;

class Main extends PluginBase implements Listener {

    private static Main $instance;
    
    private FactionManager $factionManager;
    private ClaimManager $claimManager;
    private EconomyManager $economyManager;
    private FactionsAPI $api;
    
    private Config $messages;
    private array $chatModes = [];
    private array $flyCooldowns = [];
    private array $homeCooldowns = [];

    public function onEnable(): void {
        self::$instance = $this;
        
        // Save default configuration files
        $this->saveDefaultConfig();
        $this->saveResource("messages.yml");
        
        // Load configuration
        $this->loadConfiguration();
        
        // Initialize managers
        $this->factionManager = new FactionManager($this);
        $this->claimManager = new ClaimManager($this);
        $this->economyManager = new EconomyManager($this);
        $this->api = new FactionsAPI($this);
        
        // Register commands
        $this->getServer()->getCommandMap()->register("zfactions", new FactionCommand($this));
        
        // Register events
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        // Check for BedrockEconomy dependency
        if (!$this->getServer()->getPluginManager()->getPlugin("BedrockEconomy")) {
            $this->getLogger()->warning("BedrockEconomy plugin not found! Economy features will be disabled.");
        }
        
        $this->getLogger()->info("zPlugins Factions v" . $this->getDescription()->getVersion() . " enabled!");
    }
    
    public function onDisable(): void {
        $this->factionManager->saveAll();
        $this->getLogger()->info("zPlugins Factions disabled!");
    }
    
    private function loadConfiguration(): void {
        $this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
    }
    
    public static function getInstance(): Main {
        return self::$instance;
    }
    
    public function getFactionManager(): FactionManager {
        return $this->factionManager;
    }
    
    public function getClaimManager(): ClaimManager {
        return $this->claimManager;
    }
    
    public function getEconomyManager(): EconomyManager {
        return $this->economyManager;
    }
    
    public function getAPI(): FactionsAPI {
        return $this->api;
    }
    
    public function getMessage(string $key, array $params = []): string {
        $message = $this->messages->getNested($key, $key);
        
        foreach ($params as $param => $value) {
            $message = str_replace("{" . $param . "}", (string)$value, $message);
        }
        
        return TextFormat::colorize($this->messages->get("prefix", "§8[§6zFactions§8]§r ") . $message);
    }
    
    /**
     * Event Handlers
     */
    
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $this->factionManager->initializePlayer($player);
        
        // Update faction display if enabled
        if ($this->getConfig()->getNested("display.show-faction-tags", true)) {
            $this->updatePlayerDisplayName($player);
        }
    }
    
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Clean up arrays
        unset($this->chatModes[$playerName]);
        unset($this->flyCooldowns[$playerName]);
        unset($this->homeCooldowns[$playerName]);
        
        // Disable fly if enabled
        if ($player->getAllowFlight()) {
            $player->setFlying(false);
            $player->setAllowFlight(false);
        }
    }
    
    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        $cause = $player->getLastDamageCause();
        
        // Handle power loss on death
        $deathPenalty = $this->getConfig()->getNested("strength.death-penalty", -10);
        $this->factionManager->addPlayerPower($player, $deathPenalty);
        
        if ($deathPenalty < 0) {
            $player->sendMessage($this->getMessage("power.lost", [
                "amount" => abs($deathPenalty)
            ]));
        }
        
        // Handle kill rewards
        if ($cause instanceof EntityDamageByEntityEvent) {
            $damager = $cause->getDamager();
            if ($damager instanceof Player) {
                $killReward = $this->getConfig()->getNested("strength.kill-reward", 5);
                $this->factionManager->addPlayerPower($damager, $killReward);
                
                if ($killReward > 0) {
                    $damager->sendMessage($this->getMessage("power.gained", [
                        "amount" => $killReward,
                        "player" => $player->getName()
                    ]));
                }
            }
        }
        
        // Disable fly on death
        if ($player->getAllowFlight()) {
            $player->setFlying(false);
            $player->setAllowFlight(false);
            $player->sendMessage($this->getMessage("fly.disabled"));
        }
    }
    
    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $from = $event->getFrom();
        $to = $event->getTo();
        
        // Check if player moved to different chunk
        if ($from->getFloorX() >> 4 !== $to->getFloorX() >> 4 || 
            $from->getFloorZ() >> 4 !== $to->getFloorZ() >> 4) {
            
            $this->handleChunkChange($player, $to);
        }
    }
    
    private function handleChunkChange(Player $player, $position): void {
        $chunkX = $position->getFloorX() >> 4;
        $chunkZ = $position->getFloorZ() >> 4;
        $world = $position->getWorld();
        
        $claim = $this->claimManager->getClaimAt($chunkX, $chunkZ, $world->getFolderName());
        $playerFaction = $this->factionManager->getPlayerFaction($player);
        
        // Handle faction fly
        if ($this->getConfig()->getNested("fly.enabled", true) && $player->getAllowFlight()) {
            $canFlyHere = false;
            
            if ($claim && $playerFaction && $claim->getFactionName() === $playerFaction->getName()) {
                $canFlyHere = true;
            }
            
            if (!$canFlyHere && $this->getConfig()->getNested("fly.disable-on-leave", true)) {
                $player->setFlying(false);
                $player->setAllowFlight(false);
                $player->sendMessage($this->getMessage("fly.disabled-left-territory"));
            }
        }
    }
    
    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Handle faction chat modes
        if (isset($this->chatModes[$playerName])) {
            $mode = $this->chatModes[$playerName];
            $faction = $this->factionManager->getPlayerFaction($player);
            
            if (!$faction) {
                unset($this->chatModes[$playerName]);
                return;
            }
            
            $event->cancel();
            
            if ($mode === "faction") {
                $this->sendFactionMessage($player, $faction, $event->getMessage());
            } elseif ($mode === "ally") {
                $this->sendAllyMessage($player, $faction, $event->getMessage());
            }
        }
    }
    
    private function sendFactionMessage(Player $player, Faction $faction, string $message): void {
        $format = $this->getConfig()->getNested("chat.faction-chat-format", "§6[FC] §r{rank}{player}: {message}");
        $rank = $this->factionManager->getPlayerRank($player);
        
        $formattedMessage = str_replace([
            "{rank}", "{player}", "{message}"
        ], [
            $rank->getPrefix(), $player->getName(), $message
        ], $format);
        
        foreach ($faction->getOnlineMembers() as $member) {
            $member->sendMessage(TextFormat::colorize($formattedMessage));
        }
    }
    
    private function sendAllyMessage(Player $player, Faction $faction, string $message): void {
        $format = $this->getConfig()->getNested("chat.ally-chat-format", "§a[AC] §r{faction} {player}: {message}");
        
        $formattedMessage = str_replace([
            "{faction}", "{player}", "{message}"
        ], [
            $faction->getName(), $player->getName(), $message
        ], $format);
        
        // Send to faction members
        foreach ($faction->getOnlineMembers() as $member) {
            $member->sendMessage(TextFormat::colorize($formattedMessage));
        }
        
        // Send to ally factions
        foreach ($faction->getAllies() as $allyName) {
            $ally = $this->factionManager->getFaction($allyName);
            if ($ally) {
                foreach ($ally->getOnlineMembers() as $member) {
                    $member->sendMessage(TextFormat::colorize($formattedMessage));
                }
            }
        }
    }
    
    public function onEntityDamage(EntityDamageByEntityEvent $event): void {
        $entity = $event->getEntity();
        $damager = $event->getDamager();
        
        if (!$entity instanceof Player || !$damager instanceof Player) {
            return;
        }
        
        // Disable fly on damage
        if ($this->getConfig()->getNested("fly.disable-on-damage", true) && $entity->getAllowFlight()) {
            $entity->setFlying(false);
            $entity->setAllowFlight(false);
            $entity->sendMessage($this->getMessage("fly.disabled-damage"));
        }
        
        // PvP protection checks
        $entityFaction = $this->factionManager->getPlayerFaction($entity);
        $damagerFaction = $this->factionManager->getPlayerFaction($damager);
        
        if ($entityFaction && $damagerFaction) {
            // Same faction protection
            if ($entityFaction->getName() === $damagerFaction->getName()) {
                $event->cancel();
                return;
            }
            
            // Ally protection
            if ($entityFaction->isAlly($damagerFaction->getName())) {
                $event->cancel();
                return;
            }
        }
        
        // Territory-based PvP protection
        $position = $entity->getPosition();
        $claim = $this->claimManager->getClaimAt(
            $position->getFloorX() >> 4,
            $position->getFloorZ() >> 4,
            $position->getWorld()->getFolderName()
        );
        
        if ($claim) {
            $claimFaction = $this->factionManager->getFaction($claim->getFactionName());
            
            if ($claimFaction) {
                // Disable PvP in own land
                if ($this->getConfig()->getNested("pvp.disable-pvp-own-land", true)) {
                    if (($entityFaction && $entityFaction->getName() === $claimFaction->getName()) ||
                        ($damagerFaction && $damagerFaction->getName() === $claimFaction->getName())) {
                        $event->cancel();
                        return;
                    }
                }
                
                // Disable PvP in ally land
                if ($this->getConfig()->getNested("pvp.disable-pvp-ally-land", true)) {
                    if (($entityFaction && $claimFaction->isAlly($entityFaction->getName())) ||
                        ($damagerFaction && $claimFaction->isAlly($damagerFaction->getName()))) {
                        $event->cancel();
                        return;
                    }
                }
            }
        }
    }
    
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $blockPosition = $event->getBlock()->getPosition();
        $this->handleBlockAction($player, $blockPosition, $event);
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();

        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            $this->handleBlockAction($player, $block->getPosition(), $event);
        }
    }

    private function handleBlockAction(Player $player, Position $position, Cancellable $event): void {
        $claim = $this->claimManager->getClaimAt(
            $position->getFloorX() >> 4,
            $position->getFloorZ() >> 4,
            $position->getWorld()->getFolderName()
        );

        if (!$claim) {
            return; // No claim, allow
        }

        $playerFaction = $this->factionManager->getPlayerFaction($player);
        $claimFaction = $this->factionManager->getFaction($claim->getFactionName());

        if (!$claimFaction) {
            return; // Claim exists but faction doesn't, allow
        }

        // Allow if same faction
        if ($playerFaction && $playerFaction->getName() === $claimFaction->getName()) {
            return;
        }

        // Allow if ally
        if ($playerFaction && $claimFaction->isAlly($playerFaction->getName())) {
            return;
        }

        // Deny action
        $event->cancel();
        $player->sendMessage($this->getMessage("errors.no-permission"));
    }

    
    private function updatePlayerDisplayName(Player $player): void {
        $faction = $this->factionManager->getPlayerFaction($player);
        
        if ($faction && $this->getConfig()->getNested("display.show-faction-tags", true)) {
            $format = $this->getConfig()->getNested("display.faction-tag-format", "[{faction}]");
            $tag = str_replace("{faction}", $faction->getName(), $format);
            
            if ($this->getConfig()->getNested("display.show-rank-tags", false)) {
                $rank = $this->factionManager->getPlayerRank($player);
                $rankFormat = $this->getConfig()->getNested("display.rank-tag-format", "{prefix}");
                $rankTag = str_replace("{prefix}", $rank->getPrefix(), $rankFormat);
                $tag = $rankTag . " " . $tag;
            }
            
            $player->setDisplayName(TextFormat::colorize($tag . " " . $player->getName()));
        } else {
            $player->setDisplayName($player->getName());
        }
    }
    
    /**
     * Utility methods for commands
     */
    
    public function setChatMode(Player $player, string $mode): void {
        $this->chatModes[$player->getName()] = $mode;
    }
    
    public function getChatMode(Player $player): ?string {
        return $this->chatModes[$player->getName()] ?? null;
    }
    
    public function removeChatMode(Player $player): void {
        unset($this->chatModes[$player->getName()]);
    }
    
    public function setFlyCooldown(Player $player, int $seconds): void {
        $this->flyCooldowns[$player->getName()] = time() + $seconds;
    }
    
    public function getFlyCooldown(Player $player): int {
        $cooldown = $this->flyCooldowns[$player->getName()] ?? 0;
        return max(0, $cooldown - time());
    }
    
    public function setHomeCooldown(Player $player, int $seconds): void {
        $this->homeCooldowns[$player->getName()] = time() + $seconds;
    }
    
    public function getHomeCooldown(Player $player): int {
        $cooldown = $this->homeCooldowns[$player->getName()] ?? 0;
        return max(0, $cooldown - time());
    }
}
