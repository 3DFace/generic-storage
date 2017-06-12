<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Memory\MemoryManyToMany;
use dface\GenericStorage\Generic\GenericManyToMany;
use PHPUnit\Framework\TestCase;

abstract class GenericManyToManyTest extends TestCase {

	/** @var GenericManyToMany */
	protected $assoc;

	public function testOneToOne() : void {
		$l = new TestId();
		$r = new TestId();
		$this->assoc->add($l, $r);

		$byLeft = iterator_to_array($this->assoc->getAllByLeft($l));
		$this->assertEquals([$r], $byLeft);

		$byRight = iterator_to_array($this->assoc->getAllByRight($r));
		$this->assertEquals([$l], $byRight);
	}

	public function testManyToOne() : void {
		$this->assoc = new MemoryManyToMany();
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
		$this->assertEquals([$l1, $l2], $byRight);

		$this->assoc->clearRight($r);
		$byRight = iterator_to_array($this->assoc->getAllByRight($r));
		$this->assertEquals([], $byRight);
	}

	public function testOneToMany() : void {
		$this->assoc = new MemoryManyToMany();
		$l = new TestId();

		$r1 = new TestId();
		$r2 = new TestId();
		$this->assoc->add($l, $r1);
		$this->assoc->add($l, $r2);

		$byLeft = iterator_to_array($this->assoc->getAllByLeft($l));
		$this->assertEquals([$r1, $r2], $byLeft);

		$byRight = iterator_to_array($this->assoc->getAllByRight($r1));
		$this->assertEquals([$l], $byRight);

		$byRight = iterator_to_array($this->assoc->getAllByRight($r2));
		$this->assertEquals([$l], $byRight);

		$this->assoc->clearLeft($l);
		$byLeft = iterator_to_array($this->assoc->getAllByLeft($l));
		$this->assertEquals([], $byLeft);
	}

}
