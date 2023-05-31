<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour\internal;

use muqsit\fakeplayer\behaviour\FakePlayerBehaviour;
use muqsit\fakeplayer\FakePlayer;
use muqsit\fakeplayer\Loader;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use ReflectionMethod;
use ReflectionProperty;

final class UpdateMovementInternalFakePlayerBehaviour implements FakePlayerBehaviour{
	use InternalFakePlayerBehaviourTrait;

	public static function init(Loader $plugin) : void{
	}

	private static function readMovementFromPlayer(Player $player) : Vector3{
		/** @see Human::$motion */
		static $_motion = null;
		$_motion ??= new ReflectionProperty(Human::class, "motion");
		return $_motion->getValue($player)->asVector3();
	}

	private static function movePlayer(Player $player, Vector3 $dv) : void{
		/** @see Human::move() */
		static $reflection_method = null;
		$reflection_method ??= new ReflectionMethod(Human::class, "move");
		$reflection_method->getClosure($player)($dv->x, $dv->y, $dv->z);
	}

	private static function setPlayerLocation(Player $player, Location $location) : void{
		/** @see Human::$location */
		static $reflection_property = null;
		$reflection_property ??= new ReflectionProperty(Human::class, "location");
		$reflection_property->setValue($player, $location);
	}

	public function __construct(
		private FakePlayerMovementData $data
	){}

	public function onAddToPlayer(FakePlayer $player) : void{
	}

	public function onRemoveFromPlayer(FakePlayer $player) : void{
	}

	public function tick(FakePlayer $player) : void{
		$player_instance = $player->getPlayer();
		$this->data->motion = self::readMovementFromPlayer($player_instance);
		if($player_instance->hasMovementUpdate()){
			$this->data->motion = $this->data->motion->withComponents(
				abs($this->data->motion->x) <= Entity::MOTION_THRESHOLD ? 0 : null,
				abs($this->data->motion->y) <= Entity::MOTION_THRESHOLD ? 0 : null,
				abs($this->data->motion->z) <= Entity::MOTION_THRESHOLD ? 0 : null
			);

			if($this->data->motion->x != 0 || $this->data->motion->y != 0 || $this->data->motion->z != 0){
				$old_location = $player_instance->getLocation();
				self::movePlayer($player_instance, $this->data->motion);
				$new_location = $player_instance->getLocation();

				self::setPlayerLocation($player_instance, $old_location);
				$player_instance->handleMovement($new_location);
			}

			$this->data->motion = self::readMovementFromPlayer($player_instance);
		}
	}

	public function onRespawn(FakePlayer $player) : void{
	}
}