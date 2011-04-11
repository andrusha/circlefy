import logging
import eventlet
import json 
from collections import defaultdict

thread_count = defaultdict(int) 

class AbstractServer(object):
    def __init__(self, port, cls):
        self.port = port
        self.cls  = cls

    def __call__(self):
        server = eventlet.listen(('localhost', self.port))
        while True:
            c = self.cls(self, *server.accept())
            cls = self.cls.__name__
            thread_count[cls] += 1
            logging.info("Spawn %s thread %r" % (cls, dict(thread_count)))
            eventlet.spawn(c)

class AbstractConnection(object):
    def __init__(self, server, conn = None, addr = None):
        self.conn = conn
        self.server = server
        self.buffer = ""

    def __call__(self):
        while True:
            data = self.conn.recv(4096)
            if not data:
                break
            self.buffer += data
            self.dispatchBuffer()

        cls = self.__class__.__name__
        thread_count[cls] -= 1
        logging.info('Quiting %s thread %r' % (cls, dict(thread_count)))

    def dispatchBuffer(self):
        while '\n' in self.buffer:
            rawFrame, self.buffer = self.buffer.split('\n', 1)
            rawFrame = rawFrame.strip(' \r\n\t')
            try:
                frame = json.loads(rawFrame)
            except(Exception):
                continue

            self.receivedFrame(frame)

    def receivedFrame(self, frame):
        raise NotImplementedError()
