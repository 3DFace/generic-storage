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
		$this->assertEquals([$id], self::iterable_to_array($this->set->iterate()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function TestId() : void {
		$id = TestId::generate($this->getIdLength());
		$this->set->add($id);
		$another_id = TestId::generate($this->getIdLength());
		$this->assertFalse($this->set->contains($another_id));
		$this->assertEquals([$id], self::iterable_to_array($this->set->iterate()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testNotContainsRemoved() : void {
		$id = TestId::generate($this->getIdLength());
		$this->set->add($id);
		$this->set->remove($id);
		$this->assertFalse($this->set->contains($id));
		$this->assertEquals([], self::iterable_to_array($this->set->iterate()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testEmptyAfterClear() : void {
		$this->set->add(TestId::generate($this->getIdLength()));
		$this->set->add(TestId::generate($this->getIdLength()));
		$this->set->clear();
		$this->assertEquals([], self::iterable_to_array($this->set->iterate()));
	}

	protected static function iterable_to_array($it, $use_keys = true){
		return \is_array($it) ? $it : \iterator_to_array($it, $use_keys);
	}

}
