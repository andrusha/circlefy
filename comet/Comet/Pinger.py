# Pings users & mysql connection
# to bypass timeouts

import logging
from eventlet.green import time

class Pinger():
    def __init__(self, mysql, user_server):
        self.mysql = mysql
        self.user_server = user_server
    
    def __call__(self):
        logging.info('Starting pinger')
        while True:
            time.sleep(60)
            self.ping()
    
    def ping(self):
        logging.info("Client ping")
        for uid in self.user_server.users:
            logging.info("Ping %s (%i)" % (self.user_server.usernames[uid], uid))
            for uniq_conn in self.user_server.users[uid]:
                uniq_conn.send_message('ping', {})

        logging.info("MySQL Ping")
        self.mysql.ping()
 
