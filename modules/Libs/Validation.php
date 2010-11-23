<?php

class Validation {
    
    public static function email($str) {
        return filter_var($str, FILTER_VALIDATE_EMAIL);
    }
    
    public static function url($str) {
        return filter_var($str, FILTER_VALIDATE_URL);
    }
    
    public static function integer($int, $min=false, $max=false) {
        if ($min || $max) {
            if ($min && $max) $options = array('min' => $min, 'max' => $max);
            elseif ($min && !$max) $options = array('min' => $min);
            elseif (!$min && $max) $options = array('max' => $max);
            
            return filter_var($int, FILTER_VALIDATE_INT, array('options' => $options));
        } else
            return filter_var($int, FILTER_VALIDATE_INT);
    }
    
    public static function float($var, $decimal=false) {
        if ($decimal)
            return filter_var($var, FILTER_VALIDATE_FLOAT, array('options' => array('decimal' => $decimal)));
        
        return filter_var($var, FILTER_VALIDATE_FLOAT);
    }
    
    public static function boolean($var) {
        return filter_var($var, FILTER_VALIDATE_BOOLEAN);
    }
    
    public static function ip($str) {
        return filter_var($str, FILTER_VALIDATE_IP);
    }
    
    public static function regexp($str, $regexp) {
        return filter_var($str, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $regexp)));
    }
}
