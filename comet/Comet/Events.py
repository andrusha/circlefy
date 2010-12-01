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
from Mail import Mailer

intcast = lambda *args: map(lambda smth: int(smth) if smth is not None else None, args)

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
        self.mailer = Mailer(mysql)
        logging.info('Initializing event dispatcher')

    def __call__(self, frame):
        action = frame['action']
        data = frame['data']

        if action == 'tap.new':
            self.on_new_message(action, data)
        elif action == 'tap.delete':
            self.on_delete_message(action, data)
        elif action == 'response.new':
            self.on_new_response(action, data)
        elif action == 'group.follow':
            gid, uid, status = map(int, [data['group_id'], data['user_id'], data['status']])
            self.on_group_follow(gid, uid, status)
        elif action == 'convo.follow':
            mid, uid, status = map(int, [data['message_id'], data['user_id'], data['status']])
            self.on_convo_follow(mid, uid, status)
        elif action == 'response.typing':
            self.user_server.send_to(action, data, convo = int(data['cid']))
        elif action == 'user.follow':
            uid, fid, status = map(int, [data['who'], data['whom'], data['status']])
            unrecieved = self.user_server.send_to(action, data, users = set([fid]))
            if unrecieved or not status:
                self.on_user_follow(uid, fid, status)
        elif action == 'event.delete':
            self.on_delete_event(action, data)
        elif action == 'event.delete.all':
            self.on_delete_event(action, data, all = True)
        else:
            logging.warning('Unknow event! %s: %s' (action, data))

    def on_new_message(self, action, message):
        "New message handler"
        sender, group, private, reciever = intcast(message['sender_id'], 
            message['group_id'], message['private'], message['reciever_id'])

        if reciever is not None:
            recievers = {'users': set([sender, reciever])}
        else: 
            recievers = {'users': self.group_users(group)}
            if not private:
                recievers['group'] = group

        unrecieved = self.user_server.send_to(action, message, **recievers)
        if unrecieved:
            self.on_unrecieved_message(int(message['id']), unrecieved, data = message)

    def group_users(self, group):
        users = self.cassandra.get('group_members', group)
        return set(users) if users else set()

    def on_new_response(self, action, response):
        mid, timestamp = map(int, [response['message_id'], response['timestamp']])
        self.touch_message(mid, timestamp)

        unrecieved = self.user_server.send_to(action, response, 
                        users = self.convo_followers(mid), convo = mid)
        if unrecieved:
            self.on_unrecieved_response(mid, unrecieved, data = response)
            
    def touch_message(self, mid, timestamp):
        "Update message timestamp to reply timestamp"
        sql = "UPDATE message SET modification_time = FROM_UNIXTIME(%i) WHERE id = %i" % (timestamp, mid)
        self.mysql.cursor().execute(sql)
        self.mysql.commit()

    def convo_followers(self, convo):
        return set(self.cassandra.get('convo_followers', convo))

    def on_group_follow(self, group, user, status):
        if status:
            self.cassandra.insert('group_members', group, {user: user})
        else:
            self.cassandra.remove('group_members', group, columns=[user])

    def on_convo_follow(self, message, user, status):
        if status:
            self.cassandra.insert('convo_followers', message, {user: user})
        else:
            self.cassandra.remove('convo_followers', message, columns=[user])

    def on_unrecieved_message(self, message, users, data = None):
        "Adds message to events queue for each user"
        joined = (', 0, %i),(' % message).join(map(str, users))
        sql = 'INSERT IGNORE INTO events (user_id, type, related_id) VALUES (%s, 0, %i)' % (joined, message)
        self.mysql.cursor().execute(sql)
        self.mysql.commit()

        type = 'new_personal' if data['reciever_id'] is not None else 'new_message'
        self.mailer.queue(users, type, message_id = data['id'], 
            group_id = data['group_id'], user_id = data['sender_id'])

    def on_unrecieved_response(self, message, users, data = None):
        "Insert new reply event or update every event to +1"
        joined = (', 1, %i, 1),(' % message).join(map(str, users))
        sql = 'INSERT INTO events (user_id, type, related_id, new_replies) VALUES (%s, 1, %i, 1)' % (joined, message) + \
              'ON DUPLICATE KEY UPDATE new_replies = new_replies + 1'
        self.mysql.cursor().execute(sql)
        self.mysql.commit()

        self.mailer.queue(users, 'new_reply', message_id = data['message_id'], 
            user_id = data['user_id'], reply_id = data['id'])

    def on_user_follow(self, user, friend, status):
        "Updates events on user following/unfollowing"
        sql = ''
        if status:
            sql = 'INSERT IGNORE INTO events (user_id, type, related_id) VALUES (%i, 2, %i)' % (friend, user)
        else:
            sql = 'DELETE FROM events WHERE user_id = %i AND type = 2 AND related_id = %i' % (friend, user)
        self.mysql.cursor().execute(sql)
        self.mysql.commit()

        if status:
            self.mailer.queue(friend, 'new_follower', user_id = user)

    def on_delete_message(self, action, message):
        "Delete message from events queue and cassandra tables"
        cid = int(message['id'])
        sql = 'DELETE FROM events WHERE related_id = %i' % cid
        self.mysql.cursor().execute(sql)
        self.mysql.commit()

        self.cassandra.remove('convo_followers', cid)

        self.user_server.send_to(action, message, convo = cid)

    def on_delete_event(self, action, event, all = False):
        "Delete event by type"
        uid, type, id = intcast(event['user_id'], event['type'], event['event_id'])

        where = 'WHERE user_id = %i' % uid
        if not all:
            where += ' AND related_id = %i' % id
            where += ' AND type IN (0, 1)' if type in (0, 1) else (' AND type = %i' % type)

        sql = 'DELETE FROM events %s' % where
        self.mysql.cursor().execute(sql)
        self.mysql.commit()

        self.user_server.send_to(action, event, users = set([uid]))
