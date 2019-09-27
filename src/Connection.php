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

	/**
	 * @param	array	$connectionParameters	connection parameters
	 * @param	boolean	$debugMode				debug mode
	 * @return	IConnectable	driver superclass
	 */
	public function __invoke(array $connectionParameters, bool $debugMode = false): IConnectable
	{
		if (!isset($connectionParameters, $connectionParameters['driver']) || empty($connectionParameters)) throw new BadMethodCallException("Connection parameters are required");
		try {
			$found = self::checkExtension($connectionParameters['driver']);
			if(is_null($found)) throw new \Exception();
			$className = 'Swolley\YardBird\Drivers\\' . ucfirst($found);
			return new $className($connectionParameters, $debugMode);
		} catch (ConnectionException $e) {
			throw $e;
		} catch (\Exception $e) {
			throw new \Exception('No driver found or extension not supported with current php configuration');
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
			return 'oci';
		} elseif(extension_loaded('mysqli') && ($driver === 'mysql' || $driver === 'mysqli')) {
			return 'mysqli';
		}

		return null;
	}
}
