<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use muqsit\fakeplayer\listener\FakePlayerListener;
use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\RespawnPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

final class DefaultFakePlayerListener implements FakePlayerListener{

	private Loader $plugin;

	public function __construct(Loader $plugin){
		$this->plugin = $plugin;
	}

	public function onPlayerAdd(Player $player) : void{
		$session = $player->getNetworkSession();
		assert($session instanceof FakePlayerNetworkSession);

		$entity_runtime_id = $player->getId();
		$session->registerSpecificPacketListener(PlayStatusPacket::class, new ClosureFakePlayerPacketListener(function(ClientboundPacket $packet, NetworkSession $session) use($entity_runtime_id) : void{
			assert($packet instanceof PlayStatusPacket);
			if($packet->status === PlayStatusPacket::PLAYER_SPAWN){
				$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(static function() use($session, $entity_runtime_id) : void{
					if($session->isConnected()){
						$packet = SetLocalPlayerAsInitializedPacket::create($entity_runtime_id);
						$serializer = PacketSerializer::encoder(new PacketSerializerContext(GlobalItemTypeDictionary::getInstance()->getDictionary()));
						$packet->encode($serializer);
						$session->handleDataPacket($packet, $serializer->getBuffer());
					}
				}), 40);
			}
		}));

		$session->registerSpecificPacketListener(RespawnPacket::class, new ClosureFakePlayerPacketListener(function(ClientboundPacket $packet, NetworkSession $session) : void{
			$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($session) : void{
				if($session->isConnected()){
					/** @var Player $player */
					$player = $session->getPlayer();
					$player->respawn();
					$fake_player = $this->plugin->getFakePlayer($player);
					foreach($fake_player->getBehaviours() as $behaviour){
						$behaviour->onRespawn($fake_player);
					}
				}
			}), 40);
		}));

		$session->registerSpecificPacketListener(ChangeDimensionPacket::class, new ClosureFakePlayerPacketListener(function(ClientboundPacket $packet, NetworkSession $session) : void{
			$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($session) : void{
				if($session->isConnected()){
					$player = $session->getPlayer();
					if($player !== null){
						$packet = PlayerActionPacket::create(
							$player->getId(),
							PlayerAction::DIMENSION_CHANGE_ACK,
							BlockPosition::fromVector3($player->getPosition()->floor()),
							BlockPosition::fromVector3($player->getPosition()->floor()),
							0
						);

						$serializer = PacketSerializer::encoder(new PacketSerializerContext(GlobalItemTypeDictionary::getInstance()->getDictionary()));
						$packet->encode($serializer);
						$session->handleDataPacket($packet, $serializer->getBuffer());
					}
				}
			}), 40);
		}));
	}

	public function onPlayerRemove(Player $player) : void{
		// not necessary to unregister listeners because they'll automatically
		// be gc-d as nothing holds ref to player object?
	}
}