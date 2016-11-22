<?php

namespace nl\naturalis\medialib\util\logging;

use Monolog\Logger;

class LogConfig
{
    private $_file;
    private $_level;
    private $_stdout;

    /**
     * @return the $_file
     */
    public function getFile ()
    {
        return $this->_file;
    }

    
    /**
     * @return the $_level
     */
    public function getLevel ()
    {
        return $this->_level;
    }

    
    /**
     * @return the $_stdout
     */
    public function getStdout ()
    {
        return $this->_stdout;
    }

    
    /**
     * @param field_type $_file
     */
    public function setFile ($_file)
    {
        $this->_file = $_file;
    }

    
    /**
     * @param field_type $_level
     */
    public function setLevel ($_level)
    {
        $this->_level = $_level;
    }

    
    /**
     * @param field_type $_stdout
     */
    public function setStdout ($_stdout)
    {
        $this->_stdout = $_stdout;
    }

}