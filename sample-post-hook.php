<?php
/*
    $data['hook_data'] =>Data received from git repo 
    $data['project']   = Project details from database
    $data['hook']      = Hook type pre or post
    $data['status']    = Hook completion status , 1=Success, 0=Failed
*/

/*
    this sample hook will update db details in app/settings.php 
*/

$data                   = @json_decode(file_get_contents('php://stdin'), 1);
$settings               = require $data['project']['path'] . "/app/settings.php";
$settings['settings']['db']['db'] = 'admin_new';
$settings['settings']['db']['user'] = 'admin_new';
$settings['settings']['db']['pass'] = 'mypass';

file_put_contents($data['project']['path'] . "/app/settings.php", '<?php' . "\n return " . var_export($settings, 1) . ";");
