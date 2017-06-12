<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MySet;

class MySetTest extends GenericSetTest {

	protected function setUp() : void {
		$dbi = DbiFactory::getConnection();
		$this->set = new MySet(
			$dbi,
			'test_set',
			TestId::class,
			true);
		$this->set->reset();
	}

}
