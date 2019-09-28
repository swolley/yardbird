<?php
declare(strict_types=1);

namespace Swolley\YardBird\Utils;

use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;

class Utils
{
	/**
	 * @param 	string	$query query string
	 * @return	string	trimmed query
	 */
	public static function trimQueryString(string $query): string
	{
		return rtrim(mb_ereg_replace('/\s\s+/', ' ', $query), ';');
	}

	/**
	 * @param	mixed	$data	data to hash
	 * @return	string	hashed data
	 */
	public static function hash($data): string
	{
		return md5(serialize($data));
	}

	public static function toCamelCase(string $string): string
	{
		return mb_ereg_replace('/_/', '', ucwords(mb_ereg_replace("/-|_|\s/", '_', $string), '_'));
	}

	public static function toPascalCase(string $string): string 
	{
		return lcfirst(self::toCamelCase($string));
	}
}
