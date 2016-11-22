<?php

namespace nl\naturalis\medialib\util;



/** 
 * @author ayco_holleman
 * 
 */
class DateTimeUtil
{

    public static function hoursMinutesSeconds ($seconds, $asString = false)
    {
        $hours = (int) floor($seconds / 3600);
        $seconds = $seconds - ($hours * 3600);
        $minutes = (int) floor($seconds / 60);
        $seconds = $seconds - ($minutes * 60);
        if ($asString) {
            $hours = self::_zeropad($hours);
            $minutes = self::_zeropad($minutes);
            $seconds = self::_zeropad($seconds);
            return "$hours:$minutes:$seconds";
        }
        return array($hours, $minutes, $seconds);
    }

    private static function _zeropad ($string)
    {
        return str_pad($string, 2, '0', STR_PAD_LEFT);
    }

}