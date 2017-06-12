<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

class TestEntity implements \JsonSerializable {

	/** @var TestId */
	private $id;
	/** @var string */
	private $name;
	/** @var string */
	private $email;

	/**
	 * TestEntity constructor.
	 * @param TestId $id
	 * @param string $name
	 * @param string $email
	 */
	public function __construct(TestId $id, $name, $email) {
		$this->id = $id;
		$this->name = $name;
		$this->email = $email;
	}

	public function getId() : TestId {
		return $this->id;
	}

	public function getName() : string {
		return $this->name;
	}

	public function getEmail() : string {
		return $this->email;
	}

	public function jsonSerialize() : array {
		return [
			'id' => (string)$this->id,
			'name' => $this->name,
			'email' => $this->email,
		];
	}

	public static function deserialize(array $arr) : self {
		$id = TestId::deserialize($arr['id']);
		return new self(
			$id,
			$arr['name'],
			$arr['email']);
	}

}
