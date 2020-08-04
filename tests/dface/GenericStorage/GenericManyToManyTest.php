<?php

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\GenericManyToMany;
use PHPUnit\Framework\TestCase;

abstract class GenericManyToManyTest extends TestCase
{

	protected GenericManyToMany $assoc;

	protected function getIdLength() : int
	{
		return 16;
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testOneToOne() : void
	{
		$l = TestId::generate($this->getIdLength());
		$r = TestId::generate($this->getIdLength());
		$this->assoc->add($l, $r);

		$byLeft = self::iterable_to_array($this->assoc->getAllByLeft($l));
		self::assertEquals([$r], $byLeft);

		$byRight = self::iterable_to_array($this->assoc->getAllByRight($r));
		self::assertEquals([$l], $byRight);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testHas() : void
	{
		$l = TestId::generate($this->getIdLength());
		$r = TestId::generate($this->getIdLength());
		$x = TestId::generate($this->getIdLength());

		self::assertFalse($this->assoc->has($l, $r));

		$this->assoc->add($l, $r);

		self::assertTrue($this->assoc->has($l, $r));
		self::assertFalse($this->assoc->has($l, $x));
		self::assertFalse($this->assoc->has($x, $r));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testManyToOne() : void
	{
		$l1 = TestId::generate($this->getIdLength());
		$l2 = TestId::generate($this->getIdLength());
		$r = TestId::generate($this->getIdLength());

		$this->assoc->add($l1, $r);
		$this->assoc->add($l2, $r);

		$byLeft = self::iterable_to_array($this->assoc->getAllByLeft($l1));
		self::assertEquals([$r], $byLeft);

		$byLeft = self::iterable_to_array($this->assoc->getAllByLeft($l2));
		self::assertEquals([$r], $byLeft);

		$byRight = self::iterable_to_array($this->assoc->getAllByRight($r));
		$this->assertSetIs([$l1, $l2], $byRight);

		$this->assoc->clearRight($r);
		$byRight = self::iterable_to_array($this->assoc->getAllByRight($r));
		self::assertEquals([], $byRight);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testOneToMany() : void
	{
		$l = TestId::generate($this->getIdLength());

		$r1 = TestId::generate($this->getIdLength());
		$r2 = TestId::generate($this->getIdLength());
		$this->assoc->add($l, $r1);
		$this->assoc->add($l, $r2);

		$byLeft = self::iterable_to_array($this->assoc->getAllByLeft($l));
		$this->assertSetIs([$r1, $r2], $byLeft);

		$byRight = self::iterable_to_array($this->assoc->getAllByRight($r1));
		self::assertEquals([$l], $byRight);

		$byRight = self::iterable_to_array($this->assoc->getAllByRight($r2));
		self::assertEquals([$l], $byRight);

		$this->assoc->clearLeft($l);
		$byLeft = self::iterable_to_array($this->assoc->getAllByLeft($l));
		self::assertEquals([], $byLeft);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemove() : void
	{
		$l = TestId::generate($this->getIdLength());
		$r = TestId::generate($this->getIdLength());
		$this->assoc->add($l, $r);
		$this->assoc->remove($l, $r);

		$byLeft = self::iterable_to_array($this->assoc->getAllByLeft($l));
		self::assertEquals([], $byLeft);

		$byRight = self::iterable_to_array($this->assoc->getAllByRight($r));
		self::assertEquals([], $byRight);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testClearLeft() : void
	{
		$l1 = TestId::generate($this->getIdLength());
		$l2 = TestId::generate($this->getIdLength());
		$r1 = TestId::generate($this->getIdLength());
		$r2 = TestId::generate($this->getIdLength());
		$r3 = TestId::generate($this->getIdLength());
		$r4 = TestId::generate($this->getIdLength());

		$this->assoc->add($l1, $r1);
		$this->assoc->add($l1, $r2);
		$this->assoc->add($l2, $r3);
		$this->assoc->add($l2, $r4);

		$this->assoc->clearLeft($l1);
		$byLeft = self::iterable_to_array($this->assoc->getAllByLeft($l2));
		$this->assertSetIs([$r3, $r4], $byLeft);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testClearRight() : void
	{
		$l1 = TestId::generate($this->getIdLength());
		$l2 = TestId::generate($this->getIdLength());
		$l3 = TestId::generate($this->getIdLength());
		$l4 = TestId::generate($this->getIdLength());
		$r1 = TestId::generate($this->getIdLength());
		$r2 = TestId::generate($this->getIdLength());

		$this->assoc->add($l1, $r1);
		$this->assoc->add($l2, $r1);
		$this->assoc->add($l3, $r2);
		$this->assoc->add($l4, $r2);

		$this->assoc->clearRight($r1);
		$byRight = self::iterable_to_array($this->assoc->getAllByRight($r2));
		$this->assertSetIs([$l3, $l4], $byRight);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testEmptyAfterClear() : void
	{
		$l1 = TestId::generate($this->getIdLength());
		$l2 = TestId::generate($this->getIdLength());
		$l3 = TestId::generate($this->getIdLength());
		$l4 = TestId::generate($this->getIdLength());
		$r1 = TestId::generate($this->getIdLength());
		$r2 = TestId::generate($this->getIdLength());

		$this->assoc->add($l1, $r1);
		$this->assoc->add($l2, $r1);
		$this->assoc->add($l3, $r2);
		$this->assoc->add($l4, $r2);
		$this->assoc->clear();

		self::assertFalse($this->assoc->has($l1, $r1));
		self::assertFalse($this->assoc->has($l2, $r1));
		self::assertFalse($this->assoc->has($l3, $r2));
		self::assertFalse($this->assoc->has($l4, $r2));
	}

	private function assertSetIs(array $expected, array $set) : void
	{
		self::assertCount(\count($expected), $set);
		foreach ($expected as $e) {
			/** @noinspection PhpUnitTestsInspection */
			self::assertTrue(\in_array($e, $set, false));
		}
	}

	protected static function iterable_to_array($it, $use_keys = true)
	{
		return \is_array($it) ? $it : \iterator_to_array($it, $use_keys);
	}

}
