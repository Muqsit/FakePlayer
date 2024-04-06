<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use pocketmine\network\mcpe\PacketSender;

final class FakePacketSender implements PacketSender{

	public function send(string $payload, bool $immediate, ?int $receiptId) : void{
	}

	public function close(string $reason = "unknown reason") : void{
	}
}