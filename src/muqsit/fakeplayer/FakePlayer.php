<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\player\Player;
use ReflectionMethod;
use ReflectionProperty;

final class FakePlayer{

	/** @var FakePlayerNetworkSession */
	private $session;

	/** @var Player */
	private $player;

	/** @var Vector3 */
	private $motion;

	public function __construct(FakePlayerNetworkSession $session){
		$this->session = $session;
		$this->player = $session->getPlayer();
		$this->init();
	}

	private function init() : void{
		$this->player->keepMovement = false;
		$this->motion = new Vector3();
		$this->session->registerSpecificPacketListener(SetActorMotionPacket::class, new ClosureFakePlayerPacketListener(function(ClientboundPacket $packet, NetworkSession $session) : void{
			/** @var SetActorMotionPacket $packet */
			if($packet->entityRuntimeId === $this->player->getId()){
				$this->motion = $packet->motion->asVector3();
			}
		}));
	}

	public function getNetworkSession() : FakePlayerNetworkSession{
		return $this->session;
	}

	public function tick() : void{
		$this->doMovementUpdates();
	}

	private function getPlayerMotion() : Vector3{
		/** @noinspection PhpUnhandledExceptionInspection */
		$rp = new ReflectionProperty($this->player, "motion");
		$rp->setAccessible(true);
		return $rp->getValue($this->player);
	}

	private function setPlayerMotion() : void{
		/** @noinspection PhpUnhandledExceptionInspection */
		$rp = new ReflectionProperty($this->player, "motion");
		$rp->setAccessible(true);
		$rp->setValue($this->player, $this->motion->asVector3());
	}

	private function syncPlayerMotion() : void{
		$this->motion = $this->getPlayerMotion()->asVector3();
	}

	private function doMovementUpdates() : void{
		$this->setPlayerMotion();
		$this->tryChangeMovement();
		$this->syncPlayerMotion();

		if($this->player->hasMovementUpdate()){
			if(abs($this->motion->x) <= Entity::MOTION_THRESHOLD){
				$this->motion->x = 0;
			}

			if(abs($this->motion->y) <= Entity::MOTION_THRESHOLD){
				$this->motion->y = 0;
			}

			if(abs($this->motion->z) <= Entity::MOTION_THRESHOLD){
				$this->motion->z = 0;
			}

			if($this->motion->x != 0 or $this->motion->y != 0 or $this->motion->z != 0){
				$this->setPlayerMotion();
				$this->move($this->motion->x, $this->motion->y, $this->motion->z);
				$this->syncPlayerMotion();
			}
		}

		$this->updateMovement();
	}

	private function tryChangeMovement() : void{
		static $reflection_method = null;
		if($reflection_method === null){
			$reflection_method = new ReflectionMethod(Human::class, "tryChangeMovement");
			$reflection_method->setAccessible(true);
		}
		$reflection_method->getClosure($this->player)();
	}

	private function updateMovement() : void{
		static $reflection_method = null;
		if($reflection_method === null){
			$reflection_method = new ReflectionMethod(Human::class, "updateMovement");
			$reflection_method->setAccessible(true);
		}
		$reflection_method->getClosure($this->player)();
	}

	private function move(float $dx, float $dy, float $dz) : void{
		static $reflection_method = null;
		if($reflection_method === null){
			$reflection_method = new ReflectionMethod(Human::class, "move");
			$reflection_method->setAccessible(true);
		}
		$reflection_method->getClosure($this->player)($dx, $dy, $dz);
	}
}