<?php
namespace Swolley\YardBird;

use Swolley\YardBird\Interfaces\IConnectable;
use Swolley\YardBird\Exceptions\ConnectionException;

final class Pool/* implements \Countable*/
{
	/** @var array $connections */
	private $connections = [];

	public function add(string $name, array $connectionParameters, bool $debugMode = false): bool
	{
		if(array_key_exists($name, $this->connections)) throw new ConnectionException("$name connection already exists");
		
		if($this->connections[$name] = (new Connection)($connectionParameters)) {
			return true;
		}

		return false;
	}

	public function remove(string $name): bool
	{
		if(array_key_exists($name, $this->connections)) {
			unset($this->connections[$name]);
			return true;
		} 

		return false;
	}

	public function list(): array
	{
		$list = [];
		foreach($this->connections as $name => $connection) {
			$list[$name] = $connection->getType();
		}

		return $list;
	}

	public function __get(string $connectionName): ?IConnectable
	{
		return array_key_exists($connectionName, $this->connections) ? $this->connections[$connectionName] : null;
	}
}