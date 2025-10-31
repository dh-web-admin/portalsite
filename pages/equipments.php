<?php
// Backwards-compatibility redirect: the plural page was renamed to singular.
// This keeps old links working while the site transitions.
header('Location: ../pages/equipment.php', true, 302);
exit();

