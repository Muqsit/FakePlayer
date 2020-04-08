<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour;

use muqsit\fakeplayer\Loader;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\GameMode;
use pocketmine\player\Player;

class FakePlayerPVPBehaviour implements FakePlayerBehaviour{

	/** @var Loader */
	private $plugin;

	/** @var int */
	private $last_check = 0;

	public function init(Loader $plugin) : void{
		$this->plugin = $plugin;
	}

	protected function isValidTarget(Entity $entity) : bool{
		return $entity instanceof Living && (!($entity instanceof Player) || (!$this->plugin->isFakePlayer($entity) && $entity->getGamemode()->equals(GameMode::SURVIVAL())));
	}

	public function tick(Player $player) : void{
		if($player->onGround && $player->isAlive()){
			$motion = $player->getMotion();
			if($motion->y === -0.0672){
				$pos = $player->getPosition()->asVector3();
				$least_dist = INF;
				if($player->ticksLived - $this->last_check >= 50){
					$nearest_entity = null;
					foreach($player->getWorld()->getNearbyEntities(AxisAlignedBB::one()->expand(8, 16, 8)->offset($pos->x, $pos->y, $pos->z)) as $entity){
						if($this->isValidTarget($entity)){
							$dist = $pos->distanceSquared($entity->getPosition());
							if($dist < $least_dist){
								$nearest_entity = $entity;
								$least_dist = $dist;
							}
						}
					}
					if($nearest_entity !== null){
						$player->setTargetEntity($nearest_entity);
						$this->last_check = $player->ticksLived;
					}
				}else{
					$nearest_entity = $player->getTargetEntity();
					if($nearest_entity !== null){
						if($this->isValidTarget($nearest_entity)){
							$least_dist = $pos->distanceSquared($nearest_entity->getLocation());
						}else{
							$nearest_entity = null;
							$player->setTargetEntity(null);
						}
					}
				}

				if($nearest_entity !== null && $least_dist <= 256){
					$nearest_player_pos = $nearest_entity->getPosition();
					if($least_dist > ($nearest_entity->width + 6.25)){
						$x = $nearest_player_pos->x - $pos->x;
						$z = $nearest_player_pos->z - $pos->z;
						$xz_modulus = sqrt($x * $x + $z * $z);
						$y = ($nearest_player_pos->y - $pos->y) / 16;
						$player->setMotion(new Vector3(0.4 * ($x / $xz_modulus), $y, 0.4 * ($z / $xz_modulus)));
					}
					$player->lookAt($nearest_player_pos);
					if($least_dist <= (mt_rand(1200, 1600) * 0.01)){
						$player->attackEntity($nearest_entity);
					}
				}
			}
		}
	}
}