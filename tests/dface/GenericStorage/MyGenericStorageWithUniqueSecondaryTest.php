<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\UnderlyingStorageError;
use dface\GenericStorage\Generic\UniqueConstraintViolation;
use dface\GenericStorage\Mysql\MyStorage;
use dface\GenericStorage\Mysql\MyStorageBuilder;
use dface\Mysql\MysqlException;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\ParserException;

class MyGenericStorageWithUniqueSecondaryTest extends GenericStorageTest {

	/**
	 * @throws MysqlException
	 * @throws FormatterException
	 * @throws ParserException
	 */
	protected function setUp() {
		$dbi = DbiFactory::getConnection();
		$dbiFac = DbiFactory::getConnectionFactory();
		$this->storage = (new MyStorageBuilder(TestEntity::class, $dbi, 'test_gen_storage'))
			->setDedicatedConnectionFactory($dbiFac)
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

	public function testBatchListSizeProtected(){
		$dbi = DbiFactory::getConnection();
		$this->expectException(\InvalidArgumentException::class);
		(new MyStorageBuilder(TestEntity::class, $dbi, 'test_gen_storage'))
			->setBatchListSize(-1)
			->build();
	}

	public function testIdBatchSizeProtected(){
		$dbi = DbiFactory::getConnection();
		$this->expectException(\InvalidArgumentException::class);
		(new MyStorageBuilder(TestEntity::class, $dbi, 'test_gen_storage'))
			->setIdBatchSize(-1)
			->build();
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testUniqueConstraint() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$uid2 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User', 'user@test.php', new TestData('asd', 10), 1);
		$entity2 = new TestEntity($uid2, 'Test User', 'user@test.php', new TestData('asd', 10), 1);
		$s->saveItem($uid1, $entity1);
		$this->expectException(UniqueConstraintViolation::class);
		$s->saveItem($uid2, $entity2);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testDataSizeLimited() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$data = new TestData(str_repeat('x', 65535), 10);
		$entity1 = new TestEntity($uid1, 'Test User', 'user@test.php', $data, 1);
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$s->saveItem($uid1, $entity1);
	}

	/**
	 * @throws FormatterException
	 * @throws Generic\GenericStorageError
	 * @throws MysqlException
	 * @throws ParserException
	 */
	public function testUpdateColumns() : void {
		/** @var MyStorage $s */
		$s = $this->storage;
		$uid1 = new TestId();
		$uid2 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user@test.php', new TestData('asd', 10), 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->updateColumns();
		$loaded = iterator_to_array($s->listAll(), false);
		$this->assertEquals([$entity1, $entity2], $loaded);
	}

}
