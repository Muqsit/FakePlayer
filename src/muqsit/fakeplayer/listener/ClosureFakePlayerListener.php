<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\listener;

use Closure;
use pocketmine\player\Player;
use pocketmine\utils\Utils;

final class ClosureFakePlayerListener implements FakePlayerListener{

	private Closure $on_player_add;
	private Closure $on_player_remove;

	public function __construct(Closure $on_player_add, Closure $on_player_remove){
		Utils::validateCallableSignature(static function(Player $player) : void{}, $on_player_add);
		$this->on_player_add = $on_player_add;

		Utils::validateCallableSignature(static function(Player $player) : void{}, $on_player_remove);
		$this->on_player_remove = $on_player_remove;
	}

	public function onPlayerAdd(Player $player) : void{
		($this->on_player_add)($player);
	}

	public function onPlayerRemove(Player $player) : void{
		($this->on_player_remove)($player);
	}
}