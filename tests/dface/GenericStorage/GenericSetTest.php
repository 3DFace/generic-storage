<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\GenericSet;
use PHPUnit\Framework\TestCase;

abstract class GenericSetTest extends TestCase {

	/** @var GenericSet */
	protected $set;

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testContainsAdded() : void {
		$id = new TestId();
		$this->set->add($id);
		$this->assertTrue($this->set->contains($id));
		$this->assertEquals([$id], iterator_to_array($this->set->iterate()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function TestId() : void {
		$id = new TestId();
		$this->set->add($id);
		$another_id = new TestId();
		$this->assertFalse($this->set->contains($another_id));
		$this->assertEquals([$id], iterator_to_array($this->set->iterate()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testNotContainsRemoved() : void {
		$id = new TestId();
		$this->set->add($id);
		$this->set->remove($id);
		$this->assertFalse($this->set->contains($id));
		$this->assertEquals([], iterator_to_array($this->set->iterate()));
	}

}
