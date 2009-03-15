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
 * @category   UnitTests
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008, William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id: AllTests.php 3 2008-02-20 21:19:29Z william.bailey@cowboysfromhell.co.uk $
 * @encoding   UTF-8
 */

/**
 * Main UnitTest suite
 *
 * @category   UnitTests
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008 William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
class AllTests
{
    
    protected static $base;
    
    /**
     * Builds the test suite
     *
     * @return PHPUnit_Framework_TestSuite
     */
    public static function suite()
    {
        self::$base = realpath(dirname(__FILE__));
        $suite = new PHPUnit_Framework_TestSuite('cfhMeteorClient Test Suite');
        self::populateSuite($suite, self::$base);
        return $suite;
    }
    
    /**
     * Populate a test suite with files found in the directory.
     *
     * @param PHPUnit_Framework_TestSuite $suite
     * @param String $directory
     */
    protected static function populateSuite(PHPUnit_Framework_TestSuite $suite, $directory)
    {
        foreach(new DirectoryIterator($directory) as $item)
        {
            $firstChr = substr($item->getBaseName(), 0, 1);
            if($item->isDot() || $firstChr == '.' || $firstChr == '_')
            {
                continue;
            }
            if($item->isFile())
            {
                if(substr($item->getBaseName('.php'), -4) == 'Test')
                {
                    $prefix = str_replace(
                               DIRECTORY_SEPARATOR,
                               '_',
                               substr($item->getPath(), strlen(self::$base) + 1)
                               );            
                    if($prefix)
                    {
                        $testClass = $prefix.'_'.$item->getBaseName('.php');
                    }
                    else
                    {
                        $testClass = $item->getBaseName('.php');
                    }
                    require_once $item->getPathName();
                    $suite->addTestSuite($testClass);
                }
            }
            elseif($item->isDir())
            {
                $prefix = str_replace(
                               DIRECTORY_SEPARATOR,
                               '_',
                               substr($item->getPathName(), strlen(self::$base) + 1)
                               );            
                $newSuite = new PHPUnit_Framework_TestSuite($prefix.'_AllTests');
                self::populateSuite($newSuite, $item->getPathName());
                $suite->addTest($newSuite);
            }
        }
    }
}
?>