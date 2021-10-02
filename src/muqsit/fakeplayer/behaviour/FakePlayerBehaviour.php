<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour;

use muqsit\fakeplayer\Loader;
use pocketmine\player\Player;

interface FakePlayerBehaviour{

	public static function init(Loader $plugin) : void;

	/**
	 * @param mixed[] $data
	 * @return static
	 *
	 * @phpstan-param array<string, mixed> $data
	 */
	public static function create(array $data) : self;

	public function onAddToPlayer(Player $player) : void;

	public function onRemoveFromPlayer(Player $player) : void;

	public function tick(Player $player) : void;

	public function onRespawn(Player $player) : void;
}