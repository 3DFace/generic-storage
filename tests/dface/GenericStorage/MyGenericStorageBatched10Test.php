<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyStorageBuilder;

class MyGenericStorageBatched10Test extends GenericStorageTest
{

	protected function getIdColumnLength() : int
	{
		return 32;
	}

	protected function setUp() : void
	{
		$linkProvider = LinkProviderFactory::createLinkProvider();
		$this->storage = (new MyStorageBuilder(TestEntity::class, $linkProvider, 'test_gen_storage'))
			->setIdPropertyName('id')
			->setIdColumnDef('BINARY(32)')
			->setRevisionPropertyName('revision')
			->addColumns([
				'email' => 'VARCHAR(128)',
				'data/a' => 'VARCHAR(128)',
			])
			->addIndexes([
				'INDEX email(email)',
			])
			->setBatchListSize(10)
			->setIdBatchSize(10)
			->setTemporary(false)
			->build();
		$this->storage->reset();
	}

}
