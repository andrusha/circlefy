<?php
/**
 * Copyright (c) 2008, William Bailey <william.bailey@cowboysfromhell.co.uk>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of William Bailey nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   library
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008, William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id: Loader.php 4 2008-02-21 14:15:09Z william.bailey@cowboysfromhell.co.uk $
 * @encoding   UTF-8
 */

/**
 * MeteorClient class loader
 *
 * @category   library
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008 William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
class cfhMeteorClient_Loader
{

    static protected $basePath;

    /**
     * Loads a class from a PHP file.  The filename must be formatted
     * as "$class.php".
     *
     * @param string $class
     * @return void
     * @throws RuntimeException
     */
    public static function loadClass($class)
    {
        if(class_exists($class, false) || interface_exists($class, false))
        {
            return;
        }
        if(!self::$basePath)
        {
            self::$basePath = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.'..').DIRECTORY_SEPARATOR;
        }
        $file     = str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
        $pathName = self::$basePath.$file;
        if(!is_file($pathName))
        {
            throw new RuntimeException('Unable to load class "'.$class.'" as file "'.$file.'" does not exist.');
        }
        if(!@is_readable($pathName))
        {
            throw new RuntimeException('Unable to load class "'.$class.'" as file "'.$file.'" is not readable.');
        }
        require $pathName;
        if(!class_exists($class, false) && !interface_exists($class, false))
        {
            throw new RuntimeException('File "'.$file.'" was loaded but class "'.$class.'" was not found in the file.');
        }
    }

    /**
     * spl_autoload() suitable implementation for supporting class autoloading.
     *
     * Attach to spl_autoload() using the following:
     * <code>
     * spl_autoload_register(array('cfhMeteorClient_Loader', 'autoload'));
     * </code>
     *
     * @param string $class
     * @return string|false Class name on success; false on failure
     */
    public static function autoload($class)
    {
        try {
            self::loadClass($class);
            return $class;
        }
        catch(Exception $e)
        {
            return false;
        }
    }

    /**
     * Register {@link autoload()} with spl_autoload()
     *
     * @param boolean OPTIONAL $enabled
     * @return void
     * @throws RuntimeException if spl_autoload() is not found
     */
    public static function registerAutoload($enabled = TRUE)
    {
        if (!function_exists('spl_autoload_register'))
        {
            throw new RuntimeException('spl_autoload does not exist in this PHP installation.');
        }
        if($enabled === TRUE)
        {
            spl_autoload_register(array(__CLASS__, 'autoload'));
        }
        else
        {
            spl_autoload_unregister(array(__CLASS__, 'autoload'));
        }
    }

}
?>