<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use InvalidArgumentException;
use muqsit\fakeplayer\behaviour\FakePlayerBehaviourFactory;
use muqsit\fakeplayer\behaviour\internal\FakePlayerMovementData;
use muqsit\fakeplayer\behaviour\internal\TryChangeMovementInternalFakePlayerBehaviour;
use muqsit\fakeplayer\behaviour\internal\UpdateMovementInternalFakePlayerBehaviour;
use muqsit\fakeplayer\info\FakePlayerInfo;
use muqsit\fakeplayer\listener\FakePlayerListener;
use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use pocketmine\command\PluginCommand;
use pocketmine\entity\Skin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializerContext;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\network\mcpe\protocol\types\login\ClientData;
use pocketmine\network\mcpe\StandardEntityEventBroadcaster;
use pocketmine\network\mcpe\StandardPacketBroadcaster;
use pocketmine\player\Player;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\plugin\PluginBase;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Limits;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use function array_merge;

final class Loader extends PluginBase implements Listener{

	/** @var FakePlayerListener[] */
	private array $listeners = [];

	/** @var FakePlayer[] */
	private array $fake_players = [];

	/** @var array<string, mixed> */
	private array $default_extra_data = [
		"CurrentInputMode" => InputMode::MOUSE_KEYBOARD, /** @see ClientData::$CurrentInputMode */
		"DefaultInputMode" => InputMode::MOUSE_KEYBOARD, /** @see ClientData::$DefaultInputMode */
		"DeviceOS" => DeviceOS::DEDICATED, /** @see ClientData::$DeviceOS */
		"GameVersion" => ProtocolInfo::MINECRAFT_VERSION_NETWORK, /** @see ClientData::$GameVersion */
	];

	protected function onEnable() : void{
		$client_data = new ReflectionClass(ClientData::class);
		foreach($client_data->getProperties() as $property){
			$comment = $property->getDocComment();
			if($comment === false || !in_array("@required", explode(PHP_EOL, $comment), true)){
				continue;
			}

			$property_name = $property->getName();
			if(isset($this->default_extra_data[$property_name])){
				continue;
			}

			$this->default_extra_data[$property_name] = $property->hasDefaultValue() ? $property->getDefaultValue() : match($property->getType()?->getName()){
				"string" => "",
				"int" => 0,
				"array" => [],
				"bool" => false,
				default => throw new RuntimeException("Cannot map default value for property: " . ClientData::class . "::{$property_name}")
			};
		}

		$command = new PluginCommand("fakeplayer", $this, new FakePlayerCommandExecutor($this));
		$command->setPermission("fakeplayer.command.fakeplayer");
		$command->setDescription("Control fake player");
		$command->setAliases(["fp"]);
		$this->getServer()->getCommandMap()->register($this->getName(), $command);

		$this->registerListener(new DefaultFakePlayerListener($this));
		FakePlayerBehaviourFactory::registerDefaults($this);

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			foreach($this->fake_players as $player){
				$player->tick();
			}
		}), 1);

		$this->saveResource("players.json");

		$configured_players_add_delay = (int) $this->getConfig()->get("configured-players-add-delay");
		if($configured_players_add_delay === -1){
			$this->addConfiguredPlayers();
		}else{
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() : void{
				$this->addConfiguredPlayers();
			}), $configured_players_add_delay);
		}
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

	/**
	 * @param FakePlayerInfo $info
	 * @return Promise<Player>
	 */
	public function addPlayer(FakePlayerInfo $info) : Promise{
		$server = $this->getServer();
		$network = $server->getNetwork();
		$type_converter = TypeConverter::getInstance();
		$packet_serializer_context = new PacketSerializerContext($type_converter->getItemTypeDictionary());
		$packet_broadcaster = new StandardPacketBroadcaster($this->getServer(), $packet_serializer_context);
		$entity_event_broadcaster = new StandardEntityEventBroadcaster($packet_broadcaster, $type_converter);

		$internal_resolver = new PromiseResolver();
		$session = new FakePlayerNetworkSession(
			$server,
			$network->getSessionManager(),
			PacketPool::getInstance(),
			$packet_serializer_context,
			new FakePacketSender(),
			$packet_broadcaster,
			$entity_event_broadcaster,
			ZlibCompressor::getInstance(),
			$type_converter,
			$server->getIp(),
			$server->getPort(),
			$internal_resolver
		);
		$network->getSessionManager()->add($session);

		$rp = new ReflectionProperty(NetworkSession::class, "info");
		$rp->setValue($session, new XboxLivePlayerInfo($info->xuid, $info->username, $info->uuid, $info->skin, "en_US" /* TODO: Make locale configurable? */, array_merge($info->extra_data, $this->default_extra_data)));

		$rp = new ReflectionMethod(NetworkSession::class, "onServerLoginSuccess");
		$rp->invoke($session);

		$packet = ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_COMPLETED, []);
		$serializer = PacketSerializer::encoder($packet_serializer_context);
		$packet->encode($serializer);
		$session->handleDataPacket($packet, $serializer->getBuffer());

		$internal_resolver->getPromise()->onCompletion(function(Player $player) use($info, $session) : void{
			$player->setViewDistance(4);

			$this->fake_players[$player->getUniqueId()->getBytes()] = $fake_player = new FakePlayer($session);

			$movement_data = FakePlayerMovementData::new();
			$fake_player->addBehaviour(new TryChangeMovementInternalFakePlayerBehaviour($movement_data), Limits::INT32_MIN);
			$fake_player->addBehaviour(new UpdateMovementInternalFakePlayerBehaviour($movement_data), Limits::INT32_MAX);
			foreach($info->behaviours as $behaviour_identifier => $behaviour_data){
				$fake_player->addBehaviour(FakePlayerBehaviourFactory::create($behaviour_identifier, $behaviour_data));
			}

			foreach($this->listeners as $listener){
				$listener->onPlayerAdd($player);
			}

			if(!$player->isAlive()){
				$player->respawn();
			}
		}, static function() : void{ /* no internal steps to take if player creation failed */ });

		// Create a new promise, to make sure a FakePlayer is always
		// registered before the caller's onCompletion is called.
		$result = new PromiseResolver();
		$internal_resolver->getPromise()->onCompletion(static function(Player $player) use($result) : void{
			$result->resolve($player);
		}, static function() use($result) : void{ $result->reject(); });
		return $result->getPromise();
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
	 * @return array<string, Promise<Player>>
	 */
	public function addConfiguredPlayers() : array{
		$players = json_decode(Filesystem::fileGetContents($this->getDataFolder() . "players.json"), true, 512, JSON_THROW_ON_ERROR);

		$_skin_data = $this->getResource("skin.rgba");
		$skin_data = stream_get_contents($_skin_data);
		fclose($_skin_data);
		$skin = new Skin("Standard_Custom", $skin_data);

		$promises = [];
		foreach($players as $uuid => $data){
			["xuid" => $xuid, "gamertag" => $gamertag] = $data;
			$promises[$uuid] = $this->addPlayer(new FakePlayerInfo(Uuid::fromString($uuid), $xuid, $gamertag, $skin, $data["extra_data"] ?? [], $data["behaviours"] ?? []));
		}
		return $promises;
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