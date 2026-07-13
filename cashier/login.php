<?php
// Cashier login now delegates to the unified staff login page with cashier tab pre-selected
header('Location: /sweetheaven/admin/login.php?role=cashier');
exit;
