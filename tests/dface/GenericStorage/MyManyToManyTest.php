<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\UnderlyingStorageError;
use dface\GenericStorage\Mysql\MyManyToMany;

class MyManyToManyTest extends GenericManyToManyTest
{

	/** @var MySameLinkProvider */
	private $linkProvider;

	protected function setUp() : void
	{
		$this->linkProvider = DbiFactory::getSameLinkProvider();
		$this->assoc = new MyManyToMany(
			$this->linkProvider,
			'test_many_to_many',
			TestId::class,
			TestId::class,
			'left', 'right', true);
		$this->assoc->reset();
	}

	private function broke() : void
	{
		/** @noinspection SqlResolve */
		$this->linkProvider->getLink()->query('DROP TABLE test_many_to_many');
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testGetAllByLeftTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		iterator_to_array($this->assoc->getAllByLeft(new TestId()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testGetAllByRightTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		iterator_to_array($this->assoc->getAllByRight(new TestId()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testAddTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->add(new TestId(), new TestId());
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemoveTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->remove(new TestId(), new TestId());
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testClearRightTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->clearRight(new TestId());
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testClearLeftTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->clearLeft(new TestId());
	}

}
