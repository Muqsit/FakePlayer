<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour;

use muqsit\fakeplayer\FakePlayer;
use muqsit\fakeplayer\Loader;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\EventPriority;
use pocketmine\item\Armor;
use pocketmine\player\Player;

final class AutoEquipArmorFakePlayerBehaviour implements FakePlayerBehaviour{

	private const METADATA_KEY = "behaviour:auto_equip_armor";

	public static function create(array $data) : self{
		return new self();
	}

	public static function init(Loader $plugin) : void{
		$plugin->getServer()->getPluginManager()->registerEvent(EntityItemPickupEvent::class, static function(EntityItemPickupEvent $event) use($plugin) : void{
			$item = $event->getItem();
			if(!($item instanceof Armor)){
				return;
			}

			$entity = $event->getEntity();
			if(!($entity instanceof Player)){
				return;
			}

			$fake_player = $plugin->getFakePlayer($entity);
			if($fake_player === null || $fake_player->getMetadata(self::METADATA_KEY) === null){
				return;
			}

			if($event->getInventory() !== $entity->getInventory()){
				return;
			}

			$destination_inventory = $entity->getArmorInventory();
			$destination_slot = $item->getArmorSlot();
			if(!$destination_inventory->getItem($destination_slot)->isNull()){
				return;
			}

			($ev = new EntityItemPickupEvent($entity, $event->getOrigin(), $item, $destination_inventory))->call();
			if($ev->isCancelled()){
				return;
			}

			$event->cancel();
			$event->getOrigin()->flagForDespawn();
			$destination_inventory->setItem($destination_slot, $item);
		}, EventPriority::NORMAL, $plugin);
	}

	public function __construct(){
	}

	public function onAddToPlayer(FakePlayer $player) : void{
		$player->setMetadata(self::METADATA_KEY, true);
	}

	public function onRemoveFromPlayer(FakePlayer $player) : void{
		$player->deleteMetadata(self::METADATA_KEY);
	}

	public function tick(FakePlayer $player) : void{
	}

	public function onRespawn(FakePlayer $player) : void{
	}
}
