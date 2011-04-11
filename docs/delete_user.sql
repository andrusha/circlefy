set @uname="taso";
set @id = (select id from user where uname = @uname limit 1);

delete from conversations where user_id = @id or message_id in (select id from message where sender_id = @id or reciever_id = @id);
delete from reply where user_id = @id;
delete from message where sender_id = @id or reciever_id = @id;
delete from group_members where user_id = @id;
delete from friends where user_id = @id or friend_id = @id;
delete from notification_settings where user_id = @id;
delete from user where id = @id;
