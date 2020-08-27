<?php

namespace dface\GenericStorage;

use dface\criteria\node\Equals;
use dface\criteria\node\IntegerConstant;
use dface\criteria\node\NotEquals;
use dface\criteria\node\Reference;
use dface\criteria\node\StringConstant;
use dface\GenericStorage\Generic\GenericStorage;
use dface\GenericStorage\Generic\InvalidDataType;
use dface\GenericStorage\Generic\ItemAlreadyExists;
use dface\GenericStorage\Generic\UnexpectedRevision;
use PHPUnit\Framework\TestCase;

abstract class GenericStorageTest extends TestCase
{

	protected GenericStorage $storage;
	protected bool $seq_id_injected = false;

	protected function getIdColumnLength() : int
	{
		return 16;
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testWrongTypeDoNotPass() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$this->expectException(InvalidDataType::class);
		$s->saveItem($uid, new TestData('a', 1));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testCorrectlySaved() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$entity = new TestEntity($uid, 'Test User', 'user@test.php', new TestData('asd', 10), 1);
		$s->saveItem($uid, $entity);
		$loaded = $s->getItem($uid);
		$expected = $entity;
		if ($this->seq_id_injected) {
			$expected = $expected->withSeqId(1);
		}
		self::assertEquals($expected, $loaded);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemoved() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$entity = new TestEntity($uid, 'Test User', 'user@test.php', null, 1);
		$s->saveItem($uid, $entity);
		$loaded = $s->getItem($uid);
		$expected = $entity;
		if ($this->seq_id_injected) {
			$expected = $expected->withSeqId(1);
		}
		self::assertEquals($expected, $loaded);
		$s->removeItem($uid);
		$must_be_null = $s->getItem($uid);
		self::assertNull($must_be_null);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemovedByCriteria() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user1@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user2@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);

		$s->removeByCriteria(new Equals(new Reference('email'), new StringConstant('user1@test.php')));

		$loaded_arr = self::iterable_to_entries($s->listAll());
		$expected2 = $entity2;
		if ($this->seq_id_injected) {
			$expected2 = $expected2->withSeqId(2);
		}
		self::assertEquals([
			[(string)$uid2, $expected2],
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testCleared() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user1@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user2@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);

		$s->clear();

		$loaded_arr = self::iterable_to_entries($s->listAll());
		self::assertEmpty($loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testOverwrite() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);
		$entity2 = new TestEntity($uid, 'Test User 2', 'user@test.php', new TestData('asd', 10), 1);
		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity2);
		$loaded = $s->getItem($uid);
		$expected = $entity2->withRevision(2);
		if ($this->seq_id_injected) {
			$expected = $expected->withSeqId(1);
		}
		self::assertEquals($expected, $loaded);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testOverwriteRevisionGrows() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);

		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity1);
		$loaded = $s->getItem($uid);
		$expected = $entity1->withRevision(3);
		if ($this->seq_id_injected) {
			$expected = $expected->withSeqId(1);
		}
		self::assertEquals($expected, $loaded);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemoveResetRevision() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);
		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity1);
		$s->removeItem($uid);
		$s->saveItem($uid, $entity1);
		$loaded = $s->getItem($uid);
		$expected = $entity1->withRevision(1);
		if ($this->seq_id_injected) {
			$expected = $expected->withSeqId(3);
		}
		self::assertEquals($expected, $loaded);
	}

	public function testExpectedNew() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);

		$s->saveItem($uid, $entity1, 0);
		$this->expectException(ItemAlreadyExists::class);
		$s->saveItem($uid, $entity1, 0);
	}

	public function testExpectedNewIdempotency() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);

		$s->saveItem($uid, $entity1, 0);
		$s->saveItem($uid, $entity1, 0, true);
		/** @var TestEntity $e */
		$e = $s->getItem($uid);
		self::assertEquals(1, $e->getRevision());
	}

	public function testExpectedNewIdempotencyNotMatch() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);

		$s->saveItem($uid, $entity1, 0);
		$this->expectException(ItemAlreadyExists::class);
		$s->saveItem($uid, $entity1->withEmail('changed@test.php'), 0, true);
	}

	public function testExpected1() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);

		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity1, 1);
		$this->expectException(UnexpectedRevision::class);
		$s->saveItem($uid, $entity1, 1);
	}

	public function testExpected1Idempotency() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);

		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity1, 1);
		$s->saveItem($uid, $entity1, 1, true);
		/** @var TestEntity $e */
		$e = $s->getItem($uid);
		self::assertEquals(2, $e->getRevision());
	}

	public function testExpected1IdempotencyNotMatch() : void
	{
		$s = $this->storage;
		$uid = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid, 'Test User 1', 'user@test.php', new TestData('asd', 10), 1);

		$s->saveItem($uid, $entity1);
		$s->saveItem($uid, $entity1, 1);
		$this->expectException(UnexpectedRevision::class);
		$s->saveItem($uid, $entity1->withEmail('changed@test.php'), 1, true);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testIndexWorks() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);

		$expected1 = $entity1;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(1);
		}
		$expected2 = $entity2;
		if ($this->seq_id_injected) {
			$expected2 = $expected2->withSeqId(2);
		}

		$criteria = new Equals(new Reference('email'), new StringConstant('user@test.php'));
		$loaded_arr = self::iterable_to_entries($s->listByCriteria($criteria));
		self::assertEquals([
			[(string)$uid1, $expected1],
			[(string)$uid2, $expected2],
		], $loaded_arr);

		$criteria = new Equals(new Reference('email'), new StringConstant('no@test.php'));
		$loaded_arr = self::iterable_to_entries($s->listByCriteria($criteria));
		self::assertEquals([], $loaded_arr);

		if ($this->seq_id_injected) {
			$criteria = new Equals(new Reference('seq_id'), new IntegerConstant(1));
			$loaded_arr = self::iterable_to_entries($s->listByCriteria($criteria));
			self::assertEquals([
				[(string)$uid1,  $expected1],
			], $loaded_arr);
		}
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testIdIndexWorks() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php', null, 1);
		$s->saveItem($uid1, $entity1);

		$expected1 = $entity1;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(1);
		}

		$criteria = new Equals(new Reference('id'), new StringConstant((string)$uid1));
		$loaded_arr = self::iterable_to_entries($s->listByCriteria($criteria));
		self::assertEquals([
			[(string)$uid1, $expected1],
		], $loaded_arr);

		$criteria = new Equals(new Reference('id'), new StringConstant('asd'));
		$loaded_arr = self::iterable_to_entries($s->listByCriteria($criteria));
		self::assertEquals([], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testMultiGetWorks() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$uid3 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'user@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$expected1 = $entity1;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(1);
		}
		$expected3 = $entity3;
		if ($this->seq_id_injected) {
			$expected3 = $expected3->withSeqId(3);
		}

		$loaded_arr = self::iterable_to_entries($s->getItems([$uid1, $uid3]));
		self::assertEquals([
			[(string)$uid1, $expected1],
			[(string)$uid3, $expected3],
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllWorks() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'user@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'user@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);

		$expected1 = $entity1;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(1);
		}
		$expected2 = $entity2;
		if ($this->seq_id_injected) {
			$expected2 = $expected2->withSeqId(2);
		}

		$loaded_arr = self::iterable_to_entries($s->listAll());
		self::assertEquals([
			[(string)$uid1, $expected1],
			[(string)$uid2, $expected2],
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllOrderedWorks() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$uid3 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$expected1 = $entity1;
		$expected2 = $entity2;
		$expected3 = $entity3;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(1);
			$expected2 = $expected2->withSeqId(2);
			$expected3 = $expected3->withSeqId(3);
		}

		$loaded_arr = self::iterable_to_entries($s->listAll([['email', false]]));
		self::assertEquals([
			[(string)$uid3, $expected3],
			[(string)$uid2, $expected2],
			[(string)$uid1, $expected1]
		], $loaded_arr);

		$loaded_arr = self::iterable_to_entries($s->listAll([['email', true]]));
		self::assertEquals([
			[(string)$uid1, $expected1],
			[(string)$uid2, $expected2],
			[(string)$uid3, $expected3],
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllOrderedWorksWhenPropertyEquals() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$uid3 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'b@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$expected1 = $entity1;
		$expected2 = $entity2;
		$expected3 = $entity3;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(1);
			$expected2 = $expected2->withSeqId(2);
			$expected3 = $expected3->withSeqId(3);
		}

		$loaded_arr = self::iterable_to_entries($s->listAll([['email', false]]));
		self::assertEquals([
			[(string)$uid2, $expected2],
			[(string)$uid3, $expected3],
			[(string)$uid1, $expected1],
		], $loaded_arr);

		$loaded_arr = self::iterable_to_entries($s->listAll([['email', true]]));
		self::assertEquals([
			[(string)$uid1, $expected1],
			[(string)$uid2, $expected2],
			[(string)$uid3, $expected3],
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllOrderedWithLimitWorks() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$uid3 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$expected1 = $entity1;
		$expected2 = $entity2;
		$expected3 = $entity3;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(1);
			$expected2 = $expected2->withSeqId(2);
			$expected3 = $expected3->withSeqId(3);
		}

		$loaded_arr = self::iterable_to_entries($s->listAll([['email', false]], 2));
		self::assertEquals([
			[(string)$uid3, $expected3],
			[(string)$uid2, $expected2],
		], $loaded_arr);

		$loaded_arr = self::iterable_to_entries($s->listAll([['email', true]], 2));
		self::assertEquals([
			[(string)$uid1, $expected1],
			[(string)$uid2, $expected2],
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllUnorderedWithLimitWorks() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$uid3 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$expected1 = $entity1;
		$expected2 = $entity2;
		$expected3 = $entity3;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(1);
			$expected2 = $expected2->withSeqId(2);
			$expected3 = $expected3->withSeqId(3);
		}

		$loaded_arr = self::iterable_to_entries($s->listAll([], 3));
		self::assertEquals([
			[(string)$uid1, $expected1],
			[(string)$uid2, $expected2],
			[(string)$uid3, $expected3],
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListFilteredAndOrderedWorks() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$uid3 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$expected1 = $entity1;
		$expected3 = $entity3;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(1);
			$expected3 = $expected3->withSeqId(3);
		}

		$c = new NotEquals(new Reference('email'), new StringConstant('b@test.php'));

		$loaded_arr = self::iterable_to_entries($s->listByCriteria($c, [['email', false]]));
		self::assertEquals([
			[(string)$uid3, $expected3],
			[(string)$uid1, $expected1],
		], $loaded_arr);

		$loaded_arr = self::iterable_to_entries($s->listByCriteria($c, [['email', true]]));
		self::assertEquals([
			[(string)$uid1, $expected1],
			[(string)$uid3, $expected3],
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListFilteredAndOrderedWithLimitWorks() : void
	{
		$s = $this->storage;
		$uid1 = TestId::generate($this->getIdColumnLength());
		$uid2 = TestId::generate($this->getIdColumnLength());
		$uid3 = TestId::generate($this->getIdColumnLength());
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$expected1 = $entity1;
		$expected3 = $entity3;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(1);
			$expected3 = $expected3->withSeqId(3);
		}

		$c = new NotEquals(new Reference('email'), new StringConstant('b@test.php'));

		$loaded_arr = self::iterable_to_entries($s->listByCriteria($c, [['email', false]], 1));
		self::assertEquals([
			[(string)$uid3, $expected3],
		], $loaded_arr);

		$loaded_arr = self::iterable_to_entries($s->listByCriteria($c, [['email', true]], 1));
		self::assertEquals([
			[(string)$uid1, $expected1],
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllOrderByIdWorks() : void
	{
		$s = $this->storage;
		$pad = \str_repeat('0', $this->getIdColumnLength() * 2 - 1);
		$uid1 = new TestId(hex2bin($pad.'1'));
		$uid2 = new TestId(hex2bin($pad.'2'));
		$uid3 = new TestId(hex2bin($pad.'3'));
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid3, $entity3);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid1, $entity1);

		$expected1 = $entity1;
		$expected2 = $entity2;
		$expected3 = $entity3;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(3);
			$expected2 = $expected2->withSeqId(2);
			$expected3 = $expected3->withSeqId(1);
		}

		$loaded_arr = self::iterable_to_entries($s->listAll([['id', false]]));
		self::assertEquals([
			[(string)$uid3, $expected3],
			[(string)$uid2, $expected2],
			[(string)$uid1, $expected1],
		], $loaded_arr);

		$loaded_arr = self::iterable_to_entries($s->listAll([['id', true]]));
		self::assertEquals([
			[(string)$uid1, $expected1],
			[(string)$uid2, $expected2],
			[(string)$uid3, $expected3],
		], $loaded_arr);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testListAllOrderByIdWithLimitWorks() : void
	{
		$s = $this->storage;
		$pad = \str_repeat('0', $this->getIdColumnLength() * 2 - 1);
		$uid1 = new TestId(hex2bin($pad.'1'));
		$uid2 = new TestId(hex2bin($pad.'2'));
		$uid3 = new TestId(hex2bin($pad.'3'));
		$entity1 = new TestEntity($uid1, 'Test User 1', 'a@test.php', null, 1);
		$entity2 = new TestEntity($uid2, 'Test User 2', 'b@test.php', null, 1);
		$entity3 = new TestEntity($uid3, 'Test User 3', 'c@test.php', null, 1);
		$s->saveItem($uid1, $entity1);
		$s->saveItem($uid2, $entity2);
		$s->saveItem($uid3, $entity3);

		$expected1 = $entity1;
		$expected2 = $entity2;
		$expected3 = $entity3;
		if ($this->seq_id_injected) {
			$expected1 = $expected1->withSeqId(1);
			$expected2 = $expected2->withSeqId(2);
			$expected3 = $expected3->withSeqId(3);
		}

		$loaded_arr = self::iterable_to_entries($s->listAll([['id', false]], 1));
		self::assertEquals([
			[(string)$uid3, $expected3],
		], $loaded_arr);

		$loaded_arr = self::iterable_to_entries($s->listAll([['id', true]], 2));
		self::assertEquals([
			[(string)$uid1, $expected1],
			[(string)$uid2, $expected2],
		], $loaded_arr);
	}

	protected static function iterable_to_array($it, $use_keys = true)
	{
		return \is_array($it) ? $it : \iterator_to_array($it, $use_keys);
	}

	protected static function iterable_to_entries($it) : array{
		$result = [];
		foreach ($it as $k=>$v){
			$result[] = [$k, $v];
		}
		return $result;
	}

}
