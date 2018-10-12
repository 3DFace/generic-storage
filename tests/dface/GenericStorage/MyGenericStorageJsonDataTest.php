<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyStorageBuilder;

class MyGenericStorageJsonDataTest extends GenericStorageTest
{

	protected function setUp()
	{
		$linkProvider = LinkProviderFactory::createLinkProvider();
		$this->storage = (new MyStorageBuilder(TestEntity::class, $linkProvider, 'test_gen_storage'))
			->setIdPropertyName('id')
			->setRevisionPropertyName('revision')
			->addColumns([
//				'email' => 'VARCHAR(128)',
			])
			->addGeneratedColumns([
				'`email` VARCHAR(128) AS (JSON_UNQUOTE(JSON_EXTRACT(`$data`, \'$.email\'))) VIRTUAL',
				'`data/a` VARCHAR(128) AS (JSON_UNQUOTE(JSON_EXTRACT(`$data`, \'$.data.a\'))) VIRTUAL',
			])
			->addIndexes([
				'INDEX email(email)',
			])
			->setBatchListSize(10)
			->setIdBatchSize(10)
			->setTemporary(false)
			->setDataColumnDef('JSON')
			->setDataMaxSize(65535)
			->setCompressed(true)
			->build();
		$this->storage->reset();
	}

}
