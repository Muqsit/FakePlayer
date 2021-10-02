<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour;

use muqsit\fakeplayer\Loader;

final class FakePlayerBehaviourFactory{

	/**
	 * @var string[]|FakePlayerBehaviour[]
	 * @phpstan-var class-string<FakePlayerBehaviour>[]
	 */
	private static array $behaviours = [];

	public static function registerDefaults(Loader $plugin) : void{
		self::register($plugin, "fakeplayer:auto_equip_armor", AutoEquipArmorFakePlayerBehaviour::class);
		self::register($plugin, "fakeplayer:pvp", PvPFakePlayerBehaviour::class);
	}

	/**
	 * @param Loader $plugin
	 * @param string $identifier
	 * @param string|FakePlayerBehaviour $class
	 *
	 * @phpstan-param class-string<FakePlayerBehaviour> $class
	 */
	public static function register(Loader $plugin, string $identifier, string $class) : void{
		self::$behaviours[$identifier] = $class;
		$class::init($plugin);
	}

	/**
	 * @param string $identifier
	 * @param mixed[] $data
	 * @return FakePlayerBehaviour
	 *
	 * @phpstan-param array<string, mixed> $data
	 */
	public static function create(string $identifier, array $data) : FakePlayerBehaviour{
		return self::$behaviours[$identifier]::create($data);
	}
}