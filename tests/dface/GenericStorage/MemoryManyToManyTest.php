<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Memory\MemoryManyToMany;

class MemoryManyToManyTest extends GenericManyToManyTest {

	protected function setUp() : void {
		$this->assoc = new MemoryManyToMany();
	}

}
