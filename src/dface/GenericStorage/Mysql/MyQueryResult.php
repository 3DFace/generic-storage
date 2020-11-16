<?php

namespace dface\GenericStorage\Mysql;

interface MyQueryResult
{

	public function iterate() : iterable;

	public function fetchRow() : ?array;

	public function fetchAssoc() : ?array;

}

