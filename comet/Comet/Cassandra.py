import logging
import struct
import pycassa
import MySQLdb
from pycassa import ColumnFamily
from pycassa.cassandra.ttypes import NotFoundException
from collections import defaultdict

# See:
# http://svn.apache.org/viewvc/cassandra/trunk/interface/cassandra.thrift?view=markup
# http://github.com/pycassa/pycassa/blob/master/pycassa/cassandra/Cassandra.py
# 
# For direct-api calls, like dynamic column creation\update:
#
#    from pycassa.cassandra.ttypes import CfDef, ColumnDef, IndexType
#
#    conn.system_add_column_family(CfDef(
#           name     = 'groups',
#           keyspace = keyspace,
#           comparator_type          = 'TimeUUIDType',
#           column_metadata = [
#               ColumnDef(
#                   name             = 'message_id', 
#                   validation_class = 'BytesType',
#                   index_type       = IndexType.KEYS),
#               ],
#           comment = 'group events, private & public as well'))

# use 8-byte long as identifier
#def pack(integer):
#    return struct.pack('l', integer)
#
#def unpack(bytestr):
#    return struct.unpack('l', bytestr)

pack   = str
unpack = int

class defaultdict_extended(defaultdict):
    def __missing__(self, key):
        self[key] = value = self.default_factory(key) 
        return value

class Cassandra():
    def __init__(self, keyspace, server = 'localhost:9160'):
        self.connection = pycassa.connect(keyspace, [server])
        self.families = defaultdict_extended(lambda family: ColumnFamily(self.connection, family)) 

    def initTables(self, mysql):
        self.initFromDB(mysql, 'SELECT group_id, user_id FROM group_members', 
            'group_members', 'group_id', 'user_id')

        self.initFromDB(mysql, 'SELECT group_id, user_id FROM group_members', 
            'inverted_members', 'user_id', 'group_id')

        self.initFromDB(mysql, 'SELECT message_id, user_id FROM conversations',
            'convo_followers',  'message_id', 'user_id')

    def initFromDB(self, mysql, sql, family, id_name, val_name):
       cursor = mysql.cursor(MySQLdb.cursors.DictCursor)
       cursor.execute(sql)
       inserting = defaultdict(dict)
       for row in cursor.fetchall():
           val = row[val_name]
           id  = pack(row[id_name])
           inserting[id][val] = val 
       fam = ColumnFamily(self.connection, family)
       fam.truncate()
       logging.info('Initializing %s' % family)
       fam.batch_insert(inserting)

    def insert(self, family, key, *args, **kwargs):
        self.families[family].insert(pack(key), *args, **kwargs)

    def batch_insert(self, family, *args, **kwargs):
        self.families[family].batch_insert(*args, **kwargs)

    def remove(self, family, key, *args, **kwargs):
        self.families[family].remove(pack(key), *args, **kwargs)

    def get(self, family, key, *args, **kwargs):
        try:
            return self.families[family].get(pack(key), *args, **kwargs)
        except NotFoundException:
            return None
