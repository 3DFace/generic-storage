<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Generic;

class UniqueConstraintViolation extends GenericStorageError {

	/** @var string */
	private $attribute;
	/** @var mixed */
	private $value;

	/**
	 * UniqueConstraintViolation constructor.
	 * @param string $attribute
	 * @param mixed $value
	 * @param string $message
	 * @param int $code
	 * @param \Exception $prev
	 */
	public function __construct(string $attribute, $value, $message = '', $code = 0, $prev = null) {
		parent::__construct($message, $code, $prev);
		$this->attribute = $attribute;
		$this->value = $value;
	}

	public function getAttribute() : string {
		return $this->attribute;
	}

	public function getValue() {
		return $this->value;
	}

}
