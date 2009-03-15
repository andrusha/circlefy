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
 * @version    SVN: $Id: 105.php 13 2008-02-28 13:47:56Z william.bailey@cowboysfromhell.co.uk $
 * @encoding   UTF-8
 */

/**
 * Meteor Server <= 1.05 API
 *
 * @category   library
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008 William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
class cfhMeteorClient_ApiStrategy_105
extends cfhMeteorClient_ApiStrategy_Abstract
{
    
    /**
     * Perform any processing to connect using the specified transport.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     */
    public function connect(cfhMeteorClient_Transport_Interface $transport)
    {
    }
    
    /**
     * Perform any processing to disconnect using the specified transport.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     */
    public function disconnect(cfhMeteorClient_Transport_Interface $transport)
    {
        $transport->write('QUIT'.PHP_EOL);
        $result = $transport->read();
        if(!preg_match('/^OK$/', $result))
        {
            throw new cfhMeteorClient_ApiStrategy_Exception('Unexpected result, expecting OK got '.$result);
        }
    }
    
    /**
     * Check to see in the channel name is valid.
     *
     * @param String $channelName
     * @return Boolean
     */
    protected function isValidChannelName($channelName)
    {
        return (boolean) preg_match('/^\S+$/', $channelName);
    }
    
    /**
     * Check to see in the message is valid. 
     *
     * @param String $message
     * @return Boolean
     */
    protected function isValidMessage($message)
    {
        return strpos($message, "\n", 0) === FALSE;
    }
    
    /**
     * Adds a message to the specified channel.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     * @param String $channelName
     * @param String $message
     * @throws cfhMeteorClient_ApiStrategy_Exception
     */
    protected function doAddMessage(cfhMeteorClient_Transport_Interface $transport, $channelName, $message)
    {
        $cmd = sprintf('ADDMESSAGE %1$s %2$s'.PHP_EOL, $channelName, $message);
        $transport->write($cmd);
        $result = $transport->read();
        if(!preg_match('/^OK$/', $result))
        {
            throw new cfhMeteorClient_ApiStrategy_Exception('Unexpected result, expecting OK got '.$result);
        }
    }
    
    /**
     * Gets the subscriber count for a channel.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     * @param String $channelName
     * @return Integer
     * @throws cfhMeteorClient_ApiStrategy_Exception
     */
    protected function doGetSubscriberCount(cfhMeteorClient_Transport_Interface $transport, $channelName)
    {
        $cmd = sprintf('COUNTSUBSCRIBERS %1$s'.PHP_EOL, $channelName);
        $transport->write($cmd);
        $result  = $transport->read();
        $matches = array();
        if(!preg_match('/^OK\s([0-9]+)$/', $result, $matches))
        {
            throw new cfhMeteorClient_ApiStrategy_Exception('Unexpected result, expecting OK # got '.$result);
        }
        return (integer) $matches[1];
    }
    
}
?>