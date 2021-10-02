<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour;

use muqsit\fakeplayer\FakePlayer;
use muqsit\fakeplayer\Loader;

interface FakePlayerBehaviour{

	public static function init(Loader $plugin) : void;

	/**
	 * @param mixed[] $data
	 * @return static
	 *
	 * @phpstan-param array<string, mixed> $data
	 */
	public static function create(array $data) : self;

	public function onAddToPlayer(FakePlayer $player) : void;

	public function onRemoveFromPlayer(FakePlayer $player) : void;

	public function tick(FakePlayer $player) : void;

	public function onRespawn(FakePlayer $player) : void;
}