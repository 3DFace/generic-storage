<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\UnderlyingStorageError;
use dface\GenericStorage\Generic\UniqueConstraintViolation;
use dface\GenericStorage\Mysql\MyStorage;
use dface\GenericStorage\Mysql\MyStorageBuilder;

class MyGenericStorageWithUniqueSecondaryTest extends GenericStorageTest
{

	protected function setUp()
	{
		$linkProvider = LinkProviderFactory::createLinkProvider();
		$this->storage = (new MyStorageBuilder(TestEntity::class, $linkProvider, 'test_gen_storage'))
			->setIdPropertyName('id')
			->setRevisionPropertyName('revision')
			->addColumns([
				'name' => 'VARCHAR(128)',
				'email' => 'VARCHAR(128)',
				'data/a' => 'VARCHAR(128)',
			])
			->addIndexes([
				'UNIQUE name(name)',
				'INDEX email(email)',
			])
			->setHasUniqueSecondary(true)
			->setBatchListSize(1)
			->setIdBatchSize(1)
			->setTemporary(true)
			->build();
		$this->storage->reset();
	}

	public function testBatchListSizeProtected() : void
	{
		$linkProvider = LinkProviderFactory::createLinkProvider();
		$this->expectException(\InvalidArgumentException::class);
		(new MyStorageBuilder(TestEntity::class, $linkProvider, 'test_gen_storage'))
			->setBatchListSize(-1)
			->build();
	}

	public function testIdBatchSizeProtected() : void
	{
		$linkProvider = LinkProviderFactory::createLinkProvider();
		$this->expectException(\InvalidArgumentException::class);
		(new MyStorageBuilder(TestEntity::class, $linkProvider, 'test_gen_storage'))
			->setIdBatchSize(-1)
			->build();
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testUniqueConstraint() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User', 'user@test.php', new TestData('asd', 10), 1);
		$entity2 = new TestEntity($uid2, 'Test User', 'user@test.php', new TestData('asd', 10), 1);
		$s->saveItem($uid1, $entity1);
		$this->expectException(UniqueConstraintViolation::class);
		$s->saveItem($uid2, $entity2);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testDataSizeLimited() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$data = new TestData(str_repeat('x', 65535), 10);
		$entity1 = new TestEntity($uid1, 'Test User', 'user@test.php', $data, 1);
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$s->saveItem($uid1, $entity1);
	}

	public function testUpdateColumns() : void
	{
		/** @var MyStorage $s */
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user@test.php', new TestData('asd', 10), 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->updateColumns();
		$loaded = iterator_to_array($s->listAll(), false);
		$this->assertEquals([$entity1, $entity2], $loaded);
	}

}
