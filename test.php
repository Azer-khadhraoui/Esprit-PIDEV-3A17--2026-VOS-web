<?php

require 'vendor/autoload.php';

echo "autoload ok\n";

try {

    $k = new App\Kernel('dev', true);

    echo "kernel ok\n";

} catch (Exception $e) {

    echo "error: " . $e->getMessage() . "\n";

}