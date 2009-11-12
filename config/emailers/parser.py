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
		file = open('freenode.txt.final')
		
		for line in file:
			split_col = line.split('SPACE_TOKEN_HERE')
			gname = split_col[0]
			try:
				descr = split_col[1]
			except Exception:
				pass
			q = 'INSERT INTO groups(gname,descr,connected) values("%s","%s",3)' % (gname,descr)
			try:
				self.mysql_cursor.execute(q)
				self.mysql_conn.commit()
			except Exception:
				pass
			print q
			
			


notify_obj = Response_Notify()
notify_obj.get_users()
