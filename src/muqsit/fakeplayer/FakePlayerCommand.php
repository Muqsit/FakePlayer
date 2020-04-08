<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;

final class FakePlayerCommand extends Command implements PluginIdentifiableCommand{

	/** @var Loader */
	private $plugin;

	public function init(Loader $plugin) : void{
		$this->plugin = $plugin;
	}

	public function getPlugin() : Plugin{
		return $this->plugin;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(isset($args[0], $args[1])){
			$player = $this->plugin->getServer()->getPlayer($args[0]);
			if($player !== null){
				if($this->plugin->isFakePlayer($player)){
					/** @var FakePlayerNetworkSession $session */
					$session = $player->getNetworkSession();
					switch($args[1]){
						case "chat":
							if(isset($args[2])){
								$chat = implode(" ", array_slice($args, 2)); // TODO: use a method that complies with arg containing spaces

								$session->registerSpecificPacketListener(TextPacket::class, $listener = new ClosureFakePlayerPacketListener(static function(ClientboundPacket $packet, NetworkSession $session) use($sender) : void{
									/** @var TextPacket $packet */
									if($packet->type !== TextPacket::TYPE_JUKEBOX_POPUP && $packet->type !== TextPacket::TYPE_POPUP && $packet->type !== TextPacket::TYPE_TIP){
										$sender->sendMessage($packet->message);
									}
								}));
								$player->chat($chat);
								$session->unregisterSpecificPacketListener(TextPacket::class, $listener);
							}else{
								$sender->sendMessage(TextFormat::RED . "Usage: /" . $commandLabel . " " . $player->getName() . " " . $args[1] . " <...chat>");
							}
							return;
					}
				}else{
					$sender->sendMessage(TextFormat::RED . $player->getName() . " is NOT a fake player!");
					return;
				}
			}else{
				$sender->sendMessage(TextFormat::RED . $args[0] . " is NOT online!");
				return;
			}
		}

		$sender->sendMessage(
			TextFormat::AQUA . TextFormat::BOLD . $this->plugin->getName() . " Commands" . TextFormat::RESET . TextFormat::EOL .
			TextFormat::AQUA . "/" . $commandLabel . " <player> chat <...chat>" . TextFormat::GRAY . " - Chat on behalf of a fake player"
		);
	}
}