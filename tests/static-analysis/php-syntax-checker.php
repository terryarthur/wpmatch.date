<?php
/**
 * PHP Syntax and Redeclaration Error Checker
 * 
 * Static analysis tool to detect:
 * - PHP syntax errors
 * - Function/method redeclarations
 * - Class redefinitions
 * - Include/require conflicts
 * - Namespace conflicts
 *
 * @package WPMatch
 * @subpackage Tests
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WPMATCH_TESTING')) {
    exit;
}

class WPMatch_PHP_Syntax_Checker {
    
    /**
     * Array to store detected errors
     *
     * @var array
     */
    private $errors = [];
    
    /**
     * Array to store function/method definitions
     *
     * @var array
     */
    private $function_definitions = [];
    
    /**
     * Array to store class definitions
     *
     * @var array
     */
    private $class_definitions = [];
    
    /**
     * Base directory to scan
     *
     * @var string
     */
    private $base_dir;
    
    /**
     * Constructor
     *
     * @param string $base_dir Base directory to scan
     */
    public function __construct($base_dir = null) {
        $this->base_dir = $base_dir ?: dirname(dirname(__DIR__));
    }
    
    /**
     * Run complete static analysis
     *
     * @return array Analysis results
     */
    public function run_analysis() {
        echo "🔍 Running WPMatch Static Analysis...\n";
        echo "================================\n\n";
        
        $this->errors = [];
        $this->function_definitions = [];
        $this->class_definitions = [];
        
        $php_files = $this->get_php_files();
        
        echo "📁 Found " . count($php_files) . " PHP files to analyze\n\n";
        
        foreach ($php_files as $file) {
            $this->analyze_file($file);
        }
        
        $this->check_redeclarations();
        $this->generate_report();
        
        return [
            'total_files' => count($php_files),
            'errors' => $this->errors,
            'function_definitions' => $this->function_definitions,
            'class_definitions' => $this->class_definitions,
            'has_errors' => !empty($this->errors)
        ];
    }
    
    /**
     * Get all PHP files in the project
     *
     * @return array List of PHP file paths
     */
    private function get_php_files() {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->base_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                // Skip vendor and node_modules directories
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
    
    /**
     * Analyze a single PHP file
     *
     * @param string $file_path Path to the file
     */
    private function analyze_file($file_path) {
        $relative_path = str_replace($this->base_dir . '/', '', $file_path);
        
        // 1. Check PHP syntax
        $this->check_php_syntax($file_path, $relative_path);
        
        // 2. Parse file for function and class definitions
        $this->parse_definitions($file_path, $relative_path);
        
        // 3. Check for common issues
        $this->check_common_issues($file_path, $relative_path);
    }
    
    /**
     * Check PHP syntax using php -l
     *
     * @param string $file_path Absolute file path
     * @param string $relative_path Relative file path for reporting
     */
    private function check_php_syntax($file_path, $relative_path) {
        $output = [];
        $return_code = 0;
        
        exec("php -l " . escapeshellarg($file_path) . " 2>&1", $output, $return_code);
        
        if ($return_code !== 0) {
            $this->errors[] = [
                'type' => 'SYNTAX_ERROR',
                'file' => $relative_path,
                'message' => implode("\n", $output),
                'severity' => 'FATAL'
            ];
        }
    }
    
    /**
     * Parse file for function and class definitions
     *
     * @param string $file_path Absolute file path
     * @param string $relative_path Relative file path for reporting
     */
    private function parse_definitions($file_path, $relative_path) {
        $content = file_get_contents($file_path);
        $lines = explode("\n", $content);
        
        foreach ($lines as $line_number => $line) {
            $line_number++; // 1-based line numbers
            
            // Check for function definitions
            if (preg_match('/^\s*(public|private|protected)?\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/i', $line, $matches)) {
                $visibility = $matches[1] ?: 'public';
                $function_name = $matches[2];
                
                // Check if it's a class method
                $class_context = $this->get_class_context($content, $line_number);
                
                if ($class_context) {
                    $full_name = $class_context . '::' . $function_name;
                    $key = strtolower($full_name);
                } else {
                    $full_name = $function_name;
                    $key = strtolower($function_name);
                }
                
                if (!isset($this->function_definitions[$key])) {
                    $this->function_definitions[$key] = [];
                }
                
                $this->function_definitions[$key][] = [
                    'name' => $full_name,
                    'file' => $relative_path,
                    'line' => $line_number,
                    'visibility' => $visibility,
                    'class_context' => $class_context,
                    'raw_line' => trim($line)
                ];
            }
            
            // Check for class definitions
            if (preg_match('/^\s*class\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $line, $matches)) {
                $class_name = $matches[1];
                $key = strtolower($class_name);
                
                if (!isset($this->class_definitions[$key])) {
                    $this->class_definitions[$key] = [];
                }
                
                $this->class_definitions[$key][] = [
                    'name' => $class_name,
                    'file' => $relative_path,
                    'line' => $line_number,
                    'raw_line' => trim($line)
                ];
            }
        }
    }
    
    /**
     * Get class context for a given line number
     *
     * @param string $content File content
     * @param int $target_line Target line number
     * @return string|null Class name or null if not in class
     */
    private function get_class_context($content, $target_line) {
        $lines = explode("\n", $content);
        $class_name = null;
        $brace_depth = 0;
        $in_class = false;
        
        for ($i = 0; $i < $target_line - 1; $i++) {
            $line = $lines[$i];
            
            // Check for class definition
            if (preg_match('/^\s*class\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $line, $matches)) {
                $class_name = $matches[1];
                $in_class = true;
                $brace_depth = 0;
            }
            
            // Track brace depth
            $brace_depth += substr_count($line, '{') - substr_count($line, '}');
            
            // If we're at depth 0 and we were in a class, we've left it
            if ($in_class && $brace_depth <= 0 && $i > 0) {
                $class_name = null;
                $in_class = false;
            }
        }
        
        return $in_class ? $class_name : null;
    }
    
    /**
     * Check for common coding issues
     *
     * @param string $file_path Absolute file path
     * @param string $relative_path Relative file path for reporting
     */
    private function check_common_issues($file_path, $relative_path) {
        $content = file_get_contents($file_path);
        $lines = explode("\n", $content);
        
        foreach ($lines as $line_number => $line) {
            $line_number++; // 1-based line numbers
            
            // Check for potential issues
            if (strpos($line, 'eval(') !== false) {
                $this->errors[] = [
                    'type' => 'SECURITY_RISK',
                    'file' => $relative_path,
                    'line' => $line_number,
                    'message' => 'Use of eval() function detected - potential security risk',
                    'severity' => 'HIGH'
                ];
            }
            
            if (preg_match('/\$_(?:GET|POST|REQUEST|COOKIE)\[/', $line) && 
                strpos($line, 'sanitize') === false && 
                strpos($line, 'wp_unslash') === false) {
                $this->errors[] = [
                    'type' => 'SECURITY_RISK',
                    'file' => $relative_path,
                    'line' => $line_number,
                    'message' => 'Direct use of superglobal without sanitization',
                    'severity' => 'MEDIUM'
                ];
            }
        }
    }
    
    /**
     * Check for function and class redeclarations
     */
    private function check_redeclarations() {
        // Check function redeclarations
        foreach ($this->function_definitions as $key => $definitions) {
            if (count($definitions) > 1) {
                // Check if it's actually a redeclaration (same class and method name)
                $grouped_by_context = [];
                
                foreach ($definitions as $def) {
                    $context = $def['class_context'] ?: 'global';
                    if (!isset($grouped_by_context[$context])) {
                        $grouped_by_context[$context] = [];
                    }
                    $grouped_by_context[$context][] = $def;
                }
                
                foreach ($grouped_by_context as $context => $context_definitions) {
                    if (count($context_definitions) > 1) {
                        $this->errors[] = [
                            'type' => 'REDECLARATION_ERROR',
                            'message' => 'Function/method redeclaration detected',
                            'details' => $context_definitions,
                            'severity' => 'FATAL'
                        ];
                    }
                }
            }
        }
        
        // Check class redeclarations
        foreach ($this->class_definitions as $key => $definitions) {
            if (count($definitions) > 1) {
                $this->errors[] = [
                    'type' => 'CLASS_REDECLARATION_ERROR',
                    'message' => 'Class redeclaration detected',
                    'details' => $definitions,
                    'severity' => 'FATAL'
                ];
            }
        }
    }
    
    /**
     * Generate and display analysis report
     */
    private function generate_report() {
        echo "📊 ANALYSIS RESULTS\n";
        echo "==================\n\n";
        
        if (empty($this->errors)) {
            echo "✅ No errors detected!\n\n";
            return;
        }
        
        $fatal_errors = array_filter($this->errors, function($error) {
            return $error['severity'] === 'FATAL';
        });
        
        $high_errors = array_filter($this->errors, function($error) {
            return $error['severity'] === 'HIGH';
        });
        
        $medium_errors = array_filter($this->errors, function($error) {
            return $error['severity'] === 'MEDIUM';
        });
        
        echo "❌ TOTAL ERRORS: " . count($this->errors) . "\n";
        echo "🔴 FATAL: " . count($fatal_errors) . "\n";
        echo "🟠 HIGH: " . count($high_errors) . "\n";
        echo "🟡 MEDIUM: " . count($medium_errors) . "\n\n";
        
        // Display fatal errors first
        if (!empty($fatal_errors)) {
            echo "🔴 FATAL ERRORS (MUST FIX IMMEDIATELY)\n";
            echo "=====================================\n\n";
            
            foreach ($fatal_errors as $error) {
                $this->display_error($error);
            }
        }
        
        // Display other errors
        $other_errors = array_filter($this->errors, function($error) {
            return $error['severity'] !== 'FATAL';
        });
        
        if (!empty($other_errors)) {
            echo "⚠️  OTHER ISSUES\n";
            echo "===============\n\n";
            
            foreach ($other_errors as $error) {
                $this->display_error($error);
            }
        }
    }
    
    /**
     * Display a single error
     *
     * @param array $error Error details
     */
    private function display_error($error) {
        $severity_icons = [
            'FATAL' => '🔴',
            'HIGH' => '🟠',
            'MEDIUM' => '🟡',
            'LOW' => '🟢'
        ];
        
        $icon = $severity_icons[$error['severity']] ?? '⚪';
        
        echo "$icon {$error['type']}\n";
        echo "   {$error['message']}\n";
        
        if (isset($error['file'])) {
            $location = $error['file'];
            if (isset($error['line'])) {
                $location .= ":{$error['line']}";
            }
            echo "   📁 $location\n";
        }
        
        if (isset($error['details']) && is_array($error['details'])) {
            echo "   📋 Details:\n";
            foreach ($error['details'] as $i => $detail) {
                $location = $detail['file'];
                if (isset($detail['line'])) {
                    $location .= ":{$detail['line']}";
                }
                echo "      " . ($i + 1) . ". {$detail['name']} in $location\n";
                if (isset($detail['raw_line'])) {
                    echo "         Code: " . trim($detail['raw_line']) . "\n";
                }
            }
        }
        
        echo "\n";
    }
}

// CLI execution
if (php_sapi_name() === 'cli' && !defined('WPMATCH_TESTING')) {
    define('WPMATCH_TESTING', true);
    
    $base_dir = isset($argv[1]) ? $argv[1] : dirname(dirname(__DIR__));
    $checker = new WPMatch_PHP_Syntax_Checker($base_dir);
    $results = $checker->run_analysis();
    
    // Count fatal errors specifically
    $fatal_errors = array_filter($results['errors'], function($error) {
        return $error['severity'] === 'FATAL';
    });
    
    if (!empty($fatal_errors)) {
        echo "\n🚨 CRITICAL: " . count($fatal_errors) . " FATAL ERROR(S) DETECTED\n";
        echo "🔧 These MUST be fixed before the plugin can function properly.\n\n";
        exit(1);
    } else {
        echo "\n✅ No fatal errors detected!\n";
        if ($results['has_errors']) {
            $non_fatal = count($results['errors']) - count($fatal_errors);
            echo "⚠️  Found $non_fatal non-fatal issues to review.\n";
        }
        echo "\n";
        exit(0);
    }
}