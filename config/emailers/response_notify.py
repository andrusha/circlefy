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
		l.email,
		sc.chat_text
		FROM special_chat AS sc 
		JOIN login AS l ON l.uid = sc.uid
		JOIN chat AS c ON c.cid = sc.cid
		WHERE sc.chat_timestamp > SUBTIME(NOW(),'900:00:00')
		GROUP BY l.uid
		'''	
		 

		self.mysql_cursor.execute( new_message_check_query )
		result_set = self.mysql_cursor.fetchall()
		old_uid = None
		email_group = {}
                for row in result_set:
                        email = row["email"]
			chat_text = row["chat_text"]

			group_content = chat_text

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
		email = 'tasoduv@gmail.com'
		print "SENDING EMAIL TO %s..." % email
		SENDMAIL = "/usr/sbin/sendmail" # sendmail location
		p = os.popen("%s -t" % SENDMAIL, "w")
		p.write("To: %s\n" % email)
		p.write("From: tap.info\n")
		p.write("Subject: Your groups have new taps!\n")
		p.write("\n") # blank line separating headers from body
		p.write("Hey!  Swine Flu is still ramping up this year and it seems people have been talking about it.  People in your tap community are tapping it up.  You have new responses for this new tap:\n")
		p.write("%s\n" % content)
		p.write("Keep connected in real-time with things you're interested in at http://tap.info , keep on tapping!\n-Team Tap")
		sts = p.close()
		print "EMAIL TO %s SENT!" % email
		time.sleep(5)
		if sts != 0:
		    print "Sendmail exit status", sts

notify_obj = Response_Notify()
notify_obj.get_users()
