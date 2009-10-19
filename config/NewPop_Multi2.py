import socket
import random
import time
import sys
import random
import fcntl
import memcache
import simplejson as json
#
# Written By Taso Du Val
# 2009 tap
#

from eventlet import api
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
    def start(self):
        api.spawn(self.admin_server)
        api.spawn(self.user_server)
        api.spawn(self.message_server)
        api.spawn(self.pinger_obj)

#START of Message Server

class Pinger(object):
	def __init__(self, root):
        	self.root = root
	
	def __call__(self):
		while True:
			self.ping()
			time.sleep(60)
	
	def ping(self):
		for uid in self.root.user_server.users:
			for uniq_conn in self.root.user_server.users[uid]:
					uniq_conn.send_message('ping')
		
class MessageServer(object):
    def __init__(self, root):
        self.root = root

    def __call__(self):
        server = api.tcp_listener(('0.0.0.0', 3333))
        while True:
            c = MessageConnection(self,*server.accept())
            api.spawn(c)

class MessageConnection(object):
    
    def __init__(self,server, conn, addr):
        self.conn = conn
        self.server = server
        self.addr = addr
        self.buffer = ""
	self.uids = []

    def __call__(self):
        while True:
            data = self.conn.recv(BUF_SIZE)
            if not data: break
            self.buffer += data
            self.dispatchBuffer()


    def dispatchBuffer(self):
        while '\n' in self.buffer:
	    print  "BUFFER: %s" % ( self.buffer ) 
            rawFrame, self.buffer = self.buffer.split('\r\n', 1)
            print 'Message Console:', rawFrame
	    try:
	            frame = json.loads(rawFrame)
	    except(Exception):
		    continue

            self.receivedFrame(frame)

    def receivedFrame(self, frame):
	if frame.has_key('response') and frame.has_key('cid'):
                response_data = frame['response'].split('\n')
                cid = frame['cid']
		type = frame['action']
		data = frame['response']

		if type == 'response':
			init_tapper = frame['init_tapper']
		
		
		if frame.has_key('uname'):
			uname = frame['uname']
		else:
			uname = 'NEED UNAME'

                response = self.response_generator(cid,data,uname,type)
                try:
                        for uid in self.server.root.user_server.channels[cid]:
                                for uniq_conn in self.server.root.user_server.users[uid]:
                                                uniq_conn.send_message(response)

			#Send response to the initial tapper to activate his active convo
			if type == 'response':
				for uniq_conn in self.server.root.user_server.users[int(init_tapper)]:
							uniq_conn.send_message([cid,"convo"])

                except Exception:
                        print "No user subscribed to specific channel"
                        return False

	if frame.has_key('msg'):
	        msg_data = frame['msg'].split('\n')
	else:
		return False

	matches_list = self.check_new_bits(self.server.root.user_server.users,msg_data)
	if matches_list != []:
		results = self.bit_generator(matches_list)
		for item in results:pass
	
		 #Make Unique list ( THIS IS A BIG HACK , needs to be changed to set() TODO / FIX ASAP
                k={}
                for e in self.uids:
                        k[e] = 1
                uniq_uids = k.keys()	
		for uid in uniq_uids:
		    if uid in self.server.root.user_server.users:
			for uniq_conn in self.server.root.user_server.users[uid]:
				uniq_conn.send_message(results)
		self.uids = []

    def response_generator(self,cid,data,uname,type):
                response = [cid,data,uname,type]
                return response

	
    def bit_generator(self,matches_list):
		bits = []
		for tuples in matches_list:
			#Get row from memcache
			for els in tuples:
				if type(els) == list:
					#Create HTML/bits based on types ( there are either one or two types) 
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
				
				groups = 'groups'
				friends = 'friends'
				rel = 'rel'
				group_hit=1
				friend_hit=1
				rel_hit=1
				if len(cols) >= 6:
					uid = cols[0]
					cid = cols[1]
					gid = cols[2]
					rid = cols[3]
					fuid =cols[4]
					row_type=cols[5]
					if row_type == 0:
						tap_type = cols[6]
					else:
						tap_type = None

					#Group Processing
					if row_type == 0:
						if group_hit != 0:
							groups=False
							matches_list.insert(0,(uid,cid,row_type,tap_type,["groups","group_%s" % ( gid )]))
							continue

                                        #Friend Processing
                                        if row_type == 1:
	                                        if friend_hit != 0:
							friends=False
                                                	matches_list.insert(0,(uid,cid,row_type,tap_type,["friends","friend_%s" % ( fuid )]))
							continue

                                        #Direct Processing
                                        if row_type == 2:
                                                matches_list.insert(0,(uid,cid,row_type,tap_type,["direct"]))
						continue

                                        #Direct Processing
                                        if row_type == 4:
                                                matches_list.insert(0,(uid,cid,row_type,tap_type,["building"]))
						continue

                                        #Filter Processing
                                        if row_type == 3:
                                                if rel_hit != 0:
							rel=False
	                                                matches_list.insert(0,(uid,cid,row_type,tap_type,["rel","tab_rel_%s" % ( rid )] ))
							continue

		return matches_list

#START of User Server
class UserServer(object):
    def __init__(self, root):
        self.users = {}
	self.usernames = {}
	self.channels = {}
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
	self.channel_map = []

    def __call__(self):
	"""
	This enters the user into an infinite loop.
	When the user disconnects, there is a special case where no data is sent.
	This will cause the while loop to break and will then check if the user is still in a connected state
	if he is, cleanup his connection by deleting his id out of online users, etc
	"""
        while True:
            data = self.conn.recv(BUF_SIZE)
            if not data: break
            self.buffer += data
            self.dispatchBuffer()
        if self.state == 'connected':
	    if  len(self.server.users[self.uid]) is not 1:
		self.server.users[self.uid].remove(self)
	    else:
	        del self.server.users[self.uid]
                for channel in self.channel_map:
                        self.server.channels[channel].remove(self.uid)
                        if not len(self.server.channels[channel]):
                                del self.server.channels[channel]

            # TODO: on disconnect
            # ...
	    #I should probably run some querieis here and whatnot

    def dispatchBuffer(self):
        while '\n' in self.buffer:
            rawFrame, self.buffer = self.buffer.split('\r\n', 1)
	    print rawFrame
	    try:
		frame = json.loads(rawFrame)
	    except(Exception):
		continue

            self.receivedFrame(frame)

    def receivedFrame(self, frame):
        if self.state == "init":
	    if frame.has_key('uid'):
	            self.uid = frame['uid']
	    else:
		    return False
	
            self.state = 'connected'
            self.server.usernames[self.uid] = frame['uname']
	    if not self.server.users.has_key(self.uid):
		self.server.users[self.uid] = []
            self.server.users[self.uid].append(self)
	    print self.server.users

	if self.state == "connected":
            if frame.has_key('cids'):
                #NEEDS CHANGE - One catch here is that all User Interfaces might need different cids, this could waste bw

		print "Befre list: %s" % ( self.server.channels ) 
                if self.channel_map != [] and type(self.channel_map) == list:
                        for channel in self.channel_map:
				try:
					self.server.channels[channel].remove(self.uid)
					if not self.server.channels[channel]:
						del self.server.channels[channel]
				except KeyError:
					print "Key %s ERROR" % (channel)
					print "Original Map: %s" % (self.channel_map)
					print "New Map Map: %s" % (frame['cids'])
					continue

                channel_list = frame['cids']
                seq = channel_list.split(',')
		#Make Unique list
		k={}
		for e in seq:
			k[e] = 1
                self.channel_map = k.keys()
                for channel in self.channel_map:
                        channel_exist = self.server.channels.setdefault(channel,set([self.uid]))
                        channel_exist.add(self.uid)
		print "After list: %s" % ( self.server.channels ) 

    def send_message(self, data):
        self.conn.send(json.dumps({'msgData': data }) + '\r\n')


#START of Admin Server
class AdminServer(object):
    def __init__(self, root):
        self.root = root

    def __call__(self):
        server = api.tcp_listener(('0.0.0.0', 4444))
        while True:
            c = AdminConnection(self, *server.accept())
            api.spawn(c)

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
            if not data: break
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

    def receivedFrame(self, frame):
        msg_data = frame
	if msg_data:
		try:
			results = {
			'online?' :  "%s users online" % ( len(self.server.root.user_server.users) ) 
			}[msg_data]
		except Exception:
			results = """
%s is not an option, please try the following:\n
	online? ... This will show how many users are currently online
	data_all? ... This will show how much data has flown through the system since start
	data_user user? ... This will show how much data has flown through the system per a specific user
	dump? ... TO DO
			""" % ( msg_data )
		self.users['admin'].send_message(results)
	
    def send_message(self, data):
        self.conn.send("%s" % (data) + '\r\n')
#JSON   self.conn.send(json.dumps({'adminData': data }) + '\r\n')
        

if __name__ == "__main__":
    root = ServerRoot()
    root.start()
    print "Starting Multi-User/Connection TCP server...\n"
    from eventlet.green import time
    try:
	    while True:
		time.sleep(1)
    except KeyboardInterrupt:
	    print "Exiting Multi-User/Connection TCP server..."
