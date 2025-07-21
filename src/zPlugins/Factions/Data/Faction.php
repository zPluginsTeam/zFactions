<?php

declare(strict_types=1);

namespace zPlugins\Factions\Data;

use pocketmine\player\Player;
use pocketmine\world\Position;
use zPlugins\Factions\Main;

class Faction {

    private string $name;
    private string $leader;
    private string $description = "";
    private array $members = [];
    private array $allies = [];
    private array $enemies = [];
    private float $bankBalance = 0.0;
    private ?Position $home = null;
    private int $createdTime;

    public function __construct(string $name) {
        $this->name = $name;
        $this->createdTime = time();
    }

    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function getLeader(): string {
        return $this->leader;
    }

    public function setLeader(string $leader): void {
        $this->leader = $leader;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function setDescription(string $description): void {
        $this->description = $description;
    }

    public function addMember(FactionMember $member): void {
        $this->members[$member->getPlayerName()] = $member;
    }

    public function removeMember(string $playerName): void {
        unset($this->members[$playerName]);
    }

    public function getMember(string $playerName): ?FactionMember {
        return $this->members[$playerName] ?? null;
    }

    public function hasMember(string $playerName): bool {
        return isset($this->members[$playerName]);
    }

    public function getMembers(): array {
        return $this->members;
    }

    public function getMemberCount(): int {
        return count($this->members);
    }

    public function getOnlineMembers(): array {
        $onlineMembers = [];
        $server = Main::getInstance()->getServer();
        
        foreach ($this->members as $member) {
            $player = $server->getPlayerExact($member->getPlayerName());
            if ($player instanceof Player) {
                $onlineMembers[] = $player;
            }
        }
        
        return $onlineMembers;
    }

    public function getOnlineMemberCount(): int {
        return count($this->getOnlineMembers());
    }

    public function addAlly(string $factionName): void {
        if (!in_array($factionName, $this->allies)) {
            $this->allies[] = $factionName;
        }
        
        // Remove from enemies if present
        $this->removeEnemy($factionName);
    }

    public function removeAlly(string $factionName): void {
        $key = array_search($factionName, $this->allies);
        if ($key !== false) {
            unset($this->allies[$key]);
            $this->allies = array_values($this->allies); // Re-index array
        }
    }

    public function isAlly(string $factionName): bool {
        return in_array($factionName, $this->allies);
    }

    public function getAllies(): array {
        return $this->allies;
    }

    public function addEnemy(string $factionName): void {
        if (!in_array($factionName, $this->enemies)) {
            $this->enemies[] = $factionName;
        }
        
        // Remove from allies if present
        $this->removeAlly($factionName);
    }

    public function removeEnemy(string $factionName): void {
        $key = array_search($factionName, $this->enemies);
        if ($key !== false) {
            unset($this->enemies[$key]);
            $this->enemies = array_values($this->enemies); // Re-index array
        }
    }

    public function isEnemy(string $factionName): bool {
        return in_array($factionName, $this->enemies);
    }

    public function getEnemies(): array {
        return $this->enemies;
    }

    public function getBankBalance(): float {
        return $this->bankBalance;
    }

    public function setBankBalance(float $balance): void {
        $this->bankBalance = max(0.0, $balance);
    }

    public function addBankMoney(float $amount): void {
        $this->bankBalance += $amount;
    }

    public function subtractBankMoney(float $amount): bool {
        if ($this->bankBalance >= $amount) {
            $this->bankBalance -= $amount;
            return true;
        }
        return false;
    }

    public function getHome(): ?Position {
        return $this->home;
    }

    public function setHome(Position $position): void {
        $this->home = $position;
    }

    public function hasHome(): bool {
        return $this->home !== null;
    }

    public function getPower(): int {
        $totalPower = 0;
        $factionManager = Main::getInstance()->getFactionManager();
        
        foreach ($this->members as $member) {
            $player = Main::getInstance()->getServer()->getPlayerExact($member->getPlayerName());
            if ($player instanceof Player) {
                $totalPower += $factionManager->getPlayerPower($player);
            }
        }
        
        return $totalPower;
    }

    public function getMaxPower(): int {
        $maxPerPlayer = Main::getInstance()->getConfig()->getNested("strength.max-per-player", 100);
        return $this->getMemberCount() * $maxPerPlayer;
    }

    public function getCreatedTime(): int {
        return $this->createdTime;
    }

    public function getFormattedCreatedDate(): string {
        return date("Y-m-d H:i:s", $this->createdTime);
    }

    public function toArray(): array {
        $homeData = null;
        if ($this->home) {
            $homeData = [
                "x" => $this->home->getX(),
                "y" => $this->home->getY(),
                "z" => $this->home->getZ(),
                "world" => $this->home->getWorld()->getFolderName()
            ];
        }

        $membersData = [];
        foreach ($this->members as $member) {
            $membersData[] = $member->toArray();
        }

        return [
            "name" => $this->name,
            "leader" => $this->leader,
            "description" => $this->description,
            "members" => $membersData,
            "allies" => $this->allies,
            "enemies" => $this->enemies,
            "bank_balance" => $this->bankBalance,
            "home" => $homeData,
            "created_time" => $this->createdTime
        ];
    }

    public function fromArray(array $data): void {
        $this->name = $data["name"] ?? $this->name;
        $this->leader = $data["leader"] ?? "";
        $this->description = $data["description"] ?? "";
        $this->allies = $data["allies"] ?? [];
        $this->enemies = $data["enemies"] ?? [];
        $this->bankBalance = $data["bank_balance"] ?? 0.0;
        $this->createdTime = $data["created_time"] ?? time();

        // Load members
        $this->members = [];
        if (isset($data["members"])) {
            foreach ($data["members"] as $memberData) {
                $member = new FactionMember("", null);
                $member->fromArray($memberData);
                $this->members[$member->getPlayerName()] = $member;
            }
        }

        // Load home
        if (isset($data["home"]) && $data["home"] !== null) {
            $homeData = $data["home"];
            $world = Main::getInstance()->getServer()->getWorldManager()->getWorldByName($homeData["world"]);
            if ($world) {
                $this->home = new Position(
                    $homeData["x"],
                    $homeData["y"],
                    $homeData["z"],
                    $world
                );
            }
        }
    }
}
