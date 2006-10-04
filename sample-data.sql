insert
into globaluser
  (gu_id,gu_name,gu_email,gu_email_authenticated,
   gu_salt,gu_password,gu_locked,gu_hidden,
   gu_registration)
values
  (1, 'Duderino', 'dude@localhost', '20060719012345',
   '34', MD5(CONCAT('34', '-', MD5('mycoolpass'))), 0, 0,
   '20060719012345');

