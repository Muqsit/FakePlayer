<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\behaviour\internal;

use RuntimeException;

trait InternalFakePlayerBehaviourTrait{

	public static function create(array $data) : self{
		throw new RuntimeException("Cannot create internal fake player behavior " . static::class . " from data");
	}
}