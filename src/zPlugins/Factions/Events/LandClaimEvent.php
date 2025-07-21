<?php

declare(strict_types=1);

namespace zPlugins\Factions\Events;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use zPlugins\Factions\Data\Faction;
use zPlugins\Factions\Data\Claim;

class LandClaimEvent extends Event implements Cancellable {
    use CancellableTrait;

    public function __construct(
        private Faction $faction,
        private Claim $claim
    ) {}

    public function getFaction(): Faction {
        return $this->faction;
    }

    public function getClaim(): Claim {
        return $this->claim;
    }

    public function getChunk(): Claim {
        return $this->claim; // Alias for backward compatibility
    }
}
