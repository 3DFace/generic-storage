<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyManyToMany;
use dface\GenericStorage\Mysql\MyStorageError;
use dface\Mysql\MysqliConnection;
use dface\sql\placeholders\DefaultFormatter;
use dface\sql\placeholders\DefaultParser;

class MyManyToManyTest extends GenericManyToManyTest {

	/** @var MysqliConnection */
	private $dbi;

	protected function setUp() : void {
		$link = DbiFactory::getConnection();
		$this->dbi = new MysqliConnection($link, new DefaultParser(), new DefaultFormatter());
		$this->assoc = new MyManyToMany(
			$link,
			'test_many_to_many',
			TestId::class,
			TestId::class,
			'left', 'right', true);
		$this->assoc->reset();
	}

	private function broke(){
		/** @noinspection SqlResolve */
		$this->dbi->query('DROP TABLE test_many_to_many');
	}

	public function testGetAllByLeftTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		iterator_to_array($this->assoc->getAllByLeft(new TestId()));
	}

	public function testGetAllByRightTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		iterator_to_array($this->assoc->getAllByRight(new TestId()));
	}

	public function testAddTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->add(new TestId(), new TestId());
	}

	public function testRemoveTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->remove(new TestId(), new TestId());
	}

	public function testClearRightTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->clearRight(new TestId());
	}

	public function testClearLeftTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->clearLeft(new TestId());
	}

}
