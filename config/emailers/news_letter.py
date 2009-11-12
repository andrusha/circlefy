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
		l.email,l.uname,l.fname,l.lname
		FROM login AS l
		'''	
		 

		self.mysql_cursor.execute( new_message_check_query )
		result_set = self.mysql_cursor.fetchall()
		old_uid = None
		email_group = {}
                for row in result_set:
                        email = row["email"]
			content = '''
			This is a sample news letter.  Fill in the content here for a new one!
			

			'''
			self.send_mail(content,email)

	def send_mail(self,content,email):
		email = 'tasoduv@gmail.com'
		to = "To: %s\n" % (email)
		content = "%s\n" % (content)
		sender = "From: tap.info\n"
		subject = "Subject: Lot's of new features on tap!\n"

		print "SENDING EMAIL TO %s..." % email
		SENDMAIL = "/usr/sbin/sendmail" # sendmail location
		p = os.popen("%s -t" % SENDMAIL, "w")
		p.write(to)
		p.write(sender)
		p.write(subject)
		p.write("\n") # blank line separating headers from body
		p.write(content)
		sts = p.close()
		print "EMAIL TO %s SENT!" % email
		time.sleep(5)
		if sts != 0:
		    print "Sendmail exit status", sts

notify_obj = Response_Notify()
notify_obj.get_users()
