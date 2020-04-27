<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\criteria\IsNull;
use dface\criteria\Reference;
use dface\GenericStorage\Generic\UnderlyingStorageError;
use dface\GenericStorage\Mysql\MyStorageBuilder;

class MyGenericStorageNonBatchedTest extends GenericStorageTest
{

	/** @var MysqliSameLinkProvider */
	protected $linkProvider;

	protected function setUp() : void
	{
		$this->linkProvider = LinkProviderFactory::createLinkProvider();
		$this->storage = (new MyStorageBuilder(TestEntity::class, $this->linkProvider, 'test_gen_storage'))
			->setIdPropertyName('id')
			->setRevisionPropertyName('revision')
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

	/**
	 * @throws UnderlyingStorageError
	 */
	private function broke() : void
	{
		/** @noinspection SqlResolve */
		$this->linkProvider->getLink()->query('DROP TABLE test_gen_storage');
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		self::iterable_to_array($this->storage->listAll());
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListByCriteriaTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		self::iterable_to_array($this->storage->listByCriteria(new IsNull(new Reference('x'))));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testGetItemsTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		self::iterable_to_array($this->storage->getItems([TestId::generate($this->getIdColumnLength())]));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testGetItemTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->storage->getItem(TestId::generate($this->getIdColumnLength()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testSaveItemTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->storage->saveItem($id = TestId::generate($this->getIdColumnLength()),
			new TestEntity($id, 'name', 'none', null, 1));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemoveItemTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->storage->removeItem(TestId::generate($this->getIdColumnLength()));
	}

}
