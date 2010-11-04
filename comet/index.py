#!/usr/bin/env python
# ____________________
#/ Written by         \
#|   Andrew Korzhuev  |
#\      2010 Tap     /
# -------------------
#        \   ^__^
#         \  (oo)\_______
#            (__)\       )\/\
#                ||----w |
#                ||     ||

import sys
import logging
import eventlet
import MySQLdb
from optparse import OptionParser
from eventlet.green import time
from Comet.User import UserServer
from Comet.Events import EventServer, EventDispatcher
from Comet.Pinger import Pinger
from Comet.Cassandra import Cassandra

# cassandra threading workaround
# eventlet.monkey_patch(thread=True)

def clearViewers(mysql_conn):
    mysql_cursor = mysql_conn.cursor (MySQLdb.cursors.DictCursor)
    for table, field in [('user', 'online'), ('group', 'online_count')]:
        clear_query = "UPDATE `%s` SET %s = 0" % (table, field)
        mysql_cursor.execute(clear_query)
        mysql_conn.commit()

def main(mysql, cassandra):
    user_server      = UserServer(mysql)
    event_dispatcher = EventDispatcher(mysql, cassandra, user_server)
    event_server     = EventServer(event_dispatcher)
    pinger           = Pinger(mysql, user_server)
    
    eventlet.spawn(user_server)
    eventlet.spawn(event_server)
    eventlet.spawn(pinger)

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        clearViewers(mysql)
    
if __name__ == "__main__":
    usage = 'Usage: %prog [options]'
    parser = OptionParser(usage)
    parser.add_option('-d', '--debug', action='store_true', dest='debug', 
                      help='shows debug output in console')
    parser.add_option('--init', action='store_true', dest='init', 
                      help='initialize Cassandra tables')
    (options, args) = parser.parse_args()

    log_format  = '%(asctime)s %(levelname)s: %(message)s'
    log_datefmt = '%H:%M:%S'
    outfile     = '/var/log/tap-server.log' if not options.debug else None
    logging.basicConfig(level = logging.DEBUG, filename = outfile,
        format = log_format, datefmt = log_datefmt)

    mysql = MySQLdb.connect (host = "localhost", user = "root", passwd = "root", db = "circlefy")
    mysql.cursor().execute('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED')
    cassandra = Cassandra('circlefy')

    if options.init:
        clearViewers(mysql)
        cassandra.initTables(mysql)
        sys.exit(1)

    logging.info("Starting Comet-server...")
    main(mysql, cassandra)
    logging.info("Exiting Comet-server...")
    sys.exit(1)
