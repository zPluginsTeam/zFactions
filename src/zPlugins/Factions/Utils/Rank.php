<?php

declare(strict_types=1);

namespace zPlugins\Factions\Utils;

use zPlugins\Factions\Main;

class Rank {

    private string $name;
    private string $prefix;
    private int $level;
    private array $permissions;

    public function __construct(string $name, string $prefix, int $level, array $permissions = []) {
        $this->name = $name;
        $this->prefix = $prefix;
        $this->level = $level;
        $this->permissions = $permissions;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getPrefix(): string {
        return $this->prefix;
    }

    public function getLevel(): int {
        return $this->level;
    }

    public function getPermissions(): array {
        return $this->permissions;
    }

    public function hasPermission(string $permission): bool {
        return in_array($permission, $this->permissions) || in_array("*", $this->permissions);
    }

    public function addPermission(string $permission): void {
        if (!$this->hasPermission($permission)) {
            $this->permissions[] = $permission;
        }
    }

    public function removePermission(string $permission): void {
        $key = array_search($permission, $this->permissions);
        if ($key !== false) {
            unset($this->permissions[$key]);
            $this->permissions = array_values($this->permissions);
        }
    }

    public static function RECRUIT(): Rank {
        $config = Main::getInstance()->getConfig();
        return new Rank(
            $config->getNested("ranks.recruit.name", "Recruit"),
            $config->getNested("ranks.recruit.prefix", "[R]"),
            1,
            $config->getNested("ranks.recruit.permissions", [
                "faction.chat",
                "faction.home"
            ])
        );
    }

    public static function MEMBER(): Rank {
        $config = Main::getInstance()->getConfig();
        return new Rank(
            $config->getNested("ranks.member.name", "Member"),
            $config->getNested("ranks.member.prefix", "[M]"),
            2,
            $config->getNested("ranks.member.permissions", [
                "faction.chat",
                "faction.home",
                "faction.deposit"
            ])
        );
    }

    public static function OFFICER(): Rank {
        $config = Main::getInstance()->getConfig();
        return new Rank(
            $config->getNested("ranks.officer.name", "Officer"),
            $config->getNested("ranks.officer.prefix", "[O]"),
            3,
            $config->getNested("ranks.officer.permissions", [
                "faction.chat",
                "faction.home",
                "faction.deposit",
                "faction.invite",
                "faction.claim",
                "faction.unclaim"
            ])
        );
    }

    public static function COLEADER(): Rank {
        $config = Main::getInstance()->getConfig();
        return new Rank(
            $config->getNested("ranks.coleader.name", "Co-Leader"),
            $config->getNested("ranks.coleader.prefix", "[C]"),
            4,
            $config->getNested("ranks.coleader.permissions", [
                "faction.chat",
                "faction.home",
                "faction.deposit",
                "faction.withdraw",
                "faction.invite",
                "faction.kick",
                "faction.promote",
                "faction.demote",
                "faction.claim",
                "faction.unclaim",
                "faction.ally",
                "faction.enemy"
            ])
        );
    }

    public static function LEADER(): Rank {
        $config = Main::getInstance()->getConfig();
        return new Rank(
            $config->getNested("ranks.leader.name", "Leader"),
            $config->getNested("ranks.leader.prefix", "[L]"),
            5,
            $config->getNested("ranks.leader.permissions", ["*"])
        );
    }

    public static function fromName(string $name): Rank {
        return match (strtolower($name)) {
            "recruit" => self::RECRUIT(),
            "officer" => self::OFFICER(),
            "coleader", "co-leader" => self::COLEADER(),
            "leader" => self::LEADER(),
            default => self::MEMBER()
        };
    }

    public static function fromLevel(int $level): Rank {
        return match ($level) {
            1 => self::RECRUIT(),
            3 => self::OFFICER(),
            4 => self::COLEADER(),
            5 => self::LEADER(),
            default => self::MEMBER()
        };
    }

    public static function getAllRanks(): array {
        return [
            self::RECRUIT(),
            self::MEMBER(),
            self::OFFICER(),
            self::COLEADER(),
            self::LEADER()
        ];
    }

    public function canPromote(Rank $targetRank): bool {
        return $this->level > $targetRank->level;
    }

    public function canDemote(Rank $targetRank): bool {
        return $this->level > $targetRank->level;
    }

    public function canTarget(Rank $targetRank): bool {
        return $this->level >= $targetRank->level;
    }

    public function equals(Rank $other): bool {
        return $this->name === $other->name && $this->level === $other->level;
    }

    public function __toString(): string {
        return $this->name;
    }
}
