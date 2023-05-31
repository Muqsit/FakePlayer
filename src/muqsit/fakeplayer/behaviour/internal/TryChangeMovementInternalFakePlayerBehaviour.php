<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour\internal;

use muqsit\fakeplayer\behaviour\FakePlayerBehaviour;
use muqsit\fakeplayer\FakePlayer;
use muqsit\fakeplayer\Loader;
use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use muqsit\fakeplayer\network\listener\FakePlayerPacketListener;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\player\Player;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

final class TryChangeMovementInternalFakePlayerBehaviour implements FakePlayerBehaviour{
	use InternalFakePlayerBehaviourTrait;

	public static function init(Loader $plugin) : void{
	}

	private static function changeDrag(Player $player) : void{
		/** @see Player::$drag */
		static $_drag = null;
		$_drag ??= new ReflectionProperty(Entity::class, "drag");
		$_drag->setValue($player, $_drag->getValue($player) * 8);
	}

	private static function writeMovementToPlayer(Player $player, Vector3 $motion) : void{
		/** @see Player::$motion */
		static $_motion = null;
		$_motion ??= new ReflectionProperty(Entity::class, "motion");
		$_motion->setValue($player, $motion->asVector3());
	}

	private static function tryChangeMovement(Player $player) : void{
		/** @see Human::tryChangeMovement() */
		static $reflection_method = null;
		$reflection_method ??= new ReflectionMethod(Human::class, "tryChangeMovement");
		$reflection_method->getClosure($player)();
	}

	private ?FakePlayerPacketListener $motion_packet_listener = null;

	public function __construct(
		private FakePlayerMovementData $data
	){}

	public function onAddToPlayer(FakePlayer $player) : void{
		if($this->motion_packet_listener !== null){
			throw new RuntimeException("Listener was already added");
		}

		$player_instance = $player->getPlayer();
		$player_instance->keepMovement = false;
		self::changeDrag($player_instance);

		$player_id = $player_instance->getId();
		$this->motion_packet_listener = new ClosureFakePlayerPacketListener(function(ClientboundPacket $packet, NetworkSession $session) use($player_id) : void{
			/** @var SetActorMotionPacket $packet */
			if($packet->actorRuntimeId === $player_id){
				$this->data->motion = $packet->motion->asVector3();
			}
		});
		$player->getNetworkSession()->registerSpecificPacketListener(SetActorMotionPacket::class, $this->motion_packet_listener);
	}

	public function onRemoveFromPlayer(FakePlayer $player) : void{
		$player->getNetworkSession()->unregisterSpecificPacketListener(
			SetActorMotionPacket::class,
			$this->motion_packet_listener ?? throw new RuntimeException("Listener was already removed")
		);
		$this->motion_packet_listener = null;
	}

	public function tick(FakePlayer $player) : void{
		$player_instance = $player->getPlayer();
		self::writeMovementToPlayer($player_instance, $this->data->motion);
		self::tryChangeMovement($player_instance);
	}

	public function onRespawn(FakePlayer $player) : void{
	}
}