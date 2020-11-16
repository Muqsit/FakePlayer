<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use InvalidArgumentException;
use muqsit\fakeplayer\behaviour\FakePlayerBehaviourManager;
use muqsit\fakeplayer\listener\FakePlayerListener;
use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use pocketmine\entity\Skin;
use pocketmine\event\Listener;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\player\Player;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\uuid\UUID;
use ReflectionMethod;
use ReflectionProperty;

final class Loader extends PluginBase implements Listener{

	/** @var FakePlayerListener[] */
	private $listeners = [];

	/** @var FakePlayer[] */
	private $fake_players = [];

	protected function onEnable() : void{
		$cmd = new FakePlayerCommand("fakeplayer", "Control fake player", null, ["fp"]);
		$cmd->init($this);
		$this->getServer()->getCommandMap()->register($this->getName(), $cmd);

		$this->registerListener(new DefaultFakePlayerListener($this));
		FakePlayerBehaviourManager::registerDefaults($this);

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
				$this->addPlayer(UUID::fromString($uuid), $xuid, $gamertag, $skin, $data["extra_data"] ?? [], $data["behaviours"] ?? []);
			}
		}), 20);
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
		return isset($this->fake_players[$player->getUniqueId()->toBinary()]);
	}

	public function getFakePlayer(Player $player) : ?FakePlayer{
		return $this->fake_players[$player->getUniqueId()->toBinary()] ?? null;
	}

	/**
	 * @param UUID $uuid
	 * @param string $xuid
	 * @param string $username
	 * @param Skin $skin
	 * @param array<string, mixed> $extra_data
	 * @param string[] $behaviours
	 * @return Player
	 */
	public function addPlayer(UUID $uuid, string $xuid, string $username, Skin $skin, array $extra_data, array $behaviours = []) : Player{
		$_skin_data = $this->getResource("skin.rgba");
		$skin_data = stream_get_contents($_skin_data);
		fclose($_skin_data);

		$server = $this->getServer();
		$network = $server->getNetwork();

		$session = new FakePlayerNetworkSession($server, $network->getSessionManager(), PacketPool::getInstance(), new FakePacketSender(), ZlibCompressor::getInstance(), $server->getIp(), $server->getPort());
		$network->getSessionManager()->add($session);

		$rp = new ReflectionProperty(NetworkSession::class, "info");
		$rp->setAccessible(true);
		$rp->setValue($session, new XboxLivePlayerInfo($xuid, $username, $uuid, $skin, "en_US", $extra_data));

		$rp = new ReflectionMethod(NetworkSession::class, "onServerLoginSuccess");
		$rp->setAccessible(true);
		$rp->invoke($session);

		$rp = new ReflectionMethod(NetworkSession::class, "beginSpawnSequence");
		$rp->setAccessible(true);
		$rp->invoke($session);

		$session->getPlayer()->setViewDistance(4);

		$player = $session->getPlayer();
		assert($player !== null);
		$this->fake_players[$player->getUniqueId()->toBinary()] = $fake_player = new FakePlayer($session);
		foreach($behaviours as $behaviour){
			$fake_player->addBehaviour(FakePlayerBehaviourManager::get($this, $behaviour));
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

		unset($this->fake_players[$player->getUniqueId()->toBinary()]);
		if($disconnect){
			$player->disconnect("Removed");
		}

		foreach($this->listeners as $listener){
			$listener->onPlayerRemove($player);
		}
	}
}