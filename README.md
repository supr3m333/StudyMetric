# StudyMetric (XAMPP)

Built with HTML, CSS, JavaScript, PHP, and MySQL. No JSON files are used.

## XAMPP setup

1. Start **Apache** and **MySQL** from the XAMPP manager.
2. Copy this project folder into XAMPP's `htdocs` folder.
3. Open `http://localhost/phpmyadmin`.
4. Choose **Import**, select `database.sql`, and import it.
5. Visit `http://localhost/student-manager/index.html` (change `student-manager` if your folder has a different name).

Teacher administrator login: `teacher` / `teacher1`

Students create their own account from the **Student Register** option. Student passwords are securely hashed in the `users` SQL table.

The default connection in `database.php` is:

- Host: `127.0.0.1`
- Port: `3306`
- User: `root`
- Password: empty
- Database: `student_management`

If your XAMPP MySQL settings differ, update those values in `database.php`.
