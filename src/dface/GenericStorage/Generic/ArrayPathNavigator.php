<?php

namespace dface\GenericStorage\Generic;

class ArrayPathNavigator
{

	private static function withPath(
		array &$arr,
		array $path,
		bool $create_if_absent,
		callable $found_fn,
		callable $not_found_fn
	) {
		$last = \count($path) - 1;
		$x = &$arr;
		for ($i = 0; $i < $last; $i++) {
			$p = $path[$i];
			if (!isset($x[$p])) {
				if ($create_if_absent) {
					$x[$p] = [];
				} else {
					return $not_found_fn($x, $p);
				}
			}
			$x = &$x[$p];
		}
		$last_name = $path[$last];
		if (!\array_key_exists($last_name, $x)) {
			return $not_found_fn($x, $last_name);
		}
		return $found_fn($x, $last_name);
	}

	/**
	 * @param $arr
	 * @param array $path
	 * @param $default
	 * @return mixed
	 */
	public static function getPropertyValue(array $arr, array $path, $default = null)
	{
		return self::withPath($arr, $path, false,
			function ($x, $prop) {
				return $x[$prop];
			},
			function () use ($default) {
				return $default;
			}
		);
	}

	/**
	 * @param $arr
	 * @param array $path
	 * @param $value
	 */
	public static function fallbackPropertyValue(array &$arr, array $path, $value) : void
	{
		self::withPath($arr, $path, true,
			function () {
			},
			function (&$x, $prop) use ($value) {
				$x[$prop] = $value;
			}
		);
	}

	/**
	 * @param $arr
	 * @param array $path
	 * @param $value
	 */
	public static function setPropertyValue(array &$arr, array $path, $value) : void
	{
		$set = function (&$x, $prop) use ($value) {
			$x[$prop] = $value;
		};
		self::withPath($arr, $path, true, $set, $set);
	}

	/**
	 * @param array $arr
	 * @param array $path
	 * @param mixed|null $default
	 * @return mixed|null
	 */
	public static function extractProperty(array &$arr, array $path, $default = null)
	{
		return self::withPath($arr, $path, false,
			function (&$x, $prop){
				$result = $x[$prop];
				unset($x[$prop]);
				return $result;
			},
			function () use ($default) {
				return $default;
			}
		);
	}

}
