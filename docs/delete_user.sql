set @uname="taso";

set @id = (select id from user where uname = @uname limit 1);
delete from group_members where user_id = @id;
delete from friends where user_id = @id or friend_id = @id;
delete from user where id = @id;
