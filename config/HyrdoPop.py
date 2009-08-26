import socket
import random
import time
import sys
import MySQLdb
import random
import fcntl
import memcache
import simplejson as json
#
# Written By Taso Du Val
# 2009 tap
#

class chat_functions():
        online_id = []
        id_chan = []
	#Meteor Information
        HOST = '127.0.0.1'
        PORT = 4671
	#Separate Connections for Meteor
        s_adm = ''
        s_ctrl = ''
	offline_ids = []
	online_ids = []
	conn = ''
	cursor = ''
	
	#File Vars
	active_slices = 5
	offsets = []

        def __init__(self):
		#Initiated Separate Metoer Connections
                self.s_ctlr = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
                self.s_ctlr.connect((self.HOST, self.PORT))
                self.s_adm = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
                self.s_adm.connect((self.HOST, self.PORT))

		#Initiate MySQL Connection
		try:
			self.conn = MySQLdb.connect (host = "localhost",user = "root",passwd = "root",db = "rewrite2")
		except MySQLdb.Error, e:
			print "Error %d: %s" % (e.args[0], e.args[1])
			sys.exit (1)

		self.cursor = self.conn.cursor (MySQLdb.cursors.DictCursor)

		#When Process First Starts, Process to the Last Place in the file.  This is so it does not sound out old date.
		for slice in xrange(self.active_slices):
			file = "/var/data/flat/flat_%s" % (slice)
			f = open(file)
			f.seek(0,2)
			self.offsets.append( int(f.tell()) )
			f.close()

        def get_users(self):
		self.cursor.execute ("SELECT uid,cid,timeout,gids FROM TEMP_ONLINE")
		result_set = self.cursor.fetchall()
		for row in result_set:
			self.id_chan.insert(0,(int(row["uid"]),row["cid"],int(row["timeout"]),row["gids"] ))

        def loop_start(self):
		while(1):
			status_id = []
			time.sleep(1)
			self.get_users()
			for item in self.id_chan:
				self.s_adm.send('COUNTSUBSCRIBERS %s\n' % (item[1]))
				online_status = self.s_adm.recv(10)
				print online_status
				status_id.insert(0,(int(online_status.strip("\n").strip("\r").strip("OK")),item[0],item[2],item[3]) )
			self.offline_ids = [ (int(x[1]),x[2],x[3]) for x in status_id if x[0] == 0 ]
			self.online_ids = [ int(x[1]) for x in status_id if x[0] > 0 ]

			print "Updating Online ID's"
			online_users = []
			for online_user in self.online_ids:
				online_users.append("OR uid = %s" % (online_user))
				print "RESET"

			print "Offline_ids %s" %(self.offline_ids)
			updated_users = []
			del_users = []
			minus_gids = []
			del_state = 0
			for offline_user in self.offline_ids:
				if offline_user[1] != 20:
					updated_users.append("OR uid = %s" % (offline_user[0]))
					print "UPDATE"
				else:
					del_state = 1
					del_users.append("OR uid = %s" % (offline_user[0]))
					minus_gids.append("%s" % (offline_user[2]))
					print "DELETE"
	
			#Update Online Users ( Reset their timeout values )
			online_users = ' '.join(online_users)
			online_query = "UPDATE TEMP_ONLINE SET timeout = 0 WHERE uid = 0 %s" % ( online_users )
			self.cursor.execute( online_query )
			#Update Timeout Users
			updated_users = ' '.join(updated_users)
			update_query = "UPDATE TEMP_ONLINE SET timeout = timeout + 1 WHERE uid = 0 %s" % ( updated_users )
			self.cursor.execute( update_query )
			#Del Timeout Users
			del_users = ' '.join(del_users)
			del_query = "DELETE FROM TEMP_ONLINE WHERE uid = 0 %s" % ( del_users )
			self.cursor.execute( del_query )
			#For each group they're in , minus one online state
			if del_state:
				minus_gids = ','.join( minus_gids )
				minus_group_query = "UPDATE GROUP_ONLINE SET online = online-1 WHERE gid IN(%s)" % ( minus_gids )
				print minus_group_query
				self.cursor.execute( minus_group_query )

			'''	self.cursor.execute ("DELETE FROM TEMP_ONLINE WHERE uid = %s" % ( offline_user ) )
				print "DELETE FROM TEMP_ONLINE WHERE uid = %s" % ( offline_user )
				print "User %s Deleted" % ( offline_user )
			'''
			self.offline_ids = []
			self.id_chan = []

			self.conn.commit()
			self.get_users()
			print "Current Users: %s" % ( self.id_chan ) 

			matches_list = self.check_new_bits([x[0] for x in self.id_chan])

			if matches_list != []:
				results = self.bit_generator(matches_list)
				dict_id_chan = dict( [(int(items[0]),items[1]) for items in self.id_chan] )
				for item in results:
					print "FUCK IM SENDING %s %s %s %s" % ( dict_id_chan[item[0]], item[0], item[3], item[1] ) 
					self.s_ctlr.send('ADDMESSAGE %s %r\n' % (dict_id_chan[item[0]],json.dumps({"data": {item[1]: item[2]},"cid":item[3] })) )
					'''test =  json.dumps({"data": {item[1]: item[2]} })
					print test'''
			print "Current Users Online: %s" % ( len(self.id_chan) )
			self.id_chan = []

	def html_escape(self,text):
		text = text.replace('&', '&amp;')
		text = text.replace('"', '&quot;')
		text = text.replace("'", '&#39;')
		text = text.replace(">", '&gt;')
		text = text.replace("<", '&lt;')
		return text


	def check_new_bits(self,uid_list):
		matches_list = []
		c = 0
		for slice in xrange(self.active_slices):
			file = "/var/data/flat/flat_%s" % (slice)
			f = open(file)
			f.seek(self.offsets[slice])
			for row in f.readlines():

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
			#Slice Maintenance ( Clearing the slice files [ flat_X ] so they don't become to big, performance, etc )
			if int(f.tell()) > 20000:
				f.close()
				f2 = open(file,'w')
				fcntl.flock(f2.fileno(), fcntl.LOCK_EX|fcntl.LOCK_NB)
				f2.write(" ")
				self.offsets[slice] = int(f2.tell())
				print "Clearing Slice: file_%s" % (slice)
				f2.close()
			else:
				self.offsets[slice] = int(f.tell())
				f.close()

		return matches_list

	def bit_generator(self,matches_list):
		bits = []
		for tuples in matches_list:
			#Get row from memcache
			memc = memcache.Client(['127.0.0.1:11211'], debug=0)
			cid = "%s" % (tuples[1])
			print cid
			user_obj = json.loads(memc.get(cid))
			#Parse out data in useable manner
			uname = user_obj["uname"]
			fname = user_obj["fname"]
			lname = user_obj["lname"]
			chat_text = user_obj["chat_text"].replace("\\","")
			pic_100 = user_obj["pic_100"]
			fuid = user_obj["fuid"]
			for els in tuples:
				if type(els) == list:
					#Create HTML/bits based on types ( there are either one or two types) 
					for el in els:
#						print "Type: %s, uid: %s, cid: %s" % (el,tuples[0],tuples[1])
						row_type=el
						uid=tuples[0]
						cid=tuples[1]
						rand=random.randrange(1,99999)
						chat_timestamp = "Now!"

						#good part, I might want to remove this
						bits.append((uid,row_type,'''
						<div id="super_bit_%(cid)s_%(row_type)s_%(rand)s">
							<div class="bit %(color_class)s %(cid)s_bit" id="bit_%(cid)s_%(row_type)s_%(rand)s">

								<span class="bit_img_container"><img class="bit_img" src="user_pics/%(pic_100)s" /></span>
								<span class="bit_text">
									<a href="profile">%(uname)s</a> %(chat_text)s
								</span>
								<span class="bit_timestamp"><i>%(chat_timestamp)s</i></span>
								<ul class="bits_lists_options">
									<li class="0" id="good_%(cid)s_%(row_type)s" onclick="good(this,'%(cid)s','%(uid)s','%(fuid)s','%(row_type)s');"><img src="images/icons/thumb_up.png" /> <span class="bits_lists_options_text"></span></li>
									<li class="0" onclick="toggle_show_response('_%(cid)s_%(row_type)s_%(rand)s',this,0);"><img src="images/icons/comment.png" /> <span class="bits_lists_options_text"></span></li>
								</ul>

							</div>

							<div class="respond_text_area_div" id="respond_%(cid)s_%(row_type)s_%(rand)s">
							<ul>
								<li><textarea class="textarea_response gray_text" id="textarea_response_%(cid)s" onfocus="if (this.className[this.className.length-2] != '1') vanish_text('textarea_response',this);">Response..</textarea></li>
								<li><button>Send</button></li>
							</ul>

							</div>

								<ul class="bit_responses %(cid)s_resp" id="responses_%(cid)s_%(row_type)s_%(rand)s">
						''' % { 'cid': cid, 'uid':uid, 'fuid': fuid,'row_type':row_type,'rand':rand,'color_class':"blue",'chat_text':chat_text,'uname':uname,'pic_100':pic_100,'chat_timestamp': chat_timestamp },cid))

		return bits

something = chat_functions()
something.loop_start()
