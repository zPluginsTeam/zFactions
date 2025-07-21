<?php

declare(strict_types=1);

namespace zPlugins\Factions\Manager;

use pocketmine\player\Player;
use cooldogedev\BedrockEconomy\api\type\LegacyAPI;
use cooldogedev\BedrockEconomy\BedrockEconomy;
use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use zPlugins\Factions\Main;
use zPlugins\Factions\Data\Faction;
use zPlugins\Factions\Events\FactionDepositEvent;

class EconomyManager {

    private Main $plugin;
    private ?LegacyAPI $economyAPI = null;
    private bool $economyEnabled = false;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->initializeEconomy();
    }

    private function initializeEconomy(): void {
        $economyPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin("BedrockEconomy");

        if ($economyPlugin instanceof BedrockEconomy && $economyPlugin->isEnabled()) {
            $this->economyAPI = BedrockEconomyAPI::getInstance(); // Returns LegacyAPI
            $this->economyEnabled = $this->plugin->getConfig()->getNested("economy.enabled", true);
            $this->plugin->getLogger()->info("BedrockEconomy integration enabled!");
        } else {
            $this->plugin->getLogger()->warning("BedrockEconomy not found! Economy features disabled.");
            $this->economyEnabled = false;
        }
    }

    public function isEconomyEnabled(): bool {
        return $this->economyEnabled && $this->economyAPI !== null;
    }

    public function getPlayerBalance(Player $player): float {
        if (!$this->isEconomyEnabled()) {
            return 0.0;
        }

        try {
            $result = 0.0;
            $this->economyAPI->getPlayerBalance(
                $player->getName(),
                function (float $balance) use (&$result) {
                    $result = $balance;
                }
            );
            return $result;
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to get player balance: " . $e->getMessage());
            return 0.0;
        }
    }

    public function addPlayerMoney(Player $player, float $amount): bool {
        if (!$this->isEconomyEnabled() || $amount <= 0) {
            return false;
        }

        try {
            $success = false;
            $this->economyAPI->addToPlayerBalance(
                $player->getName(),
                $amount,
                function (bool $wasSuccessful) use (&$success) {
                    $success = $wasSuccessful;
                }
            );
            return $success;
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to add player money: " . $e->getMessage());
            return false;
        }
    }

    public function subtractPlayerMoney(Player $player, float $amount): bool {
        if (!$this->isEconomyEnabled() || $amount <= 0) {
            return false;
        }

        try {
            $success = false;
            $this->economyAPI->subtractFromPlayerBalance(
                $player->getName(),
                $amount,
                function (bool $wasSuccessful) use (&$success) {
                    $success = $wasSuccessful;
                }
            );
            return $success;
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to subtract player money: " . $e->getMessage());
            return false;
        }
    }

    public function canAffordClaim(Faction $faction, Player $player): bool {
        if (!$this->isEconomyEnabled()) {
            return true;
        }

        $costType = $this->plugin->getConfig()->getNested("claiming.cost-type", "both");

        if ($costType === "none") {
            return true;
        }

        $moneyCost = $this->plugin->getConfig()->getNested("economy.claim-cost-money", 100);
        $strengthCost = $this->plugin->getConfig()->getNested("economy.claim-cost-strength", 10);

        if ($costType === "money" || $costType === "both") {
            if ($this->getPlayerBalance($player) < $moneyCost) {
                return false;
            }
        }

        if ($costType === "strength" || $costType === "both") {
            if ($this->plugin->getFactionManager()->getPlayerPower($player) < $strengthCost) {
                return false;
            }
        }

        return true;
    }

    public function chargeClaim(Faction $faction, Player $player): bool {
        if (!$this->isEconomyEnabled()) {
            return true;
        }

        $costType = $this->plugin->getConfig()->getNested("claiming.cost-type", "both");

        if ($costType === "none") {
            return true;
        }

        $moneyCost = $this->plugin->getConfig()->getNested("economy.claim-cost-money", 100);
        $strengthCost = $this->plugin->getConfig()->getNested("economy.claim-cost-strength", 10);

        if ($costType === "money" || $costType === "both") {
            if (!$this->subtractPlayerMoney($player, $moneyCost)) {
                return false;
            }
        }

        if ($costType === "strength" || $costType === "both") {
            $this->plugin->getFactionManager()->addPlayerPower($player, -$strengthCost);
        }

        return true;
    }

    public function canAffordOverclaim(Faction $faction, Player $player): bool {
        if (!$this->isEconomyEnabled()) {
            return true;
        }

        $overclaimCost = $this->plugin->getConfig()->getNested("economy.overclaim-cost", 500);
        return $this->getPlayerBalance($player) >= $overclaimCost;
    }

    public function chargeOverclaim(Faction $faction, Player $player): bool {
        if (!$this->isEconomyEnabled()) {
            return true;
        }

        $overclaimCost = $this->plugin->getConfig()->getNested("economy.overclaim-cost", 500);
        return $this->subtractPlayerMoney($player, $overclaimCost);
    }

    public function depositToFaction(Faction $faction, Player $player, float $amount): bool {
        if (!$this->isEconomyEnabled() || $amount <= 0) {
            return false;
        }

        if ($this->getPlayerBalance($player) < $amount) {
            return false;
        }

        if (!$this->subtractPlayerMoney($player, $amount)) {
            return false;
        }

        $faction->addBankMoney($amount);

        $event = new FactionDepositEvent($faction, $player, $amount);
        $event->call();

        return true;
    }

    public function withdrawFromFaction(Faction $faction, Player $player, float $amount): bool {
        if (!$this->isEconomyEnabled() || $amount <= 0) {
            return false;
        }

        if ($faction->getBankBalance() < $amount) {
            return false;
        }

        if (!$faction->subtractBankMoney($amount)) {
            return false;
        }

        if (!$this->addPlayerMoney($player, $amount)) {
            $faction->addBankMoney($amount);
            return false;
        }

        return true;
    }

    public function getClaimCostDisplay(): string {
        if (!$this->isEconomyEnabled()) {
            return "Free";
        }

        $costType = $this->plugin->getConfig()->getNested("claiming.cost-type", "both");

        if ($costType === "none") {
            return "Free";
        }

        $moneyCost = $this->plugin->getConfig()->getNested("economy.claim-cost-money", 100);
        $strengthCost = $this->plugin->getConfig()->getNested("economy.claim-cost-strength", 10);

        $parts = [];

        if ($costType === "money" || $costType === "both") {
            $parts[] = "$" . $moneyCost;
        }

        if ($costType === "strength" || $costType === "both") {
            $parts[] = $strengthCost . " strength";
        }

        return implode(" + ", $parts);
    }

    public function getOverclaimCostDisplay(): string {
        if (!$this->isEconomyEnabled()) {
            return "Free";
        }

        $overclaimCost = $this->plugin->getConfig()->getNested("economy.overclaim-cost", 500);
        return "$" . $overclaimCost;
    }
}
