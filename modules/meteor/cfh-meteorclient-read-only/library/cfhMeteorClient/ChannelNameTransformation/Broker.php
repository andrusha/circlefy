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
 * @version    SVN: $Id: Broker.php 4 2008-02-21 14:15:09Z william.bailey@cowboysfromhell.co.uk $
 * @encoding   UTF-8
 */

/**
 * Meteor Client channel name transformation broker
 *
 * @category   library
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008 William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
class cfhMeteorClient_ChannelNameTransformation_Broker
implements cfhMeteorClient_ChannelNameTransformation_Interface,
           IteratorAggregate
{
    
    /**
     * @var splObjectStorage
     */
    protected $transformations;
    
    public function __construct()
    {
        $this->clear();
    }
    
    /**
     * Clears all attached transformations
     */
    public function clear()
    {
        $this->transformations = new splObjectStorage();
    }
    
    /**
     * Attach a transformation.
     *
     * @param cfhMeteorClient_ChannelNameTransformation_Interface $t
     * @return cfhMeteorClient_ChannelNameTransformation_Interface
     */
    public function attach(cfhMeteorClient_ChannelNameTransformation_Interface $t)
    {
        $this->transformations->attach($t);
        return $t;
    }
    
    /**
     * Detach a transformation.
     *
     * @param cfhMeteorClient_ChannelNameTransformation_Interface $t
     * @return cfhMeteorClient_ChannelNameTransformation_Interface
     */
    public function detach(cfhMeteorClient_ChannelNameTransformation_Interface $t)
    {
        $this->transformations->detach($t);
        return $t;
    }
    
    /**
     * Gets an iterator for the transformations
     *
     * @return Iterator
     */
    public function getIterator()
    {
        return $this->transformations;
    }
    
    /**
     * Perform channel name transformation
     *
     * @param String $channelName
     * @return String
     */
    public function transformChannelName($channelName)
    {
        foreach($this->transformations as $t)
        {
            /* @var $t cfhMeteorClient_ChannelNameTransformation_Interface */
            $ChannelName = $t->transformChannelName($channelName);
        }
        return $channelName;
    }
    
}
?>