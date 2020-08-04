<?php

namespace dface\GenericStorage;

class TestData implements \JsonSerializable
{

	private string $a;
	private int $b;

	public function __construct(string $a, int $b)
	{
		$this->a = $a;
		$this->b = $b;
	}

	public function getA() : string
	{
		return $this->a;
	}

	public function getB() : int
	{
		return $this->b;
	}

	public function jsonSerialize()
	{
		return [
			'a' => $this->a,
			'b' => $this->b,
		];
	}

	public static function deserialize(array $arr) : self
	{
		return new self($arr['a'], $arr['b']);
	}

}
