#!/bin/bash

# WPMatch Test Suite Runner
# Comprehensive testing script for the WPMatch dating plugin

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test directories
TESTS_DIR="tests"
COVERAGE_DIR="tests/coverage"
STATIC_ANALYSIS_DIR="tests/static-analysis"

echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}  WPMatch Test Suite Runner     ${NC}"
echo -e "${BLUE}================================${NC}"
echo ""

# Create coverage directory if it doesn't exist
mkdir -p "$COVERAGE_DIR"

# Function to run static analysis
run_static_analysis() {
    echo -e "${YELLOW}🔍 Running Static Analysis...${NC}"
    echo "================================"
    
    if [ -f "$STATIC_ANALYSIS_DIR/php-syntax-checker.php" ]; then
        php "$STATIC_ANALYSIS_DIR/php-syntax-checker.php"
        STATIC_EXIT_CODE=$?
        
        if [ $STATIC_EXIT_CODE -eq 0 ]; then
            echo -e "${GREEN}✅ Static analysis passed${NC}"
        else
            echo -e "${RED}❌ Static analysis failed with exit code $STATIC_EXIT_CODE${NC}"
            echo -e "${RED}🚨 CRITICAL ERRORS DETECTED - Fix before continuing${NC}"
            return $STATIC_EXIT_CODE
        fi
    else
        echo -e "${RED}❌ Static analysis tool not found${NC}"
        return 1
    fi
    
    echo ""
}

# Function to run PHPUnit tests
run_phpunit_tests() {
    echo -e "${YELLOW}🧪 Running PHPUnit Test Suite...${NC}"
    echo "=================================="
    
    # Check if PHPUnit is available
    if ! command -v phpunit &> /dev/null; then
        if [ -f "vendor/bin/phpunit" ]; then
            PHPUNIT_CMD="vendor/bin/phpunit"
        else
            echo -e "${RED}❌ PHPUnit not found. Please install PHPUnit.${NC}"
            echo "Run: composer install --dev"
            return 1
        fi
    else
        PHPUNIT_CMD="phpunit"
    fi
    
    # Run all test suites
    echo -e "${BLUE}Running all test suites...${NC}"
    $PHPUNIT_CMD --configuration phpunit.xml
    PHPUNIT_EXIT_CODE=$?
    
    if [ $PHPUNIT_EXIT_CODE -eq 0 ]; then
        echo -e "${GREEN}✅ All PHPUnit tests passed${NC}"
    else
        echo -e "${RED}❌ Some PHPUnit tests failed${NC}"
        return $PHPUNIT_EXIT_CODE
    fi
    
    echo ""
}

# Function to run specific test suite
run_test_suite() {
    local suite_name=$1
    echo -e "${YELLOW}🧪 Running $suite_name Test Suite...${NC}"
    echo "=================================="
    
    case $suite_name in
        "static")
            phpunit --testsuite "Static Analysis"
            ;;
        "unit")
            phpunit --testsuite "Unit Tests"
            ;;
        "integration")
            phpunit --testsuite "Integration Tests"
            ;;
        "security")
            phpunit --testsuite "Security Tests"
            ;;
        "performance")
            phpunit --testsuite "Performance Tests"
            ;;
        "wordpress")
            phpunit --testsuite "WordPress Compatibility"
            ;;
        *)
            echo -e "${RED}❌ Unknown test suite: $suite_name${NC}"
            echo "Available suites: static, unit, integration, security, performance, wordpress"
            return 1
            ;;
    esac
    
    echo ""
}

# Function to generate coverage report
generate_coverage() {
    echo -e "${YELLOW}📊 Generating Coverage Report...${NC}"
    echo "=================================="
    
    if command -v phpunit &> /dev/null || [ -f "vendor/bin/phpunit" ]; then
        $PHPUNIT_CMD --coverage-html "$COVERAGE_DIR/html" --coverage-clover "$COVERAGE_DIR/clover.xml"
        echo -e "${GREEN}✅ Coverage report generated in $COVERAGE_DIR${NC}"
        
        if [ -f "$COVERAGE_DIR/html/index.html" ]; then
            echo -e "${BLUE}📝 Open $COVERAGE_DIR/html/index.html to view detailed coverage${NC}"
        fi
    else
        echo -e "${RED}❌ Cannot generate coverage without PHPUnit${NC}"
        return 1
    fi
    
    echo ""
}

# Function to run performance benchmarks
run_performance_benchmarks() {
    echo -e "${YELLOW}⚡ Running Performance Benchmarks...${NC}"
    echo "====================================="
    
    # Create a performance test runner
    cat > performance-runner.php << 'EOF'
<?php
// Simple performance benchmark runner
define('WPMATCH_TESTING', true);

$start_time = microtime(true);
$start_memory = memory_get_usage();

echo "Performance Benchmarks\n";
echo "======================\n\n";

// Simulate large dataset test
$large_array = [];
for ($i = 0; $i < 10000; $i++) {
    $large_array[] = ['id' => $i, 'data' => "test_data_$i"];
}

$process_start = microtime(true);
$processed = array_map(function($item) {
    return $item['id'] * 2;
}, $large_array);
$process_end = microtime(true);

echo "✅ Large dataset processing: " . round(($process_end - $process_start) * 1000, 2) . "ms\n";

// Memory usage test
$end_memory = memory_get_usage();
$memory_used = ($end_memory - $start_memory) / 1024 / 1024;
echo "📊 Memory usage: " . round($memory_used, 2) . "MB\n";

// Total execution time
$end_time = microtime(true);
$total_time = ($end_time - $start_time) * 1000;
echo "⏱️  Total execution time: " . round($total_time, 2) . "ms\n";

echo "\n✅ Performance benchmarks completed\n";
EOF

    php performance-runner.php
    rm performance-runner.php
    echo ""
}

# Function to check test environment
check_environment() {
    echo -e "${YELLOW}🔧 Checking Test Environment...${NC}"
    echo "==============================="
    
    # Check PHP version
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    echo "PHP Version: $PHP_VERSION"
    
    # Check required extensions
    REQUIRED_EXTENSIONS=("json" "mbstring" "mysqli")
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if php -m | grep -q "^$ext$"; then
            echo -e "✅ PHP Extension $ext: ${GREEN}installed${NC}"
        else
            echo -e "❌ PHP Extension $ext: ${RED}missing${NC}"
        fi
    done
    
    # Check test files
    if [ -d "$TESTS_DIR" ]; then
        TEST_COUNT=$(find "$TESTS_DIR" -name "*.php" -type f | wc -l)
        echo "📁 Test files found: $TEST_COUNT"
    else
        echo -e "${RED}❌ Tests directory not found${NC}"
        return 1
    fi
    
    echo ""
}

# Function to display help
show_help() {
    echo "WPMatch Test Suite Runner"
    echo ""
    echo "Usage: $0 [OPTION]"
    echo ""
    echo "Options:"
    echo "  all                Run all tests (static analysis + PHPUnit)"
    echo "  static             Run static analysis only"
    echo "  phpunit            Run PHPUnit tests only"
    echo "  unit               Run unit tests only"
    echo "  integration        Run integration tests only"
    echo "  security           Run security tests only"
    echo "  performance        Run performance tests only"
    echo "  wordpress          Run WordPress compatibility tests only"
    echo "  coverage           Generate test coverage report"
    echo "  benchmark          Run performance benchmarks"
    echo "  check              Check test environment"
    echo "  help               Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 all             # Run complete test suite"
    echo "  $0 static          # Run only static analysis"
    echo "  $0 unit            # Run only unit tests"
    echo "  $0 coverage        # Generate coverage report"
    echo ""
}

# Main execution logic
case "${1:-all}" in
    "all")
        check_environment
        run_static_analysis
        STATIC_RESULT=$?
        
        if [ $STATIC_RESULT -eq 0 ]; then
            run_phpunit_tests
            PHPUNIT_RESULT=$?
            
            if [ $PHPUNIT_RESULT -eq 0 ]; then
                echo -e "${GREEN}🎉 All tests passed successfully!${NC}"
                exit 0
            else
                echo -e "${RED}❌ Some tests failed${NC}"
                exit $PHPUNIT_RESULT
            fi
        else
            echo -e "${RED}🚨 Critical errors detected. Fix static analysis issues first.${NC}"
            exit $STATIC_RESULT
        fi
        ;;
    "static")
        run_static_analysis
        ;;
    "phpunit")
        run_phpunit_tests
        ;;
    "unit"|"integration"|"security"|"performance"|"wordpress")
        run_test_suite "$1"
        ;;
    "coverage")
        generate_coverage
        ;;
    "benchmark")
        run_performance_benchmarks
        ;;
    "check")
        check_environment
        ;;
    "help"|"-h"|"--help")
        show_help
        ;;
    *)
        echo -e "${RED}❌ Unknown option: $1${NC}"
        echo ""
        show_help
        exit 1
        ;;
esac