<?php

declare(strict_types=1);

namespace zPlugins\Factions\Events;

use pocketmine\event\Event;
use zPlugins\Factions\Data\Faction;

class PowerChangeEvent extends Event {

    public function __construct(
        private Faction $faction,
        private int $oldPower,
        private int $newPower
    ) {}

    public function getFaction(): Faction {
        return $this->faction;
    }

    public function getOldPower(): int {
        return $this->oldPower;
    }

    public function getNewPower(): int {
        return $this->newPower;
    }

    public function getPowerChange(): int {
        return $this->newPower - $this->oldPower;
    }
}
