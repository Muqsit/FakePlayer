<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use InvalidArgumentException;
use muqsit\fakeplayer\behaviour\FakePlayerBehaviourFactory;
use muqsit\fakeplayer\info\FakePlayerInfo;
use muqsit\fakeplayer\listener\FakePlayerListener;
use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use pocketmine\entity\Skin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;
use pocketmine\network\mcpe\StandardPacketBroadcaster;
use pocketmine\player\Player;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionMethod;
use ReflectionProperty;

final class Loader extends PluginBase implements Listener{

	/** @var FakePlayerListener[] */
	private array $listeners = [];

	/** @var FakePlayer[] */
	private array $fake_players = [];

	protected function onEnable() : void{
		$cmd = new FakePlayerCommand("fakeplayer", "Control fake player", null, ["fp"]);
		$cmd->init($this);
		$this->getServer()->getCommandMap()->register($this->getName(), $cmd);

		$this->registerListener(new DefaultFakePlayerListener($this));
		FakePlayerBehaviourFactory::registerDefaults($this);

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			foreach($this->fake_players as $player){
				$player->tick();
			}
		}), 1);

		$this->saveResource("players.json");
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() : void{
			$players = json_decode(file_get_contents($this->getDataFolder() . "players.json"), true, 512, JSON_THROW_ON_ERROR);

			$_skin_data = $this->getResource("skin.rgba");
			$skin_data = stream_get_contents($_skin_data);
			fclose($_skin_data);
			$skin = new Skin("Standard_Custom", $skin_data);

			foreach($players as $uuid => $data){
				["xuid" => $xuid, "gamertag" => $gamertag] = $data;
				$this->addPlayer(new FakePlayerInfo(Uuid::fromString($uuid), $xuid, $gamertag, $skin, $data["extra_data"] ?? [], $data["behaviours"] ?? []));
			}
		}), 20);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function registerListener(FakePlayerListener $listener) : void{
		$this->listeners[spl_object_id($listener)] = $listener;
		$server = $this->getServer();
		foreach($this->fake_players as $uuid => $_){
			$listener->onPlayerAdd($server->getPlayerByRawUUID($uuid));
		}
	}

	public function unregisterListener(FakePlayerListener $listener) : void{
		unset($this->listeners[spl_object_id($listener)]);
	}

	public function isFakePlayer(Player $player) : bool{
		return isset($this->fake_players[$player->getUniqueId()->getBytes()]);
	}

	public function getFakePlayer(Player $player) : ?FakePlayer{
		return $this->fake_players[$player->getUniqueId()->getBytes()] ?? null;
	}

	public function addPlayer(FakePlayerInfo $info) : Player{
		$server = $this->getServer();
		$network = $server->getNetwork();

		$session = new FakePlayerNetworkSession($server, $network->getSessionManager(), PacketPool::getInstance(), new FakePacketSender(), new StandardPacketBroadcaster($server), ZlibCompressor::getInstance(), $server->getIp(), $server->getPort());
		$network->getSessionManager()->add($session);

		$rp = new ReflectionProperty(NetworkSession::class, "info");
		$rp->setAccessible(true);
		$rp->setValue($session, new XboxLivePlayerInfo($info->xuid, $info->username, $info->uuid, $info->skin, "en_US" /* TODO: Make locale configurable? */, $info->extra_data));

		$rp = new ReflectionMethod(NetworkSession::class, "onServerLoginSuccess");
		$rp->setAccessible(true);
		$rp->invoke($session);

		$packet = new ResourcePackClientResponsePacket();
		$packet->status = ResourcePackClientResponsePacket::STATUS_COMPLETED;
		$serializer = PacketSerializer::encoder(new PacketSerializerContext(GlobalItemTypeDictionary::getInstance()->getDictionary()));
		$packet->encode($serializer);
		$session->handleDataPacket($packet, $serializer->getBuffer());

		$rp = new ReflectionMethod(NetworkSession::class, "beginSpawnSequence");
		$rp->setAccessible(true);
		$rp->invoke($session);

		$session->getPlayer()->setViewDistance(4);

		$player = $session->getPlayer();
		assert($player !== null);
		$this->fake_players[$player->getUniqueId()->getBytes()] = $fake_player = new FakePlayer($session);
		foreach($info->behaviours as $behaviour_identifier => $behaviour_data){
			$fake_player->addBehaviour(FakePlayerBehaviourFactory::create($behaviour_identifier, $behaviour_data));
		}

		foreach($this->listeners as $listener){
			$listener->onPlayerAdd($player);
		}

		if(!$player->isAlive()){
			$player->respawn();
		}

		return $player;
	}

	public function removePlayer(Player $player, bool $disconnect = true) : void{
		if(!$this->isFakePlayer($player)){
			throw new InvalidArgumentException("Invalid Player supplied, expected a fake player, got " . $player->getName());
		}

		if(!isset($this->fake_players[$id = $player->getUniqueId()->getBytes()])){
			return;
		}

		$this->fake_players[$id]->destroy();
		unset($this->fake_players[$id]);

		if($disconnect){
			$player->disconnect("Removed");
		}

		foreach($this->listeners as $listener){
			$listener->onPlayerRemove($player);
		}
	}

	/**
	 * @param PlayerQuitEvent $event
	 * @priority MONITOR
	 */
	public function onPlayerQuit(PlayerQuitEvent $event) : void{
		$player = $event->getPlayer();
		try{
			$this->removePlayer($player, false);
		}catch(InvalidArgumentException $e){
		}
	}
}