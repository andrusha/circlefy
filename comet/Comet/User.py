# User server handles all outer-space user connections
# recieving user state and send events to users

import logging
import json
import MySQLdb
from collections import defaultdict
from Abstract import AbstractServer, AbstractConnection

class UserServer(AbstractServer):
    def __init__(self, mysql, cassandra):
        logging.info('Starting user server')
        super(UserServer, self).__init__(2223, UserConnection)
        
        self.users     = defaultdict(list) #{user_id: [UserConnection, ...]
        self.usernames = defaultdict(str)  #{user_id: username, ...
        self.groups    = defaultdict(set)  #{group_id: [user_id, ...]
        self.convos    = defaultdict(set)  #{message_id: [user_id, ...]

        self.mysql = mysql
        self.cassandra = cassandra
 
    def send_to(self, type, message, users = None, group = None, convo = None):
        "Send message to users in userlist or ones who see group/convo"
        "returns a set of users, wich wasn't accessible"

        users = users or set()
        send_uids = set()
        if group is not None:
            send_uids |= self.groups[group]
        if convo is not None:
            send_uids |= self.convos[convo]
        
        sended = set()
        for uid in users | send_uids:
            if uid in self.users:
                for conn in self.users[uid]:
                    if ((group is not None and group not in conn.groups) or \
                       (convo is not None and convo not in conn.convos)) and \
                       conn.uid not in users:
                       continue

                    conn.send_message(type, message)
                    sended.add(conn.uid)

        return users - sended

           
class UserConnection(AbstractConnection):
    def __init__(self, server, conn, addr):
        super(UserConnection, self).__init__(server, conn)

        self.state  = "init"
        self.uid    = None
        self.groups = set()
        self.convos = set()

    def __call__(self):
        super(UserConnection, self).__call__()
        
        # it actually means 'disconnected'
        if self.state == 'connected' or True:
            logging.info("User %i (%s), %s disconnected" % (self.uid, self.server.usernames[self.uid], self.state))
            self.del_groups()
            self.del_convos()
            self.server.users[self.uid].remove(self)

            if not self.server.users[self.uid]:
                self.userOnline(0)

                logging.info("User %i (%s) gone offline" % (self.uid, self.server.usernames[self.uid]))
                del self.server.usernames[self.uid]
                del self.server.users[self.uid]

    def receivedFrame(self, frame):
        if self.state == "init" and \
           ('uid' in frame and 'uname' in frame) and 'action' not in frame:
            self.state = 'connected'
            self.uid = int(frame['uid'])
            self.server.usernames[self.uid] = frame['uname']
            self.server.users[self.uid].append(self)

            if len(self.server.users[self.uid]) == 1:
                self.userOnline(1)

            logging.info("User %s (%i) online" % (self.server.usernames[self.uid], self.uid))
        elif self.state == "connected" and \
            ('cids' in frame or 'gids' in frame) and 'action' not in frame:

            typecast = lambda string: set(map(int, string.split(',')))
            if 'cids' in frame and frame['cids']: 
                self.del_convos()
                self.add_convos(typecast(frame['cids']))

            if 'gids' in frame and frame['gids']:
                self.del_groups()
                self.add_groups(typecast(frame['gids']))
        
            logging.info('Connection state for %s (%i) group: %s, convos: %s' % \
                (self.server.usernames[self.uid], self.uid, self.groups, self.convos))
        elif 'action' in frame and 'data' in frame and frame['action'] == 'response.typing':
            self.server.send_to(frame['action'], frame['data'], convo=int(frame['data']['cid']))
        else:
            logging.warning("Bad packet! In UserConnection (%s): %s" % (self.state, frame))

    def add_groups(self, groups):
        online = []
        self.groups = groups
        for gid in groups:
            if self.uid not in self.server.groups[gid]:
                self.server.groups[gid].add(self.uid)
                online += [gid]
        #self.groupOnline(online, 1)

    def del_groups(self):
        other = set()
        for con in self.server.users[self.uid]:
            if con is not self:
                other |= con.groups

        update = self.groups - other
        for gid in update:
            self.server.groups[gid].remove(self.uid)
            if not self.server.groups[gid]:
                del self.server.groups[gid]
        #self.groupOnline(update, 0)

    def add_convos(self, convos):
        self.convos = convos
        for cid in convos:
            self.server.convos[cid].add(self.uid)

    def del_convos(self):
        other = set()
        for con in self.server.users[self.uid]:
            if con is not self:
                other |= con.convos

        for cid in self.convos - other:
            self.server.convos[cid].remove(self.uid)
            if not self.server.convos[cid]:
                del self.server.convos[cid]

    def userOnline(self, status = 1):
        query = "UPDATE user SET online = %d WHERE id = %d" % (status, self.uid)
        self.server.mysql.cursor().execute(query)
        self.server.mysql.commit()
    
        users = self.server.cassandra.get('inverted_members', self.uid)
        self.groupOnline(set(users) if users else set(), status)

    def groupOnline(self, gid_list, status = 1):
        if not gid_list:
            return
        sql = '+ 1' if status else '- 1'
        gid_list = ', '.join(map(str, gid_list))
        query = "UPDATE `group` SET online_count = CASE WHEN online_count %s < 0 THEN 0 ELSE online_count %s END WHERE id IN (%s)" % (sql, sql, gid_list)
        self.server.mysql.cursor().execute(query)
        self.server.mysql.commit()

    def send_message(self, type, data):
        try:
            message = json.dumps({'type': type, 'data': data})
            logging.debug('Try to send %s to %i' % (message, self.uid))
            res = self.conn.send(message + '\r\n')
            logging.debug('Something %s' % res)
        except:
            logging.error('Message sending error')
