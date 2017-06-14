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
	/** @var TestData */
	private $data;

	/**
	 * TestEntity constructor.
	 * @param TestId $id
	 * @param string $name
	 * @param string $email
	 * @param TestData|null $data
	 */
	public function __construct(TestId $id, $name, $email, ?TestData $data = null) {
		$this->id = $id;
		$this->name = $name;
		$this->email = $email;
		$this->data = $data;
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

	public function getData() : ?TestData {
		return $this->data;
	}

	public function jsonSerialize() : array {
		return [
			'id' => (string)$this->id,
			'name' => $this->name,
			'email' => $this->email,
			'data' => $this->data === null ? null : $this->data->jsonSerialize(),
		];
	}

	public static function deserialize(array $arr) : self {
		$id = TestId::deserialize($arr['id']);
		$data = $arr['data'] ? TestData::deserialize($arr['data']) : null;
		return new self(
			$id,
			$arr['name'],
			$arr['email'],
			$data);
	}

}
