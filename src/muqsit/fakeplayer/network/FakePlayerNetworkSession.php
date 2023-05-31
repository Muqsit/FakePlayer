<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\network;

use muqsit\fakeplayer\network\listener\FakePlayerPacketListener;
use muqsit\fakeplayer\network\listener\FakePlayerSpecificPacketListener;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\PacketSender;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;
use pocketmine\network\NetworkSessionManager;
use pocketmine\player\Player;
use pocketmine\promise\PromiseResolver;
use pocketmine\Server;
use ReflectionMethod;
use ReflectionProperty;

class FakePlayerNetworkSession extends NetworkSession{

	/** @var FakePlayerPacketListener[] */
	private array $packet_listeners = [];

	/** @var PromiseResolver<Player>|null */
	private ?PromiseResolver $player_add_resolver;

	private ?FakePlayerSpecificPacketListener $specific_packet_listener = null;

	/**
	 * @param Server $server
	 * @param NetworkSessionManager $manager
	 * @param PacketPool $packetPool
	 * @param PacketSerializerContext $packetSerializerContext
	 * @param PacketSender $sender
	 * @param PacketBroadcaster $broadcaster
	 * @param EntityEventBroadcaster $entityEventBroadcaster
	 * @param Compressor $compressor
	 * @param string $ip
	 * @param int $port
	 * @param PromiseResolver<Player> $player_add_resolver
	 */
	public function __construct(
		Server $server,
		NetworkSessionManager $manager,
		PacketPool $packetPool,
		PacketSerializerContext $packetSerializerContext,
		PacketSender $sender,
		PacketBroadcaster $broadcaster,
		EntityEventBroadcaster $entityEventBroadcaster,
		Compressor $compressor,
		string $ip,
		int $port,
		PromiseResolver $player_add_resolver
	){
        parent::__construct($server, $manager, $packetPool, $packetSerializerContext, $sender, $broadcaster, $entityEventBroadcaster, $compressor, $ip, $port);
		$this->player_add_resolver = $player_add_resolver;

		// do not store the resolver eternally
		$this->player_add_resolver->getPromise()->onCompletion(function(Player $_) : void{
			$this->player_add_resolver = null;
		}, function() : void{ $this->player_add_resolver = null; });
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

	public function addToSendBuffer(string $buffer) : void{
		parent::addToSendBuffer($buffer);
		$rp = new ReflectionProperty(NetworkSession::class, 'packetPool');
		$packetPool = $rp->getValue($this);
		$packet = $packetPool->getPacket($buffer);
		$packet->decode(PacketSerializer::decoder($buffer, 0, $this->getPacketSerializerContext()));
		foreach($this->packet_listeners as $listener){
			$listener->onPacketSend($packet, $this);
		}
	}

	protected function createPlayer(): void{
		$get_prop = function(string $name) : mixed{
			$rp = new ReflectionProperty(NetworkSession::class, $name);
			return $rp->getValue($this);
		};

		$info = $get_prop("info");
		$authenticated = $get_prop("authenticated");
		$cached_offline_player_data = $get_prop("cachedOfflinePlayerData");
		Server::getInstance()->createPlayer($this, $info, $authenticated, $cached_offline_player_data)->onCompletion(function(Player $player) : void{
			$this->onPlayerCreated($player);
		}, function() : void{
			$this->disconnect("Player creation failed");
			$this->player_add_resolver->reject();
		});
	}

	private function onPlayerCreated(Player $player) : void{
		// call parent private method
		$rm = new ReflectionMethod(NetworkSession::class, "onPlayerCreated");
		$rm->invoke($this, $player);
		$this->player_add_resolver->resolve($player);
	}
}