<?php

declare(strict_types=1);

namespace zPlugins\Factions\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use zPlugins\Factions\Main;
use zPlugins\Factions\Utils\Rank;

class FactionCommand extends Command implements PluginOwned {
    use PluginOwnedTrait;

    public function __construct(private Main $plugin) {
        parent::__construct("f", "Faction commands", "/f <subcommand>", ["faction"]);
        $this->setPermission("zfactions.command");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage($this->plugin->getMessage("errors.console-not-allowed"));
            return false;
        }

        if (empty($args)) {
            $this->sendHelp($sender);
            return true;
        }

        $subcommand = strtolower($args[0]);
        
        switch ($subcommand) {
            case "create":
                return $this->handleCreate($sender, $args);
            case "invite":
                return $this->handleInvite($sender, $args);
            case "accept":
                return $this->handleAccept($sender, $args);
            case "deny":
                return $this->handleDeny($sender, $args);
            case "leave":
                return $this->handleLeave($sender);
            case "disband":
                return $this->handleDisband($sender);
            case "promote":
                return $this->handlePromote($sender, $args);
            case "demote":
                return $this->handleDemote($sender, $args);
            case "claim":
                return $this->handleClaim($sender);
            case "unclaim":
                return $this->handleUnclaim($sender);
            case "unclaimall":
                return $this->handleUnclaimAll($sender);
            case "overclaim":
                return $this->handleOverclaim($sender);
            case "map":
                return $this->handleMap($sender);
            case "home":
                return $this->handleHome($sender);
            case "sethome":
                return $this->handleSetHome($sender);
            case "deposit":
                return $this->handleDeposit($sender, $args);
            case "withdraw":
                return $this->handleWithdraw($sender, $args);
            case "bank":
                return $this->handleBank($sender);
            case "chat":
                return $this->handleChat($sender);
            case "allychat":
                return $this->handleAllyChat($sender);
            case "ally":
                return $this->handleAlly($sender, $args);
            case "acceptally":
                return $this->handleAcceptAlly($sender, $args);
            case "denyally":
                return $this->handleDenyAlly($sender, $args);
            case "unally":
                return $this->handleUnally($sender, $args);
            case "enemy":
                return $this->handleEnemy($sender, $args);
            case "info":
                return $this->handleInfo($sender, $args);
            case "description":
                return $this->handleDescription($sender, $args);
            case "rename":
                return $this->handleRename($sender, $args);
            case "fly":
                return $this->handleFly($sender);
            case "help":
                return $this->sendHelp($sender);
            default:
                $sender->sendMessage($this->plugin->getMessage("errors.invalid-command"));
                return false;
        }
    }

    private function handleCreate(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-create"));
            return false;
        }

        $factionName = $args[1];
        
        if ($this->plugin->getFactionManager()->getPlayerFaction($player)) {
            $player->sendMessage($this->plugin->getMessage("faction.already-in-faction"));
            return false;
        }

        if ($this->plugin->getFactionManager()->getFaction($factionName)) {
            $player->sendMessage($this->plugin->getMessage("faction.faction-already-exists", ["faction" => $factionName]));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->createFaction($factionName, $player);
        $player->sendMessage($this->plugin->getMessage("faction.created", ["faction" => $factionName]));
        return true;
    }

    private function handleInvite(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-invite"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $rank = $this->plugin->getFactionManager()->getPlayerRank($player);
        if (!$rank->hasPermission("faction.invite")) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $targetName = $args[1];
        $target = $this->plugin->getServer()->getPlayerExact($targetName);
        
        if (!$target) {
            $player->sendMessage($this->plugin->getMessage("errors.player-not-found", ["player" => $targetName]));
            return false;
        }

        if ($target === $player) {
            $player->sendMessage($this->plugin->getMessage("faction.cannot-invite-self"));
            return false;
        }

        if ($this->plugin->getFactionManager()->getPlayerFaction($target)) {
            $player->sendMessage($this->plugin->getMessage("faction.player-already-in-faction", ["player" => $targetName]));
            return false;
        }

        $this->plugin->getFactionManager()->invitePlayer($faction, $target);
        $player->sendMessage($this->plugin->getMessage("faction.invitation-sent", ["player" => $targetName]));
        $target->sendMessage($this->plugin->getMessage("faction.invited", ["player" => $player->getName()]));
        return true;
    }

    private function handleAccept(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-join"));
            return false;
        }

        $factionName = $args[1];
        $faction = $this->plugin->getFactionManager()->getFaction($factionName);
        
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("faction.faction-not-found", ["faction" => $factionName]));
            return false;
        }

        if ($this->plugin->getFactionManager()->getPlayerFaction($player)) {
            $player->sendMessage($this->plugin->getMessage("faction.already-in-faction"));
            return false;
        }

        if (!$this->plugin->getFactionManager()->hasInvitation($faction, $player)) {
            $player->sendMessage($this->plugin->getMessage("faction.no-pending-invitations"));
            return false;
        }

        $this->plugin->getFactionManager()->addPlayerToFaction($faction, $player, Rank::MEMBER());
        $player->sendMessage($this->plugin->getMessage("faction.joined", ["faction" => $factionName]));
        return true;
    }

    private function handleDeny(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-deny"));
            return false;
        }

        $factionName = $args[1];
        $faction = $this->plugin->getFactionManager()->getFaction($factionName);
        
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("faction.faction-not-found", ["faction" => $factionName]));
            return false;
        }

        $this->plugin->getFactionManager()->removeInvitation($faction, $player);
        $player->sendMessage($this->plugin->getMessage("faction.invitation-denied", ["faction" => $factionName]));
        return true;
    }

    private function handleLeave(Player $player): bool {
        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        if ($faction->getLeader() === $player->getName()) {
            $player->sendMessage($this->plugin->getMessage("faction.leader-cannot-leave"));
            return false;
        }

        $this->plugin->getFactionManager()->removePlayerFromFaction($faction, $player);
        $player->sendMessage($this->plugin->getMessage("faction.left", ["faction" => $faction->getName()]));
        return true;
    }

    private function handleDisband(Player $player): bool {
        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        if ($faction->getLeader() !== $player->getName()) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $this->plugin->getFactionManager()->disbandFaction($faction);
        $player->sendMessage($this->plugin->getMessage("faction.disbanded"));
        return true;
    }

    private function handleClaim(Player $player): bool {
        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $rank = $this->plugin->getFactionManager()->getPlayerRank($player);
        if (!$rank->hasPermission("faction.claim")) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $position = $player->getPosition();
        $chunkX = $position->getFloorX() >> 4;
        $chunkZ = $position->getFloorZ() >> 4;
        $world = $position->getWorld()->getFolderName();

        if ($this->plugin->getClaimManager()->getClaimAt($chunkX, $chunkZ, $world)) {
            $player->sendMessage($this->plugin->getMessage("claiming.already-claimed"));
            return false;
        }

        // Check if faction can afford claim
        if (!$this->plugin->getEconomyManager()->canAffordClaim($faction, $player)) {
            $player->sendMessage($this->plugin->getMessage("claiming.cannot-afford-claim"));
            return false;
        }

        // Charge for claim
        $this->plugin->getEconomyManager()->chargeClaim($faction, $player);
        
        // Create claim
        $this->plugin->getClaimManager()->createClaim($chunkX, $chunkZ, $world, $faction->getName());
        $player->sendMessage($this->plugin->getMessage("claiming.claimed", ["faction" => $faction->getName()]));
        return true;
    }

    private function handleUnclaim(Player $player): bool {
        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $rank = $this->plugin->getFactionManager()->getPlayerRank($player);
        if (!$rank->hasPermission("faction.unclaim")) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $position = $player->getPosition();
        $chunkX = $position->getFloorX() >> 4;
        $chunkZ = $position->getFloorZ() >> 4;
        $world = $position->getWorld()->getFolderName();

        $claim = $this->plugin->getClaimManager()->getClaimAt($chunkX, $chunkZ, $world);
        if (!$claim || $claim->getFactionName() !== $faction->getName()) {
            $player->sendMessage($this->plugin->getMessage("claiming.not-claimed-by-faction"));
            return false;
        }

        $this->plugin->getClaimManager()->removeClaim($chunkX, $chunkZ, $world);
        $player->sendMessage($this->plugin->getMessage("claiming.unclaimed"));
        return true;
    }

    private function handleUnclaimAll(Player $player): bool {
        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        if ($faction->getLeader() !== $player->getName()) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $this->plugin->getClaimManager()->removeAllClaims($faction->getName());
        $player->sendMessage($this->plugin->getMessage("claiming.unclaimed-all"));
        return true;
    }

    private function handleOverclaim(Player $player): bool {
        if (!$this->plugin->getConfig()->getNested("claiming.overclaim-enabled", true)) {
            $player->sendMessage($this->plugin->getMessage("errors.command-disabled"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $position = $player->getPosition();
        $chunkX = $position->getFloorX() >> 4;
        $chunkZ = $position->getFloorZ() >> 4;
        $world = $position->getWorld()->getFolderName();

        $claim = $this->plugin->getClaimManager()->getClaimAt($chunkX, $chunkZ, $world);
        if (!$claim) {
            $player->sendMessage($this->plugin->getMessage("claiming.cannot-claim"));
            return false;
        }

        $targetFaction = $this->plugin->getFactionManager()->getFaction($claim->getFactionName());
        if (!$targetFaction) {
            $player->sendMessage($this->plugin->getMessage("claiming.cannot-claim"));
            return false;
        }

        // Check power requirements
        if ($this->plugin->getConfig()->getNested("claiming.require-double-power", true)) {
            if ($faction->getPower() < $targetFaction->getPower() * 2) {
                $player->sendMessage($this->plugin->getMessage("claiming.overclaim-requirements"));
                return false;
            }
        }

        // Check if can afford overclaim cost
        if (!$this->plugin->getEconomyManager()->canAffordOverclaim($faction, $player)) {
            $player->sendMessage($this->plugin->getMessage("claiming.cannot-afford-claim"));
            return false;
        }

        // Charge for overclaim
        $this->plugin->getEconomyManager()->chargeOverclaim($faction, $player);
        
        // Transfer claim
        $this->plugin->getClaimManager()->transferClaim($chunkX, $chunkZ, $world, $faction->getName());
        $player->sendMessage($this->plugin->getMessage("claiming.overclaimed", ["faction" => $targetFaction->getName()]));
        return true;
    }

    private function handleDeposit(Player $player, array $args): bool {
        if (!$this->plugin->getConfig()->getNested("economy.enabled", true)) {
            $player->sendMessage($this->plugin->getMessage("errors.command-disabled"));
            return false;
        }

        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-deposit"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $amount = (float) $args[1];
        if ($amount <= 0) {
            $player->sendMessage($this->plugin->getMessage("errors.invalid-amount"));
            return false;
        }

        if ($this->plugin->getEconomyManager()->depositToFaction($faction, $player, $amount)) {
            $player->sendMessage($this->plugin->getMessage("economy.deposited", ["amount" => $amount]));
            return true;
        } else {
            $player->sendMessage($this->plugin->getMessage("economy.insufficient-funds"));
            return false;
        }
    }

    private function handleWithdraw(Player $player, array $args): bool {
        if (!$this->plugin->getConfig()->getNested("economy.enabled", true)) {
            $player->sendMessage($this->plugin->getMessage("errors.command-disabled"));
            return false;
        }

        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-withdraw"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $rank = $this->plugin->getFactionManager()->getPlayerRank($player);
        if (!$rank->hasPermission("faction.withdraw")) {
            $player->sendMessage($this->plugin->getMessage("economy.only-leaders-withdraw"));
            return false;
        }

        $amount = (float) $args[1];
        if ($amount <= 0) {
            $player->sendMessage($this->plugin->getMessage("errors.invalid-amount"));
            return false;
        }

        if ($this->plugin->getEconomyManager()->withdrawFromFaction($faction, $player, $amount)) {
            $player->sendMessage($this->plugin->getMessage("economy.withdrawn", ["amount" => $amount]));
            return true;
        } else {
            $player->sendMessage($this->plugin->getMessage("economy.insufficient-faction-funds"));
            return false;
        }
    }

    private function handleBank(Player $player): bool {
        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $balance = $faction->getBankBalance();
        $player->sendMessage($this->plugin->getMessage("economy.bank-balance", ["balance" => $balance]));
        return true;
    }

    private function handleChat(Player $player): bool {
        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $currentMode = $this->plugin->getChatMode($player);
        
        if ($currentMode === "faction") {
            $this->plugin->removeChatMode($player);
            $player->sendMessage($this->plugin->getMessage("chat.faction-chat-disabled"));
        } else {
            $this->plugin->setChatMode($player, "faction");
            $player->sendMessage($this->plugin->getMessage("chat.faction-chat-enabled"));
        }
        
        return true;
    }

    private function handleAllyChat(Player $player): bool {
        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $currentMode = $this->plugin->getChatMode($player);
        
        if ($currentMode === "ally") {
            $this->plugin->removeChatMode($player);
            $player->sendMessage($this->plugin->getMessage("chat.ally-chat-disabled"));
        } else {
            $this->plugin->setChatMode($player, "ally");
            $player->sendMessage($this->plugin->getMessage("chat.ally-chat-enabled"));
        }
        
        return true;
    }

    private function handleFly(Player $player): bool {
        if (!$this->plugin->getConfig()->getNested("fly.enabled", true)) {
            $player->sendMessage($this->plugin->getMessage("errors.command-disabled"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        // Check cooldown
        $cooldown = $this->plugin->getFlyCooldown($player);
        if ($cooldown > 0) {
            $player->sendMessage($this->plugin->getMessage("fly.fly-cooldown", ["time" => $cooldown]));
            return false;
        }

        // Check if in faction territory
        $position = $player->getPosition();
        $claim = $this->plugin->getClaimManager()->getClaimAt(
            $position->getFloorX() >> 4,
            $position->getFloorZ() >> 4,
            $position->getWorld()->getFolderName()
        );

        if (!$claim || $claim->getFactionName() !== $faction->getName()) {
            $player->sendMessage($this->plugin->getMessage("fly.not-in-faction-land"));
            return false;
        }

        if ($player->getAllowFlight()) {
            $player->setFlying(false);
            $player->setAllowFlight(false);
            $player->sendMessage($this->plugin->getMessage("fly.disabled"));
        } else {
            $player->setAllowFlight(true);
            $player->sendMessage($this->plugin->getMessage("fly.enabled"));
        }

        return true;
    }

    private function handlePromote(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-promote"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $rank = $this->plugin->getFactionManager()->getPlayerRank($player);
        if (!$rank->hasPermission("faction.promote")) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $targetName = $args[1];
        $target = $this->plugin->getServer()->getPlayerExact($targetName);

        if (!$faction->hasMember($targetName)) {
            $player->sendMessage($this->plugin->getMessage("errors.player-not-found"));
            return false;
        }

        if ($this->plugin->getFactionManager()->promoteMember($faction, $targetName)) {
            $newRank = $this->plugin->getFactionManager()->getMemberRank($faction, $targetName);
            $player->sendMessage($this->plugin->getMessage("ranks.promoted", [
                "player" => $targetName,
                "rank" => $newRank->getName()
            ]));
            
            if ($target) {
                $target->sendMessage($this->plugin->getMessage("ranks.promoted", [
                    "player" => "You",
                    "rank" => $newRank->getName()
                ]));
            }
            return true;
        } else {
            $player->sendMessage($this->plugin->getMessage("ranks.already-highest-rank", ["player" => $targetName]));
            return false;
        }
    }

    private function handleDemote(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-demote"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $rank = $this->plugin->getFactionManager()->getPlayerRank($player);
        if (!$rank->hasPermission("faction.demote")) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $targetName = $args[1];
        $target = $this->plugin->getServer()->getPlayerExact($targetName);

        if (!$faction->hasMember($targetName)) {
            $player->sendMessage($this->plugin->getMessage("errors.player-not-found"));
            return false;
        }

        if ($this->plugin->getFactionManager()->demoteMember($faction, $targetName)) {
            $newRank = $this->plugin->getFactionManager()->getMemberRank($faction, $targetName);
            $player->sendMessage($this->plugin->getMessage("ranks.demoted", [
                "player" => $targetName,
                "rank" => $newRank->getName()
            ]));
            
            if ($target) {
                $target->sendMessage($this->plugin->getMessage("ranks.demoted", [
                    "player" => "You",
                    "rank" => $newRank->getName()
                ]));
            }
            return true;
        } else {
            $player->sendMessage($this->plugin->getMessage("ranks.already-lowest-rank", ["player" => $targetName]));
            return false;
        }
    }

    private function handleAlly(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-ally"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $rank = $this->plugin->getFactionManager()->getPlayerRank($player);
        if (!$rank->hasPermission("faction.ally")) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $targetFactionName = $args[1];
        $targetFaction = $this->plugin->getFactionManager()->getFaction($targetFactionName);

        if (!$targetFaction) {
            $player->sendMessage($this->plugin->getMessage("faction.faction-not-found", ["faction" => $targetFactionName]));
            return false;
        }

        if ($targetFaction->getName() === $faction->getName()) {
            $player->sendMessage($this->plugin->getMessage("diplomacy.cannot-ally-self"));
            return false;
        }

        if ($faction->isAlly($targetFactionName)) {
            $player->sendMessage($this->plugin->getMessage("diplomacy.already-allies", ["faction" => $targetFactionName]));
            return false;
        }

        $this->plugin->getFactionManager()->sendAllyRequest($faction, $targetFaction);
        $player->sendMessage($this->plugin->getMessage("diplomacy.ally-request-sent", ["faction" => $targetFactionName]));
        
        // Notify target faction
        foreach ($targetFaction->getOnlineMembers() as $member) {
            $member->sendMessage($this->plugin->getMessage("diplomacy.ally-request-received", ["faction" => $faction->getName()]));
        }
        
        return true;
    }

    private function handleAcceptAlly(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-acceptally"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $rank = $this->plugin->getFactionManager()->getPlayerRank($player);
        if (!$rank->hasPermission("faction.ally")) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $requestingFactionName = $args[1];
        $requestingFaction = $this->plugin->getFactionManager()->getFaction($requestingFactionName);

        if (!$requestingFaction) {
            $player->sendMessage($this->plugin->getMessage("faction.faction-not-found", ["faction" => $requestingFactionName]));
            return false;
        }

        if ($this->plugin->getFactionManager()->acceptAllyRequest($faction, $requestingFaction)) {
            $player->sendMessage($this->plugin->getMessage("diplomacy.ally-request-accepted", ["faction" => $requestingFactionName]));
            
            // Notify requesting faction
            foreach ($requestingFaction->getOnlineMembers() as $member) {
                $member->sendMessage($this->plugin->getMessage("diplomacy.ally-request-accepted", ["faction" => $faction->getName()]));
            }
            return true;
        } else {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }
    }

    private function handleDenyAlly(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-denyally"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $requestingFactionName = $args[1];
        $this->plugin->getFactionManager()->denyAllyRequest($faction, $requestingFactionName);
        $player->sendMessage($this->plugin->getMessage("diplomacy.ally-request-denied", ["faction" => $requestingFactionName]));
        return true;
    }

    private function handleUnally(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-unally"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $rank = $this->plugin->getFactionManager()->getPlayerRank($player);
        if (!$rank->hasPermission("faction.ally")) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $allyFactionName = $args[1];
        
        if (!$faction->isAlly($allyFactionName)) {
            $player->sendMessage($this->plugin->getMessage("diplomacy.not-allies", ["faction" => $allyFactionName]));
            return false;
        }

        $this->plugin->getFactionManager()->removeAlliance($faction, $allyFactionName);
        $player->sendMessage($this->plugin->getMessage("diplomacy.alliance-broken", ["faction" => $allyFactionName]));
        return true;
    }

    private function handleEnemy(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-enemy"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $rank = $this->plugin->getFactionManager()->getPlayerRank($player);
        if (!$rank->hasPermission("faction.enemy")) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $enemyFactionName = $args[1];
        $enemyFaction = $this->plugin->getFactionManager()->getFaction($enemyFactionName);

        if (!$enemyFaction) {
            $player->sendMessage($this->plugin->getMessage("faction.faction-not-found", ["faction" => $enemyFactionName]));
            return false;
        }

        if ($enemyFaction->getName() === $faction->getName()) {
            $player->sendMessage($this->plugin->getMessage("diplomacy.cannot-enemy-self"));
            return false;
        }

        $this->plugin->getFactionManager()->declareEnemy($faction, $enemyFaction);
        $player->sendMessage($this->plugin->getMessage("diplomacy.enemy-declared", ["faction" => $enemyFactionName]));
        return true;
    }

    private function handleInfo(Player $player, array $args): bool {
        $targetFaction = null;
        
        if (count($args) >= 2) {
            $targetFaction = $this->plugin->getFactionManager()->getFaction($args[1]);
            if (!$targetFaction) {
                $player->sendMessage($this->plugin->getMessage("faction.faction-not-found", ["faction" => $args[1]]));
                return false;
            }
        } else {
            $targetFaction = $this->plugin->getFactionManager()->getPlayerFaction($player);
            if (!$targetFaction) {
                $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
                return false;
            }
        }

        $this->sendFactionInfo($player, $targetFaction);
        return true;
    }

    private function handleDescription(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-description"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        if ($faction->getLeader() !== $player->getName()) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $description = implode(" ", array_slice($args, 1));
        $faction->setDescription($description);
        $player->sendMessage($this->plugin->getMessage("faction.description-set", ["description" => $description]));
        return true;
    }

    private function handleRename(Player $player, array $args): bool {
        if (count($args) < 2) {
            $player->sendMessage($this->plugin->getMessage("usage.faction-rename"));
            return false;
        }

        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        if ($faction->getLeader() !== $player->getName()) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $newName = $args[1];
        
        if ($this->plugin->getFactionManager()->getFaction($newName)) {
            $player->sendMessage($this->plugin->getMessage("faction.faction-already-exists", ["faction" => $newName]));
            return false;
        }

        $oldName = $faction->getName();
        $this->plugin->getFactionManager()->renameFaction($faction, $newName);
        $player->sendMessage($this->plugin->getMessage("faction.renamed", ["old" => $oldName, "new" => $newName]));
        return true;
    }

    private function handleHome(Player $player): bool {
        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $home = $faction->getHome();
        if (!$home) {
            $player->sendMessage($this->plugin->getMessage("homes.no-home-set"));
            return false;
        }

        // Check cooldown
        $cooldown = $this->plugin->getHomeCooldown($player);
        if ($cooldown > 0) {
            $player->sendMessage($this->plugin->getMessage("homes.home-cooldown", ["time" => $cooldown]));
            return false;
        }

        $player->teleport($home);
        $player->sendMessage($this->plugin->getMessage("homes.home-teleport"));
        
        // Set cooldown
        $cooldownTime = $this->plugin->getConfig()->getNested("homes.home-cooldown", 30);
        $this->plugin->setHomeCooldown($player, $cooldownTime);
        
        return true;
    }

    private function handleSetHome(Player $player): bool {
        $faction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        if (!$faction) {
            $player->sendMessage($this->plugin->getMessage("errors.not-in-faction"));
            return false;
        }

        $rank = $this->plugin->getFactionManager()->getPlayerRank($player);
        if (!$rank->hasPermission("faction.sethome")) {
            $player->sendMessage($this->plugin->getMessage("errors.no-permission"));
            return false;
        }

        $position = $player->getPosition();
        
        // Check if in own territory (if required)
        if ($this->plugin->getConfig()->getNested("homes.require-own-land", false)) {
            $claim = $this->plugin->getClaimManager()->getClaimAt(
                $position->getFloorX() >> 4,
                $position->getFloorZ() >> 4,
                $position->getWorld()->getFolderName()
            );
            
            if (!$claim || $claim->getFactionName() !== $faction->getName()) {
                $player->sendMessage($this->plugin->getMessage("homes.only-own-land-home"));
                return false;
            }
        }

        $faction->setHome($position);
        $player->sendMessage($this->plugin->getMessage("homes.home-set"));
        return true;
    }

    private function handleMap(Player $player): bool {
        $position = $player->getPosition();
        $playerChunkX = $position->getFloorX() >> 4;
        $playerChunkZ = $position->getFloorZ() >> 4;
        $world = $position->getWorld()->getFolderName();

        $player->sendMessage("§8[§6zFactions§8]§r Faction Territory Map:");

        $playerFaction = $this->plugin->getFactionManager()->getPlayerFaction($player);
        $size = 16; // 9x9 map
        $half = intval($size / 2);

        for ($z = $playerChunkZ - $half; $z <= $playerChunkZ + $half; $z++) {
            $line = "";
            for ($x = $playerChunkX - $half; $x <= $playerChunkX + $half; $x++) {
                if ($x === $playerChunkX && $z === $playerChunkZ) {
                    $line .= "§6■§r";
                } else {
                    $claim = $this->plugin->getClaimManager()->getClaimAt($x, $z, $world);
                    if ($claim) {
                        $claimFaction = $this->plugin->getFactionManager()->getFaction($claim->getFactionName());
                        if ($claimFaction) {
                            if ($playerFaction && $claimFaction->getName() === $playerFaction->getName()) {
                                $line .= "§a■§r";
                            } elseif ($playerFaction && $claimFaction->isAlly($playerFaction->getName())) {
                                $line .= "§b■§r";
                            } elseif ($playerFaction && $claimFaction->isEnemy($playerFaction->getName())) {
                                $line .= "§c■§r";
                            } else {
                                $line .= "§7■§r";
                            }
                        } else {
                            $line .= "§8■§r";
                        }
                    } else {
                        $line .= "§8■§r";
                    }
                }
            }
            $player->sendMessage($line);
        }

        return true;
    }


    private function sendFactionInfo(Player $player, $faction): bool {
        $player->sendMessage($this->plugin->getMessage("info.faction-info", ["faction" => $faction->getName()]));
        $player->sendMessage($this->plugin->getMessage("info.leader", ["leader" => $faction->getLeader()]));

        $members = $faction->getMembers();
        $memberNames = array_map(function($member) {
            return $member->getPlayerName();
        }, $members);

        $player->sendMessage($this->plugin->getMessage("info.members", [
            "count" => count($members),
            "members" => implode(", ", $memberNames)
        ]));

        $player->sendMessage($this->plugin->getMessage("info.power", [
            "power" => $faction->getPower(),
            "maxpower" => $faction->getMaxPower()
        ]));

        $claimCount = $this->plugin->getClaimManager()->getFactionClaimCount($faction->getName());
        $maxClaims = $this->plugin->getConfig()->getNested("claiming.max-claims", 100);

        $player->sendMessage($this->plugin->getMessage("info.land", [
            "claims" => $claimCount,
            "maxclaims" => $maxClaims
        ]));

        if ($this->plugin->getConfig()->getNested("economy.enabled", true)) {
            $player->sendMessage($this->plugin->getMessage("info.bank", [
                "balance" => $faction->getBankBalance()
            ]));
        }

        $allies = $faction->getAllies();
        if (!empty($allies)) {
            $player->sendMessage($this->plugin->getMessage("info.allies", [
                "allies" => implode(", ", $allies)
            ]));
        }

        $enemies = $faction->getEnemies();
        if (!empty($enemies)) {
            $player->sendMessage($this->plugin->getMessage("info.enemies", [
                "enemies" => implode(", ", $enemies)
            ]));
        }

        if ($faction->getDescription()) {
            $player->sendMessage($this->plugin->getMessage("info.description", [
                "description" => $faction->getDescription()
            ]));
        }
        return true;
    }


    private function sendHelp(Player $player): bool {
        $player->sendMessage("§6=== zPlugins Factions Help ===");
        $player->sendMessage("§e/f create <name> §7- Create a faction");
        $player->sendMessage("§e/f invite <player> §7- Invite a player");
        $player->sendMessage("§e/f accept <faction> §7- Accept invitation");
        $player->sendMessage("§e/f deny <faction> §7- Deny invitation");
        $player->sendMessage("§e/f promote <player> §7- Promote a player");
        $player->sendMessage("§e/f demote <player> §7- Demote a player");
        $player->sendMessage("§e/f rename <name> §7- Rename faction");
        $player->sendMessage("§e/f description <description> §7- Change description");
        $player->sendMessage("§e/f leave §7- Leave current faction");
        $player->sendMessage("§e/f claim §7- Claim current chunk");
        $player->sendMessage("§e/f overclaim §7- Overclaims current chunk");
        $player->sendMessage("§e/f unclaim §7- Unclaim current chunk");
        $player->sendMessage("§e/f unclaimall §7- Unclaim all claimed chunks");
        $player->sendMessage("§e/f home §7- Teleport to faction home");
        $player->sendMessage("§e/f sethome §7- Set faction home");
        $player->sendMessage("§e/f bank §7- View faction bank");
        $player->sendMessage("§e/f deposit <amount> §7- Donate money to your faction");
        $player->sendMessage("§e/f withdraw <amount>§7- Withdraw money from your faction");
        $player->sendMessage("§e/f chat §7- Toggle faction chat");
        $player->sendMessage("§e/f allychat §7- Toggle ally chat");
        $player->sendMessage("§e/f fly §7- Toggle faction fly");
        $player->sendMessage("§e/f info [faction] §7- View faction info");
        $player->sendMessage("§e/f map §7- View territory map");
        $player->sendMessage("§e/f ally <faction> §7- Ally with a faction");
        $player->sendMessage("§e/f acceptally <faction> §7- Accept ally invitation");
        $player->sendMessage("§e/f denyally <faction> §7- Deny ally invitation");
        $player->sendMessage("§e/f enemy <faction> §7- Mark a faction as an enemy");
        $player->sendMessage("§e/f help §7 - Shows this message");
        return true;
    }


    public function getOwningPlugin(): Main {
        return $this->plugin;
    }
}
