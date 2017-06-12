<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyManyToMany;

class MyManyToManyTest extends GenericManyToManyTest {

	protected function setUp() : void {
		$dbi = DbiFactory::getConnection();
		$this->assoc = new MyManyToMany(
			$dbi,
			'test_many_to_many',
			TestId::class,
			TestId::class,
			'left', 'right', true);
		$this->assoc->reset();
	}

}
