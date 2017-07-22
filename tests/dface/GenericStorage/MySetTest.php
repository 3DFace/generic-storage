<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MySet;
use dface\GenericStorage\Mysql\MyStorageError;
use dface\Mysql\MysqliConnection;

class MySetTest extends GenericSetTest {

	/** @var MysqliConnection */
	private $dbi;

	protected function setUp() : void {
		$this->dbi = DbiFactory::getConnection();
		$this->set = new MySet(
			$this->dbi,
			'test_set',
			TestId::class,
			true);
		$this->set->reset();
	}

	private function broke(){
		/** @noinspection SqlResolve */
		$this->dbi->query('DROP TABLE test_set');
	}

	public function testIterateTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		iterator_to_array($this->set->iterate());
	}

	public function testContainsTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->set->contains(new TestId());
	}

	public function testAddTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->set->add(new TestId());
	}

	public function testRemoveTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->set->remove(new TestId());
	}

}
