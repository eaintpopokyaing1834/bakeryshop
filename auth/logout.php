<?php
session_start();
session_unset();
session_destroy();
header('Location: /sweetheaven/user/index.php');
exit;
