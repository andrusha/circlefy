import socket
import random
import time
import sys
import random
import fcntl
import memcache
import simplejson as json
import logging
import eventlet
import MySQLdb

# ________________________
#/ Written By Taso Du Val \
#\      2009-2010 Tap     /
# ------------------------
#        \   ^__^
#         \  (oo)\_______
#            (__)\       )\/\
#                ||----w |
#                ||     ||

DEBUG = True 
thread_count = {'message': 0, 'user': 0, 'admin': 0}
BUF_SIZE = 4096
participants = [ ]
try:
    import json
except:
    import simplejson as json

class ServerRoot(object):
    def __init__(self):
        self.admin_server = AdminServer(self)
        self.user_server = UserServer(self)
        self.message_server = MessageServer(self)
        self.pinger_obj = Pinger(self)
        self.mysql_pinger_obj = MySQL_Pinger(self)

    def start(self):
        eventlet.spawn(self.admin_server)
        eventlet.spawn(self.user_server)
        eventlet.spawn(self.message_server)
        eventlet.spawn(self.pinger_obj)
        eventlet.spawn(self.mysql_pinger_obj)

#START of Message Server
class MySQL_Pinger(object):
    def __init__(self, root):
            self.root = root
    
    def __call__(self):
        while True:
            self.ping()
            time.sleep(60)
    
    def ping(self):    
        logging.info("MySQL Ping")
        self.root.user_server.mysql_conn.ping()

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
            for uniq_conn in self.root.user_server.users[uid]:
                logging.info("Ping %s" % uniq_conn)
                uniq_conn.send_message('ping', {})
        
class MessageServer(object):
    def __init__(self, root):
        self.root = root

    def __call__(self):
        server = eventlet.listen(('0.0.0.0', 3333))
        while True:
            c = MessageConnection(self,*server.accept())
            thread_count['message'] += 1
            logging.info("Spawn message thread %r" % thread_count)
            eventlet.spawn(c)

class MessageConnection(object):
    
    def __init__(self,server, conn, addr):
        self.conn = conn
        self.server = server
        self.addr = addr
        self.buffer = ""
        self.uids = []

    def __call__(self):
        while True:
#            with eventlet.Timeout(60) as timeout:
                data = self.conn.recv(BUF_SIZE)
                if not data:
                    thread_count['message'] -= 1
                    logging.info('Quiting message thread %r' % thread_count)
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

    def tap_ACTION(self,msg_data):
        matches_list = self.check_new_bits(self.server.root.user_server.users,msg_data)
        print "%s" % matches_list
        print "%s" % self.server.root.user_server.users
        if matches_list != []:
            results = self.bit_generator(matches_list)
            uniq_uids = set(self.uids)
            print "%s" % uniq_uids
            for uid in uniq_uids:
                if uid in self.server.root.user_server.users:
                    for uniq_conn in self.server.root.user_server.users[uid]:
                        uniq_conn.send_message('tap.new', results)
            self.uids = []

    def response_ACTION(self,cid,data,uname,init_tapper,response):
        try:
            for uid in self.server.root.user_server.channels['channel'][cid]:
                for uniq_conn in self.server.root.user_server.users[uid]:
                        uniq_conn.send_message('response', response)
            #Send response to the initial tapper to activate his active convo
            for uniq_conn in self.server.root.user_server.users[int(init_tapper)]:
                        uniq_conn.send_message('convo', {'cid': cid})
        except Exception:
            logging.error("No user '%s' subscribed to channel id %s" % (uname, cid))
            return False

    def typing_ACTION(self,cid,data,uname,response):
        try:
            for uid in self.server.root.user_server.channels['channel'][cid]:
                for uniq_conn in self.server.root.user_server.users[uid]:
                    uniq_conn.send_message('typing', response)
        except Exception:
            logging.error("No user '%s' subscribed to channel id %s" % (uname, cid))
            return False

        return False

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
        if 'action' in frame:
            logging.info("Recieved frame %r" % frame)
            type = frame['action']
            
            if type in ['response', 'typing'] and 'response' in frame and 'uname' in frame and 'cid' in frame:
                #message response, init_message, typing events
                data = frame['response']
                uname = frame['uname']
                cid = frame['cid']
                pic = frame['pic_small'] if 'pic_small' in frame else ''

                response = self.response_generator(cid,data,uname,pic)

                if type == 'response' and 'init_tapper' in frame:
                    init_tapper = frame['init_tapper']
                    self.response_ACTION(cid,data,uname,init_tapper,response)

                if type == 'typing':
                    self.typing_ACTION(cid,data,uname,response)
            #everything else
            elif ('users' in frame) and ('data' in frame):
                self.sendToUsers(frame['users'], type, frame['data'])

            return True

        if 'msg' in frame:
            msg_data = frame['msg'].split('\n')
            print "%s" % msg_data
            self.tap_ACTION(msg_data)

            return True
        
        logging.error("Warning! Bad packet!")
        return False

    def response_generator(self,cid,data,uname,pic):
        response = {'cid': cid, 'data': data, 'uname': uname, 'pic': pic}
        return response

    def bit_generator(self,matches_list):
        bits = []
        for tuples in matches_list:
            for els in tuples:
                if type(els) == list:
                    for el in els:
                        display_row_type=el
                        real_row_type=tuples[2]
                        if real_row_type is 0:
                            tap_type = tuples[3]
                        else:
                            tap_type = None
                
                        uid=tuples[0]
                        cid=tuples[1]
                        bits.append((uid,cid,tap_type,display_row_type))
        return bits

    def check_new_bits(self,uid_list,msg_data):
        matches_list = []
        for row in msg_data:
            #FIGURE OUT WHICH LINES MATCH
            cols = row.split(' ')
            if cols[0]:
                cols = [ int(col) for col in cols ]
                uid = cols[0]
            else:
                continue

            if uid not in uid_list:
                continue
            else:
                self.uids.append(uid)

            '''
            Process all matches, get their association and run queries against them
            FORMAT FOR TABLES ARE AS FOLLOWS:
            uid     cid      gid    rid     fuid    type
            types: group = 0 , friends = 1, direct = 2, filters = 3, fuid = 4, type = 5
            '''

            groups, friends, rel = ('groups', 'friends', 'rel')
            group_hit = friend_hit = rel_hit = 1
            if len(cols) >= 6:
                (uid, cid, gid, rid, fuid, row_type) = cols[:5]
                tap_type = cols[6] if not row_type else None

                #Group Processing
                if row_type == 0 and group_hit:
                    groups = False
                    matches_list.insert(0,(uid,cid,row_type,tap_type,["groups","group_%s" % ( gid )]))
                #Friend Processing
                elif row_type == 1 and friend_hit:
                    friends = False
                    matches_list.insert(0,(uid,cid,row_type,tap_type,["friends","friend_%s" % ( fuid )]))
                #Direct Processing
                elif row_type == 2:
                    matches_list.insert(0,(uid,cid,row_type,tap_type,["direct"]))
                #Filter Processing
                elif row_type == 3 and rel_hit:
                    rel = False
                    matches_list.insert(0,(uid,cid,row_type,tap_type,["rel","tab_rel_%s" % ( rid )] ))
                #Direct Processing
                elif row_type == 4:
                    matches_list.insert(0,(uid,cid,row_type,tap_type,["building"]))

        return matches_list

#START of User Server
class UserServer(object):
    def __init__(self, root):
        self.users = {}
        self.usernames = {}
        self.instance_cids = {}
        self.channels = { 'user': {}, 'group' : {} , 'channel': {} }
        self.g_dicts = { 'user': {}, 'group' : {} , 'channel': {} }
        self.root = root

        self.mysql_conn = False
        self.mysql_cursor = False

        try:
            self.mysql_conn = MySQLdb.connect (host = "localhost",user = "root",passwd = "root",db = "rewrite2")
        except MySQLdb.Error, e:
            logging.error("Mysql: %d: %s" % (e.args[0], e.args[1]))
            sys.exit(1)
        self.mysql_cursor = self.mysql_conn.cursor (MySQLdb.cursors.DictCursor)


    def __call__(self):
        server = eventlet.listen(('0.0.0.0', 2222))
        while True:
            c = UserConnection(self, *server.accept())
            thread_count['user'] += 1
            logging.info('Spawn user thread %r' % thread_count)
            eventlet.spawn(c)
            
class UserConnection(object):
    def __init__(self, server, conn, addr):
        self.conn = conn
        self.addr = addr
        self.server = server
        self.buffer = ""
        self.state = "init"
        self.uid = None
        self.g_functions_add = { 'user':  self.userOnline , 'group' :  self.addCountGroup , 'channel':  self.addCountChannel  }
        self.g_functions_minus = { 'user':  self.userOnline , 'group' :  self.minusCountGroup , 'channel':  self.minusCountChannel  }
        self.push_type_add = { 'user':  'add_user', 'group' :  'add_group' , 'channel':  'add_count' }
        self.push_type_minus = { 'user':  'minus_user', 'group' :  'minus_group' , 'channel':  'minus_count' }

    def __call__(self):
        """
        This enters the user into an infinite loop.
        When the user disconnects, there is a special case where no data is sent.
        This will cause the while loop to break and will then check if the user is still in a connected state
        if he is, cleanup his connection by deleting his id out of online users, etc
        """

        #setup 60 second timeout on buffer reads
        while True:
#            with eventlet.Timeout(60) as timeout:
                data = self.conn.recv(BUF_SIZE)
                if not data:
                    thread_count['user'] -= 1
                    logging.info('Quiting user thread %r' % thread_count)
                    break
                self.buffer += data
                self.dispatchBuffer()

        if self.state == 'connected':
            if  len(self.server.users[self.uid]) is not 1:
                self.server.users[self.uid].remove(self)
            else:
                types = ['channel','group','user']
                group_bug_fix = self.server.g_dicts['group'].get(self.uid,None)
                if not group_bug_fix:
                    self.server.g_dicts['group'][self.uid] = []

                print self.server.g_dicts['user']
                print self.server.g_dicts['channel']
                print self.server.g_dicts['group']

                for type in types:
                    #this should be removed once code is updated to support groups
                    self.remove_cids(self.server.g_dicts[type][self.uid],type)
                    del self.server.g_dicts[type][self.uid]
                del self.server.users[self.uid]
                self.userOnline(self.uid,0)
                logging.info("User %s offline" % self.server.usernames[self.uid])

        # TODO: on disconnect
        # ...

    def dispatchBuffer(self):
        while '\n' in self.buffer:
            try:
                rawFrame, self.buffer = self.buffer.split('\n', 1)
                rawFrame = rawFrame.strip(' \r\n\t')
            except(Exception):
                continue

            try:
                frame = json.loads(rawFrame)
            except(Exception):
                continue

            self.receivedFrame(frame)

    def userOnline(self,ouid,status=1):
        type = 'user'
        channel = str(ouid)
        if self.server.channels[type].has_key(channel):
            response_type = self.push_type_add[type] if status else self.push_type_minus[type]
            for uid in self.server.channels[type][channel]:
                for uniq_conn in self.server.root.user_server.users[uid]:
                    uniq_conn.send_message(response_type, {'id': channel})

        userOnline_query = "UPDATE TEMP_ONLINE SET online = %s WHERE uid = %s" % (status,ouid)
        self.server.mysql_cursor.execute( userOnline_query )
        self.server.mysql_conn.commit()

    def addCountGroup(self,gid_list):
        logging.info("Group %s online" % gid_list)
        add_query = "UPDATE GROUP_ONLINE SET count = count + 1 WHERE gid IN ( %s )" % (gid_list)
        self.server.mysql_cursor.execute( add_query )
        self.server.mysql_conn.commit()

    def minusCountGroup(self,gid_list):
        minus_query = "UPDATE GROUP_ONLINE SET count = case when count - 1 < 0 then 0 else count - 1 end  WHERE gid IN ( %s )" % (gid_list)
        self.server.mysql_cursor.execute( minus_query )
        self.server.mysql_conn.commit()

    def minusCountChannel(self,cid_list):
        minus_query = "UPDATE TAP_ONLINE SET count = count - 1 WHERE cid IN ( %s )" % (cid_list)
        self.server.mysql_cursor.execute( minus_query )
        self.server.mysql_conn.commit()

    def addCountChannel(self,cid_list):
        add_query = "UPDATE TAP_ONLINE SET count = count + 1 WHERE cid IN ( %s )" % (cid_list)
        self.server.mysql_cursor.execute( add_query )
        self.server.mysql_conn.commit()
    

    def receivedFrame(self, frame):
        if self.state == "init":
            if frame.get('uid', None):
                self.uid = frame['uid']
                logging.info('UID = %s' % self.uid)
            else:
                return False
            self.state = 'connected'
            self.server.usernames[self.uid] = frame['uname']
            if not self.server.users.has_key(self.uid):
                self.server.users[self.uid] = []
                self.userOnline(self.uid,1)
                logging.info("User %s online" % self.server.usernames[self.uid])
            self.server.users[self.uid].append(self)

        if self.state == "connected":

            if 'cids' in frame and frame['cids']:
                cids = self.make_unique(frame['cids'].split(',')).keys()
            else:
                cids = []
                #return False

            if 'gids' in frame and frame['gids']:
                gids = self.make_unique(frame['gids'].split(',')).keys()
            else:
#                gids = '1' 
                gids = []

            if 'uids' in frame and frame['uids']:
                uids = self.make_unique(frame['uids'].split(',')).keys()
            elif 'uid' in frame and frame['uid']:
                uids = [frame['uid']]
            else:
                logging.info("uids/uid not in frame %r" % frame)
                uids = [self.uid]
        
            ##This is a temp fix

            new_lists = {
                'channel' : cids,
                'group' :   gids,
                'user' :    uids
            }
            for type in new_lists.keys():
#                if not new_lists[type]: continue
                #typecast
#                new_lists[type] = [ unicode(x) for x  in new_lists[type] ]
                if not self.server.g_dicts[type].has_key(self.uid):
                    self.server.g_dicts[type].setdefault(self.uid,new_lists[type])
                else:
                    channel_map_diff = set(self.server.g_dicts[type][self.uid]) - set(new_lists[type])
                    #If there's a difference, remove them, then set the new list to the new list
                    if channel_map_diff != set([]):
                        self.remove_cids(channel_map_diff,type)
                    self.server.g_dicts[type][self.uid] = new_lists[type]
                self.add_cids(new_lists[type],type)


    def add_cids(self,channel_list_cmp,type):
#   TO DO: Add per instance relationship of cids
#   uid_exists = self.server.instance_cids.setdefault(self.uid,{self:channel_list.keys()})
#   uid_exists.add({self:channel_list.keys()})
        channel_map_new = []
        uid_list_add = set()
        if channel_list_cmp:
            for channel in channel_list_cmp:
                update=0
                if self.server.channels[type].has_key(channel):
                    if not self.server.channels[type][channel].__contains__(self.uid):
                        self.server.channels[type][channel].add(self.uid)
                        update = 1
                    else:
                        continue
                else:
                    self.server.channels[type].setdefault(channel,set([self.uid]))
                    update = 1
                
                #if there was an update, make list of users who +1 update
                if update:
                    #channel_map_new is the SQL compliant list
                    channel_map_new.append(channel)
                    for uid in self.server.channels[type][channel]:
                #        if uid != self.uid:
                            for uniq_conn in self.server.root.user_server.users[uid]:
                                    uid_list_add.add(uniq_conn)
            if type == 'user': return False
            if channel_map_new != []:
                channel_map_string = self.join_string(channel_map_new)
                response_type = self.push_type_add[type]
                for uniq_conn in uid_list_add:
                    uniq_conn.send_message(response_type, {'id': channel_map_string})
                logging.info("Adding %s to %s" % (type,channel_map_string))
                self.g_functions_add[type](channel_map_string)

    def remove_cids(self,channel_map_diff,type):
        uid_list_minus = set()
        update = 0
        for channel in channel_map_diff:
            #Create a list of connection sessions to notify a tap is minus 1
            for uid in self.server.root.user_server.channels[type][channel]:
                #if uid != self.uid:
                    for uniq_conn in self.server.users[uid]:
                            uid_list_minus.add(uniq_conn)

            #Remove yourself from the channel, whatever it is
            self.server.channels[type][channel].remove(self.uid)

            #if channel is empty clean it up,
            #since this assumes you're the last one, continue 
            #and don't notify your other connections if there are any
            if not self.server.channels[type][channel]:
                del self.server.channels[type][channel]
            update = 1

        if update:
            if type == 'user': return False
            #Create SQL compliant string to -1 the tap in the database
            channel_map_string = self.join_string(channel_map_diff)
            response_type = self.push_type_minus[type]
            for uniq_conn in uid_list_minus:
                uniq_conn.send_message(response_type, {'id': channel_map_string})
            self.g_functions_minus[type](channel_map_string)

    def make_unique(self,seq):
        #Make Unique list
        k={}
        for e in seq:
            try:
                int(e)
            except:
                continue
            k[e] = 1
        return k

    def join_string(self,list):
        channel_map_string=''
        for channel in list:
            channel_map_string += "%s," % channel
        channel_map_string = channel_map_string[0:-1]
        return channel_map_string

    def send_message(self, type, data):
        try:
            self.conn.send(json.dumps({'type': type, 'module': 'user', 'data': data }) + '\r\n')
        except:
            pass


#START of Admin Server
class AdminServer(object):
    def __init__(self, root):
        self.root = root

    def __call__(self):
        server = eventlet.listen(('0.0.0.0', 4444))
        while True:
            c = AdminConnection(self, *server.accept())
            thread_count['admin'] += 1
            logging.info('Spawning admin thread %r' % thread_count)
            eventlet.spawn(c)

class AdminConnection(object):
    def __init__(self, server, conn, addr):
        self.users = {"admin":self}
        self.conn = conn
        self.addr = addr
        self.server = server
        self.buffer = ""
        self.state = "connected"
        self.uid = "admin"

    def __call__(self):
        """
        This enters the user into an infinite loop.
        When the user disconnects, there is a special case where no data is sent.
        This will cause the while loop to break and will then check if the user is still in a connected state
        if he is, cleanup his connection by deleting his id out of online users, etc
        """
        while True:
            data = self.conn.recv(BUF_SIZE)
            if not data:
                thread_count['admin'] -= 1
                logging.info('Quiting admin thread %r' % thread_count)
                break
            self.buffer += data
            self.dispatchBuffer()
        if self.state == 'connected':
            del self.users['admin']

    def dispatchBuffer(self):
        while '\n' in self.buffer:
            rawFrame, self.buffer = self.buffer.split('\r\n', 1)
            print 'Admin Console:', rawFrame
            try:
                frame = rawFrame.strip('\r').strip('\n')
            #JSON                frame = json.loads(rawFrame)
            except(Exception):
                continue

            self.receivedFrame(frame)

    def connectionPerUser(self):
        output = ''
        for uid in self.server.root.user_server.users:
            output += "User: %s - Connections: %s |||  " %(
            self.server.root.user_server.usernames[uid],
            len([ x for x in self.server.root.user_server.users[uid] ])
            )
        return output

    def userStats(self):
        output = ''
        for uid in self.server.root.user_server.users:
            output += "User: %s \n\t Connections: %s - \n\tcids: %s - \n\tgids: %s - \n\tuids: %s" %(
            self.server.root.user_server.usernames[uid],
            len([ x for x in self.server.root.user_server.users[uid] ]),
            self.server.root.user_server.g_dicts['channel'][uid],
            self.server.root.user_server.g_dicts['group'][uid],
            self.server.root.user_server.g_dicts['user'][uid]
            )
        return output
        
        
    def receivedFrame(self, frame):
        msg_data = frame
        if msg_data:
            try:
                type, data = {
                'online?' :  "%s users online" % ( len(self.server.root.user_server.users) ),
                'online_per?' :  "%s" % ( self.connectionPerUser() ),
                'user_stats?' :  "%s" % ( self.userStats() )
                } [msg_data]
                self.users['admin'].send_message(type, {'result': data})
            except KeyError,e:
                print "Bad Command!"
            
    def send_message(self, type, data):
        self.conn.send(json.dumps({'type': type, 'module': 'admin', 'data': data}) + '\r\n')


def clearViewers():
    #This is used to clear the viewers count in TAP_ONLINE,GROUP_ONLINE,TEMP_ONLINE so that when the server is
    #reset it will be in the proper state
    try:
        mysql_conn = MySQLdb.connect (host = "localhost",user = "root",passwd = "root",db = "rewrite2")
    except MySQLdb.Error, e:
        logging.error("Mysql: %d: %s" % (e.args[0], e.args[1]))
        sys.exit (1)

    mysql_cursor = mysql_conn.cursor (MySQLdb.cursors.DictCursor)
    clear_query = "UPDATE TAP_ONLINE SET count = 0"
    mysql_cursor.execute( clear_query )
    mysql_conn.commit()

    clear_query = "UPDATE GROUP_ONLINE SET count = 0"
    mysql_cursor.execute( clear_query )
    mysql_conn.commit()

    clear_query = "UPDATE TEMP_ONLINE SET online = 0"
    mysql_cursor.execute( clear_query )
    mysql_conn.commit()
    
if __name__ == "__main__":
    log_format = '%(asctime)s %(levelname)s: %(message)s'
    log_datefmt = '%H:%M:%S'
    if not DEBUG:
        logging.basicConfig(level = logging.INFO, filename = '/var/log/tap-server.log',
            format = log_format, datefmt = log_datefmt)
    else:
        logging.basicConfig(level = logging.INFO, format = log_format, datefmt = log_datefmt)
    sys.stderr = open('/var/log/tap-server.stderr.log', 'a') if not DEBUG else sys.stderr

    root = ServerRoot()
    root.start()
    logging.info("Starting Multi-User/Connection TCP server...")
    from eventlet.green import time
    try:
        logging.info('Waiting for keyboard interrupt')
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        clearViewers()
        logging.info("Exiting Multi-User/Connection TCP server...")
