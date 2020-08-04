<?php

namespace dface\GenericStorage\Generic;

class UniqueConstraintViolation extends \Exception implements GenericStorageError
{

	private string $attribute;
	/** @var mixed */
	private $value;

	public function __construct(string $attribute, $value, string $message = '', int $code = 0, \Exception $prev = null)
	{
		parent::__construct($message, $code, $prev);
		$this->attribute = $attribute;
		$this->value = $value;
	}

	public function getAttribute() : string
	{
		return $this->attribute;
	}

	public function getValue()
	{
		return $this->value;
	}

}
