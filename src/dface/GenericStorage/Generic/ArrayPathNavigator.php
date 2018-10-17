<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Generic;

class ArrayPathNavigator
{

	/**
	 * @param $arr
	 * @param array $path
	 * @param $default
	 * @return mixed
	 */
	public static function getPropertyValue(array $arr, array $path, $default = null)
	{
		$x = $arr;
		foreach ($path as $p) {
			if (!isset($x[$p])) {
				return $default;
			}
			$x = $x[$p];
		}
		return $x;
	}

	/**
	 * @param $arr
	 * @param array $path
	 * @param $value
	 */
	public static function setPropertyValue(array &$arr, array $path, $value) : void
	{
		$x = &$arr;
		foreach ($path as $p) {
			if (!isset($x[$p])) {
				$x[$p] = [];
			}
			$x = &$x[$p];
		}
		$x = $value;
	}

	/**
	 * @param array $arr
	 * @param array $path
	 */
	public static function unsetProperty(array &$arr, array $path) : void
	{
		if (!$path || !$arr) {
			return;
		}
		$end = array_pop($path);
		$x = &$arr;
		foreach ($path as $p) {
			if (!isset($x[$p])) {
				return;
			}
			$x = &$x[$p];
		}
		unset($x[$end]);
	}

}
