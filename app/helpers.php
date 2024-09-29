<?php
if (! function_exists('number')) {
    function number($number) {
        return (fmod($number, 1) !== 0.00) ? $number : (int)$number;
    }
}