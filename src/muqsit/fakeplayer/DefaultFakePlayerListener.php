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

final class DefaultFakePlayerListener implements FakePlayerListener{

	public function onPlayerAdd(Player $player) : void{
		$session = $player->getNetworkSession();
		assert($session instanceof FakePlayerNetworkSession);

		$session->registerSpecificPacketListener(RespawnPacket::class, new ClosureFakePlayerPacketListener(static function(ClientboundPacket $packet, NetworkSession $session) : void{
			$session->getPlayer()->respawn();
		}));
	}

	public function onPlayerRemove(Player $player) : void{
		// not necessary to unregister listeners because they'll automatically
		// be gc-d as nothing holds ref to player object?
	}
}