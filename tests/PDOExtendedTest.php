<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\YardBird\Drivers\PDOExtended;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Interfaces\IRelationalConnectable;

final class PDOExtendedTest extends TestCase
{

	///////////////////////////////// UNIT ////////////////////////////////////////////////
	public function test_PDOExtended_should_implements_IRelationalConnectable(): void
	{
		$reflection = new \ReflectionClass(PDOExtended::class);
		$this->assertTrue($reflection->implementsInterface(IRelationalConnectable::class));
	}

	public function test_validateConnectionParams_should_return_exception_if_no_valid_parameters_passed(): void
    {
		$this->expectException(BadMethodCallException::class);
		new PDOExtended([
			'driver' => 'mysql',
			'host' => '',
			'user' => null,
			'password' => null
		]);
	}

	public function test_validateConnectionParams_should_return_exception_if_missing_parameters(): void
    {
		$this->expectException(BadMethodCallException::class);
		new PDOExtended([
			'driver' => 'mysql'
		]);
	}

	public function test_validateConnectionParams_should_return_exception_if_driver_not_oci_and_dbName_empty(): void
    {
		$this->expectException(UnexpectedValueException::class);
		new PDOExtended([
			'driver' => 'mysql',
			'dbName' => '',
			'host' => '127.0.0.1',
			'user' => 'username',
			'password' => 'userpassword'
		]);
	}

	public function test_validateConnectionParams_should_return_exception_if_driver_is_oci_and_sid_or_serviceName_not_valid(): void
    {
		$this->expectException(UnexpectedValueException::class);
		new PDOExtended([
			'driver' => 'oci',
			'dbName' => '',
			'host' => '127.0.0.1',
			'user' => 'username',
			'password' => 'userpassword',
			'sid' => '',
			'serviceName' => ''
		]);
	}

	public function test_getDefaultString_should_return_correctly_parsed_string(): void
	{
		$params = ['driver' => 'driver', 'host' => 'host', 'port' => 'port', 'dbName' => 'dbName', 'charset' => 'charset'];
		$expected = "driver:host=host;port=port;dbname=dbName;charset=charset";
		$reflection = new \ReflectionClass(PDOExtended::class);
		$method = $reflection->getMethod('getDefaultString');
		$method->setAccessible(true);

		$result = $method->invokeArgs($reflection, [$params]);
		$this->assertEquals($expected, $result);
	}

	public function test_getOciString_should_return_exception_if_both_sid_and_serviceName_not_valid(): void
	{
		$this->expectException(BadMethodCallException::class);
		$reflection = new \ReflectionClass(PDOExtended::class);
		$method = $reflection->getMethod('getOciString');
		$method->setAccessible(true);

		$method->invokeArgs($reflection, [['sid' => null, 'serviceName' => null]]);
	}

	public function test_getOciString_should_return_correctly_parsed_string(): void
	{
		$params = ['host' => 'host', 'port' => 'port', 'sid' => 'sid', 'charset' => 'charset'];
		$expected = "oci:dbname=(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=host)(PORT=port)))(CONNECT_DATA=(SID=sid)));charset=charset";
		$reflection = new \ReflectionClass(PDOExtended::class);
		$method = $reflection->getMethod('getOciString');
		$method->setAccessible(true);

		$result = $method->invokeArgs($reflection, [$params]);
		$this->assertEquals($expected, $result);
	}

	public function test_composeConnectionParams_should_return_array(): void
	{
		$params = ['driver' => 'driver', 'host' => 'host', 'port' => 'port', 'dbName' => 'dbName', 'charset' => 'charset', 'user' => 'username', 'password' => 'userpassword'];
		$expected = [
			"driver:host=host;port=port;dbname=dbName;charset=charset",
			'username',
			'userpassword'
		];

		$reflection = new \ReflectionClass(PDOExtended::class);
		$method = $reflection->getMethod('composeConnectionParams');
		$method->setAccessible(true);

		$result = $method->invokeArgs($reflection, [$params]);

		$this->assertEquals($expected, $result);
	}

	public function test_constructor_should_throw_exception_if_cant_establish_connection(): void
	{
		$this->expectException(ConnectionException::class);
		$params = ['driver' => 'mysql', 'host' => 'localhost', 'port' => 3306, 'dbName' => 'invalid', 'charset' => 'UTF8', 'user' => 'invalid', 'password' => 'invalid'];
		new PDOExtended($params);
	}
	
	/*public function test_sql_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('sql')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->sql('SELECT * FROM table WHERE id=:id', [ 'unusedname' => 'value' ]);
	}

	public function test_sql_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('sql')
			->will($this->throwException(new QueryException));

		$dbMock->sql('SELECT * FROM table WHERE id=:id', [ 'id' => 'value' ]);
	}

	public function test_select_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('select')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->select('table', ['field1', 'field2'], ['unusedname' => function(){} ]);
	}

	public function test_select_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('select')
			->will($this->throwException(new QueryException));

		$dbMock->select('table', ['field1'], ['field1' => 'value' ]);
	}

	public function test_insert_should_throw_exception_if_driver_not_supported(): void
	{
		$this->expectException(\Exception::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('insert')
			->will($this->throwException(new \Exception));

		$dbMock->insert('table', ['field1' => 'field2']);
	}

	public function test_insert_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('insert')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->insert('table', ['name' => function() {} ]);
	}

	public function test_insert_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('insert')
			->will($this->throwException(new QueryException));

		$dbMock->insert('table', ['name' => 'value' ]);
	}

	public function test_update_should_throw_exception_if_where_param_not_valid(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('update')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->update('table', ['name' => 'table' ], [ 'invalidarray' ]);
	}

	public function test_update_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('update')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->update('table', ['name' => function() {} ]);
	}

	public function test_update_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('update')
			->will($this->throwException(new QueryException));

		$dbMock->update('table', ['name' => 'value' ]);
	}

	public function test_delete_should_throw_exception_if_where_param_not_valid(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('delete')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->delete('table', ['name' => 'table' ], [ 'invalidarray' ]);
	}

	public function test_delete_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('delete')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->delete('table', ['name' => function() {} ]);
	}

	public function test_delete_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('delete')
			->will($this->throwException(new QueryException));

		$dbMock->delete('table', ['name' => 'value' ]);
	}

	public function test_procedure_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('procedure')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->procedure('table', ['name' => function() {} ]);
	}

	public function test_procedure_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(PDOExtended::class);
		$dbMock->method('procedure')
			->will($this->throwException(new QueryException));

		$dbMock->procedure('table', ['name' => 'value' ]);
	}*/
	///////////////////////////////// INTEGRATION ////////////////////////////////////////////////
	
}