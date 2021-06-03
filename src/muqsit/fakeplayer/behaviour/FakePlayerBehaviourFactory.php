<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour;

use muqsit\fakeplayer\Loader;
use pocketmine\utils\Utils;

final class FakePlayerBehaviourFactory{

	/**
	 * @var string[]|FakePlayerBehaviour[]
	 * @phpstan-var class-string<FakePlayerBehaviour>[]
	 */
	private static array $behaviours = [];

	public static function registerDefaults() : void{
		self::register("fakeplayer:pvp", FakePlayerPVPBehaviour::class);
	}

	/**
	 * @param string $identifier
	 * @param string $class
	 *
	 * @phpstan-param class-string<FakePlayerBehaviour> $class
	 */
	public static function register(string $identifier, string $class) : void{
		Utils::testValidInstance($class, FakePlayerBehaviour::class);
		self::$behaviours[$identifier] = $class;
	}

	/**
	 * @param Loader $loader
	 * @param string $identifier
	 * @param mixed[] $data
	 * @return FakePlayerBehaviour
	 *
	 * @phpstan-param array<string, mixed> $data
	 */
	public static function get(Loader $loader, string $identifier, array $data) : FakePlayerBehaviour{
		/** @var FakePlayerBehaviour $behaviour */
		$behaviour = self::$behaviours[$identifier]::create($data);
		$behaviour->init($loader);
		return $behaviour;
	}
}