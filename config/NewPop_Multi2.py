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
		self.mysql_pinger_obj = MySQL_Pinger(self)
	def start(self):
		api.spawn(self.admin_server)
		api.spawn(self.user_server)
		api.spawn(self.message_server)
		api.spawn(self.pinger_obj)
		api.spawn(self.mysql_pinger_obj)

#START of Message Server
class MySQL_Pinger(object):
	def __init__(self, root):
        	self.root = root
	
	def __call__(self):
		while True:
			self.ping()
			time.sleep(6)
	
	def ping(self):	
		#print "MySQL Ping"
		#self.root.user_server.mysql_cursor.execute("SELECT 'ping'")
		self.root.user_server.mysql_conn.ping()

class Pinger(object):
	def __init__(self, root):
        	self.root = root
	
	def __call__(self):
		while True:
			self.ping()
			time.sleep(60)
	
	def ping(self):
		#print "normal ping"
		for uid in self.root.user_server.users:
			for uniq_conn in self.root.user_server.users[uid]:
					print uniq_conn
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
			#print  "BUFFER: %s" % ( self.buffer ) 
			rawFrame, self.buffer = self.buffer.split('\r\n', 1)
			#print 'Message Console:', rawFrame
			try:
				frame = json.loads(rawFrame)
			except(Exception):
			    continue

			self.receivedFrame(frame)

	def tap_ACTION(self,msg_data):
		matches_list = self.check_new_bits(self.server.root.user_server.users,msg_data)
		if matches_list != []:
			results = self.bit_generator(matches_list)
			uniq_uids = set(self.uids)
			for uid in uniq_uids:
			    if uid in self.server.root.user_server.users:
				for uniq_conn in self.server.root.user_server.users[uid]:
					uniq_conn.send_message(results)
			self.uids = []

	def response_ACTION(self,cid,data,uname,init_tapper,response):
		try:
			for uid in self.server.root.user_server.channels['channel'][cid]:
				for uniq_conn in self.server.root.user_server.users[uid]:
						uniq_conn.send_message(response)
			#Send response to the initial tapper to activate his active convo
			for uniq_conn in self.server.root.user_server.users[int(init_tapper)]:
						uniq_conn.send_message([cid,"convo"])
		except Exception:
			print "No user subscribed to specific channel"
			return False

	def typing_ACTION(self,cid,data,uname,response):
		try:
			for uid in self.server.root.user_server.channels['channel'][cid]:
				for uniq_conn in self.server.root.user_server.users[uid]:
						uniq_conn.send_message(response)
		except Exception:
			print "No user subscribed to specific channel"
			return False

	def receivedFrame(self, frame):
		if 'action' in frame and 'cid' in frame and 'response' in frame and 'uname' in frame:
			print "%r" % frame
			type = frame['action']
			cid = frame['cid']
			data = frame['response']
			uname = frame['uname']

			if 'pic_small' in frame:
				pic = frame['pic_small']
			else:
				pic = ''

			response = self.response_generator(cid,data,uname,type,pic)

			if type == 'response' and 'init_tapper' in frame:
				init_tapper = frame['init_tapper']
				self.response_ACTION(cid,data,uname,init_tapper,response)

			if type == 'typing':
				self.typing_ACTION(cid,data,uname,response)

			return True

		if 'msg' in frame:
			msg_data = frame['msg'].split('\n')
			self.tap_ACTION(msg_data)

			return True
		
		print "BAD PACKET!!! WARNING!!"
		return False

	def response_generator(self,cid,data,uname,type,pic):
		response = [cid,data,uname,type,pic]
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
		self.channels = { 'user': {}, 'group' : {} , 'channel': {} }
		self.g_dicts = { 'user': {}, 'group' : {} , 'channel': {} }
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
		while True:
			data = self.conn.recv(BUF_SIZE)
			if not data: break
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
				print "User %s offline" % self.server.usernames[self.uid]

	    # TODO: on disconnect
	    # ...

	def dispatchBuffer(self):
		while '\n' in self.buffer:
			try:
				rawFrame, self.buffer = self.buffer.split('\r\n', 1)
			except(Exception):
				continue	
			#print rawFrame
			try:
				frame = json.loads(rawFrame)
			except(Exception):
				continue

			self.receivedFrame(frame)

	def userOnline(self,ouid,status=1):
		type = 'user'
		channel = str(ouid)
		if self.server.channels[type].has_key(channel):
			if status is 0:
				for uid in self.server.channels[type][channel]:
					for uniq_conn in self.server.root.user_server.users[uid]:
						uniq_conn.send_message({self.push_type_minus[type]:channel})
			if status is 1:
				for uid in self.server.channels[type][channel]:
					for uniq_conn in self.server.root.user_server.users[uid]:
						uniq_conn.send_message({self.push_type_add[type]:channel})

		userOnline_query = "UPDATE TEMP_ONLINE SET online = %s WHERE uid = %s" % (status,ouid)
		self.server.mysql_cursor.execute( userOnline_query )
		self.server.mysql_conn.commit()

	def addCountGroup(self,gid_list):
		print "group up "
		add_query = "UPDATE GROUP_ONLINE SET count = count + 1 WHERE gid IN ( %s )" % (gid_list)
		self.server.mysql_cursor.execute( add_query )
		self.server.mysql_conn.commit()

	def minusCountGroup(self,gid_list):
		minus_query = "UPDATE GROUP_ONLINE SET count = case when count - 1 < 0 then 0 else count - 1 end  WHERE gid IN ( %s )" % (gid_list)
		self.server.mysql_cursor.execute( minus_query )
		self.server.mysql_conn.commit()

	def minusCountChannel(self,cid_list):
#		minus_query = "UPDATE TAP_ONLINE SET count = case when count - 1 < 0 then 0 else count - 1 end  WHERE cid IN ( %s )" % (cid_list)
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
				print self.uid
			else:
				return False
			self.state = 'connected'
			self.server.usernames[self.uid] = frame['uname']
			if not self.server.users.has_key(self.uid):
				self.server.users[self.uid] = []
				self.userOnline(self.uid,1)
				print "User %s online" % self.server.usernames[self.uid]
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
#				gids = '1' 
				gids = []

			if 'uids' in frame and frame['uids']:
				uids = self.make_unique(frame['uids'].split(',')).keys()
			else:
				print "BAM"
				uids = [self.uid]
		
			##This is a temp fix

			new_lists = {
				'channel' : cids,
				'group' :   gids,
				'user' :    uids
			}
			for type in new_lists.keys():
#				if not new_lists[type]: continue
				#typecast
#				new_lists[type] = [ unicode(x) for x  in new_lists[type] ]
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
				#		if uid != self.uid:
							for uniq_conn in self.server.root.user_server.users[uid]:
									uid_list_add.add(uniq_conn)
			if type == 'user': return False
			if channel_map_new != []:
				channel_map_string = self.join_string(channel_map_new)
				for uniq_conn in uid_list_add:
					uniq_conn.send_message({self.push_type_add[type]:channel_map_string})
				print "ADDING %s %s" % (type,channel_map_string)
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
			for uniq_conn in uid_list_minus:
				uniq_conn.send_message({self.push_type_minus[type]:channel_map_string})
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
				results = {
				'online?' :  "%s users online" % ( len(self.server.root.user_server.users) ),
				'online_per?' :  "%s" % ( self.connectionPerUser() ),
				'user_stats?' :  "%s" % ( self.userStats() )
				}[msg_data]
				self.users['admin'].send_message(results)
			except KeyError,e:
				print "Bad Command!"
			
	def send_message(self, data):
		self.conn.send("%s" % (data) + '\r\n')
		#JSON   self.conn.send(json.dumps({'adminData': data }) + '\r\n')
        

def clearViewers():
	#This is used to clear the viewers count in TAP_ONLINE,GROUP_ONLINE,TEMP_ONLINE so that when the server is
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

	clear_query = "UPDATE GROUP_ONLINE SET count = 0"
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
	#    except KeyboardInterrupt:
	except KeyboardInterrupt:
		clearViewers()
		print "Exiting Multi-User/Connection TCP server..."
