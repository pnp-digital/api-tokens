<?php

/**
 * The format specifier to use for beautified dates
 */
const UX_DATE_FORMAT = 'jS M Y, H:ia';

if (!function_exists('pretty_date')) {

    /**
     * Make a date follow the correct format for UX
     * @param string|DateTimeInterface|null $date Date to format
     * If null, an invalid date, or empty string is passed in, a dash ('-') is returned.
     * @return string Beautified date
     */
    function pretty_date(string|DateTimeInterface|null $date): string
    {
        if (is_subclass_of($date, DateTimeInterface::class)) {
            return $date->format(UX_DATE_FORMAT);
        }
        $timestamp = strtotime($date);
        return $timestamp === false ? '-' : str_replace(', 00:00am', '', date(UX_DATE_FORMAT, $timestamp));
    }
}
