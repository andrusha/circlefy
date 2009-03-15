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
 * @version    SVN: $Id: Stream.php 6 2008-02-21 14:22:34Z william.bailey@cowboysfromhell.co.uk $
 * @encoding   UTF-8
 */

/**
 * Meteor Client php stream transport
 *
 * @category   library
 * @package    cfhMeteorClient
 * @author     William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @copyright  Copyright (c) 2008 William Bailey <william.bailey@cowboysfromhell.co.uk>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
class cfhMeteorClient_Transport_Stream
implements cfhMeteorClient_Transport_Interface
{
    
    const CONNECT_PROTOCOL = 'tcp';
    const CONNECT_TIMEOUT  = 2;         // Seconds
    
    const READ_TIMEOUT     = 200000;    // Microseconds
    
    const WRITE_MAX_TRY    = 100;
    
    /**
     * @var Resource
     */
    protected $fp;
    /**
     * @var String
     */
    protected $serverAddress;
    /**
     * @var Integer
     */
    protected $serverPort;
    
    /**
     * @param String $serverName
     * @param Port $serverPort
     */
    public function __construct($serverAddress, $serverPort)
    {
        $this->serverAddress = (string)  $serverAddress;
        $this->serverPort = (integer) $serverPort;
    }
    
    public function __destruct()
    {
        $this->disconnect();
    }
    
    /**
     * Is the client connected to a server
     * @return Boolean
     */
    public function isConnected()
    {
        if(!is_resource($this->fp))
        {
            return FALSE;
        }
        $meta = stream_get_meta_data($this->fp);
        if($meta['eof'])
        {
            return FALSE;
        }
        return TRUE;
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
        $connectString = self::CONNECT_PROTOCOL.'://'.$this->serverAddress.':'.$this->serverPort;
        $fp = @stream_socket_client($connectString, $errCode, $errString, self::CONNECT_TIMEOUT);
        if(!is_resource($fp))
        {
            throw new cfhMeteorClient_ConnectException('Unable to connect to server '.$connectString.' '.$errString, $errCode);
        }
        $this->fp = $fp;
        stream_set_blocking($this->fp, TRUE);
        stream_set_timeout($this->fp, 0, self::READ_TIMEOUT);
        stream_set_write_buffer($this->fp, 0);
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
        fclose($this->fp);
        $this->fp = NULL;
    }

    /**
     * Reads a line from the transport
     * @return String
     * @throws cfhMeteorClient_Transport_ReadException
     */
    public function read()
    {
        if(!$this->isConnected())
        {
            throw new cfhMeteorClient_Transport_ReadException('Transport is not connected.');
        }
        $buffer = @stream_get_line($this->fp, 8192, cfhMeteorClient_Transport_Interface::LINE_ENDING);
        if(!is_string($buffer))
        {
            $buffer = '';
        }
        return $buffer;
    }
    
    /**
     * Write to the transport
     * @param String $data
     * @throws cfhMeteorClient_Transport_WriteException
     */
    public function write($data)
    {
        if(!$this->isConnected())
        {
            throw new cfhMeteorClient_Transport_WriteException('Transport is not connected.');
        }
        $buffer = $data;
        for($i = 0; $i < self::WRITE_MAX_TRY; $i++)
        {
            $sent   = @fwrite($this->fp, $buffer);
            $buffer = substr($buffer, $sent);
            if($buffer == '')
            {
                @fflush($this->fp);
                return;
            }
        }
        throw new cfhMeteorClient_Transport_WriteException('Did not finish sending data after '.self::WRITE_MAX_TRY.' attempts.');
    }
    
}
?>