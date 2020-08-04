<?php

namespace dface\GenericStorage;

use dface\GenericStorage\Memory\MemoryStorage;

class MemoryGenericStorageTest extends GenericStorageTest
{

	protected function setUp() : void
	{
		$this->storage = new MemoryStorage(TestEntity::class, 'revision');
	}

}
