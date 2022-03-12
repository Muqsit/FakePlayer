<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use muqsit\fakeplayer\behaviour\FakePlayerBehaviour;
use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use muqsit\fakeplayer\util\SortedMap;
use pocketmine\player\Player;

final class FakePlayer{

	private FakePlayerNetworkSession $session;
	private Player $player;

	/** @phpstan-var SortedMap<int, FakePlayerBehaviour> */
	private SortedMap $behaviours;

	/** @var array<string, mixed> */
	private array $metadata = [];

	public function __construct(FakePlayerNetworkSession $session){
		$this->session = $session;
		$this->player = $session->getPlayer();
		$this->behaviours = new SortedMap();
	}

	public function getPlayer() : Player{
		return $this->player;
	}

	public function getPlayerNullable() : ?Player{
		return $this->player;
	}

	public function destroy() : void{
		foreach($this->getBehaviours() as $behaviour){
			$this->removeBehaviour($behaviour);
		}

		$this->metadata = [];
	}

	public function getNetworkSession() : FakePlayerNetworkSession{
		return $this->session;
	}

	public function addBehaviour(FakePlayerBehaviour $behaviour, int $score = 0) : void{
		if(!$this->behaviours->contains($id = spl_object_id($behaviour))){
			$this->behaviours->set($id, $behaviour, $score);
			$behaviour->onAddToPlayer($this);
		}
	}

	/**
	 * @return array<int, FakePlayerBehaviour>
	 */
	public function getBehaviours() : array{
		return $this->behaviours->getAll();
	}

	public function removeBehaviour(FakePlayerBehaviour $behaviour) : void{
		if($this->behaviours->contains($id = spl_object_id($behaviour))){
			$this->behaviours->remove($id);
			$behaviour->onRemoveFromPlayer($this);
		}
	}

	public function tick() : void{
		foreach($this->getBehaviours() as $behaviour){
			$behaviour->tick($this);
		}
	}

	public function getMetadata(string $key, mixed $default = null) : mixed{
		return $this->metadata[$key] ?? $default;
	}

	public function setMetadata(string $key, mixed $value) : void{
		$this->metadata[$key] = $value;
	}

	public function deleteMetadata(string $key) : void{
		unset($this->metadata[$key]);
	}
}
