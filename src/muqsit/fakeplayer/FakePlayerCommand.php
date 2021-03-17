<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

final class FakePlayerCommand extends Command implements PluginOwned{

	/** @var Loader */
	private $plugin;

	public function init(Loader $plugin) : void{
		$this->plugin = $plugin;
	}

	public function getOwningPlugin() : Plugin{
		return $this->plugin;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(isset($args[0])){
			switch($args[0]){
				case "tpall":
					if($sender instanceof Player){
						$pos = $sender->getPosition();
						foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
							if($this->plugin->isFakePlayer($player)){
								$player->teleport($pos->add(8 * (lcg_value() * 2 - 1), 0.0, 8 * (lcg_value() * 2 - 1)));
							}
						}
					}
					return;
				default:
					if(isset($args[1])){
						$player = $this->plugin->getServer()->getPlayerByPrefix($args[0]);
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
									case "interact":
										$target_block = $player->getTargetBlock(5);
										$item_in_hand = $player->getInventory()->getItemInHand();
										if($target_block !== null){
											$player->interactBlock($target_block->getPos(), $player->getHorizontalFacing(), new Vector3(0, 0, 0));
											$sender->sendMessage(TextFormat::GRAY . "{$player->getName()} is interacting with {$target_block->getName()} at {$target_block->getPos()->asVector3()} using {$item_in_hand}" . TextFormat::RESET . TextFormat::GRAY . ".");
										}else{
											$player->useHeldItem();
											$sender->sendMessage(TextFormat::GRAY . "{$player->getName()} is interacting using {$item_in_hand}" . TextFormat::RESET . TextFormat::GRAY . ".");
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
					break;
			}
		}

		$sender->sendMessage(
			TextFormat::AQUA . TextFormat::BOLD . $this->plugin->getName() . " Commands" . TextFormat::RESET . TextFormat::EOL .
			TextFormat::AQUA . "/" . $commandLabel . " tpall" . TextFormat::GRAY . " - Teleport all fake players to you" . TextFormat::EOL .
			TextFormat::AQUA . "/" . $commandLabel . " <player> chat <...chat>" . TextFormat::GRAY . " - Chat on behalf of a fake player"
		);
	}
}