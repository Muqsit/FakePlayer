<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\info;

use pocketmine\entity\Skin;
use Ramsey\Uuid\UuidInterface;

final class FakePlayerInfo{

	/**
	 * @param UuidInterface $uuid
	 * @param string $xuid
	 * @param string $username
	 * @param Skin $skin
	 * @param array<string, mixed> $extra_data
	 * @param array<string, array<string, mixed>> $behaviours
	 */
	public function __construct(
		public UuidInterface $uuid,
		public string $xuid,
		public string $username,
		public Skin $skin,
		public array $extra_data,
		public array $behaviours
	){}
}