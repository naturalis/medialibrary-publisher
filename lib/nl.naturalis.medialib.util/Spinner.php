<?php

namespace nl\naturalis\medialib\util;

/**
 * Small utility class that rotates through integers.
 * @author ayco_holleman
 *
 */
class Spinner {
	private $_curr;
	private $_base;


	public function __construct($base)
	{
		$this->_curr = 0;
		$this->_base = max(1, (int) $base);
	}


	public function total()
	{
		return $this->_curr;
	}


	public function current()
	{
		return ($this->_curr % $this->_base);
	}


	public function next()
	{
		return ($this->_curr++ % $this->_base);
	}

}