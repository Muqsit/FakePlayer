<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour\internal;

use pocketmine\math\Vector3;

final class FakePlayerMovementData{

	public static function new() : self{
		return new self(Vector3::zero());
	}

	public function __construct(
		public Vector3 $motion
	){}
}