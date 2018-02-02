<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\criteria\Equals;
use dface\criteria\NotEquals;
use dface\criteria\Reference;
use dface\criteria\StringConstant;
use dface\GenericStorage\Generic\GenericStorage;
use dface\GenericStorage\Generic\InvalidDataType;
use dface\GenericStorage\Generic\ItemAlreadyExists;
use dface\GenericStorage\Generic\UnexpectedRevision;
use PHPUnit\Framework\TestCase;

abstract class GenericStorageTest extends TestCase {

	/** @var GenericStorage */
	protected $storage;

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testWrongTypeDoNotPass() : void {
		$s = $this->storage;
		$uid = new TestId();
		$this->expectException(InvalidDataType::class);
		$s->saveItem($uid, new class() implements \JsonSerializable {
			public function jsonSerialize() {
				return [];
			}
		});
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testCorrectlySaved() : void {
		$s = $this->storage;
		$uid = new TestId();
		$entity = new TestEntity($uid, 'Test User', 'user@test.php', new TestData('asd', 10), 1);
		$s->saveItem($uid, $entity);
		$loaded = $s->getItem($uid);
		$this->assertEquals($entity, $loaded);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemoved() : void {
		$s = $this->storage;
		$uid = new TestId();
		$entity = new TestEntity($uid, 'Test User', 'user@test.php', null, 1);
		$s->saveItem($uid, $entity);
		$loaded = $s->getItem($uid);
		$this->assertEquals($entity, $loaded);
		$s->removeItem($uid);
		$must_be_null = $s->getItem($uid);
		$this->assertNull($must_be_null);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemovedByCriteria() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$uid2 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user1@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user2@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);

		$s->removeByCriteria(new Equals(new Reference('email'), new StringConstant('user1@test.php')));

		$loaded_arr = iterator_to_array($s->listAll());
		$this->assertEquals([
			(string)$uid2 => $entity2,
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testOverwrite() : void {
		if(static::class === MyGenericStorageWithUniqueSecondaryTest::class){
			echo 1;
		}
		$s = $this->storage;
		$uid = new TestId();
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);
		$entity2 = new TestEntity($uid, 'Test User 2', 'user@test.php', new TestData('asd', 10), 1);
		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity2);
		$loaded = $s->getItem($uid);
		$this->assertEquals($entity2->withRevision(2), $loaded);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testOverwriteRevisionGrows() : void {
		if(static::class === MyGenericStorageWithUniqueSecondaryTest::class){
			echo 1;
		}
		$s = $this->storage;
		$uid = new TestId();
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);

		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity1);
		$loaded = $s->getItem($uid);
		$this->assertEquals($entity1->withRevision(3), $loaded);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemoveResetRevision() : void {
		if(static::class === MyGenericStorageWithUniqueSecondaryTest::class){
			echo 1;
		}
		$s = $this->storage;
		$uid = new TestId();
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);
		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity1);
		$s->removeItem($uid);
		$s->saveItem($uid, $entity1);
		$loaded = $s->getItem($uid);
		$this->assertEquals($entity1->withRevision(1), $loaded);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testExpectedNew() : void {
		if(static::class === MyGenericStorageWithUniqueSecondaryTest::class){
			echo 1;
		}
		$s = $this->storage;
		$uid = new TestId();
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);

		$s->saveItem($uid, $entity1, 0);
		$this->expectException(ItemAlreadyExists::class);
		$s->saveItem($uid, $entity1, 0);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testExpected1() : void {
		if(static::class === MyGenericStorageWithUniqueSecondaryTest::class){
			echo 1;
		}
		$s = $this->storage;
		$uid = new TestId();
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);

		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity1, 1);
		$this->expectException(UnexpectedRevision::class);
		$s->saveItem($uid, $entity1, 1);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testIndexWorks() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$uid2 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);

		$criteria = new Equals(new Reference('email'), new StringConstant('user@test.php'));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([
			(string)$uid1 => $entity1,
			(string)$uid2 => $entity2,
		], $loaded_arr);

		$criteria = new Equals(new Reference('email'), new StringConstant('no@test.php'));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testIdIndexWorks() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php', null, 1);
		$s->saveItem($uid1, $entity1);

		$criteria = new Equals(new Reference('id'), new StringConstant((string)$uid1));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([
			(string)$uid1 => $entity1,
		], $loaded_arr);

		$criteria = new Equals(new Reference('id'), new StringConstant('asd'));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testMultiGetWorks() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$uid2 = new TestId();
		$uid3 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'user@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$loaded_arr = iterator_to_array($s->getItems([$uid1, $uid3]));
		$this->assertEquals([
			(string)$uid1 => $entity1,
			(string)$uid3 => $entity3,
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllWorks() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$uid2 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);

		$loaded_arr = iterator_to_array($s->listAll());
		$this->assertEquals([
			(string)$uid1 => $entity1,
			(string)$uid2 => $entity2,
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllOrderedWorks() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$uid2 = new TestId();
		$uid3 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$loaded_arr = iterator_to_array($s->listAll([['email', false]]));
		$this->assertEquals([
			(string)$uid3 => $entity3,
			(string)$uid2 => $entity2,
			(string)$uid1 => $entity1
		], $loaded_arr);

		$loaded_arr = iterator_to_array($s->listAll([['email', true]]));
		$this->assertEquals([
			(string)$uid1 => $entity1,
			(string)$uid2 => $entity2,
			(string)$uid3 => $entity3,
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllOrderedWithLimitWorks() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$uid2 = new TestId();
		$uid3 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$loaded_arr = iterator_to_array($s->listAll([['email', false]], 2));
		$this->assertEquals([
			(string)$uid3 => $entity3,
			(string)$uid2 => $entity2,
		], $loaded_arr);

		$loaded_arr = iterator_to_array($s->listAll([['email', true]], 2));
		$this->assertEquals([
			(string)$uid1 => $entity1,
			(string)$uid2 => $entity2,
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllUnorderedWithLimitWorks() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$uid2 = new TestId();
		$uid3 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$loaded_arr = iterator_to_array($s->listAll([], 3));
		$this->assertEquals([
			(string)$uid1 => $entity1,
			(string)$uid2 => $entity2,
			(string)$uid3 => $entity3,
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListFilteredAndOrderedWorks() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$uid2 = new TestId();
		$uid3 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$c = new NotEquals(new Reference('email'), new StringConstant('b@test.php'));

		$loaded_arr = iterator_to_array($s->listByCriteria($c, [['email', false]]));
		$this->assertEquals([
			(string)$uid3 => $entity3,
			(string)$uid1 => $entity1,
		], $loaded_arr);

		$loaded_arr = iterator_to_array($s->listByCriteria($c, [['email', true]]));
		$this->assertEquals([
			(string)$uid1 => $entity1,
			(string)$uid3 => $entity3,
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListFilteredAndOrderedWithLimitWorks() : void {
		$s = $this->storage;
		$uid1 = new TestId();
		$uid2 = new TestId();
		$uid3 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$c = new NotEquals(new Reference('email'), new StringConstant('b@test.php'));

		$loaded_arr = iterator_to_array($s->listByCriteria($c, [['email', false]], 1));
		$this->assertEquals([
			(string)$uid3 => $entity3,
		], $loaded_arr);

		$loaded_arr = iterator_to_array($s->listByCriteria($c, [['email', true]], 1));
		$this->assertEquals([
			(string)$uid1 => $entity1,
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllOrderByIdWorks() : void {
		$s = $this->storage;
		$uid1 = new TestId(hex2bin('00000000000000000000000000000001'));
		$uid2 = new TestId(hex2bin('00000000000000000000000000000002'));
		$uid3 = new TestId(hex2bin('00000000000000000000000000000003'));
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$loaded_arr = iterator_to_array($s->listAll([['id', false]]));
		$this->assertEquals([
			(string)$uid3 => $entity3,
			(string)$uid2 => $entity2,
			(string)$uid1 => $entity1,
		], $loaded_arr);

		$loaded_arr = iterator_to_array($s->listAll([['id', true]]));
		$this->assertEquals([
			(string)$uid1 => $entity1,
			(string)$uid2 => $entity2,
			(string)$uid3 => $entity3,
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllOrderByIdWithLimitWorks() : void {
		$s = $this->storage;
		$uid1 = new TestId(hex2bin('00000000000000000000000000000001'));
		$uid2 = new TestId(hex2bin('00000000000000000000000000000002'));
		$uid3 = new TestId(hex2bin('00000000000000000000000000000003'));
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$loaded_arr = iterator_to_array($s->listAll([['id', false]], 1));
		$this->assertEquals([
			(string)$uid3 => $entity3,
		], $loaded_arr);

		$loaded_arr = iterator_to_array($s->listAll([['id', true]], 2));
		$this->assertEquals([
			(string)$uid1 => $entity1,
			(string)$uid2 => $entity2,
		], $loaded_arr);
	}

}
