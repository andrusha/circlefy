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
 * @version    SVN: $Id: Interface.php 13 2008-02-28 13:47:56Z william.bailey@cowboysfromhell.co.uk $
 * @encoding   UTF-8
 */

/**
 * Meteor Client api strategy interface
 *
 * @category   library
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008 William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
interface cfhMeteorClient_ApiStrategy_Interface
{
    
    /**
     * Set the message transformation to use when adding messages to the server
     *
     * @param cfhMeteorClient_MessageTransformation_Interface $t
     * @return cfhMeteorClient_MessageTransformation_Interface
     */
    public function setMessageTransformation(cfhMeteorClient_MessageTransformation_Interface $t);
    
    /**
     * Get the message transformation used when adding messages to the server
     *
     * @return cfhMeteorClient_MessageTransformation_Interface
     */
    public function getMessageTransformation();
    
    /**
     * Set the channel name transformation to use when adding messages to the server
     *
     * @param cfhMeteorClient_ChannelNameTransformation_Interface $t
     * @return cfhMeteorClient_ChannelNameTransformation_Interface
     */
    public function setChannelNameTransformation(cfhMeteorClient_ChannelNameTransformation_Interface $t);
    
    /**
     * Get the channel name transformation used when adding messages to the server
     *
     * @return cfhMeteorClient_ChannelNameTransformation_Interface
     */
    public function getChannelNameTransformation();
    
    /**
     * Perform any processing to connect using the specified transport.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     */
    public function connect(cfhMeteorClient_Transport_Interface $transport);
    
    /**
     * Perform any processing to disconnect using the specified transport.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     */
    public function disconnect(cfhMeteorClient_Transport_Interface $transport);
    
    /**
     * Adds a message to the specified channel.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     * @param String $channelName
     * @param String $message
     * @throws cfhMeteorClient_ApiStrategy_Exception
     */
    public function addMessage(cfhMeteorClient_Transport_Interface $transport, $channelName, $message);
    
    /**
     * Gets the subscriber count for a channel.
     *
     * @param cfhMeteorClient_Transport_Interface $transport
     * @param String $channelName
     * @return Integer
     * @throws cfhMeteorClient_ApiStrategy_Exception
     */
    public function getSubscriberCount(cfhMeteorClient_Transport_Interface $transport, $channelName);
    
    
}
?>