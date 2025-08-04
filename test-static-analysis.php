<?php
/**
 * Test script to verify static analysis functionality
 */

// Define testing constant
define('WPMATCH_TESTING', true);

// Load the static analysis checker
require_once 'tests/static-analysis/php-syntax-checker.php';

echo "Testing Static Analysis Functionality\n";
echo "=====================================\n\n";

$base_dir = __DIR__;
$checker = new WPMatch_PHP_Syntax_Checker($base_dir);
$results = $checker->run_analysis();

echo "\nFINAL RESULTS:\n";
echo "Total files analyzed: " . $results['total_files'] . "\n";
echo "Total errors found: " . count($results['errors']) . "\n";
echo "Has errors: " . ($results['has_errors'] ? 'YES' : 'NO') . "\n";

if ($results['has_errors']) {
    exit(1);
} else {
    echo "\n✅ Static analysis completed successfully!\n";
    exit(0);
}