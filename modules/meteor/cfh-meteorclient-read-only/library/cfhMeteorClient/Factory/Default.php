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
 * @version    SVN: $Id: Default.php 11 2008-02-27 15:03:14Z william.bailey@cowboysfromhell.co.uk $
 * @encoding   UTF-8
 */

/**
 * MeteorClient default factory
 *
 * A default factory for meteor 1.05 compatible servers. Clients connect using
 * php streams and messages are automatically escaped according to JavaScript
 * string syntax.
 * 
 * @category   library
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008 William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
class cfhMeteorClient_Factory_Default
extends cfhMeteorClient_Factory
{
    /**
     * Create a meteor client for a server.
     * 
     * @param String $serverAddress
     * @param Integer $serverPort
     * @return cfhMeteorClient_Client_Interface
     */
    public function createClient($serverAddress, $serverPort)
    {
        $jsTransformation = new cfhMeteorClient_GenericTransformation_JavaScriptSlashes();
        
        $messageBroker = new cfhMeteorClient_MessageTransformation_Broker();
        $messageBroker->attach($jsTransformation);
        
        $channelBroker = new cfhMeteorClient_ChannelNameTransformation_Broker();
        $channelBroker->attach($jsTransformation);
        
        $apiStrategy = new cfhMeteorClient_ApiStrategy_105();
        $apiStrategy->setMessageTransformation($messageBroker);
        $apiStrategy->setChannelNameTransformation($channelBroker);
        
        $transport = new cfhMeteorClient_Transport_Stream($serverAddress, $serverPort);
        $client    = new cfhMeteorClient_Client_Simple($transport, $apiStrategy);
        return $client;
    }
    
}
?>