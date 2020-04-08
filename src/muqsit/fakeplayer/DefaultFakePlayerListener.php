<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use muqsit\fakeplayer\listener\FakePlayerListener;
use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\RespawnPacket;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class DefaultFakePlayerListener implements FakePlayerListener{

	/** @var Loader */
	private $plugin;

	public function __construct(Loader $plugin){
		$this->plugin = $plugin;
	}

	public function onPlayerAdd(Player $player) : void{
		$session = $player->getNetworkSession();
		assert($session instanceof FakePlayerNetworkSession);

		$session->registerSpecificPacketListener(RespawnPacket::class, new ClosureFakePlayerPacketListener(function(ClientboundPacket $packet, NetworkSession $session) : void{
			$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(static function(int $currentTick) use($session) : void{
				if($session->isConnected()){
					$session->getPlayer()->respawn();
				}
			}), 40);
		}));
	}

	public function onPlayerRemove(Player $player) : void{
		// not necessary to unregister listeners because they'll automatically
		// be gc-d as nothing holds ref to player object?
	}
}