<?php
$data=@json_decode(file_get_contents('php://stdin'),1);
file_put_contents(__DIR__ . "/out_".$data['hook'].".txt", json_encode($data));

