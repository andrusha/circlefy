import socket
import random
import time
import sys
import random
import fcntl
import MySQLdb
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
			print  self.server.root.user_server.channels['channel']
                        for uid in self.server.root.user_server.channels['channel'][cid]:
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
	self.instance_cids = {}
	self.channels = { 'users': {}, 'groups' : {} , 'channel': {} }
	self.g_dicts = { 'users': {}, 'groups' : {} , 'channel': {} }
        self.root = root

	self.mysql_conn = False
	self.mysql_cursor = False

	try:
		self.mysql_conn = MySQLdb.connect (host = "localhost",user = "root",passwd = "root",db = "rewrite2")
	except MySQLdb.Error, e:
		print "Error %d: %s" % (e.args[0], e.args[1])
		sys.exit (1)
	self.mysql_cursor = self.mysql_conn.cursor (MySQLdb.cursors.DictCursor)


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
	self.g_functions_add = { 'users':  self.userOnline , 'groups' :  self.addCountGroup , 'channel':  self.addCountChannel  }
	self.g_functions_minus = { 'users':  self.userOnline , 'groups' :  self.minusCountGroup , 'channel':  self.minusCountChannel  }
	self.push_type_add = { 'users':  'add_user', 'groups' :  'add_group' , 'channel':  'add_count' }
	self.push_type_minus = { 'users':  'minus_user', 'groups' :  'minus_group' , 'channel':  'minus_count' }

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
	    	print "DISCONNECTING...."
	    	self.remove_cids(self.server.g_dicts['channel'][self.uid])
	        del self.server.users[self.uid]
		self.userOnline(self.uid,0)
		del self.server.g_dicts['channel'][self.uid]
	        print "DONE."

            # TODO: on disconnect
            # ...

    def dispatchBuffer(self):
        while '\n' in self.buffer:
            rawFrame, self.buffer = self.buffer.split('\r\n', 1)
	    print rawFrame
	    try:
		frame = json.loads(rawFrame)
	    except(Exception):
		continue

            self.receivedFrame(frame)

    def userOnline(self,uid,status):
	if not uid:
		print "userOnline error"
		return None
	userOnline_query = "UPDATE TEMP_ONLINE SET online = %s WHERE uid = %s" % (status,uid)
	self.server.mysql_cursor.execute( userOnline_query )
	self.server.mysql_conn.commit()

    def addCountGroup(self,gid_list):
	add_query = "UPDATE GROUP_ONLINE SET count = count + 1 WHERE gid IN ( %s )" % (gid_list)
	self.server.mysql_cursor.execute( add_query )
	self.server.mysql_conn.commit()

    def minusCountGroup(self,gid_list):
	minus_query = "UPDATE GROUP_ONLINE SET count = case when count - 1 < 0 then 0 else count - 1 end  WHERE gid IN ( %s )" % (gid_list)
	self.server.mysql_cursor.execute( minus_query )
	self.server.mysql_conn.commit()

    def minusCountChannel(self,cid_list):
	if not cid_list:
		print "FAIL"
		return None
	minus_query = "UPDATE TAP_ONLINE SET count = case when count - 1 < 0 then 0 else count - 1 end  WHERE cid IN ( %s )" % (cid_list)
	print minus_query
	self.server.mysql_cursor.execute( minus_query )
	self.server.mysql_conn.commit()

    def addCountChannel(self,cid_list):
	if not cid_list:
		print "FAIL"
		return None
	add_query = "UPDATE TAP_ONLINE SET count = count + 1 WHERE cid IN ( %s )" % (cid_list)
	print add_query
	self.server.mysql_cursor.execute( add_query )
	self.server.mysql_conn.commit()


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
		self.userOnline(self.uid,1)
            self.server.users[self.uid].append(self)
	    print self.server.users

	if self.state == "connected":
            if frame.has_key('cids'):
                #NEEDS CHANGE - One catch here is that all User Interfaces might need different cids, this could waste bw
		new_lists = {
		'channel' : self.make_unique(frame['cids'].split(',')).keys(),
		'group' : self.make_unique(frame['cids'].split(',')).keys(),
		'user' : self.make_unique(frame['cids'].split(',')).keys()
		}
		#The `type` variable defines which types of online presences to track
		types = ['channel']
		for type in types:
			if not new_lists[type]: continue
			if not self.server.g_dicts[type].has_key(self.uid):
				self.server.g_dicts[type].setdefault(self.uid,new_lists[type])
			else:
				channel_map_diff = set(self.server.g_dicts[type][self.uid]) - set(new_lists[type])
				#If there's a difference, remove them, then set the new list to the new list
				if channel_map_diff != set([]):
					self.remove_cids(channel_map_diff,type)
				self.server.g_dicts[map][self.uid] = new_lists[type]
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
				self.server.channels[type].setdefault(channel,set([self.uid]))
				update = 1
			
			#if there was an update, make list of users who +1 update
			if update:
				channel_map_new.append(channel)
				for uid in self.server.channels[type][channel]:
					for uniq_conn in self.server.root.user_server.users[uid]:
							uid_list_add.add(uniq_conn)
				#channel_map_new is the SQL compliant list
		if channel_map_new != []:
			channel_map_string = self.join_string(channel_map_new)
			for uniq_conn in uid_list_add:
				uniq_conn.send_message({self.push_type_add[type]:channel_map_string})
			self.g_functions_add[type](channel_map_string)

    def remove_cids(self,channel_map_diff,type):
		uid_list_minus = set()
		update = 0
		for channel in channel_map_diff:
			#Create a list of connection sessions to notify a tap is -1
			for uid in self.server.root.user_server.channels[type][channel]:
				for uniq_conn in self.server.users[uid]:
						uid_list_minus.add(uniq_conn)
			try:
				#For each channel, remove the current user
				self.server.channels[type][channel].remove(self.uid)
				if not self.server.channels[type][channel]:
					del self.server.channels[type][channel]
			except KeyError:
				print "Key %s ERROR" % (channel)
				print "Original Map: %s" % (self.server.g_dicts[type])
				continue
			update = 1
		if update:
			#Create SQL compliant string to -1 the tap in the database
			channel_map_string = self.join_string(channel_map_diff)
			#For each connection, notify
			for uniq_conn in uid_list_minus:
				uniq_conn.send_message({self.push_type_minus[type]:channel_map_string})
			#Execute -1 SQL
			self.g_functions_minus[type](channel_map_string)

    def make_unique(self,seq):
		#Make Unique list
		k={}
		for e in seq:
			try:
				int(e)
			except:
				print "FUCK"
				continue
			k[e] = 1
		return k

    def join_string(self,list):
	channel_map_string=''
	for channel in list:
		channel_map_string += "%s," % channel
	channel_map_string = channel_map_string[0:-1]
	return channel_map_string

    def send_message(self, data):
	try:
	        self.conn.send(json.dumps({'msgData': data }) + '\r\n')
	except:
		pass


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
			'online?' :  "%s users online" % ( len(self.server.root.user_server.users) ),
			'online_taso?' :  "%s tasos online" % ( len(self.server.root.user_server.users[63]) )
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
        

def clearViewers():
	#This is used to clear the viewers count in TAP_ONLINE so that when the server is
	#reset it will be in the proper state
	try:
                mysql_conn = MySQLdb.connect (host = "localhost",user = "root",passwd = "root",db = "rewrite2")
        except MySQLdb.Error, e:
                print "Error %d: %s" % (e.args[0], e.args[1])
                sys.exit (1)
        mysql_cursor = mysql_conn.cursor (MySQLdb.cursors.DictCursor)
	clear_query = "UPDATE TAP_ONLINE SET count = 0"
	mysql_cursor.execute( clear_query )
	mysql_conn.commit()
	clear_query = "UPDATE TEMP_ONLINE SET online = 0"
	mysql_cursor.execute( clear_query )
	mysql_conn.commit()
	
if __name__ == "__main__":
    root = ServerRoot()
    root.start()
    print "Starting Multi-User/Connection TCP server...\n"
    from eventlet.green import time
    try:
	    while True:
		time.sleep(1)
    except KeyboardInterrupt:
	    clearViewers()
	    print "Exiting Multi-User/Connection TCP server..."
