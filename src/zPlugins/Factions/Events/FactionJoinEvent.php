<?php

declare(strict_types=1);

namespace zPlugins\Factions\Events;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use pocketmine\player\Player;
use zPlugins\Factions\Data\Faction;

class FactionJoinEvent extends Event implements Cancellable {
    use CancellableTrait;

    public function __construct(
        private Faction $faction,
        private Player $player
    ) {}

    public function getFaction(): Faction {
        return $this->faction;
    }

    public function getPlayer(): Player {
        return $this->player;
    }
}
