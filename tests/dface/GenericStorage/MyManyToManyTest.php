<?php

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\UnderlyingStorageError;
use dface\GenericStorage\Mysql\MyManyToMany;

class MyManyToManyTest extends GenericManyToManyTest
{

	private MysqliSameLinkProvider $linkProvider;

	protected function setUp() : void
	{
		$this->linkProvider = LinkProviderFactory::createLinkProvider();
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
		$this->linkProvider->getLink()->command('DROP TABLE test_many_to_many');
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testGetAllByLeftTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		self::iterable_to_array($this->assoc->getAllByLeft(TestId::generate($this->getIdLength())));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testGetAllByRightTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		self::iterable_to_array($this->assoc->getAllByRight(TestId::generate($this->getIdLength())));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testAddTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->add(TestId::generate($this->getIdLength()), TestId::generate($this->getIdLength()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemoveTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->remove(TestId::generate($this->getIdLength()), TestId::generate($this->getIdLength()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testClearRightTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->clearRight(TestId::generate($this->getIdLength()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testClearLeftTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->clearLeft(TestId::generate($this->getIdLength()));
	}

}
