<?php

declare(strict_types=1);

namespace zPlugins\Factions\Data;

use zPlugins\Factions\Utils\Rank;

class FactionMember {

    private string $playerName;
    private Rank $rank;
    private int $joinedTime;

    public function __construct(string $playerName, ?Rank $rank = null) {
        $this->playerName = $playerName;
        $this->rank = $rank ?? Rank::MEMBER();
        $this->joinedTime = time();
    }

    public function getPlayerName(): string {
        return $this->playerName;
    }

    public function setPlayerName(string $playerName): void {
        $this->playerName = $playerName;
    }

    public function getRank(): Rank {
        return $this->rank;
    }

    public function setRank(Rank $rank): void {
        $this->rank = $rank;
    }

    public function getJoinedTime(): int {
        return $this->joinedTime;
    }

    public function setJoinedTime(int $time): void {
        $this->joinedTime = $time;
    }

    public function getFormattedJoinDate(): string {
        return date("Y-m-d H:i:s", $this->joinedTime);
    }

    public function hasPermission(string $permission): bool {
        return $this->rank->hasPermission($permission);
    }

    public function toArray(): array {
        return [
            "player_name" => $this->playerName,
            "rank" => $this->rank->getName(),
            "joined_time" => $this->joinedTime
        ];
    }

    public function fromArray(array $data): void {
        $this->playerName = $data["player_name"] ?? "";
        $this->joinedTime = $data["joined_time"] ?? time();
        
        $rankName = $data["rank"] ?? "Member";
        $this->rank = Rank::fromName($rankName);
    }
}
