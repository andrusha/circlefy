# Internal server serves all requests from php scripts,
# it also do event-dispatching
#
# Packet format - JSON encoded dict:
#   action - what's going on, translates to user
#   data   - what will be sended to user and used for event dispatching
#
# Event dispatching works depending on `action` and information in `data`,

import logging
import time
import MySQLdb
from Abstract import AbstractServer, AbstractConnection

class EventServer(AbstractServer):
    def __init__(self, dispatcher):
        logging.info('Starting events server')
        super(EventServer, self).__init__(3334, EventListener)
        self.dispatcher = dispatcher

class EventListener(AbstractConnection):
    def receivedFrame(self, frame):
        logging.info("Recieved frame %r" % frame)
        
        if 'data' in frame and 'action' in frame:
            a = time.time()
            self.server.dispatcher(frame) 
            logging.debug('Event served in %f sec' % (time.time() - a))
            return
        
        logging.warning("Bad packet! %s" % frame)
        
class EventDispatcher(object):
    def __init__(self, mysql, cassandra, user_server):
        self.user_server = user_server
        self.mysql = mysql
        self.cassandra = cassandra
        logging.info('Initializing event dispatcher')

    def __call__(self, frame):
        action = frame['action']
        data = frame['data']
        recievers = None

        if action == 'tap.new':
            recievers = self.on_new_message(data)
        elif action == 'response.new':
            recievers = self.on_new_response(data)
        elif action == 'group.follow':
            gid, uid, status = map(int, [data['group_id'], data['user_id'], data['status']])
            self.on_group_follow(gid, uid, status)
        elif action == 'convo.follow':
            mid, uid, status = map(int, [data['message_id'], data['user_id'], data['status']])
            self.on_convo_follow(mid, uid, status)
        elif action == 'response.typing':
            recievers = {'convo': int(data['cid'])}
        else:
            logging.warning('Unknow event! %s: %s' (action, data))

        if recievers:
            self.user_server.send_to(action, data, **recievers)

    def on_new_message(self, message):
        "New message handler"
        sender, reciever, group = map(int, [message['sender_id'], message['reciever_id'], message['group_id']])

        personal = message['reciever_id'] is not None 
        if personal:
           return {'users': set([sender, reciever])}

        private = self.is_member(sender, group)
        recievers = {'users': self.group_users(group)}
        if not private:
            recievers['group'] = group

        return recievers

    def is_member(self, user, group):
        return self.cassandra.get('inverted_members', user, columns = [group]) is not None

    def group_users(self, group):
        return set(self.cassandra.get('group_members', group))

    def on_new_response(self, response):
        mid, timestamp = map(int, [response['message_id'], response['timestamp']])
        self.touch_message(mid, timestamp)
        return {'users': self.convo_followers(mid),
                'convo': mid}
            
    def touch_message(self, mid, timestamp):
        "Update message timestamp to reply timestamp"
        sql = "UPDATE message SET time = FROM_UNIXTIME(%i) WHERE id = %i" % (timestamp, mid)
        self.mysql.cursor(MySQLdb.cursors.DictCursor).execute(sql)

    def convo_followers(self, convo):
        return set(self.cassandra.get('convo_followers', convo))

    def on_group_follow(self, group, user, status):
        if status:
            self.cassandra.insert('group_members', group, {user: user})
            self.cassandra.insert('inverted_members', user, {group: group})
        else:
            self.cassandra.remove('group_members', group, columns=[user])
            self.cassandra.remove('inverted_members', user, columns=[group])

    def on_convo_follow(self, message, user, status):
        if status:
            self.cassandra.insert('convo_followers', message, {user: user})
        else:
            self.cassandra.remove('convo_followers', message, columns=[user])
