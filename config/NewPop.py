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
            c = MessageConnection(self,*server.accept())
            api.spawn(c)

class MessageConnection(object):
    
    def __init__(self,server, conn, addr):
	print server, conn, addr
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
            rawFrame, self.buffer = self.buffer.split('\r\n', 1)
            print 'json parse:', rawFrame
	    try:
	            frame = json.loads(rawFrame)
	    except(Exception):
		    continue

            self.receivedFrame(frame)

    def receivedFrame(self, frame):
        msg_data = frame['msg'].split('\n')
	matches_list = self.check_new_bits(self.server.root.user_server.users,msg_data)

	if matches_list != []:
		results = self.bit_generator(matches_list)
		print "RESULTS: %s" % ( results ) 
		for item in results:
			print "sending"

		for uid in self.uids:
		    if uid in self.server.root.user_server.users:
			self.server.root.user_server.users[uid].send_message(results)
		self.uids = []
	
    def bit_generator(self,matches_list):
		bits = []
		print matches_list
		for tuples in matches_list:
			#Get row from memcache
#			memc = memcache.Client(['127.0.0.1:11211'], debug=0)
			cid = "%s" % (tuples[1])
#			user_obj = json.loads(memc.get(cid))
			#Parse out data in useable manner
#			uname = user_obj["uname"]
#			fname = user_obj["fname"]
#			lname = user_obj["lname"]
#			chat_text = user_obj["chat_text"].replace("\\","")
#			pic_100 = user_obj["pic_100"]
#			fuid = user_obj["fuid"]
			uname = 'Taso'
			fname = 'Taso'
			lname = 'Duval'
			chat_text = 'Hey guys testing!!!'
			pic_100 = 'ohjoifj4874848_picture.gif'
			fuid = 100110101
			print tuples
			for els in tuples:
				if type(els) == list:
					#Create HTML/bits based on types ( there are either one or two types) 
					for el in els:
						row_type=el
						uid=tuples[0]
						cid=tuples[1]
						rand=random.randrange(1,99999)
						chat_timestamp = "Now!"
						bits.append({
						  'cid': cid,
						  'uid':uid,
						  'fuid': fuid,
						  'row_type':row_type,
						  'rand':rand,
						  'color_class':"blue",
						  'chat_text':chat_text,
						  'uname':uname,
						  'pic_100':pic_100,
						  'chat_timestamp': chat_timestamp
						 })
			print "BITS %s" % (bits)
			return bits


    def check_new_bits(self,uid_list,msg_data):
		matches_list = []
		print msg_data
		print uid_list
		for row in msg_data:
				print "ROW: %s\n" %(row)
				#FIGURE OUT WHICH LINES MATCH
				col = row.split(' ')
				col[0] = col[0].strip("\n")
				if col[0]:
					col = [ int(x) for x in col ]
					uid = col[0]
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
				if len(row) > 4:
					uid = col[0]
					cid = col[1]
					gid = col[2]
					rid = col[3]
					fuid =col[4]
					row_type=col[5]
					print "TYPE: %s" % (row_type)

					#Group Processing
					if row_type == 0:
						if group_hit != 0:
							groups=False
							matches_list.insert(0,(uid,cid,["groups","group_%s" % ( gid )]))
							continue

                                        #Friend Processing
                                        if row_type == 1:
	                                        if friend_hit != 0:
							friends=False
                                                	matches_list.insert(0,(uid,cid,["friends","friend_%s" % ( fuid )]))
							continue

                                        #Direct Processing
                                        if row_type == 2:
                                                matches_list.insert(0,(uid,cid,["direct"]))
						continue

                                        #Direct Processing
                                        if row_type == 4:
                                                matches_list.insert(0,(uid,cid,["building"]))
						continue

                                        #Filter Processing
                                        if row_type == 3:
                                                if rel_hit != 0:
							rel=False
	                                                matches_list.insert(0,(uid,cid,["rel","tab_rel_%s" % ( rid )] ))
							continue
		return matches_list

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
            del self.server.users[self.uid]
            # TODO: on disconnect
            # ...
	    #I should probably run some querieis here and whatnot

    def dispatchBuffer(self):
        while '\n' in self.buffer:
            rawFrame, self.buffer = self.buffer.split('\r\n', 1)
	    try:
		frame = json.loads(rawFrame)
	    except(Exception):
		continue

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
