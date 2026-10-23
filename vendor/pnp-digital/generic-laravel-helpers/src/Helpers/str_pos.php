<?php

if (!function_exists('str_pos')) {
    /**
     * Version of built-in strpos function, except failure to find results in PHP_INT_MAX, so we can grab the smallest length of multiple searches
     *
     * @param string $haystack
     * @param string $needle
     * @param int $offset
     * @return int
     */
    function str_pos(string $haystack, string $needle, int $offset = 0): int
    {
        if (($position = strpos($haystack, $needle, $offset)) === false) {
            return PHP_INT_MAX;
        }

        return $position;
    }
}
