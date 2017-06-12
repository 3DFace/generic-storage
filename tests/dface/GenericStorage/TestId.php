<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

class TestId {

	private $bin;

	public function __construct($bin = null) {
		$this->bin = $bin ?? random_bytes(16);
	}

	public function __toString() {
		return bin2hex($this->bin);
	}

	public static function deserialize($val) : TestId {
		return new self(hex2bin($val));
	}

}
