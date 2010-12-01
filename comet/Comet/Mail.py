import MySQLdb

str_null = lambda x: str(x) if x is not None else 'NULL'

class Mailer(object):
    def __init__(self, mysql):
        self.mysql = mysql
        self.types = {'new_personal': 3, 'new_follower': 4, 'new_message': 5, 'new_reply': 6}

    def queue(self, reciever, etype, user_id = None, message_id = None, group_id = None, reply_id = None):
        "Adds email message to email queue"
       
        etype = self.types[etype]
        make_str = lambda x: map(str_null, [etype, x, user_id, message_id, group_id, reply_id])
        if type(reciever) is not set:
            values = ', '.join(make_str(reciever))
        else:
            values = (', '.join(make_str(x)) for x in reciever)
            values = '),('.join(values)

        query = 'INSERT INTO email_queue ' + \
                '(type, reciever_id, user_id, message_id, group_id, reply_id) VALUES (%s)' % values
        self.mysql.cursor().execute(query)
        self.mysql.commit()
