<?php
// Root index - redirect to login page using a relative path
// Works on both local (http://localhost/PortalSite/) and production (domain root)
header('Location: auth/login.php');
exit;
?>
