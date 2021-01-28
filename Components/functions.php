<?php

if( !function_exists('dd') )
{
    /**
     * Simple debug function
     *
     * @param  mixed   $object
     * @param  boolean $exit
     */
    function dd($object, $exit = true)
    {
        echo '<pre>';
        print_r($object);
        echo '</pre>';
        // if( $exit ) exit;
    }
}
