<?php
declare(strict_types=1);

namespace Swolley\YardBird;

use Swolley\YardBird\Interfaces\IConnectable;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;

final class Connection
{
	const FETCH_ASSOC = 2;
	const FETCH_OBJ = 5;
	const FETCH_COLUMN = 7;
	const FETCH_CLASS = 8;
	const FETCH_PROPS_LATE = 1048576;
	
	private $_driverConnection;

	/**
	 * @param	array	$connectionParameters	connection parameters
	 * @param	boolean	$debugMode				debug mode
	 */
	public function __construct(array $connectionParameters, bool $debugMode = false)
	{
		if (!isset($connectionParameters, $connectionParameters['driver']) || empty($connectionParameters)) throw new BadMethodCallException("Connection parameters are required");
		try {
			$found = self::checkExtension($connectionParameters['driver']);
			if($found === null) throw new \Exception();
			$className = 'Swolley\YardBird\Connections\\' . ucfirst($found) . 'Connection';
			$this->_driverConnection = new $className($connectionParameters, $debugMode);
		} catch (ConnectionException $e) {
			throw $e;
		} catch (\Exception $e) {
			throw new \Exception('No driver found or extension not supported with current php configuration');
		}
	}

	/**
	 * calls wrapped connection class's methods
	 */
	public function __call($method, $params)
	{
		if(method_exists($this->_driverConnection, $method)) {
			return $this->_driverConnection->$method(...$params);
		} else {
			throw new BadMethodCallException('Method $method not found');
		}
	}

	/**
	 * @param	string		$driver	requested type of connection
	 * @return	string|null	driver or null if no driver compatible
	 */
	private static function checkExtension(string $driver): ?string
	{
		$pdo_drivers = extension_loaded('pdo') ? \PDO::getAvailableDrivers() : [];
		if(strpos($driver, 'mongo') === 0 && extension_loaded('mongodb') && class_exists('MongoDB\Client')) {
			return 'mongo';
		} elseif(in_array($driver, $pdo_drivers)) {
			return 'pdo';
		} elseif(($driver === 'oci' || $driver === 'oci8') && extension_loaded('oci8')) {
			return 'oci8';
		} elseif(extension_loaded('mysqli') && ($driver === 'mysql' || $driver === 'mysqli')) {
			return 'mysqli';
		}

		return null;
	}
}
