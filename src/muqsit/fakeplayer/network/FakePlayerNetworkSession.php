<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\network;

use muqsit\fakeplayer\network\listener\FakePlayerPacketListener;
use muqsit\fakeplayer\network\listener\FakePlayerSpecificPacketListener;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\PacketSender;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\NetworkSessionManager;
use pocketmine\player\Player;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\Server;
use ReflectionMethod;
use ReflectionProperty;

class FakePlayerNetworkSession extends NetworkSession{

	/** @var PromiseResolver */
	private $playerResolver;

	/** @var FakePlayerPacketListener[] */
	private $packet_listeners = [];

	/** @var FakePlayerSpecificPacketListener|null */
	private $specific_packet_listener;

	public function __construct(Server $server, NetworkSessionManager $manager, PacketPool $packetPool, PacketSender $sender, PacketBroadcaster $broadcaster, Compressor $compressor, string $ip, int $port){
        parent::__construct($server, $manager, $packetPool, $sender, $broadcaster, $compressor, $ip, $port);
		$this->playerResolver = new PromiseResolver;
	}

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

	protected function createPlayer(): void{
		$getProp = function (string $name){
			$rp = new ReflectionProperty(NetworkSession::class, $name);
			$rp->setAccessible(true);
			return $rp->getValue($this);
		};

		$server = $getProp('server');
		$info = $getProp('info');
		$authenticated = $getProp('authenticated');
		$cachedOfflinePlayerData = $getProp('cachedOfflinePlayerData');

		$server->createPlayer($this, $info, $authenticated, $cachedOfflinePlayerData)->onCompletion(
			function (Player $player){
				$rm = new ReflectionMethod(NetworkSession::class, 'onPlayerCreated');
				$rm->setAccessible(true);
				$rm->invoke($this, $player);
				$this->playerResolver->resolve($player);
			},
			fn() => $this->disconnect("Player creation failed")
		);
	}

	public function getPlayerPromise() : Promise{
		return $this->playerResolver->getPromise();
	}
}