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
	/** @var int */
	private $revision;
	/** @var int */
	private $seq_id;

	/**
	 * TestEntity constructor.
	 * @param TestId $id
	 * @param string $name
	 * @param string $email
	 * @param TestData|null $data
	 * @param int|null $revision
	 * @param int|null $seq_id
	 */
	public function __construct(TestId $id, $name, $email, ?TestData $data, ?int $revision, ?int $seq_id = null) {
		$this->id = $id;
		$this->name = $name;
		$this->email = $email;
		$this->data = $data;
		$this->revision = $revision;
		$this->seq_id = $seq_id;
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

	public function getRevision() : ?int {
		return $this->revision;
	}

	public function getSeqId() : ?int
	{
		return $this->seq_id;
	}

	public function withRevision(?int $revision) : self {
		$x = clone $this;
		$x->revision = $revision;
		return $x;
	}

	public function withSeqId(?int $seq_id) : self {
		$x = clone $this;
		$x->seq_id = $seq_id;
		return $x;
	}

	public function jsonSerialize() : array {
		return [
			'id' => (string)$this->id,
			'name' => $this->name,
			'email' => $this->email,
			'data' => $this->data === null ? null : $this->data->jsonSerialize(),
			'revision' => $this->revision,
			'seq_id' => $this->seq_id,
		];
	}

	public static function deserialize(array $arr) : self {
		$id = TestId::deserialize($arr['id']);
		$data = $arr['data'] ? TestData::deserialize($arr['data']) : null;
		return new self(
			$id,
			$arr['name'],
			$arr['email'],
			$data,
			$arr['revision'],
			$arr['seq_id']);
	}

}
