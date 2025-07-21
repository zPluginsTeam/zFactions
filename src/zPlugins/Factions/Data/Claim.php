<?php

declare(strict_types=1);

namespace zPlugins\Factions\Data;

class Claim {

    private int $x;
    private int $z;
    private string $world;
    private string $factionName;
    private int $claimedTime;

    public function __construct(int $x, int $z, string $world, string $factionName) {
        $this->x = $x;
        $this->z = $z;
        $this->world = $world;
        $this->factionName = $factionName;
        $this->claimedTime = time();
    }

    public function getX(): int {
        return $this->x;
    }

    public function getZ(): int {
        return $this->z;
    }

    public function getWorld(): string {
        return $this->world;
    }

    public function getFactionName(): string {
        return $this->factionName;
    }

    public function setFactionName(string $factionName): void {
        $this->factionName = $factionName;
    }

    public function getClaimedTime(): int {
        return $this->claimedTime;
    }

    public function getFormattedClaimedDate(): string {
        return date("Y-m-d H:i:s", $this->claimedTime);
    }

    public function getCoordinates(): array {
        return [$this->x, $this->z];
    }

    public function toString(): string {
        return "Claim({$this->world}:{$this->x}:{$this->z} -> {$this->factionName})";
    }

    public function equals(Claim $other): bool {
        return $this->x === $other->x && 
               $this->z === $other->z && 
               $this->world === $other->world;
    }

    public function isAdjacent(Claim $other): bool {
        if ($this->world !== $other->world) {
            return false;
        }
        
        $xDiff = abs($this->x - $other->x);
        $zDiff = abs($this->z - $other->z);
        
        return ($xDiff === 1 && $zDiff === 0) || ($xDiff === 0 && $zDiff === 1);
    }

    public function getDistance(Claim $other): float {
        if ($this->world !== $other->world) {
            return PHP_FLOAT_MAX;
        }
        
        $xDiff = $this->x - $other->x;
        $zDiff = $this->z - $other->z;
        
        return sqrt($xDiff * $xDiff + $zDiff * $zDiff);
    }

    public function toArray(): array {
        return [
            "x" => $this->x,
            "z" => $this->z,
            "world" => $this->world,
            "faction" => $this->factionName,
            "claimed_time" => $this->claimedTime
        ];
    }

    public static function fromArray(array $data): Claim {
        $claim = new self(
            $data["x"],
            $data["z"],
            $data["world"],
            $data["faction"]
        );
        
        if (isset($data["claimed_time"])) {
            $claim->claimedTime = $data["claimed_time"];
        }
        
        return $claim;
    }
}
