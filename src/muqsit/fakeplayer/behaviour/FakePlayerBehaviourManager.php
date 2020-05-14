<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour;

use muqsit\fakeplayer\Loader;
use pocketmine\utils\Utils;

final class FakePlayerBehaviourManager{

	/**
	 * @var string[]|FakePlayerBehaviour[]
	 * @phpstan-var class-string<FakePlayerBehaviour>[]
	 */
	private static $behaviours = [];

	public static function registerDefaults(Loader $plugin) : void{
		self::register("fakeplayer:pvp", FakePlayerPVPBehaviour::class);
	}

	public static function register(string $identifier, string $class) : void{
		Utils::testValidInstance($class, FakePlayerBehaviour::class);
		self::$behaviours[$identifier] = $class;
	}

	public static function get(Loader $loader, string $identifier) : FakePlayerBehaviour{
		/** @var FakePlayerBehaviour $behaviour */
		$behaviour = new self::$behaviours[$identifier]();
		$behaviour->init($loader);
		return $behaviour;
	}
}