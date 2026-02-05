-- Reset admin account with valid password hash
-- Password: admin
DELETE FROM admins WHERE username = 'admin';
INSERT INTO admins (username, email, password_hash, role, status) 
VALUES ('admin', 'admin@skalinga.local', '$2y$10$Imu974feGgEPCKQERTi0Fehev60Hd9sOE93M3Cm3rZ26qWDzKV.hm', 'superadmin', 'active');
