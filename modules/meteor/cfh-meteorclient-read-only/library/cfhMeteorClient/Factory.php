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
 * @version    SVN: $Id: Factory.php 10 2008-02-21 16:01:37Z william.bailey@cowboysfromhell.co.uk $
 * @encoding   UTF-8
 */

/**
 * MeteorClient abstract factory
 *
 * @category   library
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008 William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
abstract class cfhMeteorClient_Factory
{
    
    protected static $factory    = array();
    protected static $factoryMap = array(
                                   'default' => 'cfhMeteorClient_Factory_Default',
                                   );
    
    /**
     * Adds a factory to the factory lookup map
     *
     * If the name already exists then any existing factory assigned to that
     * name will be replaced.
     * 
     * @param string $name
     * @param string $className
     */
    public static function addFactoryLookup($name, $className)
    {
        $name = strtolower($name);
        if(isset(self::$factoryMap[$name]) && self::$factoryMap[$name] != $className)
        {
            unset(self::$factory[$name]);
        }
        self::$factoryMap[$name] = $className;
    }
                                   
    /**
     * Gets a sub-factory.
     *
     * @param String $name
     * @return cfhMeteorClient_Factory
     * @throws cfhMeteorClient_FactoryException
     */
    public static function getFactory($name = 'default') {
        $name = strtolower($name);
        if(isset(self::$factory[$name]))
        {
            return self::$factory[$name];
        }
        if(!isset(self::$factoryMap[$name]))
        {
            throw new cfhMeteorClient_FactoryException('Unknown factory specified.');
        }
        $factoryClass = self::$factoryMap[$name];
        if(!class_exists($factoryClass))
        {
            throw new cfhMeteorClient_FactoryException('Unknown factory specified.');
        }
        $refClass = new ReflectionClass($factoryClass);
        if(!$refClass->isSubclassOf(__CLASS__) || !$refClass->isInstantiable())
        {
            throw new cfhMeteorClient_FactoryException('Invalid factory specified.');
        }
        $constructor = $refClass->getConstructor();
        if($constructor && $constructor->getNumberOfRequiredParameters() != 0)
        {
            throw new cfhMeteorClient_FactoryException('Invalid factory specified.');
        }
        $factory = $refClass->newInstance();
        self::$factory[$name] = $factory;
        return $factory;
    }
    
    /**
     * Create a meteor client for a server.
     * 
     * @param String $serverAddress
     * @param Integer $serverPort
     * @return cfhMeteorClient_Client_Interface
     */
    abstract public function createClient($serverAddress, $serverPort);
    
}
?>