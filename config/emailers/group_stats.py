import MySQLdb
import os
import sys
import time
#START of Response Server
class Response_Notify(object):
	def __init__(self):
		self.mysql_conn = False
		self.mysql_cursor = False

		try:
			self.mysql_conn = MySQLdb.connect (host = "localhost",user = "root",passwd = "root",db = "rewrite2")
		except MySQLdb.Error, e:
			print "Error %d: %s" % (e.args[0], e.args[1])
			sys.exit (1)
		self.mysql_cursor = self.mysql_conn.cursor (MySQLdb.cursors.DictCursor)

	def get_users(self):
		'''
		this query will find all the users who haven't logged in within 24 hours,
		then find all the newest messages in the past 24 hours,
		then send them an email with the group along with message count associated to that group
		'''
		new_message_check_query = '''
		SELECT 
		l.uid,l.uname,l.fname,l.lname,l.email,
		g.gname,
		gm.gid,
		oscm.count
		FROM login AS l 
		JOIN group_members AS gm ON gm.uid = l.uid
		JOIN ( 
			SELECT gid,COUNT(sc.mid) AS count FROM special_chat_meta AS scm
			JOIN special_chat AS sc ON scm.mid = sc.mid AND sc.chat_timestamp > SUBTIME(NOW(), '94:00:00')
			GROUP BY scm.gid
		) AS oscm ON gm.gid = oscm.gid
		JOIN groups AS g ON g.gid = oscm.gid
		WHERE l.last_login < SUBTIME(NOW(), '24:00:00')
		ORDER BY l.uid
		'''	
		 

		self.mysql_cursor.execute( new_message_check_query )
		result_set = self.mysql_cursor.fetchall()
		old_uid = None
		email_group = {}
                for row in result_set:
                        uid = row["uid"]
                        uname = row["uname"]
                        email = row["email"]
                        fname = row["fname"]
                        lname = row["lname"]
                        gid = row["gid"]
                        gname = row["gname"]
                        count = row["count"]

			group_content = '''
  Group: %(gname)s  - %(count)s new messages
			''' % { "count": count,"gname" : gname	}

			if not email_group.has_key(email):
				email_group.setdefault(email,[group_content])
			else:
				email_group[email].append(group_content)
	
		content = ''
		for email in email_group:
			for piece in email_group[email]:
				content += piece
			self.send_mail(content,email)
			content = ''

	def send_mail(self,content,email):
		print "SENDING EMAIL TO %s..." % email
		SENDMAIL = "/usr/sbin/sendmail" # sendmail location
		p = os.popen("%s -t" % SENDMAIL, "w")
		p.write("To: %s\n" % email)
		p.write("From: tap.info\n")
		p.write("Subject: Your groups have new taps!\n")
		p.write("\n") # blank line separating headers from body
		p.write("Hey!  Swine Flu is still ramping up this year and it seems people have been talking about it.  People in your tap community are tapping it up.  Here are some of the groups with new messages and members along with statistics about them:\n")
		p.write("%s\n" % content)
		p.write("Keep connected in real-time with things you're interested in at http://tap.info , keep on tapping!\n-Team Tap")
		sts = p.close()
		print "EMAIL TO %s SENT!" % email
		time.sleep(5)
		if sts != 0:
		    print "Sendmail exit status", sts

notify_obj = Response_Notify()
notify_obj.get_users()
