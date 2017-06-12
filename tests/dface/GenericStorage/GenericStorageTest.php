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
		$profile = new TestEntity($uid, 'Test User', 'user@test.php');
		$s->saveItem($uid, $profile);
		$loaded = $s->getItem($uid);
		$this->assertEquals($profile, $loaded);
	}

	public function testRemoved() : void {
		$s = $this->createStorage();
		$uid = new TestId();
		$profile = new TestEntity($uid, 'Test User', 'user@test.php');
		$s->saveItem($uid, $profile);
		$loaded = $s->getItem($uid);
		$this->assertEquals($profile, $loaded);
		$s->removeItem($uid);
		$must_be_null = $s->getItem($uid);
		$this->assertNull($must_be_null);
	}

	public function testIndexWorks() : void {
		$s = $this->createStorage();
		$uid1 = new TestId();
		$uid2 = new TestId();
		$profile1 = new TestEntity($uid1, 'Test User 1', 'user@test.php');
		$profile2 = new TestEntity($uid2, 'Test User 2', 'user@test.php');
		$s->saveItem($uid1, $profile1);
		$s->saveItem($uid2, $profile2);

		$criteria = new Equals(new Reference('email'), new StringConstant('user@test.php'));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([$profile1, $profile2], $loaded_arr);

		$criteria = new Equals(new Reference('email'), new StringConstant('no@test.php'));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([], $loaded_arr);
	}

	public function testIdIndexWorks() : void {
		$s = $this->createStorage();
		$uid1 = new TestId();
		$profile1 = new TestEntity($uid1, 'Test User 1', 'user@test.php');
		$s->saveItem($uid1, $profile1);

		$criteria = new Equals(new Reference('id'), new StringConstant((string)$uid1));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([$profile1], $loaded_arr);

		$criteria = new Equals(new Reference('id'), new StringConstant('asd'));
		$loaded_arr = iterator_to_array($s->listByCriteria($criteria));
		$this->assertEquals([], $loaded_arr);
	}

	public function testMultiGetWorks() : void {
		$s = $this->createStorage();
		$uid1 = new TestId();
		$uid2 = new TestId();
		$profile1 = new TestEntity($uid1, 'Test User 1', 'user@test.php');
		$profile2 = new TestEntity($uid2, 'Test User 2', 'user@test.php');
		$s->saveItem($uid1, $profile1);
		$s->saveItem($uid2, $profile2);

		$loaded_arr = iterator_to_array($s->getItems([$uid1, $uid2]));
		$this->assertEquals([$profile1, $profile2], $loaded_arr);
	}

	public function testListAllWorks() : void {
		$s = $this->createStorage();
		$uid1 = new TestId();
		$uid2 = new TestId();
		$profile1 = new TestEntity($uid1, 'Test User 1', 'user@test.php');
		$profile2 = new TestEntity($uid2, 'Test User 2', 'user@test.php');
		$s->saveItem($uid1, $profile1);
		$s->saveItem($uid2, $profile2);

		$loaded_arr = iterator_to_array($s->listAll());
		$this->assertEquals([$profile1, $profile2], $loaded_arr);
	}


}
