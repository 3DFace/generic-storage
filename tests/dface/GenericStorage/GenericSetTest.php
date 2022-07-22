<?php

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\GenericSet;
use PHPUnit\Framework\TestCase;

abstract class GenericSetTest extends TestCase
{

	protected GenericSet $set;

	protected function getIdLength() : int
	{
		return 16;
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testContainsAdded() : void
	{
		$id = TestId::generate($this->getIdLength());
		$this->set->add($id);
		self::assertTrue($this->set->contains($id));
		self::assertEquals([$id], self::iterable_to_array($this->set->iterate()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function TestId() : void
	{
		$id = TestId::generate($this->getIdLength());
		$this->set->add($id);
		$another_id = TestId::generate($this->getIdLength());
		self::assertFalse($this->set->contains($another_id));
		self::assertEquals([$id], self::iterable_to_array($this->set->iterate()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testNotContainsRemoved() : void
	{
		$id = TestId::generate($this->getIdLength());
		$this->set->add($id);
		$this->set->remove($id);
		self::assertFalse($this->set->contains($id));
		self::assertEquals([], self::iterable_to_array($this->set->iterate()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testEmptyAfterClear() : void
	{
		$this->set->add(TestId::generate($this->getIdLength()));
		$this->set->add(TestId::generate($this->getIdLength()));
		$this->set->clear();
		self::assertEquals([], self::iterable_to_array($this->set->iterate()));
	}

	protected static function iterable_to_array($it, $use_keys = true) : array
	{
		return \is_array($it) ? $it : \iterator_to_array($it, $use_keys);
	}

}
