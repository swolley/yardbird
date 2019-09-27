<?php
declare(strict_types=1);

namespace Swolley\YardBird;

use Swolley\YardBird\Interfaces\IConnectable;
/*use Swolley\YardBird\Interfaces\ICrudable;*/
use Swolley\YardBird\Utils\Utils;
use Swolley\YardBird\Exceptions\ConnectionException;

final class Pool /*extends ICrudable*//* implements \Countable*/
{
	/** 
	 * @var array $connections connections list
	 **/
	private $connections = [];

	/**
	 * adds connection in the pool stack
	 * @param	string	$name					connection identifier
	 * @param	array	$connectionParameters	db parameters
	 * @param	bool	$debugMode				(optional) debug mode. default is false
	 * @return	Pool	self instance (used to chain add multiple add methods)
	 */
	public function add(string $name, array $connectionParameters, bool $debugMode = false): Pool
	{
		foreach ($this->connections as $connection_name => $connection) {
			if ($connection_name === $name || $connection->getHash() === Utils::hash($connectionParameters)) throw new ConnectionException("The connection already exists");
		}

		$this->connections[$name] = (new Connection)($connectionParameters, $debugMode);
		return $this;
	}

	/** 
	 * removes connection from the stack
	 * @param	string	$name	connection identifier
	 * @return	bool	if connection correctly removed
	*/
	public function remove(string $name): bool
	{
		if (array_key_exists($name, $this->connections)) {
			unset($this->connections[$name]);
			return true;
		}

		return false;
	}

	/**
	 * clears all connections
	 */
	public function clear(): void
	{
		$this->connections = [];
	}

	/**
	 * lists all active connections
	 * @return	array	list with main details
	 */
	public function list(): array
	{
		return array_map(function($connection, $idx) {
			return $connection->getInfo();
		}, $this->connections, array_keys($this->connections));
	}

	/**
	 * gets active connection number
	 * @return int	number of active connections
	 */
	public function count(): int
	{
		return count($this->connections);
	}

	/**
	 * gets connection by name like a class property
	 */
	public function __get(string $connectionName): ?IConnectable
	{
		return array_key_exists($connectionName, $this->connections) ? $this->connections[$connectionName] : null;
	}
}
