<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Memory\MemoryStorage;

class MemoryGenericStorageTest extends GenericStorageTest {

	protected function setUp() {
		$this->storage = new MemoryStorage(TestEntity::class);
	}

}
