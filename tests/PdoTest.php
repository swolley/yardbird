<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\YardBird\Drivers\Pdo;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Interfaces\IRelationalConnectable;

/**
 * @covers Swolley\YardBird\Drivers\Pdo
 */
final class PdoTest extends TestCase
{

	///////////////////////////////// UNIT ////////////////////////////////////////////////
	/**
     * @codeCoverageIgnore
	 * @group unit
     */
	public function test_Pdo_should_implements_IRelationalConnectable(): void
	{
		$reflection = new \ReflectionClass(Pdo::class);
		$this->assertTrue($reflection->implementsInterface(IRelationalConnectable::class));
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_exception_if_invalid_driver(): void
    {
		$this->expectException(UnexpectedValueException::class);
		new Pdo([ 'driver' => 'invalid' ]);
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_exception_if_missing_parameters(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$reflection = new \ReflectionClass(Pdo::class);
		$method = $reflection->getMethod('validateConnectionParams');
		$method->setAccessible(true);
		$method->invokeArgs($reflection, [[ 'driver' => 'mysql' ]]);
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_exception_if_empty_values(): void
    {
		$this->expectException(UnexpectedValueException::class);
		$reflection = new \ReflectionClass(Pdo::class);
		$method = $reflection->getMethod('validateConnectionParams');
		$method->setAccessible(true);
		$method->invokeArgs($reflection, [[ 'driver' => 'mysql', 'host' => '', 'user' => '', 'password' => ''	]]);
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_exception_if_missing_dbName_and_connection_not_oci(): void
    {
		$this->expectException(UnexpectedValueException::class);
		$reflection = new \ReflectionClass(Pdo::class);
		$method = $reflection->getMethod('validateConnectionParams');
		$method->setAccessible(true);
		$method->invokeArgs($reflection, [[ 'driver' => 'mysql', 'host' => 'value', 'user' => 'value', 'password' => 'value']]);
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_exception_if_empty_dbName_and_connection_not_oci(): void
    {
		$this->expectException(UnexpectedValueException::class);
		$reflection = new \ReflectionClass(Pdo::class);
		$method = $reflection->getMethod('validateConnectionParams');
		$method->setAccessible(true);
		$method->invokeArgs($reflection, [[ 'driver' => 'mysql', 'host' => 'value', 'user' => 'value', 'password' => 'value', 'dbName' => '']]);
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_exception_if_driver_is_oci_and_sid_or_serviceName_not_valid(): void
    {
		$this->expectException(UnexpectedValueException::class);
		$reflection = new \ReflectionClass(Pdo::class);
		$method = $reflection->getMethod('validateConnectionParams');
		$method->setAccessible(true);
		$method->invokeArgs($reflection, [[ 'driver' => 'oci', 'dbName' => '', 'host' => '127.0.0.1', 'user' => 'username', 'password' => 'userpassword', 'sid' => '', 'serviceName' => '' ]]);
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_array_if_parameters_valid_default(): array
    {
		$reflection = new \ReflectionClass(Pdo::class);
		$method = $reflection->getMethod('validateConnectionParams');
		$method->setAccessible(true);
		$result = $method->invokeArgs($reflection, [[ 'driver' => 'mysql', 'host' => '127.0.0.1', 'user' => 'username', 'password' => 'userpassword', 'dbName' => 'name' ]]);
		$expected = [ 'driver' => 'mysql', 'host' => '127.0.0.1', 'user' => 'username', 'password' => 'userpassword', 'dbName' => 'name', 'port' => 3306, 'charset' => 'UTF8' ];
		$this->assertEquals($result, $expected);

		return $result;
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_array_if_parameters_valid_oci(): array
    {
		$reflection = new \ReflectionClass(Pdo::class);
		$method = $reflection->getMethod('validateConnectionParams');
		$method->setAccessible(true);
		$result = $method->invokeArgs($reflection, [[ 'driver' => 'oci', 'host' => '127.0.0.1', 'user' => 'username', 'password' => 'userpassword', 'sid' => 'sid', 'dbName' => 'name' ]]);
		$expected = [ 'driver' => 'oci', 'host' => '127.0.0.1', 'user' => 'username', 'password' => 'userpassword', 'sid' => 'sid', 'port' => 1521, 'charset' => 'UTF8', 'dbName' => 'name' ];
		$this->assertEquals($result, $expected);

		return $result;
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::getDefaultString
	 * @depends test_validateConnectionParams_should_return_array_if_parameters_valid_default
	 * @group unit
	 */
	public function test_getDefaultString_should_return_correctly_parsed_string($params): void
	{
		$reflection = new \ReflectionClass(Pdo::class);
		$method = $reflection->getMethod('getDefaultString');
		$method->setAccessible(true);
		$expected = "mysql:host=127.0.0.1;port=3306;dbname=name;charset=UTF8";
		$result = $method->invokeArgs($reflection, [$params]);
		$this->assertEquals($expected, $result);
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::getOciString
	 * @group unit
	 */
	public function test_getOciString_should_return_exception_if_both_sid_and_serviceName_not_valid(): void
	{
		$this->expectException(BadMethodCallException::class);
		$reflection = new \ReflectionClass(Pdo::class);
		$method = $reflection->getMethod('getOciString');
		$method->setAccessible(true);
		$method->invokeArgs($reflection, [['sid' => null, 'serviceName' => null]]);
	}
	
	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::getOciString
	 * @depends test_validateConnectionParams_should_return_array_if_parameters_valid_oci
	 * @group unit
	 */
	public function test_getOciString_should_return_correctly_parsed_string($params): void
	{
		$expected = "oci:dbname=(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=127.0.0.1)(PORT=1521)))(CONNECT_DATA=(SID=sid)));charset=UTF8";
		$reflection = new \ReflectionClass(Pdo::class);
		$method = $reflection->getMethod('getOciString');
		$method->setAccessible(true);
		$result = $method->invokeArgs($reflection, [$params]);
		$this->assertEquals($expected, $result);
	}
	
	/**
	 * @covers Swolley\YardBird\Drivers\Pdo::__construct
	 * @depends test_validateConnectionParams_should_return_array_if_parameters_valid_default
	 * @group unit
	 */
	public function test_constructor_should_throw_exception_if_cant_establish_connection($params): void
	{
		$this->expectException(ConnectionException::class);
		new Pdo($params);
	}

	///////////////////////////////// INTEGRATION ////////////////////////////////////////////////
	/*public function test_sql_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('sql')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->sql('SELECT * FROM table WHERE id=:id', [ 'unusedname' => 'value' ]);
	}

	public function test_sql_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('sql')
			->will($this->throwException(new QueryException));

		$dbMock->sql('SELECT * FROM table WHERE id=:id', [ 'id' => 'value' ]);
	}

	public function test_select_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('select')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->select('table', ['field1', 'field2'], ['unusedname' => function(){} ]);
	}

	public function test_select_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('select')
			->will($this->throwException(new QueryException));

		$dbMock->select('table', ['field1'], ['field1' => 'value' ]);
	}

	public function test_insert_should_throw_exception_if_driver_not_supported(): void
	{
		$this->expectException(\Exception::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('insert')
			->will($this->throwException(new \Exception));

		$dbMock->insert('table', ['field1' => 'field2']);
	}

	public function test_insert_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('insert')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->insert('table', ['name' => function() {} ]);
	}

	public function test_insert_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('insert')
			->will($this->throwException(new QueryException));

		$dbMock->insert('table', ['name' => 'value' ]);
	}

	public function test_update_should_throw_exception_if_where_param_not_valid(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('update')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->update('table', ['name' => 'table' ], [ 'invalidarray' ]);
	}

	public function test_update_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('update')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->update('table', ['name' => function() {} ]);
	}

	public function test_update_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('update')
			->will($this->throwException(new QueryException));

		$dbMock->update('table', ['name' => 'value' ]);
	}

	public function test_delete_should_throw_exception_if_where_param_not_valid(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('delete')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->delete('table', ['name' => 'table' ], [ 'invalidarray' ]);
	}

	public function test_delete_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('delete')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->delete('table', ['name' => function() {} ]);
	}

	public function test_delete_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('delete')
			->will($this->throwException(new QueryException));

		$dbMock->delete('table', ['name' => 'value' ]);
	}

	public function test_procedure_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('procedure')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->procedure('table', ['name' => function() {} ]);
	}

	public function test_procedure_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Pdo::class);
		$dbMock->method('procedure')
			->will($this->throwException(new QueryException));

		$dbMock->procedure('table', ['name' => 'value' ]);
	}*/
	
}