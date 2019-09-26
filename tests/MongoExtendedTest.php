<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\YardBird\Drivers\MongoExtended;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Interfaces\IConnectable;

final class MongoExtendedTest extends TestCase
{

	///////////////////////////////// UNIT ////////////////////////////////////////////////
	public function test_MongoExtended_should_implements_IConnectable(): void
	{
		$reflection = new \ReflectionClass(MongoExtended::class);
		$this->assertTrue($reflection->implementsInterface(IConnectable::class));
	}

	public function test_validateConnectionParams_should_return_exception_if_no_valid_parameters_passed(): void
    {
		$this->expectException(BadMethodCallException::class);
		new MongoExtended([
			'host' => '',
			'user' => null,
			'password' => null,
			'dbName' => null
		]);
	}

	public function test_validateConnectionParams_should_return_exception_if_missing_parameters(): void
    {
		$this->expectException(BadMethodCallException::class);
		new MongoExtended([]);
	}

	public function test_composeConnectionParams_should_return_array(): void
	{
		$params = ['host' => 'host', 'port' => 'port', 'dbName' => 'dbName', 'charset' => 'charset', 'user' => 'username', 'password' => 'userpassword'];
		$expected = [
			"mongodb://username:userpassword@host:port",
			[ 'authSource' => 'admin' ]
		];

		$reflection = new \ReflectionClass(MongoExtended::class);
		$method = $reflection->getMethod('composeConnectionParams');
		$method->setAccessible(true);

		$result = $method->invokeArgs($reflection, [$params, [ 'authSource' => 'admin' ]]);

		$this->assertEquals($expected, $result);
	}

	public function test_constructor_should_throw_exception_if_cant_establish_connection(): void
	{
		$this->expectException(ConnectionException::class);
		$params = ['host' => 'localhost', 'port' => 3306, 'dbName' => 'invalid', 'charset' => 'UTF8', 'user' => 'invalid', 'password' => 'invalid'];
		new MongoExtended($params);
	}
	
}