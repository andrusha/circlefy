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
 * @version    SVN: $Id: Simple.php 13 2008-02-28 13:47:56Z william.bailey@cowboysfromhell.co.uk $
 * @encoding   UTF-8
 */

/**
 * Simple Meteor Client
 *
 * @category   library
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008 William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
class cfhMeteorClient_Client_Simple
implements cfhMeteorClient_Client_Interface,
           cfhMeteorClient_Client_SubscriberCountInterface
{
    
    /**
     * @var cfhMeteorClient_ApiStrategy_Interface
     */
    protected $apiStrategy;
    
    /**
     * @var cfhMeteorClient_Transport_Interface
     */
    protected $transport;
    
    /**
     * @param cfhMeteorClient_Transport_Interface $transport
     * @param cfhMeteorClient_ApiStrategy_Interface $apiStrategy
     */
    public function __construct(cfhMeteorClient_Transport_Interface $transport, cfhMeteorClient_ApiStrategy_Interface $apiStrategy)
    {
        $this->apiStrategy = $apiStrategy;
        $this->transport   = $transport;
    }
    
    /**
     * Is the client connected to a server
     * @return Boolean
     */
    public function isConnected()
    {
        return $this->transport->isConnected();
    }
    
    /**
     * Connect to the meteor server
     * @throws cfhMeteorClient_ConnectException 
     */
    public function connect()
    {
        if($this->isConnected())
        {
            return;
        }
        try
        {
            $this->transport->connect();
            $this->apiStrategy->connect($this->transport);
        }
        catch(Exception $e)
        {
            throw new cfhMeteorClient_ConnectException($e->getMessage());
        }
    }
    
    /**
     * Disconnect from the meteor server
     */
    public function disconnect()
    {
        if(!$this->isConnected())
        {
            return;
        }
        $this->apiStrategy->disconnect($this->transport);
        $this->transport->disconnect();
    }
    
    /**
     * Add a message to the specified channel.
     *
     * @param String $channelName
     * @param String $message
     * @throws cfhMeteorClient_AddMessageException
     */
    public function addMessage($channelName, $message)
    {
        if(!$this->isConnected())
        {
            throw new cfhMeteorClient_AddMessageException('MeteorClient is not connected to a Meteor server.');
        }
        try
        {
            $this->apiStrategy->addMessage($this->transport, $channelName, $message);
        }
        catch(Exception $e)
        {
            throw new cfhMeteorClient_AddMessageException($e->getMessage());
        }
    }
    
    /**
     * Gets the subscriber count for a channel.
     *
     * @param String $channelName
     * @return Integer
     * @throws cfhMeteorClient_Exception
     */
    public function getSubscriberCount($channelName)
    {
        if(!$this->isConnected())
        {
            throw new cfhMeteorClient_AddMessageException('MeteorClient is not connected to a Meteor server.');
        }
        try
        {
            return $this->apiStrategy->getSubscriberCount($this->transport, $channelName);
        }
        catch(Exception $e)
        {
            throw new cfhMeteorClient_Exception($e->getMessage());
        }
    }
    
}
?>