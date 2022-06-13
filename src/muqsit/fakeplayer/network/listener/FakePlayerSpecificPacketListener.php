<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\network\listener;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;

/**
 * @internal
 */
final class FakePlayerSpecificPacketListener implements FakePlayerPacketListener{

	/** @var FakePlayerPacketListener[][] */
	private array $listeners = [];

	/**
	 * @param string $packet
	 * @param FakePlayerPacketListener $listener
	 *
	 * @phpstan-param class-string<ClientboundPacket> $packet
	 */
	public function register(string $packet, FakePlayerPacketListener $listener) : void{
		$this->listeners[$packet][spl_object_id($listener)] = $listener;
	}

	public function unregister(string $packet, FakePlayerPacketListener $listener) : void{
		if(isset($this->listeners[$packet])){
			unset($this->listeners[$packet][spl_object_id($listener)]);
			if(count($this->listeners[$packet]) === 0){
				unset($this->listeners[$packet]);
			}
		}
	}

	public function isEmpty() : bool{
		return count($this->listeners) === 0;
	}

	public function onPacketSend(ClientboundPacket $packet, NetworkSession $session) : void{
		if(isset($this->listeners[$class = $packet::class])){
			foreach($this->listeners[$class] as $listener){
				$listener->onPacketSend($packet, $session);
			}
		}
	}
}