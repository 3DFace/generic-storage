<?php

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\UnderlyingStorageError;
use dface\GenericStorage\Mysql\MySet;

class MySetTest extends GenericSetTest
{

	private MysqliSameLinkProvider $linkProvider;

	protected function setUp() : void
	{
		$this->linkProvider = LinkProviderFactory::createLinkProvider();
		$this->set = new MySet(
			$this->linkProvider,
			'test_set',
			TestId::class,
			true);
		$this->set->reset();
	}

	/**
	 * @throws UnderlyingStorageError
	 */
	private function broke() : void
	{
		/** @noinspection SqlResolve */
		$this->linkProvider->getLink()->command('DROP TABLE test_set');
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testIterateTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		self::iterable_to_array($this->set->iterate());
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testContainsTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->set->contains(TestId::generate($this->getIdLength()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testAddTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->set->add(TestId::generate($this->getIdLength()));
	}

	/**
	 * @throws Generic\GenericStorageError
	 */
	public function testRemoveTroubles() : void
	{
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->set->remove(TestId::generate($this->getIdLength()));
	}

}
