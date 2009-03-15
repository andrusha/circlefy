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
 * @version    SVN: $Id: Abstract.php 13 2008-02-28 13:47:56Z william.bailey@cowboysfromhell.co.uk $
 * @encoding   UTF-8
 */

/**
 * Abstract Meteor Client api strategy
 *
 * @category   library
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008 William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
abstract class cfhMeteorClient_ApiStrategy_Abstract
implements cfhMeteorClient_ApiStrategy_Interface
{
    
    /**
     * @var cfhMeteorClient_MessageTransformation_Interface
     */
    protected $messageTransformation;
    
    /**
     * @var cfhMeteorClient_ChannelNameTransformation_Interface
     */
    protected $channelNameTransformation;
    
    /**
     * Set the message transformation to use when adding messages to the server
     *
     * @param cfhMeteorClient_MessageTransformation_Interface $t
     * @return cfhMeteorClient_MessageTransformation_Interface
     */
    public function setMessageTransformation(cfhMeteorClient_MessageTransformation_Interface $t)
    {
        $this->messageTransformation = $t;
        return $t;
    }
    
    /**
     * Get the message transformation used when adding messages to the server
     *
     * @return cfhMeteorClient_MessageTransformation_Interface
     */
    public function getMessageTransformation()
    {
        return $this->messageTransformation;
    }
    
    /**
     * Set the channel name transformation to use when adding messages to the server
     *
     * @param cfhMeteorClient_ServerNameTransformation_Interface $t
     * @return cfhMeteorClient_ServerNameTransformation_Interface
     */
    public function setChannelNameTransformation(cfhMeteorClient_ChannelNameTransformation_Interface $t)
    {
        $this->channelNameTransformation = $t;
        return $t;
    }
    
    /**
     * Get the channel name transformation used when adding messages to the server
     *
     * @return cfhMeteorClient_ChannelNameTransformation_Interface
     */
    public function getChannelNameTransformation()
    {
        return $this->channelNameTransformation;
    }
        
    /**
     * Adds a message to the specified channel.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     * @param String $channelName
     * @param String $message
     * @throws cfhMeteorClient_ApiStrategy_Exception
     */
    public function addMessage(cfhMeteorClient_Transport_Interface $transport, $channelName, $message)
    {
        if($this->channelNameTransformation)
        {
            $channelName = $this->channelNameTransformation->transformChannelName($channelName);
        }
        if(!$this->isValidChannelName($channelName))
        {
            throw new cfhMeteorClient_ApiStrategy_Exception('Invalid channelName '.$channelName);
        }
        if($this->messageTransformation)
        {
            $message = $this->messageTransformation->transformMessage($message);
        }
        if(!$this->isValidMessage($message))
        {
            throw new cfhMeteorClient_ApiStrategy_Exception('Invalid message.');
        }
        $this->doAddMessage($transport, $channelName, $message);
    }
    
    /**
     * Gets the subscriber count for a channel.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     * @param String $channelName
     * @return Integer
     * @throws cfhMeteorClient_ApiStrategy_Exception
     */
    public function getSubscriberCount(cfhMeteorClient_Transport_Interface $transport, $channelName)
    {
        if(!$this->isValidChannelName($channelName))
        {
            throw new cfhMeteorClient_ApiStrategy_Exception('Invalid channelName '.$channelName);
        }
        return $this->doGetSubscriberCount($transport, $channelName);
    }
    
    /**
     * Check to see in the channel name is valid.
     *
     * @param String $channelName
     * @return Boolean
     */
    abstract protected function isValidChannelName($channelName);
    
    /**
     * Check to see in the message is valid. 
     *
     * @param String $message
     * @return Boolean
     */
    abstract protected function isValidMessage($message);
    
    /**
     * Adds a message to the specified channel.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     * @param String $channelName
     * @param String $message
     * @throws cfhMeteorClient_ApiStrategy_Exception
     */
    abstract protected function doAddMessage(cfhMeteorClient_Transport_Interface $transport, $channelName, $message);
    
    /**
     * Gets the subscriber count for a channel.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     * @param String $channelName
     * @return Integer
     * @throws cfhMeteorClient_ApiStrategy_Exception
     */
    abstract protected function doGetSubscriberCount(cfhMeteorClient_Transport_Interface $transport, $channelName);
    
}
?>