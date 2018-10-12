<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyStorageBuilder;

class MyGenericStorageBatched1Test extends GenericStorageTest
{
	protected function getIdLength() : int
	{
		return 32;
	}

	protected function setUp()
	{
		$linkProvider = LinkProviderFactory::createLinkProvider();
		$this->storage = (new MyStorageBuilder(TestEntity::class, $linkProvider, 'test_gen_storage'))
			->setIdPropertyName('id')
			->setIdLength(32)
			->setRevisionPropertyName('revision')
			->addColumns([
				'email' => 'VARCHAR(128)',
				'data/a' => 'VARCHAR(128)',
			])
			->addIndexes([
				'INDEX email(email)',
			])
			->setBatchListSize(1)
			->setIdBatchSize(1)
			->setTemporary(true)
			->build();
		$this->storage->reset();
	}

}
