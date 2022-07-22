<?php

namespace dface\GenericStorage;

use dface\GenericStorage\Memory\MemoryStorage;

class MemorySeqIdGenericStorageTest extends GenericStorageTest
{

	protected function setUp() : void
	{
		$this->storage = new MemoryStorage(TestEntity::class, 'revision', 'seq_id');
		$this->seq_id_injected = true;
	}

}
