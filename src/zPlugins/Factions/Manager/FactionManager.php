<?php

declare(strict_types=1);

namespace zPlugins\Factions\Manager;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use zPlugins\Factions\Main;
use zPlugins\Factions\Data\Faction;
use zPlugins\Factions\Data\FactionMember;
use zPlugins\Factions\Utils\Rank;
use zPlugins\Factions\Events\FactionCreateEvent;
use zPlugins\Factions\Events\FactionJoinEvent;
use zPlugins\Factions\Events\FactionLeaveEvent;
use zPlugins\Factions\Events\PowerChangeEvent;

class FactionManager {

    private Main $plugin;
    private array $factions = [];
    private array $playerFactions = [];
    private array $playerPower = [];
    private array $invitations = [];
    private array $allyRequests = [];
    private Config $dataConfig;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->loadData();
    }

    private function loadData(): void {
        $this->dataConfig = new Config($this->plugin->getDataFolder() . "factions.yml", Config::YAML);
        
        // Load factions
        $factionsData = $this->dataConfig->get("factions", []);
        foreach ($factionsData as $name => $data) {
            $faction = new Faction($name);
            $faction->fromArray($data);
            $this->factions[$name] = $faction;
            
            // Map players to factions
            foreach ($faction->getMembers() as $member) {
                $this->playerFactions[$member->getPlayerName()] = $name;
            }
        }
        
        // Load player power
        $this->playerPower = $this->dataConfig->get("player_power", []);
    }

    public function saveAll(): void {
        $factionsData = [];
        foreach ($this->factions as $name => $faction) {
            $factionsData[$name] = $faction->toArray();
        }
        
        $this->dataConfig->set("factions", $factionsData);
        $this->dataConfig->set("player_power", $this->playerPower);
        $this->dataConfig->save();
    }

    public function initializePlayer(Player $player): void {
        $playerName = $player->getName();
        if (!isset($this->playerPower[$playerName])) {
            $this->playerPower[$playerName] = $this->plugin->getConfig()->getNested("strength.starting-strength", 10);
        }
    }

    public function createFaction(string $name, Player $leader): Faction {
        $faction = new Faction($name);
        $faction->setLeader($leader->getName());
        
        $leaderMember = new FactionMember($leader->getName(), Rank::LEADER());
        $faction->addMember($leaderMember);
        
        $this->factions[$name] = $faction;
        $this->playerFactions[$leader->getName()] = $name;
        
        // Fire event
        $event = new FactionCreateEvent($faction, $leader);
        $event->call();
        
        $this->saveAll();
        return $faction;
    }

    public function disbandFaction(Faction $faction): void {
        $factionName = $faction->getName();
        
        // Remove all player mappings
        foreach ($faction->getMembers() as $member) {
            unset($this->playerFactions[$member->getPlayerName()]);
        }
        
        // Remove all claims
        $this->plugin->getClaimManager()->removeAllClaims($factionName);
        
        // Remove faction
        unset($this->factions[$factionName]);
        
        $this->saveAll();
    }

    public function getFaction(string $name): ?Faction {
        return $this->factions[$name] ?? null;
    }

    public function getPlayerFaction(Player $player): ?Faction {
        $factionName = $this->playerFactions[$player->getName()] ?? null;
        return $factionName ? $this->getFaction($factionName) : null;
    }

    public function addPlayerToFaction(Faction $faction, Player $player, Rank $rank): void {
        $member = new FactionMember($player->getName(), $rank);
        $faction->addMember($member);
        $this->playerFactions[$player->getName()] = $faction->getName();
        
        // Fire event
        $event = new FactionJoinEvent($faction, $player);
        $event->call();
        
        $this->saveAll();
    }

    public function removePlayerFromFaction(Faction $faction, Player $player): void {
        $faction->removeMember($player->getName());
        unset($this->playerFactions[$player->getName()]);
        
        // Fire event
        $event = new FactionLeaveEvent($faction, $player);
        $event->call();
        
        // Disband if no members left
        if (count($faction->getMembers()) === 0) {
            $this->disbandFaction($faction);
        } else {
            $this->saveAll();
        }
    }

    public function getPlayerRank(Player $player): ?Rank {
        $faction = $this->getPlayerFaction($player);
        if (!$faction) {
            return null;
        }
        
        $member = $faction->getMember($player->getName());
        return $member ? $member->getRank() : null;
    }

    public function getMemberRank(Faction $faction, string $playerName): ?Rank {
        $member = $faction->getMember($playerName);
        return $member ? $member->getRank() : null;
    }

    public function promoteMember(Faction $faction, string $playerName): bool {
        $member = $faction->getMember($playerName);
        if (!$member) {
            return false;
        }
        
        $currentRank = $member->getRank();
        $newRank = $this->getNextRank($currentRank);
        
        if ($newRank && $newRank->getLevel() > $currentRank->getLevel()) {
            $member->setRank($newRank);
            $this->saveAll();
            return true;
        }
        
        return false;
    }

    public function demoteMember(Faction $faction, string $playerName): bool {
        $member = $faction->getMember($playerName);
        if (!$member) {
            return false;
        }
        
        $currentRank = $member->getRank();
        $newRank = $this->getPreviousRank($currentRank);
        
        if ($newRank && $newRank->getLevel() < $currentRank->getLevel()) {
            $member->setRank($newRank);
            $this->saveAll();
            return true;
        }
        
        return false;
    }

    private function getNextRank(Rank $currentRank): ?Rank {
        $ranks = [
            Rank::RECRUIT(),
            Rank::MEMBER(),
            Rank::OFFICER(),
            Rank::COLEADER(),
            Rank::LEADER()
        ];
        
        $currentLevel = $currentRank->getLevel();
        foreach ($ranks as $rank) {
            if ($rank->getLevel() === $currentLevel + 1) {
                return $rank;
            }
        }
        
        return null;
    }

    private function getPreviousRank(Rank $currentRank): ?Rank {
        $ranks = [
            Rank::RECRUIT(),
            Rank::MEMBER(),
            Rank::OFFICER(),
            Rank::COLEADER(),
            Rank::LEADER()
        ];
        
        $currentLevel = $currentRank->getLevel();
        foreach ($ranks as $rank) {
            if ($rank->getLevel() === $currentLevel - 1) {
                return $rank;
            }
        }
        
        return null;
    }

    public function invitePlayer(Faction $faction, Player $player): void {
        $factionName = $faction->getName();
        $playerName = $player->getName();
        
        if (!isset($this->invitations[$playerName])) {
            $this->invitations[$playerName] = [];
        }
        
        $this->invitations[$playerName][$factionName] = time() + 300; // 5 minute expiry
    }

    public function hasInvitation(Faction $faction, Player $player): bool {
        $factionName = $faction->getName();
        $playerName = $player->getName();
        
        if (!isset($this->invitations[$playerName][$factionName])) {
            return false;
        }
        
        // Check if expired
        if ($this->invitations[$playerName][$factionName] < time()) {
            unset($this->invitations[$playerName][$factionName]);
            return false;
        }
        
        return true;
    }

    public function removeInvitation(Faction $faction, Player $player): void {
        $factionName = $faction->getName();
        $playerName = $player->getName();
        
        unset($this->invitations[$playerName][$factionName]);
    }

    public function getPlayerPower(Player $player): int {
        return $this->playerPower[$player->getName()] ?? $this->plugin->getConfig()->getNested("strength.starting-strength", 10);
    }

    public function addPlayerPower(Player $player, int $amount): void {
        $playerName = $player->getName();
        $currentPower = $this->getPlayerPower($player);
        $newPower = $currentPower + $amount;
        
        $maxPower = $this->plugin->getConfig()->getNested("strength.max-per-player", 100);
        $newPower = min($newPower, $maxPower);
        $newPower = max($newPower, 0); // Don't go below 0
        
        $this->playerPower[$playerName] = $newPower;
        
        // Fire power change event
        $faction = $this->getPlayerFaction($player);
        if ($faction) {
            $event = new PowerChangeEvent($faction, $currentPower, $newPower);
            $event->call();
        }
        
        $this->saveAll();
    }

    public function sendAllyRequest(Faction $fromFaction, Faction $toFaction): void {
        $fromName = $fromFaction->getName();
        $toName = $toFaction->getName();
        
        if (!isset($this->allyRequests[$toName])) {
            $this->allyRequests[$toName] = [];
        }
        
        $this->allyRequests[$toName][$fromName] = time() + 600; // 10 minute expiry
    }

    public function acceptAllyRequest(Faction $faction, Faction $requestingFaction): bool {
        $factionName = $faction->getName();
        $requestingName = $requestingFaction->getName();
        
        if (!isset($this->allyRequests[$factionName][$requestingName])) {
            return false;
        }
        
        // Check if expired
        if ($this->allyRequests[$factionName][$requestingName] < time()) {
            unset($this->allyRequests[$factionName][$requestingName]);
            return false;
        }
        
        // Create alliance
        $faction->addAlly($requestingName);
        $requestingFaction->addAlly($factionName);
        
        // Remove ally request
        unset($this->allyRequests[$factionName][$requestingName]);
        
        $this->saveAll();
        return true;
    }

    public function denyAllyRequest(Faction $faction, string $requestingFactionName): void {
        $factionName = $faction->getName();
        unset($this->allyRequests[$factionName][$requestingFactionName]);
    }

    public function removeAlliance(Faction $faction, string $allyFactionName): void {
        $faction->removeAlly($allyFactionName);
        
        $allyFaction = $this->getFaction($allyFactionName);
        if ($allyFaction) {
            $allyFaction->removeAlly($faction->getName());
        }
        
        $this->saveAll();
    }

    public function declareEnemy(Faction $faction, Faction $enemyFaction): void {
        $factionName = $faction->getName();
        $enemyName = $enemyFaction->getName();
        
        // Remove alliance if exists
        if ($faction->isAlly($enemyName)) {
            $this->removeAlliance($faction, $enemyName);
        }
        
        // Add as enemy
        $faction->addEnemy($enemyName);
        $enemyFaction->addEnemy($factionName);
        
        $this->saveAll();
    }

    public function renameFaction(Faction $faction, string $newName): void {
        $oldName = $faction->getName();
        
        // Update faction name
        $faction->setName($newName);
        
        // Update factions array
        unset($this->factions[$oldName]);
        $this->factions[$newName] = $faction;
        
        // Update player mappings
        foreach ($faction->getMembers() as $member) {
            $this->playerFactions[$member->getPlayerName()] = $newName;
        }
        
        // Update claims
        $this->plugin->getClaimManager()->updateFactionName($oldName, $newName);
        
        $this->saveAll();
    }

    public function getAllFactions(): array {
        return $this->factions;
    }

    public function getFactionCount(): int {
        return count($this->factions);
    }

    public function getOnlineFactionsCount(): int {
        $count = 0;
        foreach ($this->factions as $faction) {
            if (count($faction->getOnlineMembers()) > 0) {
                $count++;
            }
        }
        return $count;
    }
}
