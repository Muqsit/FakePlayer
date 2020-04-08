<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\network\listener;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;

interface FakePlayerPacketListener{

	public function onPacketSend(ClientboundPacket $packet, NetworkSession $session) : void;
}