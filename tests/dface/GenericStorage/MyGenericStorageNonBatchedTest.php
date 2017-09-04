<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\criteria\IsNull;
use dface\criteria\Reference;
use dface\GenericStorage\Mysql\MyStorageBuilder;
use dface\GenericStorage\Mysql\MyStorageError;
use dface\Mysql\MysqliConnection;

class MyGenericStorageNonBatchedTest extends GenericStorageTest {

	/** @var MysqliConnection */
	protected $dbi;

	protected function setUp() {
		$this->dbi = DbiFactory::getConnection();
		$this->storage = (new MyStorageBuilder(TestEntity::class, $this->dbi, 'test_gen_storage'))
			->setIdPropertyName('id')
			->addColumns([
				'email' => 'VARCHAR(128)',
				'data/a' => 'VARCHAR(128)',
			])
			->addIndexes([
				'INDEX email(email)',
			])
			->setBatchListSize(0)
			->setTemporary(true)
			->build();
		$this->storage->reset();
	}

	private function broke(){
		/** @noinspection SqlResolve */
		$this->dbi->query('DROP TABLE test_gen_storage');
	}

	public function testListAllTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		iterator_to_array($this->storage->listAll());
	}

	public function testListByCriteriaTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		iterator_to_array($this->storage->listByCriteria(new IsNull(new Reference('x'))));
	}

	public function testGetItemsTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		iterator_to_array($this->storage->getItems([new TestId()]));
	}

	public function testGetItemTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		$this->storage->getItem(new TestId());
	}

	public function testSaveItemTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		$this->storage->saveItem($id = new TestId(), new TestEntity($id, 'name', 'none'));
	}

	public function testRemoveItemTroubles(){
		$this->broke();
		$this->expectException(MyStorageError::class);
		$this->expectExceptionCode(0);
		$this->storage->removeItem(new TestId());
	}

}
