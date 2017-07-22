<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Memory\MemoryStorage;
use dface\GenericStorage\Generic\GenericStorage;

class MemoryGenericStorageTest extends GenericStorageTest {

	protected function setUp() {
		$this->storage = new MemoryStorage(TestEntity::class);
	}

}
