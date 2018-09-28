<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

class TestId {

	private $bin;

	public function __construct(string $bin) {
		$this->bin = $bin;
	}

	public static function generate(int $length) : self{
		/** @noinspection PhpUnhandledExceptionInspection */
		return new static(\random_bytes($length));
	}

	public function __toString() {
		return bin2hex($this->bin);
	}

	public static function deserialize($val) : TestId {
		return new self(hex2bin($val));
	}

}
