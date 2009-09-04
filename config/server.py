from eventlet import api
BUF_SIZE = 4096
participants = [ ]
try:
    import json
except:
    import simplejson as json

class ServerRoot(object):
    def __init__(self):
        self.user_server = UserServer(self)
        self.message_server = MessageServer(self)
    def start(self):
        api.spawn(self.user_server)
        api.spawn(self.message_server)

class MessageServer(object):
    def __init__(self, root):
        self.root = root

    def __call__(self):
        server = api.tcp_listener(('0.0.0.0', 3333))
        while True:
            c = MessageConnection(self, *server.accept())
            api.spawn(c)

class MessageConnection(object):
    
    def __init__(self, server, conn, addr):
        self.conn = conn
        self.server = server
        self.addr = addr
        self.buffer = ""

    def __call__(self):
        while True:
            data = self.conn.recv(BUF_SIZE)
            if not data: break
            self.buffer += data
            self.dispatchBuffer()
        # TODO: on disconnect
        # ...

    def dispatchBuffer(self):
        while '\n' in self.buffer:
            rawFrame, self.buffer = self.buffer.split('\r\n', 1)
            print 'json parse:', rawFrame	
            frame = json.loads(rawFrame)
            self.receivedFrame(frame)

    def receivedFrame(self, frame):
        msg_data = frame['msg']
        for uid in frame['recipient_ids']:
            if uid in self.server.root.user_server.users:
                self.server.root.user_server.users[uid].send_message(msg_data)

class UserServer(object):
    def __init__(self, root):
        self.users = {}
        self.root = root

    def __call__(self):
        server = api.tcp_listener(('0.0.0.0', 2222))
        while True:
            c = UserConnection(self, *server.accept())
            api.spawn(c)
            
class UserConnection(object):
    def __init__(self, server, conn, addr):
        self.conn = conn
        self.addr = addr
        self.server = server
        self.buffer = ""
        self.state = "init"
        self.uid = None

    def __call__(self):
        while True:
            data = self.conn.recv(BUF_SIZE)
            if not data: break
            self.buffer += data
            self.dispatchBuffer()
	    print self.state
	    print self.server.users
        if self.state == 'connected':
            del self.server.users[self.uid]

    def dispatchBuffer(self):
        while '\n' in self.buffer:
            rawFrame, self.buffer = self.buffer.split('\r\n', 1)
            frame = json.loads(rawFrame)
            self.receivedFrame(frame)

    def receivedFrame(self, frame):
        if self.state == "init":
            self.uid = frame['uid']
            self.state = 'connected'
            self.server.users[self.uid] = self

    def send_message(self, data):
        self.conn.send(json.dumps({'msgData': data }) + '\r\n')
        

if __name__ == "__main__":
    root = ServerRoot()
    root.start()
    from eventlet.green import time
    while True:
        time.sleep(1)
