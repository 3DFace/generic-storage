<?php

namespace dface\GenericStorage;

use dface\GenericStorage\Memory\MemorySet;

class MemorySetTest extends GenericSetTest
{

	protected function setUp() : void
	{
		$this->set = new MemorySet();
	}

}
