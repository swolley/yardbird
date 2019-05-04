<?php
namespace Swolley\Database;

trait TraitUtils
{
	protected static function castParamsToArray($params): array
	{
		$paramsType = gettype($params);
		if($paramsType !== 'array' && $paramsType !== 'object' ) {
			throw new \UnexpectedValueException('$params can be only array or object');
		}

		return (array) $params;
	}
}