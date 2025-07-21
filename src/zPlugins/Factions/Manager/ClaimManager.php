<?php

declare(strict_types=1);

namespace zPlugins\Factions\Manager;

use pocketmine\utils\Config;
use zPlugins\Factions\Main;
use zPlugins\Factions\Data\Claim;
use zPlugins\Factions\Events\LandClaimEvent;

class ClaimManager {

    private Main $plugin;
    private array $claims = [];
    private Config $dataConfig;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->loadData();
    }

    private function loadData(): void {
        $this->dataConfig = new Config($this->plugin->getDataFolder() . "claims.yml", Config::YAML);
        
        $claimsData = $this->dataConfig->get("claims", []);
        foreach ($claimsData as $claimData) {
            $claim = new Claim(
                $claimData["x"],
                $claimData["z"],
                $claimData["world"],
                $claimData["faction"]
            );
            
            $key = $this->getClaimKey($claim->getX(), $claim->getZ(), $claim->getWorld());
            $this->claims[$key] = $claim;
        }
    }

    public function saveAll(): void {
        $claimsData = [];
        foreach ($this->claims as $claim) {
            $claimsData[] = [
                "x" => $claim->getX(),
                "z" => $claim->getZ(),
                "world" => $claim->getWorld(),
                "faction" => $claim->getFactionName()
            ];
        }
        
        $this->dataConfig->set("claims", $claimsData);
        $this->dataConfig->save();
    }

    private function getClaimKey(int $x, int $z, string $world): string {
        return "{$world}:{$x}:{$z}";
    }

    public function createClaim(int $x, int $z, string $world, string $factionName): Claim {
        $claim = new Claim($x, $z, $world, $factionName);
        $key = $this->getClaimKey($x, $z, $world);
        $this->claims[$key] = $claim;
        
        // Fire event
        $faction = $this->plugin->getFactionManager()->getFaction($factionName);
        if ($faction) {
            $event = new LandClaimEvent($faction, $claim);
            $event->call();
        }
        
        $this->saveAll();
        return $claim;
    }

    public function removeClaim(int $x, int $z, string $world): bool {
        $key = $this->getClaimKey($x, $z, $world);
        if (isset($this->claims[$key])) {
            unset($this->claims[$key]);
            $this->saveAll();
            return true;
        }
        return false;
    }

    public function getClaimAt(int $x, int $z, string $world): ?Claim {
        $key = $this->getClaimKey($x, $z, $world);
        return $this->claims[$key] ?? null;
    }

    public function transferClaim(int $x, int $z, string $world, string $newFactionName): bool {
        $claim = $this->getClaimAt($x, $z, $world);
        if ($claim) {
            $claim->setFactionName($newFactionName);
            $this->saveAll();
            return true;
        }
        return false;
    }

    public function getFactionClaims(string $factionName): array {
        $factionClaims = [];
        foreach ($this->claims as $claim) {
            if ($claim->getFactionName() === $factionName) {
                $factionClaims[] = $claim;
            }
        }
        return $factionClaims;
    }

    public function getFactionClaimCount(string $factionName): int {
        return count($this->getFactionClaims($factionName));
    }

    public function removeAllClaims(string $factionName): void {
        foreach ($this->claims as $key => $claim) {
            if ($claim->getFactionName() === $factionName) {
                unset($this->claims[$key]);
            }
        }
        $this->saveAll();
    }

    public function updateFactionName(string $oldName, string $newName): void {
        foreach ($this->claims as $claim) {
            if ($claim->getFactionName() === $oldName) {
                $claim->setFactionName($newName);
            }
        }
        $this->saveAll();
    }

    public function isAdjacentToFaction(int $x, int $z, string $world, string $factionName): bool {
        $adjacentChunks = [
            [$x + 1, $z],
            [$x - 1, $z],
            [$x, $z + 1],
            [$x, $z - 1]
        ];
        
        foreach ($adjacentChunks as [$adjX, $adjZ]) {
            $claim = $this->getClaimAt($adjX, $adjZ, $world);
            if ($claim && $claim->getFactionName() === $factionName) {
                return true;
            }
        }
        
        return false;
    }

    public function canClaim(int $x, int $z, string $world, string $factionName): bool {
        // Check if already claimed
        if ($this->getClaimAt($x, $z, $world)) {
            return false;
        }
        
        // Check max claims limit
        $maxClaims = $this->plugin->getConfig()->getNested("claiming.max-claims", 100);
        if ($this->getFactionClaimCount($factionName) >= $maxClaims) {
            return false;
        }
        
        // Check adjacency requirement
        if ($this->plugin->getConfig()->getNested("claiming.require-adjacent", false)) {
            $factionClaims = $this->getFactionClaimCount($factionName);
            if ($factionClaims > 0 && !$this->isAdjacentToFaction($x, $z, $world, $factionName)) {
                return false;
            }
        }
        
        return true;
    }

    public function getAllClaims(): array {
        return $this->claims;
    }

    public function getTotalClaimsCount(): int {
        return count($this->claims);
    }

    public function getClaimsInWorld(string $world): array {
        $worldClaims = [];
        foreach ($this->claims as $claim) {
            if ($claim->getWorld() === $world) {
                $worldClaims[] = $claim;
            }
        }
        return $worldClaims;
    }

    public function getClaimsInRadius(int $centerX, int $centerZ, string $world, int $radius): array {
        $claims = [];
        for ($x = $centerX - $radius; $x <= $centerX + $radius; $x++) {
            for ($z = $centerZ - $radius; $z <= $centerZ + $radius; $z++) {
                $claim = $this->getClaimAt($x, $z, $world);
                if ($claim) {
                    $claims[] = $claim;
                }
            }
        }
        return $claims;
    }
}
