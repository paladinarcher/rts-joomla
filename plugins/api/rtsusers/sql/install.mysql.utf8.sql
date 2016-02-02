CREATE TABLE `#__rts_users`(  
  `user_id` int(11) NOT NULL,
  `rts_server` varchar(50) NOT NULL,
  `rts_user_id` int(11) NOT NULL,
  `creation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `server_user` (`rts_server`,`rts_user_id`),
  KEY `user_id` (`user_id`)
);