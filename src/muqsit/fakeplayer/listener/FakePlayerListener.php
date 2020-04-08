<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\listener;

use pocketmine\player\Player;

interface FakePlayerListener{

	public function onPlayerAdd(Player $player) : void;

	public function onPlayerRemove(Player $player) : void;
}