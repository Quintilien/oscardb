<?php
// OscarDB — the web root should be public/ (see README).
// This redirect only exists in case the whole folder was put in the web
// root; .htaccess then protects the rest (Apache only).
header('Location: public/');
exit;
