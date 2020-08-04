<?php


use dface\GenericStorage\Mysql\MyStorageBuilder;
use dface\GenericStorage\MysqliSameLinkProvider;
use dface\GenericStorage\TestEntity;
use dface\sql\placeholders\DefaultFormatter;
use dface\sql\placeholders\DefaultParser;

include_once __DIR__.'/bootstrap.php';

$link = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_BASE);
$link->set_charset(DB_CHARSET);
$parser = new DefaultParser();
$formatter = new DefaultFormatter();

$provider = new MysqliSameLinkProvider($link);

$storage = (new MyStorageBuilder(TestEntity::class, $provider, 'test_gen_storage'))
	->setIdPropertyName('id')
	->setRevisionPropertyName('revision')
	->addColumns([
		'email' => 'VARCHAR(128)',
		'data/a' => 'VARCHAR(128)',
	])
	->addIndexes([
		'INDEX email(email)',
	])
	->setBatchListSize(0)
	->build();

/** @noinspection PhpUnhandledExceptionInspection */
$storage->reset();

$limit = 10000;

/** @var TestEntity[] $data */
$data = [];
for($i=0; $i<$limit; $i++){
	$id = \dface\GenericStorage\TestId::generate(16);
	$e = new TestEntity($id, 'name-'.$i, 'name-'.$i.'@benchmark', new \dface\GenericStorage\TestData('asd', $i), 1);
	$data[] = $e;
}

$started = microtime(true);

$link->autocommit(false);

foreach($data as $e){
	$id = $e->getId();
	/** @noinspection PhpUnhandledExceptionInspection */
	$storage->saveItem($id, $e);
	/** @noinspection PhpUnhandledExceptionInspection */
	$storage->getItem($id);
}

$link->rollback();

echo (microtime(true) - $started);
