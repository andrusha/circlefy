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
from uuid import uuid1 as time_uuid
from Abstract import AbstractServer, AbstractConnection
from Cassandra import pack

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
    def __init__(self, cassandra, user_server):
        self.cassandra = cassandra
        self.user_server = user_server
        self.messages = CassandraMessage(cassandra)
        logging.info('Initializing event dispatcher')

    def __call__(self, frame):
        action = frame['action']
        data = frame['data']

        for key, val in data.items():
            if key in ['id', 'message_id', 'user_id', 'friend_id',
                      'sender_id', 'group_id', 'reciever_id', 'status'] and val is not None:
                data[key] = int(val)
        
        if action == 'convo.follow':
            self.follow('inverted_convos', data['message_id'], data['user_id'], data['status']) 
            self.follow_convo(data['user_id'], data['message_id'], data['status'])
        elif action == 'group.follow':
            sellf.follow('group_members', data['group_id'], data['user_id'], data['status'])
        elif action == 'user.follow':
            self.follow('inverted_friends', data['friend_id'], data['user_id'], data['status'])
            self.follow_user(action, data)
        elif action == 'tap.new':
            self.new_message(action, data)
        elif action == 'response.new':
            self.new_response(action, data)
        elif action == 'response.typing':
            self.user_server.send_to(action, data, convo=int(data['cid']))
        else:
            logging.warning('Unknown event! %s: %s' % (action, data))

    def follow(self, keyspace, id, val, status):
        if status:
            self.cassandra.insert(keyspace, id, {val: val})
        else:
            self.cassandra.remove(keyspace, id)

    def follow_user(self, action, data):
        "Sends message to user, updates following table"
        unrecieved = self.user_server.send_to(action, data, users = set([data['friend_id']]))
        if unrecieved:
            self.cassandra.insert('misc', data['friend_id'], {'following': {time_uuid(): data['user_id']}})

    def follow_convo(self, uid, mid, state):
        "Updates user convo feed"
        pass

    def new_message(self, action, message):
        mid, rid, uid, gid = (message['id'], message['reciever_id'], message['sender_id'], message['group_id'])

        a = time.time()
        if rid is not None:
            uuid = self.messages.add_personal(mid, uid, rid)
            recievers = {'users': set([uid, rid])}
        else:
            private = self.cassandra.get('group_members', gid, columns = [uid]) is not None
            uuid, recievers = self.messages.add_group(mid, gid, uid, private)

        logging.debug('Adding message in %f sec' % (time.time() - a))
        unrecieved = self.user_server.send_to(action, message, **recievers)
        self.messages.add_unrecieved(uuid, mid, unrecieved)

    def new_response(self, action, message):
        a = time.time()
        mid = int(message['message_id'])
        self.messages.update_message(mid)
        logging.debug('Updating response in %f sec' % (time.time() - a))
        self.user_server.send_to(action, message, convo=mid)

class CassandraMessage():
    def __init__(self, cassandra):
        self.cassandra = cassandra

    def add(self, mid):
        uuid = time_uuid()
        self.cassandra.insert('TIME_BY_MESSAGE', 'global', {mid: uuid})
        return uuid

    def add_unrecieved(self, uuid, mid, users):
        insert = dict(zip(map(pack, users), [{'unrecieved': {uuid: mid}}]*len(users)))
        self.cassandra.batch_insert('feeds', insert)

    def add_personal(self, mid, uid, rid):
        "uid - sender id, rid - recepient id"
        uuid = self.add(mid)
        data = {uuid: mid}

        insert = {'feed':    data,
                  'private': data,
                  'friends': data}
        self.cassandra.batch_insert('feeds', {pack(uid): insert,
                                              pack(rid): insert})
        return uuid

    def add_group(self, mid, gid, uid, private = False):
        prefix = 'private' if private else 'public'
        uuid = self.add(mid)
        data = {uuid: mid}

        # update global feeds
        self.cassandra.insert('global_events', 'public', data)
        self.cassandra.insert('group_events', gid, data)
        self.cassandra.insert('group_events', '%s/%s' % (prefix, gid), data)
        self.cassandra.insert('GROUP_BY_MESSAGE', mid, {gid: gid})
        if not private:
            self.cassandra.insert('user_events', uid, data)

        # update individual feeds
        recievers = self.cassandra.get('group_members', gid)
        if not recievers:
            return {'users': set()}

        followers = self.cassandra.get('inverted_friends', uid)
        followers = followers.keys() if followers else {}
        inserted = {}
        for uid in recievers.iterkeys():
            insert = {'feed': data,
                      'groups': data,
                      '%s/groups' % prefix: data}
            if uid in followers:
                insert['friends'] = data
            inserted[pack(uid)] = insert

        self.cassandra.batch_insert('feeds', inserted)
        self.cassandra.batch_insert('USER_BY_MESSAGE', {pack(mid): recievers})

        return uuid, {'users': set(recievers), 'group': gid if not private else None}

    def remove(self, mid, purge = True):
        "Removes message from all feeds it can find"
        "purge - remove completly"
        uuid = self.cassandra.get('TIME_BY_MESSAGE', 'global', columns=[mid])
        
        # remove from global feed
        self.cassandra.remove('global_events', 'public', columns=[uuid])
        
        # remove from groups
        groups = self.cassandra.get('GROUP_BY_MESSAGE', mid)
        if groups is not None:
            self.remove_from_groups(mid, groups)

            if purge:
                self.cassandra.remove('GROUP_BY_MESSAGE', mid)

        # remove from users
        users  = self.cassandra.get('USERS_BY_MESSAGE', mid)
        if users is not None:
            self.remove_from_users(mid, users)

            if purge:
                self.cassandra.remove('USERS_BY_MESSAGE', mid)

        self.cassandra.remove('TIME_BY_MESSAGE', 'global', columns=[mid])

    def remove_from_groups(self, mid, groups):
        for gid in groups:
            for sub in ['%i', 'public/%i', 'private/%i']:
                self.cassandra.remove('group_events', sub % gid, columns=[uuid])

    def remove_from_users(self, mid, users, purge = True):
        for uid in users:
            self.cassandra.remove('user_events', uid, columns=[uuid])
            for sup in ['feed', 'groups', 'public/groups', 'private/groups', 'friends', 'convos', 'private']:
                self.cassandra.remove('feeds', uid, super_column = sup, columns=[uuid.bytes])

            if purge:
                self.cassandra.remove('feeds', uid, super_column = 'unrecieved', columns=[mid])

    def update_message(self, mid):
        old_uuid = self.cassandra.get('TIME_BY_MESSAGE', 'global', columns=[mid])
        new_uuid = time_uuid()
        data = {new_uuid: mid}

        #what we do here is going through all feeds
        #and if there is message with old uuid, delete it
        #and add message with new uuid

        if self.cassandra.get('global_events', 'public', old_uuid) is not None:
            self.cassandra.remove('global_events', 'public', columns=[old_uuid])
            self.cassandra.insert('global_events', 'public', data)

        groups = self.cassandra.get('GROUP_BY_MESSAGE', mid)
        if groups is not None:
            for gid in groups:
                for sub in ['%i', 'public/%i', 'private/%i']:
                    if self.cassandra.get('group_events', sub % gid, old_uuid) is not None:
                        self.cassandra.remove('group_events', sub % gid, columns=[old_uuid])
                        self.cassandra.insert('group_events', sub % gid, data)


        users  = self.cassandra.get('USERS_BY_MESSAGE', mid)
        if users is not None:
            for uid in users:
                if self.cassandra.get('user_events', uid, old_uuid) is not None:
                    self.cassandra.remove('user_events', uid, columns=[old_uuid])
                    self.cassandra.insert('user_events', uid, data)

                for sup in ['feed', 'groups', 'public/groups', 'private/groups', 'friends', 'convos', 'private']:
                    if self.cassandra.get('feeds', uid, super_column = sup, columns=[old_uuid.bytes]) is not None: 
                        self.cassandra.remove('feeds', uid, super_column = sup, columns=[old_uuid.bytes])
                        self.cassandra.insert('feeds', uid, {pack(sup): data})
