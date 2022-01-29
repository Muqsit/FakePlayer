<?php

declare(strict_types=1);

namespace muqsit\fakeplayer\info;

use pocketmine\entity\Skin;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class FakePlayerInfoBuilder{

	public static function create() : self{
		static $counter = 0;
		return new self(
			Uuid::uuid4(),
			(string) mt_rand(2 * (10 ** 15), (3 * (10 ** 15)) - 1), // xuids *look* like they're 16 numeric digits in length and begin with a "2"
			"FakePlayer" . ++$counter,
			new Skin("Standard_Custom", str_repeat("\xff", 8_192)),
			[],
			[]
		);
	}

	/**
	 * @param UuidInterface $uuid
	 * @param string $xuid
	 * @param string $username
	 * @param Skin $skin
	 * @param array<string, mixed> $extra_data
	 * @param array<string, array<string, mixed>> $behaviours
	 */
	private function __construct(
		private UuidInterface $uuid,
		private string $xuid,
		private string $username,
		private Skin $skin,
		private array $extra_data,
		private array $behaviours
	){}

	public function setUuid(UuidInterface $uuid) : self{
		$this->uuid = $uuid;
		return $this;
	}

	public function setXuid(string $xuid) : self{
		$this->xuid = $xuid;
		return $this;
	}

	public function setUsername(string $username) : self{
		$this->username = $username;
		return $this;
	}

	public function setSkin(Skin $skin) : self{
		$this->skin = $skin;
		return $this;
	}

	/**
	 * @param array<string, mixed> $extra_data
	 */
	public function setExtraData(array $extra_data) : self{
		$this->extra_data = $extra_data;
		return $this;
	}

	/**
	 * @param array<string, array<string, mixed>> $behaviours
	 */
	public function setBehaviours(array $behaviours) : self{
		$this->behaviours = $behaviours;
		return $this;
	}

	public function addExtraData(string $identifier, mixed $value) : self{
		$this->extra_data[$identifier] = $value;
		return $this;
	}

	/**
	 * @param string $identifier
	 * @param array<string, mixed> $data
	 * @return self
	 */
	public function addBehaviour(string $identifier, array $data) : self{
		$this->behaviours[$identifier] = $data;
		return $this;
	}

	public function build() : FakePlayerInfo{
		return new FakePlayerInfo($this->uuid, $this->xuid, $this->username, $this->skin, $this->extra_data, $this->behaviours);
	}
}