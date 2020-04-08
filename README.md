# FakePlayer

## What does this plugin do?
Similar to [Specter](https://github.com/falkirks/Specter), this plugin spawns players to debug stuff on your server. However, this plugin strictly supports API version 4.0.0.

## Usage
**Who the heck is BoxierChimera37?**<br>My alt xbox live account.

When you first run this plugin, BoxierChimera37 will join the server. You can edit the `players.json` file to add as many players as you wish.
Does this fake your server player count? Yes, but that's not the point of this plugin.<br>
`players.json` structure:
```jsonc
{
	"uuid-v4-string": {
		"xuid": "required",
		"gamertag": "required",
		"extra_data": {} // this field is OPTIONAL
		"behaviours": [] // this field is OPTIONAL
	}
}
```

Once a fake player joins, you can chat or run commands on their behalf using:<br>
`/fp <player> chat hello wurld!`<br>
`/fp <player> chat /help 4`

## API Documentation
### Adding a fake player
```php
/**
 * @param UUID $uuid
 * @param string $xuid
 * @param string $username
 * @param mixed[] $extra_data
 */
Loader::addPlayer(UUID $uuid, string $xuid, string $username, array $extra_data) : Player;
```

### Test for fake player
```php
/**
 * @param Player $player
 */
Loader::isFakePlayer(Player $player) : bool;
```

### Removing a fake player
```php
/**
 * NOTE: $player MUST be a fake player, or else an InvalidArgumentException will
 * be thrown.
 * @param Player $player
 */
Loader::removePlayer(Player $player) : void;
```

### Listeners
#### Registering/Unregistering a fake player listener
```php
Loader::registerListener(FakePlayerListener $listener) : void;
Loader::unregisterListener(FakePlayerListener $listener) : void;
```
Example:
```php
Loader::registerListener(new ClosureFakePlayerListener(
	function(Player $player) : void{
		Server::getInstance()->broadcastMessage("Fake player joined: " . $player->getName());
	},
	function(Player $player) : void{
		Server::getInstance()->broadcastMessage("Fake player is kil: " . $player->getName());
	}
));
```

#### Listening to packets sent
Each fake player holds a `FakePlayerNetworkSession` that you can register packet listeners to.
```php
/** @var Player $fake_player */
/** @var FakePlayerNetworkSession $session */
$session = $fake_player->getNetworkSession();
```

There are two kinds of packet listeners you can register:
1. A catch-all packet listener that is notified for every packet sent.
2. A specific packet listener

##### Registering a catch-all packet listener
```php
$session->registerPacketListener(new ClosureFakePlayerPacketListener(
	function(ClientboundPacket $packet, NetworkSession $session) : void{
		// do something
	}
));
```

##### Registering a specific packet listener
```php
$session->registerSpecificPacketListener(TextPacket::class, new ClosureFakePlayerPacketListener(
	function(ClientboundPacket $packet, NetworkSession $session) : void{
		/** @var TextPacket $packet */
		Server::getInstance()->broadcastMessage($session->getPlayer()->getName() . " was sent text: " . $packet->message);
	}
));
```

### Fake Player Behaviours
Behaviours are basically a way you can do anything you like on a fake player every tick.
By default, there's a `fakeplayer:pvp` behaviour which makes the fake player fight all living entities except non-survival mode players.
You can add multiple behaviours to a fake player. Make sure to register your custom behaviours during `PluginBase::onEnable()`.<br>
To register a fake player behaviour:
```php
FakePlayerBehaviourManager::register("myplugin:cool_ai", MyCoolAIThatImplementsFakePlayerBehaviour::class);
```
To add a behaviour to a player, pass it during `Loader::addPlayer()` OR specify it in the `players.json` file.

### Frequently Asked Questions
Q. Does this work with encryption enabled?<br>
A. ye
