<?php
// FlightDeck OS - Stratos Dispatch Module Update
ini_set('display_errors', 1);
error_reporting(E_ALL);

$root = dirname(__DIR__);
$core = $root . '/fdos-core';
$routes = $core . '/routes';
$web = $routes . '/web.php';
$include = $routes . '/stratos_dispatch_module_routes.php';
$source = __DIR__ . '/../fdos-core/routes/stratos_dispatch_module_routes.php';

echo "<pre>FlightDeck OS - Stratos Dispatch Module Update\n";

if (!is_dir($routes)) {
    echo "ERROR: fdos-core/routes folder not found.\n";
    exit;
}

if (!file_exists($source)) {
    echo "ERROR: Source route file missing from update package.\n";
    exit;
}

if (!copy($source, $include)) {
    echo "ERROR: Could not copy route file. Manually copy fdos-core/routes/stratos_dispatch_module_routes.php into fdos-core/routes/.\n";
    exit;
}

echo "Installed route file: fdos-core/routes/stratos_dispatch_module_routes.php\n";

if (!file_exists($web)) {
    echo "ERROR: fdos-core/routes/web.php not found. Route file copied but include was not added.\n";
    exit;
}

$contents = file_get_contents($web);
$needle = "stratos_dispatch_module_routes.php";

if (strpos($contents, $needle) === false) {
    $backup = $web . '.backup-stratos-dispatch-module-' . date('YmdHis');
    copy($web, $backup);
    echo "Backup created: {$backup}\n";

    $block = "\n\n// === FLIGHTDECK OS STRATOS DISPATCH MODULE ROUTES START ===\n";
    $block .= "if (file_exists(__DIR__ . '/stratos_dispatch_module_routes.php')) { require_once __DIR__ . '/stratos_dispatch_module_routes.php'; }\n";
    $block .= "// === FLIGHTDECK OS STRATOS DISPATCH MODULE ROUTES END ===\n";

    if (file_put_contents($web, $contents . $block) === false) {
        echo "ERROR: Could not append include to web.php. Add this manually:\n";
        echo htmlentities($block) . "\n";
        exit;
    }

    echo "Route include appended to fdos-core/routes/web.php\n";
} else {
    echo "Route include already exists in web.php\n";
}

echo "\nUpdate complete. Test:\n";
echo "\n/api/stratos/dispatch/current";
echo "\n/api/stratos/dispatch/briefing?booking_id=YOUR_BOOKING_ID";
echo "</pre>";
