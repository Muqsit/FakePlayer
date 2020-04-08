<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour;

use muqsit\fakeplayer\Loader;
use pocketmine\player\Player;

interface FakePlayerBehaviour{

	public function init(Loader $plugin) : void;

	public function tick(Player $player) : void;
}