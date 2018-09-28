<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\GenericSet;
use PHPUnit\Framework\TestCase;

abstract class GenericSetTest extends TestCase {

	/** @var GenericSet */
	protected $set;

	protected function getIdLength() : int {
		return 16;
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testContainsAdded() : void {
		$id = TestId::generate($this->getIdLength());
		$this->set->add($id);
		$this->assertTrue($this->set->contains($id));
		$this->assertEquals([$id], iterator_to_array($this->set->iterate()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function TestId() : void {
		$id = TestId::generate($this->getIdLength());
		$this->set->add($id);
		$another_id = TestId::generate($this->getIdLength());
		$this->assertFalse($this->set->contains($another_id));
		$this->assertEquals([$id], iterator_to_array($this->set->iterate()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testNotContainsRemoved() : void {
		$id = TestId::generate($this->getIdLength());
		$this->set->add($id);
		$this->set->remove($id);
		$this->assertFalse($this->set->contains($id));
		$this->assertEquals([], iterator_to_array($this->set->iterate()));
	}

}
