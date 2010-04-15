# Copyright (c) 2006-2007, Linden Research, Inc.
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
# 
# The above copyright notice and this permission notice shall be included in
# all copies or substantial portions of the Software.
# 
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
# THE SOFTWARE.

from unittest import TestCase, main
from eventlet import api, util, greenio
import os
import socket

# TODO try and reuse unit tests from within Python itself

class TestGreenIo(TestCase):
    def test_close_with_makefile(self):
        def accept_close_early(listener):
            # verify that the makefile and the socket are truly independent
            # by closing the socket prior to using the made file
            try:
                conn, addr = listener.accept()
                fd = conn.makeGreenFile()
                conn.close()
                fd.write('hello\n')
                fd.close()
                self.assertRaises(socket.error, fd.write, 'a')
                self.assertRaises(socket.error, conn.send, 'b')
            finally:
                listener.close()

        def accept_close_late(listener):
            # verify that the makefile and the socket are truly independent
            # by closing the made file and then sending a character
            try:
                conn, addr = listener.accept()
                fd = conn.makeGreenFile()
                fd.write('hello')
                fd.close()
                conn.send('\n')
                conn.close()
                self.assertRaises(socket.error, fd.write, 'a')
                self.assertRaises(socket.error, conn.send, 'b')
            finally:
                listener.close()
                
        def did_it_work(server):
            client = api.connect_tcp(('127.0.0.1', server.getsockname()[1]))
            fd = client.makeGreenFile()
            client.close()
            assert fd.readline() == 'hello\n'    
            assert fd.read() == ''
            fd.close()
            
        server = api.tcp_listener(('0.0.0.0', 0))
        killer = api.spawn(accept_close_early, server)
        did_it_work(server)
        api.kill(killer)
        
        server = api.tcp_listener(('0.0.0.0', 0))
        killer = api.spawn(accept_close_late, server)
        did_it_work(server)
        api.kill(killer)

        
    def test_del_closes_socket(self):
        timer = api.exc_after(0.5, api.TimeoutError)
        def accept_once(listener):
            # delete/overwrite the original conn
            # object, only keeping the file object around
            # closing the file object should close everything
            try:
                conn, addr = listener.accept()
                conn = conn.makeGreenFile()
                conn.write('hello\n')
                conn.close()
                self.assertRaises(socket.error, conn.write, 'a')
            finally:
                listener.close()
        server = api.tcp_listener(('0.0.0.0', 0))
        killer = api.spawn(accept_once, server)
        client = api.connect_tcp(('127.0.0.1', server.getsockname()[1]))
        fd = client.makeGreenFile()
        client.close()
        assert fd.read() == 'hello\n'    
        assert fd.read() == ''
        
        timer.cancel()

    def test_wrap_socket(self):
        try:
            import ssl
        except ImportError:
            pass  # pre-2.6
        else:
            sock = api.tcp_listener(('127.0.0.1', 0))
            ssl_sock = ssl.wrap_socket(sock)
 
 
def test_server(sock, func, *args):
    """ Convenience function for writing cheap test servers.
    
    It calls *func* on each incoming connection from *sock*, with the first argument
    being a file for the incoming connector.
    """
    def inner_server(connaddr, *args):
        conn, addr = connaddr
        fd = conn.makefile()
        func(fd, *args)
        fd.close()
        conn.close()
            
    if sock is None:
        sock = api.tcp_listener(('', 9909))
    api.spawn(api.tcp_server, sock, inner_server, *args)
    

class SSLTest(TestCase):
    def setUp(self):
        self.timer = api.exc_after(1, api.TimeoutError)
        self.certificate_file = os.path.join(os.path.dirname(__file__), 'test_server.crt')
        self.private_key_file = os.path.join(os.path.dirname(__file__), 'test_server.key')
        
    def tearDown(self):
        self.timer.cancel()
        
    def test_greensslobject(self):
        def serve(listener):
            sock, addr = listener.accept()
            sock.write('content')
            sock.shutdown()
            sock.close()
        listener = api.ssl_listener(('', 4201), 
                                    self.certificate_file, 
                                    self.private_key_file)
        killer = api.spawn(serve, listener)
        client = util.wrap_ssl(api.connect_tcp(('localhost', 4201)))
        client = greenio.GreenSSLObject(client)
        self.assertEquals(client.read(1024), 'content')
        self.assertEquals(client.read(1024), '')
        

    def dont_test_duplex_response(self):
        def serve(sock):
            line = True
            while line != '\r\n':
                line = sock.readline()
                print '<', line.strip()
            sock.write('response')
  
        certificate_file = os.path.join(os.path.dirname(__file__), 'test_server.crt')
        private_key_file = os.path.join(os.path.dirname(__file__), 'test_server.key')
        sock = api.ssl_listener(('', 4201), certificate_file, private_key_file)
        test_server(sock, serve)
        
        client = util.wrap_ssl(api.connect_tcp(('localhost', 4201)))
        f = client.makefile()
        
        f.write('line 1\r\nline 2\r\n\r\n')
        f.read(8192)
                
if __name__ == '__main__':
    main()
