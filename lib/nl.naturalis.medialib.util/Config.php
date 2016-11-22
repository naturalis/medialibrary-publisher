<?php

namespace nl\naturalis\medialib\util;

use \Exception;

class Config extends ConfigObject
{
	private static $TRUE_VALUES = array('true', 'on', '1', 'yes');

	private $_raw;
	
	public function __construct ($iniFile)
	{
		$this->_raw = parse_ini_file($iniFile);
		if ($this->_raw === false) {
			throw new Exception('Error parsing ' . $iniFile);
		}
		foreach ($this->_raw as $key => $value) {
			$this->_addProperty($this, $key, $value);
		}
	}

	public function addIniFile ($iniFile)
	{
		$raw = parse_ini_file($iniFile);
		if ($raw === false) {
			throw new Exception('Error parsing ' . $iniFile);
		}
		$this->_raw = array_merge($this->_raw, $raw);
		foreach ($raw as $key => $value) {
			$this->_addProperty($this, $key, $value);
		}
	}

	public function get ($property)
	{
		if (!isset($this->_raw[$property])) {
			throw new \Exception('No such property: ' . $property);
		}
		return $this->_raw[$property];
	}

	public function getBoolean ($property)
	{
		if (!isset($this->_raw[$property])) {
			throw new \Exception('No such property: ' . $property);
		}
		$val = strtolower($this->_raw[$property]);
		return in_array($val, self::$TRUE_VALUES);
	}

	private function _addProperty (ConfigObject $obj, $property, $value)
	{
		$i = strpos($property, '.');
		if ($i === false) {
			$obj->$property = strlen($value) === 0 ? null : $value;
		}
		else {
			$parent = substr($property, 0, $i);
			$child = substr($property, $i + 1);
			if (!property_exists($obj, $parent)) {
				$obj->$parent = new ConfigObject();
			}
			$this->_addProperty($obj->$parent, $child, $value);
		}
	}

}

