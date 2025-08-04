<?php
/**
 * Static Analysis Test Class
 * 
 * Tests for code quality, syntax errors, and redeclaration issues
 *
 * @package WPMatch
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;

class StaticAnalysisTest extends TestCase {
    
    /**
     * Test for PHP syntax errors across all files
     */
    public function test_php_syntax_errors() {
        $base_dir = dirname(dirname(__DIR__));
        
        // Load the syntax checker
        require_once dirname(__FILE__) . '/php-syntax-checker.php';
        
        $checker = new WPMatch_PHP_Syntax_Checker($base_dir);
        $results = $checker->run_analysis();
        
        // Check for syntax errors specifically
        $syntax_errors = array_filter($results['errors'], function($error) {
            return $error['type'] === 'SYNTAX_ERROR';
        });
        
        if (!empty($syntax_errors)) {
            $error_messages = [];
            foreach ($syntax_errors as $error) {
                $error_messages[] = "{$error['file']}: {$error['message']}";
            }
            $this->fail("PHP syntax errors detected:\n" . implode("\n", $error_messages));
        }
        
        $this->assertTrue(true, "No PHP syntax errors detected");
    }
    
    /**
     * Test for function/method redeclaration errors
     * 
     * CRITICAL: This test MUST detect the get_dashboard_stats() redeclaration
     */
    public function test_function_redeclaration_errors() {
        $base_dir = dirname(dirname(__DIR__));
        
        // Load the syntax checker
        require_once dirname(__FILE__) . '/php-syntax-checker.php';
        
        $checker = new WPMatch_PHP_Syntax_Checker($base_dir);
        $results = $checker->run_analysis();
        
        // Check for redeclaration errors specifically
        $redeclaration_errors = array_filter($results['errors'], function($error) {
            return $error['type'] === 'REDECLARATION_ERROR';
        });
        
        if (!empty($redeclaration_errors)) {
            $error_messages = [];
            foreach ($redeclaration_errors as $error) {
                $error_messages[] = $error['message'];
                if (isset($error['details'])) {
                    foreach ($error['details'] as $detail) {
                        $error_messages[] = "  - {$detail['name']} in {$detail['file']}:{$detail['line']}";
                    }
                }
            }
            
            // This test should FAIL if redeclaration errors are found
            $this->fail("Function/method redeclaration errors detected:\n" . implode("\n", $error_messages));
        }
        
        $this->assertTrue(true, "No function/method redeclaration errors detected");
    }
    
    /**
     * Test for class redeclaration errors
     */
    public function test_class_redeclaration_errors() {
        $base_dir = dirname(dirname(__DIR__));
        
        // Load the syntax checker
        require_once dirname(__FILE__) . '/php-syntax-checker.php';
        
        $checker = new WPMatch_PHP_Syntax_Checker($base_dir);
        $results = $checker->run_analysis();
        
        // Check for class redeclaration errors
        $class_redeclaration_errors = array_filter($results['errors'], function($error) {
            return $error['type'] === 'CLASS_REDECLARATION_ERROR';
        });
        
        if (!empty($class_redeclaration_errors)) {
            $error_messages = [];
            foreach ($class_redeclaration_errors as $error) {
                $error_messages[] = $error['message'];
                if (isset($error['details'])) {
                    foreach ($error['details'] as $detail) {
                        $error_messages[] = "  - {$detail['name']} in {$detail['file']}:{$detail['line']}";
                    }
                }
            }
            
            $this->fail("Class redeclaration errors detected:\n" . implode("\n", $error_messages));
        }
        
        $this->assertTrue(true, "No class redeclaration errors detected");
    }
    
    /**
     * Test for security risks in code
     */
    public function test_security_risks() {
        $base_dir = dirname(dirname(__DIR__));
        
        // Load the syntax checker
        require_once dirname(__FILE__) . '/php-syntax-checker.php';
        
        $checker = new WPMatch_PHP_Syntax_Checker($base_dir);
        $results = $checker->run_analysis();
        
        // Check for high severity security risks
        $security_risks = array_filter($results['errors'], function($error) {
            return $error['type'] === 'SECURITY_RISK' && 
                   in_array($error['severity'], ['HIGH', 'FATAL']);
        });
        
        if (!empty($security_risks)) {
            $error_messages = [];
            foreach ($security_risks as $error) {
                $location = $error['file'];
                if (isset($error['line'])) {
                    $location .= ":{$error['line']}";
                }
                $error_messages[] = "[{$error['severity']}] {$error['message']} in $location";
            }
            
            $this->fail("High severity security risks detected:\n" . implode("\n", $error_messages));
        }
        
        $this->assertTrue(true, "No high severity security risks detected");
    }
    
    /**
     * Test for WordPress coding standards violations
     */
    public function test_wordpress_coding_standards() {
        $base_dir = dirname(dirname(__DIR__));
        $violations = [];
        
        // Check for direct database queries without $wpdb->prepare
        $php_files = $this->get_php_files($base_dir);
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            $relative_path = str_replace($base_dir . '/', '', $file);
            
            // Skip test files
            if (strpos($relative_path, 'tests/') === 0) {
                continue;
            }
            
            $lines = explode("\n", $content);
            
            foreach ($lines as $line_number => $line) {
                $line_number++; // 1-based
                
                // Check for unprepared queries
                if (preg_match('/\$wpdb->(query|get_var|get_results|get_row|get_col)\s*\(\s*["\']/', $line)) {
                    if (strpos($line, '$wpdb->prepare') === false) {
                        $violations[] = [
                            'file' => $relative_path,
                            'line' => $line_number,
                            'message' => 'Direct database query without $wpdb->prepare()',
                            'code' => trim($line)
                        ];
                    }
                }
                
                // Check for missing capability checks on admin actions
                if (preg_match('/wp_ajax_[a-zA-Z0-9_]+/', $line) && 
                    !preg_match('/current_user_can\s*\(/', $content)) {
                    $violations[] = [
                        'file' => $relative_path,
                        'line' => $line_number,
                        'message' => 'AJAX handler without capability check',
                        'code' => trim($line)
                    ];
                }
            }
        }
        
        if (!empty($violations)) {
            $error_messages = [];
            foreach (array_slice($violations, 0, 10) as $violation) { // Limit output
                $error_messages[] = "{$violation['file']}:{$violation['line']} - {$violation['message']}";
            }
            
            if (count($violations) > 10) {
                $error_messages[] = "... and " . (count($violations) - 10) . " more violations";
            }
            
            $this->fail("WordPress coding standards violations detected:\n" . implode("\n", $error_messages));
        }
        
        $this->assertTrue(true, "No critical WordPress coding standards violations detected");
    }
    
    /**
     * Test that all required WordPress files exist
     */
    public function test_required_files_exist() {
        $base_dir = dirname(dirname(__DIR__));
        
        $required_files = [
            'wpmatch.php',
            'admin/class-admin.php',
            'admin/class-profile-fields-admin.php',
            'admin/class-profile-fields-list-table.php',
            'includes/class-profile-field-manager.php',
            'includes/class-database.php',
            'includes/class-activator.php',
            'includes/class-deactivator.php'
        ];
        
        $missing_files = [];
        
        foreach ($required_files as $file) {
            $full_path = $base_dir . '/' . $file;
            if (!file_exists($full_path)) {
                $missing_files[] = $file;
            }
        }
        
        if (!empty($missing_files)) {
            $this->fail("Required files are missing:\n" . implode("\n", $missing_files));
        }
        
        $this->assertTrue(true, "All required files exist");
    }
    
    /**
     * Test that all PHP files have proper opening tags
     */
    public function test_proper_php_opening_tags() {
        $base_dir = dirname(dirname(__DIR__));
        $php_files = $this->get_php_files($base_dir);
        $violations = [];
        
        foreach ($php_files as $file) {
            $relative_path = str_replace($base_dir . '/', '', $file);
            
            // Skip test files
            if (strpos($relative_path, 'tests/') === 0) {
                continue;
            }
            
            $content = file_get_contents($file);
            
            // Check for proper PHP opening tag
            if (!preg_match('/^<\?php/', $content)) {
                $violations[] = $relative_path;
            }
            
            // Check for closing PHP tags in files (should not have them)
            if (preg_match('/\?\>\s*$/', $content)) {
                $violations[] = "$relative_path (has closing PHP tag)";
            }
        }
        
        if (!empty($violations)) {
            $this->fail("PHP opening/closing tag violations:\n" . implode("\n", $violations));
        }
        
        $this->assertTrue(true, "All PHP files have proper opening tags");
    }
    
    /**
     * Get all PHP files in the project
     *
     * @param string $base_dir Base directory
     * @return array List of PHP file paths
     */
    private function get_php_files($base_dir) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $path = $file->getPathname();
                if (strpos($path, '/vendor/') === false && 
                    strpos($path, '/node_modules/') === false &&
                    strpos($path, '/.git/') === false) {
                    $files[] = $path;
                }
            }
        }
        
        return $files;
    }
}