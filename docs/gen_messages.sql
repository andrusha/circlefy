CREATE TEMPORARY TABLE IF NOT EXISTS t_words(word VARCHAR(255) NOT NULL);
TRUNCATE TABLE t_words;
INSERT INTO t_words (word) VALUES('Twas'),('brillig'), ('and'),('the'),('slithy'),('toves'),('Did'),('gyre'), ('and'), ('gimble'),('in'),('the'), ('wabe'),('All'),('mimsy'),('were'),('the'),('borogoves'),('And'),('the'),('mome'),('raths'),('outgrabe');

INSERT INTO message (sender_id, text, group_id)
SELECT (SELECT id FROM user ORDER BY RAND() LIMIT 1) AS sender_id,
 	 (SELECT GROUP_CONCAT(word ORDER BY RAND() SEPARATOR ' ') FROM t_words) AS text,
	 (SELECT id FROM `group` ORDER BY RAND() LIMIT 1) AS group_id;
INSERT INTO message (sender_id, text, group_id)
SELECT (SELECT id FROM user ORDER BY RAND() LIMIT 1) AS sender_id,
 	 (SELECT GROUP_CONCAT(word ORDER BY RAND() SEPARATOR ' ') FROM t_words) AS text,
	 (SELECT id FROM `group` ORDER BY RAND() LIMIT 1) AS group_id;

INSERT INTO reply (user_id, text, message_id)
SELECT (SELECT id FROM user ORDER BY RAND() LIMIT 1) AS user_id,
 	 (SELECT GROUP_CONCAT(word ORDER BY RAND() SEPARATOR ' ') FROM t_words) AS text,
	 (SELECT id FROM message ORDER BY RAND() LIMIT 1) AS message_id;
INSERT INTO reply (user_id, text, message_id)
SELECT (SELECT id FROM user ORDER BY RAND() LIMIT 1) AS user_id,
 	 (SELECT GROUP_CONCAT(word ORDER BY RAND() SEPARATOR ' ') FROM t_words) AS text,
	 (SELECT id FROM message ORDER BY RAND() LIMIT 1) AS message_id;
INSERT INTO reply (user_id, text, message_id)
SELECT (SELECT id FROM user ORDER BY RAND() LIMIT 1) AS user_id,
 	 (SELECT GROUP_CONCAT(word ORDER BY RAND() SEPARATOR ' ') FROM t_words) AS text,
	 (SELECT id FROM message ORDER BY RAND() LIMIT 1) AS message_id;
INSERT INTO reply (user_id, text, message_id)
SELECT (SELECT id FROM user ORDER BY RAND() LIMIT 1) AS user_id,
 	 (SELECT GROUP_CONCAT(word ORDER BY RAND() SEPARATOR ' ') FROM t_words) AS text,
	 (SELECT id FROM message ORDER BY RAND() LIMIT 1) AS message_id;
INSERT INTO reply (user_id, text, message_id)
SELECT (SELECT id FROM user ORDER BY RAND() LIMIT 1) AS user_id,
 	 (SELECT GROUP_CONCAT(word ORDER BY RAND() SEPARATOR ' ') FROM t_words) AS text,
	 (SELECT id FROM message ORDER BY RAND() LIMIT 1) AS message_id;
INSERT INTO reply (user_id, text, message_id)
SELECT (SELECT id FROM user ORDER BY RAND() LIMIT 1) AS user_id,
 	 (SELECT GROUP_CONCAT(word ORDER BY RAND() SEPARATOR ' ') FROM t_words) AS text,
	 (SELECT id FROM message ORDER BY RAND() LIMIT 1) AS message_id;
INSERT INTO reply (user_id, text, message_id)
SELECT (SELECT id FROM user ORDER BY RAND() LIMIT 1) AS user_id,
 	 (SELECT GROUP_CONCAT(word ORDER BY RAND() SEPARATOR ' ') FROM t_words) AS text,
	 (SELECT id FROM message ORDER BY RAND() LIMIT 1) AS message_id;
INSERT INTO reply (user_id, text, message_id)
SELECT (SELECT id FROM user ORDER BY RAND() LIMIT 1) AS user_id,
 	 (SELECT GROUP_CONCAT(word ORDER BY RAND() SEPARATOR ' ') FROM t_words) AS text,
	 (SELECT id FROM message ORDER BY RAND() LIMIT 1) AS message_id;
DROP TABLE t_words;