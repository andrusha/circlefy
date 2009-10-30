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
		this query will find all messages that have received responses in the 
		last X hours and email the people letting them know they've gotten a response
		'''
		new_message_check_query = '''
		SELECT 
		l.email,l.uname,
		sc.chat_text,sc.cid
		FROM special_chat AS sc 
		JOIN login AS l ON l.uid = sc.uid
		JOIN chat AS c ON c.cid = sc.cid
		WHERE sc.chat_timestamp > SUBTIME(NOW(),'900:00:00')
		GROUP BY l.uid
		'''	
		 

		self.mysql_cursor.execute( new_message_check_query )
		result_set = self.mysql_cursor.fetchall()
		old_uid = None
		email_resp = {}
                for row in result_set:
                        email = row["email"]
                        uname = row["uname"]
                        cid = row["cid"]
			chat_text = row["chat_text"]

			email_resp.setdefault(email,[uname,chat_text,cid])
	
		content = ''
		for email in email_resp:
			u = email_resp[email]
			content += "%s : %s\n\n" % (u[0],u[1])
			content += "You can access your tap directly via http://tap.info/tap/%s" % (u[2])
			self.send_mail(content,email)
			content = ''

	def send_mail(self,content,email):
	#	email = 'tasoduv@gmail.com'
		to = "To: %s\n" % email
		sender = "From: tap.info\n"
		subject = "Subject: Your tap has new responses!\n"
		body = '''
Hey! You have new responses for the following tap:

%(content)s

You should also know we added public profiles, easier access to groups and group creation, and a lot more!  Check it out.

Keep connected in real-time with things you're interested in at http://tap.info , keep on tapping!

-Team Tap ''' % { 'content' : content }

		print "SENDING EMAIL TO %s..." % email
		SENDMAIL = "/usr/sbin/sendmail" # sendmail location
		p = os.popen("%s -t" % SENDMAIL, "w")
		p.write(to)
		p.write(sender)
		p.write(subject)
		p.write("\n") # blank line separating headers from body
		p.write(body)
		sts = p.close()
		print "EMAIL TO %s SENT!" % email
		time.sleep(5)
		if sts != 0:
		    print "Sendmail exit status", sts

notify_obj = Response_Notify()
notify_obj.get_users()
