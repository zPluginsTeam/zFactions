<?php

declare(strict_types=1);

namespace zPlugins\Factions\API;

use pocketmine\player\Player;
use pocketmine\world\Position;
use zPlugins\Factions\Main;
use zPlugins\Factions\Data\Faction;
use zPlugins\Factions\Data\Claim;
use zPlugins\Factions\Utils\Rank;

/**
 * FactionsAPI - Main API class for interacting with the Factions plugin
 * 
 * This class provides static methods for other plugins to interact with
 * the Factions plugin functionality.
 */
class FactionsAPI {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Get a faction by name
     */
    public static function getFactionByName(string $name): ?Faction {
        return Main::getInstance()->getFactionManager()->getFaction($name);
    }

    /**
     * Get a player's faction
     */
    public static function getFactionByPlayer(Player $player): ?Faction {
        return Main::getInstance()->getFactionManager()->getPlayerFaction($player);
    }

    /**
     * Get all factions
     */
    public static function getAllFactions(): array {
        return Main::getInstance()->getFactionManager()->getAllFactions();
    }

    /**
     * Create a new faction
     */
    public static function createFaction(Player $leader, string $name): ?Faction {
        $factionManager = Main::getInstance()->getFactionManager();
        
        // Check if faction already exists
        if ($factionManager->getFaction($name)) {
            return null;
        }
        
        // Check if player is already in a faction
        if ($factionManager->getPlayerFaction($leader)) {
            return null;
        }
        
        return $factionManager->createFaction($name, $leader);
    }

    /**
     * Disband a faction
     */
    public static function disbandFaction(Faction $faction): bool {
        try {
            Main::getInstance()->getFactionManager()->disbandFaction($faction);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Add a player to a faction
     */
    public static function addPlayerToFaction(Faction $faction, Player $player, ?Rank $rank = null): bool {
        $factionManager = Main::getInstance()->getFactionManager();
        
        // Check if player is already in a faction
        if ($factionManager->getPlayerFaction($player)) {
            return false;
        }
        
        $rank = $rank ?? Rank::MEMBER();
        $factionManager->addPlayerToFaction($faction, $player, $rank);
        return true;
    }

    /**
     * Remove a player from their faction
     */
    public static function removePlayerFromFaction(Player $player): bool {
        $factionManager = Main::getInstance()->getFactionManager();
        $faction = $factionManager->getPlayerFaction($player);
        
        if (!$faction) {
            return false;
        }
        
        $factionManager->removePlayerFromFaction($faction, $player);
        return true;
    }

    /**
     * Get a player's rank in their faction
     */
    public static function getPlayerRank(Player $player): ?Rank {
        return Main::getInstance()->getFactionManager()->getPlayerRank($player);
    }

    /**
     * Set a player's rank in their faction
     */
    public static function setPlayerRank(Player $player, Rank $rank): bool {
        $factionManager = Main::getInstance()->getFactionManager();
        $faction = $factionManager->getPlayerFaction($player);
        
        if (!$faction) {
            return false;
        }
        
        $member = $faction->getMember($player->getName());
        if (!$member) {
            return false;
        }
        
        $member->setRank($rank);
        $factionManager->saveAll();
        return true;
    }

    /**
     * Check if a chunk is claimed
     */
    public static function isChunkClaimed(int $chunkX, int $chunkZ, string $world): bool {
        $claim = Main::getInstance()->getClaimManager()->getClaimAt($chunkX, $chunkZ, $world);
        return $claim !== null;
    }

    /**
     * Get the claim at specific coordinates
     */
    public static function getClaimAt(int $chunkX, int $chunkZ, string $world): ?Claim {
        return Main::getInstance()->getClaimManager()->getClaimAt($chunkX, $chunkZ, $world);
    }

    /**
     * Claim a chunk for a faction
     */
    public static function claimChunk(Faction $faction, int $chunkX, int $chunkZ, string $world): bool {
        $claimManager = Main::getInstance()->getClaimManager();
        
        if (!$claimManager->canClaim($chunkX, $chunkZ, $world, $faction->getName())) {
            return false;
        }
        
        $claimManager->createClaim($chunkX, $chunkZ, $world, $faction->getName());
        return true;
    }

    /**
     * Unclaim a chunk
     */
    public static function unclaimChunk(int $chunkX, int $chunkZ, string $world): bool {
        return Main::getInstance()->getClaimManager()->removeClaim($chunkX, $chunkZ, $world);
    }

    /**
     * Get all claims for a faction
     */
    public static function getFactionClaims(Faction $faction): array {
        return Main::getInstance()->getClaimManager()->getFactionClaims($faction->getName());
    }

    /**
     * Get faction claim count
     */
    public static function getFactionClaimCount(Faction $faction): int {
        return Main::getInstance()->getClaimManager()->getFactionClaimCount($faction->getName());
    }

    /**
     * Get a player's power/strength
     */
    public static function getPlayerPower(Player $player): int {
        return Main::getInstance()->getFactionManager()->getPlayerPower($player);
    }

    /**
     * Add power to a player
     */
    public static function addPlayerPower(Player $player, int $amount): void {
        Main::getInstance()->getFactionManager()->addPlayerPower($player, $amount);
    }

    /**
     * Get faction total power
     */
    public static function getFactionPower(Faction $faction): int {
        return $faction->getPower();
    }

    /**
     * Get faction maximum power
     */
    public static function getFactionMaxPower(Faction $faction): int {
        return $faction->getMaxPower();
    }

    /**
     * Check if two factions are allies
     */
    public static function areAllies(Faction $faction1, Faction $faction2): bool {
        return $faction1->isAlly($faction2->getName());
    }

    /**
     * Check if two factions are enemies
     */
    public static function areEnemies(Faction $faction1, Faction $faction2): bool {
        return $faction1->isEnemy($faction2->getName());
    }

    /**
     * Create an alliance between two factions
     */
    public static function createAlliance(Faction $faction1, Faction $faction2): void {
        $faction1->addAlly($faction2->getName());
        $faction2->addAlly($faction1->getName());
        Main::getInstance()->getFactionManager()->saveAll();
    }

    /**
     * Break an alliance between two factions
     */
    public static function breakAlliance(Faction $faction1, Faction $faction2): void {
        Main::getInstance()->getFactionManager()->removeAlliance($faction1, $faction2->getName());
    }

    /**
     * Declare war between two factions
     */
    public static function declareWar(Faction $faction1, Faction $faction2): void {
        Main::getInstance()->getFactionManager()->declareEnemy($faction1, $faction2);
    }

    /**
     * Get faction bank balance
     */
    public static function getFactionBankBalance(Faction $faction): float {
        return $faction->getBankBalance();
    }

    /**
     * Add money to faction bank
     */
    public static function addToFactionBank(Faction $faction, float $amount): void {
        $faction->addBankMoney($amount);
        Main::getInstance()->getFactionManager()->saveAll();
    }

    /**
     * Remove money from faction bank
     */
    public static function removeFromFactionBank(Faction $faction, float $amount): bool {
        if ($faction->subtractBankMoney($amount)) {
            Main::getInstance()->getFactionManager()->saveAll();
            return true;
        }
        return false;
    }

    /**
     * Get faction home location
     */
    public static function getFactionHome(Faction $faction): ?Position {
        return $faction->getHome();
    }

    /**
     * Set faction home location
     */
    public static function setFactionHome(Faction $faction, Position $position): void {
        $faction->setHome($position);
        Main::getInstance()->getFactionManager()->saveAll();
    }

    /**
     * Check if a player can build at a location
     */
    public static function canPlayerBuild(Player $player, Position $position): bool {
        $chunkX = $position->getFloorX() >> 4;
        $chunkZ = $position->getFloorZ() >> 4;
        $world = $position->getWorld()->getFolderName();
        
        $claim = Main::getInstance()->getClaimManager()->getClaimAt($chunkX, $chunkZ, $world);
        
        if (!$claim) {
            return true; // Wilderness, anyone can build
        }
        
        $playerFaction = Main::getInstance()->getFactionManager()->getPlayerFaction($player);
        $claimFaction = Main::getInstance()->getFactionManager()->getFaction($claim->getFactionName());
        
        if (!$claimFaction) {
            return true; // Claim faction doesn't exist
        }
        
        // Allow if player is in the same faction
        if ($playerFaction && $playerFaction->getName() === $claimFaction->getName()) {
            return true;
        }
        
        // Allow if player is ally (configurable)
        if ($playerFaction && $claimFaction->isAlly($playerFaction->getName())) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if PvP is allowed at a location
     */
    public static function isPvpAllowed(Position $position): bool {
        $chunkX = $position->getFloorX() >> 4;
        $chunkZ = $position->getFloorZ() >> 4;
        $world = $position->getWorld()->getFolderName();
        
        $claim = Main::getInstance()->getClaimManager()->getClaimAt($chunkX, $chunkZ, $world);
        
        if (!$claim) {
            return Main::getInstance()->getConfig()->getNested("pvp.enable-pvp-wilderness", true);
        }
        
        // PvP restrictions in claimed territory depend on configuration
        return !Main::getInstance()->getConfig()->getNested("pvp.disable-pvp-own-land", true);
    }

    /**
     * Get online members of a faction
     */
    public static function getOnlineMembers(Faction $faction): array {
        return $faction->getOnlineMembers();
    }

    /**
     * Get total member count of a faction
     */
    public static function getMemberCount(Faction $faction): int {
        return $faction->getMemberCount();
    }

    /**
     * Get online member count of a faction
     */
    public static function getOnlineMemberCount(Faction $faction): int {
        return $faction->getOnlineMemberCount();
    }

    /**
     * Check if the economy system is enabled
     */
    public static function isEconomyEnabled(): bool {
        return Main::getInstance()->getEconomyManager()->isEconomyEnabled();
    }

    /**
     * Get the cost to claim land
     */
    public static function getClaimCost(): string {
        return Main::getInstance()->getEconomyManager()->getClaimCostDisplay();
    }

    /**
     * Get the cost to overclaim land
     */
    public static function getOverclaimCost(): string {
        return Main::getInstance()->getEconomyManager()->getOverclaimCostDisplay();
    }

    /**
     * Get total faction count
     */
    public static function getTotalFactionCount(): int {
        return Main::getInstance()->getFactionManager()->getFactionCount();
    }

    /**
     * Get count of factions with online members
     */
    public static function getActiveFactionCount(): int {
        return Main::getInstance()->getFactionManager()->getOnlineFactionsCount();
    }

    /**
     * Get total claim count across all factions
     */
    public static function getTotalClaimCount(): int {
        return Main::getInstance()->getClaimManager()->getTotalClaimsCount();
    }

    /**
     * Check if a player has a pending invitation to a faction
     */
    public static function hasInvitation(Player $player, Faction $faction): bool {
        return Main::getInstance()->getFactionManager()->hasInvitation($faction, $player);
    }

    /**
     * Send an invitation to a player
     */
    public static function invitePlayer(Faction $faction, Player $player): void {
        Main::getInstance()->getFactionManager()->invitePlayer($faction, $player);
    }

    /**
     * Remove an invitation
     */
    public static function removeInvitation(Player $player, Faction $faction): void {
        Main::getInstance()->getFactionManager()->removeInvitation($faction, $player);
    }

    /**
     * Get faction by claim location
     */
    public static function getFactionByClaim(int $chunkX, int $chunkZ, string $world): ?Faction {
        $claim = Main::getInstance()->getClaimManager()->getClaimAt($chunkX, $chunkZ, $world);
        if (!$claim) {
            return null;
        }
        
        return Main::getInstance()->getFactionManager()->getFaction($claim->getFactionName());
    }

    /**
     * Transfer ownership of a claim to another faction
     */
    public static function transferClaim(int $chunkX, int $chunkZ, string $world, Faction $newFaction): bool {
        return Main::getInstance()->getClaimManager()->transferClaim($chunkX, $chunkZ, $world, $newFaction->getName());
    }

    /**
     * Check if claiming is allowed in a world
     */
    public static function isClaimingAllowedInWorld(string $world): bool {
        $disabledWorlds = Main::getInstance()->getConfig()->getNested("claiming.disabled-worlds", []);
        return !in_array($world, $disabledWorlds);
    }

    /**
     * Get claims in a radius around coordinates
     */
    public static function getClaimsInRadius(int $centerX, int $centerZ, string $world, int $radius): array {
        return Main::getInstance()->getClaimManager()->getClaimsInRadius($centerX, $centerZ, $world, $radius);
    }

    /**
     * Get all claims in a world
     */
    public static function getClaimsInWorld(string $world): array {
        return Main::getInstance()->getClaimManager()->getClaimsInWorld($world);
    }

    /**
     * Check if a faction can afford to claim land
     */
    public static function canAffordClaim(Faction $faction, Player $player): bool {
        return Main::getInstance()->getEconomyManager()->canAffordClaim($faction, $player);
    }

    /**
     * Check if a faction can afford to overclaim land
     */
    public static function canAffordOverclaim(Faction $faction, Player $player): bool {
        return Main::getInstance()->getEconomyManager()->canAffordOverclaim($faction, $player);
    }

    /**
     * Get the main plugin instance
     */
    public static function getPlugin(): Main {
        return Main::getInstance();
    }
}
