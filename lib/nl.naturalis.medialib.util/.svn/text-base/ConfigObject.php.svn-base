<?php

namespace nl\naturalis\medialib\util;

class ConfigObject
{

	public function __get ($property)
	{
		if (!isset($this->$property)) {
			throw new \Exception('No such property: ' . $property);
		}
		return $this->$property;
	}

}