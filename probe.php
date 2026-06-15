<?php
// Simulate a real GET /login request through the HTTP kernel.
$kernel = app(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::create('/login', 'GET');

try {
    $response = $kernel->handle($request);
    echo "STATUS: ".$response->getStatusCode()."\n";
    if ($response->getStatusCode() >= 500) {
        echo substr(strip_tags($response->getContent()), 0, 600)."\n";
    }
} catch (\Throwable $e) {
    echo get_class($e).': '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
    foreach (array_slice($e->getTrace(), 0, 5) as $t) {
        echo '  at '.($t['class'] ?? '').($t['type'] ?? '').($t['function'] ?? '').' '.basename($t['file'] ?? '').':'.($t['line'] ?? '')."\n";
    }
}
