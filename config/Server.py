import sys
import logging
import eventlet
import MySQLdb
import simplejson as json
from eventlet.green import time
from collections import defaultdict

import eventlet
eventlet.monkey_patch(thread=True)

# ________________________
#/ Written by             \
#|        Taso Du Val     |
#| Modified by            | 
#|        Andrew Korzhuev |
#\      2009-2010 Tap     /
# ------------------------
#        \   ^__^
#         \  (oo)\_______
#            (__)\       )\/\
#                ||----w |
#                ||     ||

DEBUG = True 
thread_count = defaultdict(int) 

class BasicConnection(object):
    def __init__(self, server, conn = None, addr = None):
        self.conn = conn
        self.server = server
        self.buffer = ""

    def __call__(self):
        while True:
            data = self.conn.recv(4096)
            if not data:
                cls = self.__class__.__name__
                thread_count[cls] -= 1
                logging.info('Quiting %s thread %r' % (cls, dict(thread_count)))
                break
            self.buffer += data
            self.dispatchBuffer()

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

class BasicServer(object):
    def __init__(self, root, port, cls):
        self.root = root
        self.port = port
        self.cls  = cls

    def __call__(self):
        server = eventlet.listen(('0.0.0.0', self.port))
        while True:
            c = self.cls(self, *server.accept())
            cls = self.cls.__name__
            thread_count[cls] += 1
            logging.info("Spawn %s thread %r" % (cls, dict(thread_count)))
            eventlet.spawn(c)

class ServerRoot(object):
    def __init__(self):
        self.user_server = UserServer(self)
        self.message_server = MessageServer(self)
        self.pinger_obj = Pinger(self)

    def start(self):
        eventlet.spawn(self.user_server)
        eventlet.spawn(self.message_server)
        eventlet.spawn(self.pinger_obj)

class Pinger(object):
    def __init__(self, root):
        self.root = root
    
    def __call__(self):
        while True:
            self.ping()
            time.sleep(60)
    
    def ping(self):
        logging.info("Client ping")
        for uid in self.root.user_server.users:
            logging.info("Ping %s (%i)" % (self.root.user_server.usernames[uid], uid))
            for uniq_conn in self.root.user_server.users[uid]:
                uniq_conn.send_message('ping', {})

        logging.info("MySQL Ping")
        self.root.user_server.mysql_conn.ping()
        
class MessageServer(BasicServer):
    def __init__(self, root):
        super(MessageServer, self).__init__(root, 3334, MessageConnection)

# Dispatch PHP messages to online users
class MessageConnection(BasicConnection):
    def makeList(self, users, exclude, cid = None, gid = None):
        server = self.server.root.user_server

        cid = int(cid) if cid is not None else None
        if cid in server.byId['convo']:
            users.update(server.byId['convo'][cid])

        gid = int(gid) if gid is not None else None
        if gid in server.byId['group']:
            users.update(server.byId['group'][gid])

        return users ^ exclude

    def sendToUsers(self, users, event, message):
        try:
            server_users = self.server.root.user_server.users
            for uid in users:
                if uid in server_users:
                    for uniq_conn in server_users[uid]:
                        uniq_conn.send_message(event, message)
        except Exception as e:
            logging.error("Can't send message %s to %s because of %s" % (message, users, e))
            return False
        return True

    def receivedFrame(self, frame):
        logging.info("Recieved frame %r" % frame)
        
        if ('users' in frame or 'cid' in frame or 'gid' in frame) and \
           ('data' in frame) and \
           ('action' in frame):
            users = set(map(int, frame.get('users', [])))
            exclude = set(map(int, frame.get('exclude', [])))

            cid = frame.get('cid', None)
            gid = frame.get('gid', None)

            users_list = self.makeList(users, exclude, cid, gid)
            logging.debug(users_list)
            self.sendToUsers(users_list, frame['action'], frame['data'])

            return True
        
        logging.warning("Bad packet! %s" % frame)
        return False

class UserServer(BasicServer):
    def __init__(self, root):
        super(UserServer, self).__init__(root, 2223, UserConnection)
        
        #e.g. {user_id: [UserConnection, ...
        self.users = defaultdict(list)
        #e.g. {user_id: username, ...
        self.usernames = defaultdict(str) 

        #e.g. {group_id: [userConnection, ...
        self.byId   = {'group' : defaultdict(set) , 'convo': defaultdict(set)}
        #e.g. {user_id: [group_id, ...
        self.byUser = {'group' : defaultdict(set) , 'convo': defaultdict(set)}

        try:
            self.mysql_conn = MySQLdb.connect (host = "localhost",user = "root",passwd = "root",db = "circlefy")
        except MySQLdb.Error, e:
            logging.error("Mysql: %d: %s" % (e.args[0], e.args[1]))
            sys.exit(1)

        self.mysql_cursor = self.mysql_conn.cursor (MySQLdb.cursors.DictCursor)
           
class UserConnection(BasicConnection):
    def __init__(self, server, conn, addr):
        super(UserConnection, self).__init__(server, conn)

        self.state = "init"
        self.uid = None

    def __call__(self):
        super(UserConnection, self).__call__()
        
        if self.state == 'connected':
            if  len(self.server.users[self.uid]) is not 1:
                self.server.users[self.uid].remove(self)
            else:
                for type in ['group', 'convo']:
                    self.remove_ids(self.server.byUser[type][self.uid], type)
                    del self.server.byUser[type][self.uid]
                del self.server.users[self.uid]
                self.userOnline(0)

                logging.info("User %s offline" % self.server.usernames[self.uid])

    def receivedFrame(self, frame):
        if self.state == "init" and \
           ('uid' in frame and 'uname' in frame) and 'action' not in frame:
            self.state = 'connected'
            self.uid = int(frame['uid'])
            self.server.usernames[self.uid] = frame['uname']
            self.server.users[self.uid].append(self)
            self.userOnline(1)

            logging.info("User %s (%i) online" % (self.server.usernames[self.uid], self.uid))
        elif self.state == "connected" and \
            ('cids' in frame or 'gids' in frame) and 'action' not in frame:

            cids = set(map(int, frame['cids'].split(','))) if 'cids' in frame and frame['cids'] else set() 
            gids = set(map(int, frame['gids'].split(','))) if 'gids' in frame and frame['gids'] else set()

            for type, data in [('convo', cids), ('group', gids)]:
                old = self.server.byUser[type][self.uid]
                self.remove_ids(old - data, type)
                self.add_ids(data - old, type)
                self.server.byUser[type][self.uid] = data
        elif 'action' in frame and 'data' in frame and frame['action'] == 'response.typing':
            MessageConnection(self.server.root.message_server).receivedFrame(frame)
        else:
            logging.warning("Bad packet! In UserConnection (%s): %s" % (self.state, frame))

    def add_ids(self, items, type):
        online = set()
        for cid in items:
            if self.uid not in self.server.byId[type][cid]:
                self.server.byId[type][cid].add(self.uid)
                online.add(cid)

        if type == 'group' and online:
            self.groupOnline(online, 1)
        
    def remove_ids(self, items, type):
        offline = set()
        for cid in items:
            if self.uid in self.server.byId[type][cid]:
                self.server.byId[type][cid].remove(self.uid)
                offline.add(cid)
                if not self.server.byId[type][cid]:
                    del self.server.byId[type][cid]

        if type == 'group' and offline:
            self.groupOnline(offline, 0)

    def userOnline(self, status = 1):
        query = "UPDATE LOW_PRIORITY user SET online = %d WHERE id = %d" % (status, self.uid)
        self.server.mysql_cursor.execute(query)
        self.server.mysql_conn.commit()

    def groupOnline(self, gid_list, status = 1):
        sql = '+ 1' if status else '- 1'
        gid_list = ', '.join(map(str, gid_list))
        query = "UPDATE LOW_PRIORITY `group` SET online_count = CASE WHEN online_count %s < 0 THEN 0 ELSE online_count %s END WHERE id IN (%s)" % (sql, sql, gid_list)
        self.server.mysql_cursor.execute(query)
        self.server.mysql_conn.commit()

    def send_message(self, type, data):
        try:
            message = json.dumps({'type': type, 'data': data })
            logging.debug('Try to send %s to %i' % (message, self.uid))
            self.conn.send(message + '\r\n')
        except:
            logging.error('Message sending error')

def clearViewers(mysql_conn):
    mysql_cursor = mysql_conn.cursor (MySQLdb.cursors.DictCursor)
    for table, field in [('user', 'online'), ('group', 'online_count')]:
        clear_query = "UPDATE `%s` SET %s = 0" % (table, field)
        mysql_cursor.execute(clear_query)
        mysql_conn.commit()
    
if __name__ == "__main__":
    log_format = '%(asctime)s %(levelname)s: %(message)s'
    log_datefmt = '%H:%M:%S'
    if not DEBUG:
        logging.basicConfig(level = logging.DEBUG, filename = '/var/log/tap-server.log',
            format = log_format, datefmt = log_datefmt)
    else:
        logging.basicConfig(level = logging.DEBUG, format = log_format, datefmt = log_datefmt)
    sys.stderr = open('/var/log/tap-server.stderr.log', 'a') if not DEBUG else sys.stderr

    root = ServerRoot()
    root.start()
    logging.info("Starting Multi-User/Connection TCP server...")
    try:
        logging.info('Waiting for keyboard interrupt')
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        clearViewers(root.user_server.mysql_conn)
        logging.info("Exiting Multi-User/Connection TCP server...")
