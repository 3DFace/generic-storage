<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\GenericManyToMany;
use PHPUnit\Framework\TestCase;

abstract class GenericManyToManyTest extends TestCase {

	/** @var GenericManyToMany */
	protected $assoc;

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testOneToOne() : void {
		$l = new TestId();
		$r = new TestId();
		$this->assoc->add($l, $r);

		$byLeft = iterator_to_array($this->assoc->getAllByLeft($l));
		$this->assertEquals([$r], $byLeft);

		$byRight = iterator_to_array($this->assoc->getAllByRight($r));
		$this->assertEquals([$l], $byRight);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testHas() : void {
		$l = new TestId();
		$r = new TestId();
		$x = new TestId();

		$this->assertFalse($this->assoc->has($l, $r));

		$this->assoc->add($l, $r);

		$this->assertTrue($this->assoc->has($l, $r));
		$this->assertFalse($this->assoc->has($l, $x));
		$this->assertFalse($this->assoc->has($x, $r));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testManyToOne() : void {
		$l1 = new TestId();
		$l2 = new TestId();
		$r = new TestId();

		$this->assoc->add($l1, $r);
		$this->assoc->add($l2, $r);

		$byLeft = iterator_to_array($this->assoc->getAllByLeft($l1));
		$this->assertEquals([$r], $byLeft);

		$byLeft = iterator_to_array($this->assoc->getAllByLeft($l2));
		$this->assertEquals([$r], $byLeft);

		$byRight = iterator_to_array($this->assoc->getAllByRight($r));
		$this->assertSetIs([$l1, $l2], $byRight);

		$this->assoc->clearRight($r);
		$byRight = iterator_to_array($this->assoc->getAllByRight($r));
		$this->assertEquals([], $byRight);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testOneToMany() : void {
		$l = new TestId();

		$r1 = new TestId();
		$r2 = new TestId();
		$this->assoc->add($l, $r1);
		$this->assoc->add($l, $r2);

		$byLeft = iterator_to_array($this->assoc->getAllByLeft($l));
		$this->assertSetIs([$r1, $r2], $byLeft);

		$byRight = iterator_to_array($this->assoc->getAllByRight($r1));
		$this->assertEquals([$l], $byRight);

		$byRight = iterator_to_array($this->assoc->getAllByRight($r2));
		$this->assertEquals([$l], $byRight);

		$this->assoc->clearLeft($l);
		$byLeft = iterator_to_array($this->assoc->getAllByLeft($l));
		$this->assertEquals([], $byLeft);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemove() : void {
		$l = new TestId();
		$r = new TestId();
		$this->assoc->add($l, $r);
		$this->assoc->remove($l, $r);

		$byLeft = iterator_to_array($this->assoc->getAllByLeft($l));
		$this->assertEquals([], $byLeft);

		$byRight = iterator_to_array($this->assoc->getAllByRight($r));
		$this->assertEquals([], $byRight);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testClearLeft() : void {
		$l1 = new TestId();
		$l2 = new TestId();
		$r1 = new TestId();
		$r2 = new TestId();
		$r3 = new TestId();
		$r4 = new TestId();

		$this->assoc->add($l1, $r1);
		$this->assoc->add($l1, $r2);
		$this->assoc->add($l2, $r3);
		$this->assoc->add($l2, $r4);

		$this->assoc->clearLeft($l1);
		$byLeft = iterator_to_array($this->assoc->getAllByLeft($l2));
		$this->assertSetIs([$r3, $r4], $byLeft);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testClearRight() : void {
		$l1 = new TestId();
		$l2 = new TestId();
		$l3 = new TestId();
		$l4 = new TestId();
		$r1 = new TestId();
		$r2 = new TestId();

		$this->assoc->add($l1, $r1);
		$this->assoc->add($l2, $r1);
		$this->assoc->add($l3, $r2);
		$this->assoc->add($l4, $r2);

		$this->assoc->clearRight($r1);
		$byRight = iterator_to_array($this->assoc->getAllByRight($r2));
		$this->assertSetIs([$l3, $l4], $byRight);
	}

	private function assertSetIs(array $expected, array $set){
		$this->assertCount(\count($expected), $set);
		foreach($expected as $e){
			$this->assertTrue(\in_array($e, $set, false));
		}
	}

}
