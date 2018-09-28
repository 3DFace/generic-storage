<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\GenericManyToMany;
use PHPUnit\Framework\TestCase;

abstract class GenericManyToManyTest extends TestCase {

	/** @var GenericManyToMany */
	protected $assoc;

	protected function getIdLength() : int {
		return 16;
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testOneToOne() : void {
		$l = TestId::generate($this->getIdLength());
		$r = TestId::generate($this->getIdLength());
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
		$l = TestId::generate($this->getIdLength());
		$r = TestId::generate($this->getIdLength());
		$x = TestId::generate($this->getIdLength());

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
		$l1 = TestId::generate($this->getIdLength());
		$l2 = TestId::generate($this->getIdLength());
		$r = TestId::generate($this->getIdLength());

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
		$l = TestId::generate($this->getIdLength());

		$r1 = TestId::generate($this->getIdLength());
		$r2 = TestId::generate($this->getIdLength());
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
		$l = TestId::generate($this->getIdLength());
		$r = TestId::generate($this->getIdLength());
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
		$byLeft = iterator_to_array($this->assoc->getAllByLeft($l2));
		$this->assertSetIs([$r3, $r4], $byLeft);
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testClearRight() : void {
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
