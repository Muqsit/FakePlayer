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

class PvPFakePlayerBehaviour implements FakePlayerBehaviour{

	public static function create(array $data) : self{
		return new self($data["reach_distance"]);
	}

	protected float $reach_distance_sq;

	protected Loader $plugin;
	protected int $last_check = 0;
	protected ?int $target_entity_id = null;
	protected int $last_movement = 0;

	public function __construct(float $reach_distance){
		$this->reach_distance_sq = $reach_distance * $reach_distance;
	}

	public function init(Loader $plugin) : void{
		$this->plugin = $plugin;
	}

	protected function isValidTarget(Player $player, Entity $entity) : bool{
		return $entity !== $player && $entity instanceof Living && (!($entity instanceof Player) || ($entity->getGamemode()->equals(GameMode::SURVIVAL()) && $entity->isOnline()));
	}

	protected function getTargetEntity(Player $player) : ?Entity{
		return $this->target_entity_id !== null ? $player->getWorld()->getEntity($this->target_entity_id) : null;
	}

	protected function setTargetEntity(?Entity $target) : void{
		$this->target_entity_id = $target !== null ? $target->getId() : null;
	}

	public function onAddToPlayer(Player $player) : void{
	}

	public function onRemoveFromPlayer(Player $player) : void{
	}

	public function onRespawn(Player $player) : void{
	}

	public function tick(Player $player) : void{
		if($player->onGround && $player->isAlive()){
			$motion = $player->getMotion();
			if($motion->y === -0.0672){
				if($player->ticksLived - $this->last_movement > 500){
					$pos = $player->getSpawn()->asPosition();
					$pos->x += 3 * (lcg_value() * 2 - 1);
					$pos->z += 3 * (lcg_value() * 2 - 1);
					$player->teleport($pos);
					$this->last_movement = $player->ticksLived;
					return;
				}

				$pos = $player->getPosition()->asVector3();
				$least_dist = INF;
				if($player->ticksLived - $this->last_check >= 50){
					$nearest_entity = null;
					foreach($player->getWorld()->getNearbyEntities(AxisAlignedBB::one()->expand(8, 16, 8)->offset($pos->x, $pos->y, $pos->z)) as $entity){
						if($this->isValidTarget($player, $entity)){
							$dist = $pos->distanceSquared($entity->getPosition());
							if($dist < $least_dist){
								$nearest_entity = $entity;
								$least_dist = $dist;
								if(mt_rand(1, 3) === 1){
									break;
								}
							}
						}
					}
					if($nearest_entity !== null){
						$this->setTargetEntity($nearest_entity);
						$this->last_check = $player->ticksLived;
					}
				}else{
					$nearest_entity = $this->getTargetEntity($player);
					if($nearest_entity !== null){
						if($this->isValidTarget($player, $nearest_entity)){
							$least_dist = $pos->distanceSquared($nearest_entity->getLocation());
						}else{
							$nearest_entity = null;
							$this->setTargetEntity(null);
						}
					}
				}

				if($nearest_entity !== null && $least_dist <= 256){
					$nearest_player_pos = $nearest_entity->getPosition();
					if($least_dist > ($nearest_entity->size->getWidth() + 6.25)){
						$x = ($nearest_player_pos->x - $pos->x) + (lcg_value() * 2 - 1);
						$z = ($nearest_player_pos->z - $pos->z) + (lcg_value() * 2 - 1);
						$xz_modulus = sqrt($x * $x + $z * $z);
						if($xz_modulus > 0.0){
							$y = ($nearest_player_pos->y - $pos->y) / 16;
							$this->setMotion($player, 0.4 * ($x / $xz_modulus), $y, 0.4 * ($z / $xz_modulus));
						}
					}
					$player->lookAt($nearest_player_pos);
					if($least_dist <= $this->reach_distance_sq){
						$player->attackEntity($nearest_entity);
					}
				}
			}
		}
	}

	private function setMotion(Player $player, float $x, float $y, float $z) : void{
		$player->setMotion(new Vector3($x, $y, $z));
		$this->last_movement = $player->ticksLived;
	}
}
