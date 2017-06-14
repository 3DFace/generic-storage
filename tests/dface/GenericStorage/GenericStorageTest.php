<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\criteria\Equals;
use dface\criteria\Reference;
use dface\criteria\StringConstant;
use dface\GenericStorage\Generic\GenericStorage;
use PHPUnit\Framework\TestCase;

abstract class GenericStorageTest extends TestCase {

	abstract protected function createStorage() : GenericStorage;

	public function testCorrectlySaved() : void {
		$s = $this->createStorage();
		$uid = new TestId();
		$entity = new TestEntity($uid, 'Test User', 'user@test.php', new TestData('asd', 10));
		$s->saveItem($uid, $entity);
		$loaded = $s->getItem($uid);
		$this->assertEquals($entity, $loaded);
	}

	public function testRemoved() : void {
		$s = $this->createStorage();
		$uid = new TestId();
		$entity = new TestEntity($uid, 'Test User', 'user@test.php');
		$s->saveItem($uid, $entity);
		$loaded = $s->getItem($uid);
		$this->assertEquals($entity, $loaded);
		$s->removeItem($uid);
		$must_be_null = $s->getItem($uid);
		$this->assertNull($must_be_null);
	}

	public function testIndexWorks() : void {
		$s = $this->createStorage();
		$uid1 = new TestId();
		$uid2 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php');
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user@test.php');
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);

		$criteria = new Equals(new Reference('email'), new StringConstant('user@test.php'));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([$entity1, $entity2], $loaded_arr);

		$criteria = new Equals(new Reference('email'), new StringConstant('no@test.php'));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([], $loaded_arr);
	}

	public function testIdIndexWorks() : void {
		$s = $this->createStorage();
		$uid1 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php');
		$s->saveItem($uid1, $entity1);

		$criteria = new Equals(new Reference('id'), new StringConstant((string)$uid1));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([$entity1], $loaded_arr);

		$criteria = new Equals(new Reference('id'), new StringConstant('asd'));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([], $loaded_arr);
	}

	public function testMultiGetWorks() : void {
		$s = $this->createStorage();
		$uid1 = new TestId();
		$uid2 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php');
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user@test.php');
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);

		$loaded_arr = iterator_to_array($s->getItems([$uid1, $uid2]));
		$this->assertEquals([$entity1, $entity2], $loaded_arr);
	}

	public function testListAllWorks() : void {
		$s = $this->createStorage();
		$uid1 = new TestId();
		$uid2 = new TestId();
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php');
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user@test.php');
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);

		$loaded_arr = iterator_to_array($s->listAll());
		$this->assertEquals([$entity1, $entity2], $loaded_arr);
	}


}
