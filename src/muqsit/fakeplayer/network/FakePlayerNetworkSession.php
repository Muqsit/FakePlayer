<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\network;

use muqsit\fakeplayer\Loader;
use muqsit\fakeplayer\network\listener\FakePlayerPacketListener;
use muqsit\fakeplayer\network\listener\FakePlayerSpecificPacketListener;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\Server;

class FakePlayerNetworkSession extends NetworkSession{

	/** @var FakePlayerPacketListener[] */
	private $packet_listeners = [];

	/** @var FakePlayerSpecificPacketListener|null */
	private $specific_packet_listener;

	public function registerPacketListener(FakePlayerPacketListener $listener) : void{
		$this->packet_listeners[spl_object_id($listener)] = $listener;
	}

	public function unregisterPacketListener(FakePlayerPacketListener $listener) : void{
		unset($this->packet_listeners[spl_object_id($listener)]);
	}

	public function registerSpecificPacketListener(string $packet, FakePlayerPacketListener $listener) : void{
		if($this->specific_packet_listener === null){
			$this->specific_packet_listener = new FakePlayerSpecificPacketListener();
			$this->registerPacketListener($this->specific_packet_listener);
		}
		$this->specific_packet_listener->register($packet, $listener);
	}

	public function unregisterSpecificPacketListener(string $packet, FakePlayerPacketListener $listener) : void{
		if($this->specific_packet_listener !== null){
			$this->specific_packet_listener->unregister($packet, $listener);
			if($this->specific_packet_listener->isEmpty()){
				$this->unregisterPacketListener($this->specific_packet_listener);
				$this->specific_packet_listener = null;
			}
		}
	}

	public function addToSendBuffer(ClientboundPacket $packet) : void{
		parent::addToSendBuffer($packet);
		foreach($this->packet_listeners as $listener){
			$listener->onPacketSend($packet, $this);
		}
	}

	public function onPlayerDestroyed(string $reason, bool $notify = true) : void{
		parent::onPlayerDestroyed($reason);

		/** @var Loader $loader */
		$loader = Server::getInstance()->getPluginManager()->getPlugin("FakePlayer");
		$loader->removePlayer($this->getPlayer(), false);
	}
}