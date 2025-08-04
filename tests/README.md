# WPMatch Test Suite

Comprehensive testing framework for the WPMatch dating plugin profile fields management interface.

## 🎯 Overview

This test suite is designed to detect and prevent critical errors in the WPMatch codebase, with special focus on:

- **PHP Fatal Errors**: Function/method redeclarations and syntax errors
- **Security Vulnerabilities**: Input sanitization, capability checks, CSRF protection
- **Performance Issues**: Large dataset handling, database optimization, caching
- **WordPress Compatibility**: Standards compliance, plugin lifecycle, multisite support

## 🚨 Critical Error Detection

The test suite **WILL DETECT** the following critical errors currently in the codebase:

### Fatal Errors Found:
1. **`WPMatch_Admin::get_dashboard_stats()` redeclaration** in `admin/class-admin.php:1134`
2. **`WPMatch_Profile_Field_Manager::get_next_field_order()` redeclaration** in `includes/class-profile-field-manager.php:1012`

### Security Issues Found:
- 177+ instances of direct superglobal usage without sanitization
- Missing input validation in AJAX handlers
- Insufficient capability checks

## 📁 Test Structure

```
tests/
├── README.md                           # This documentation
├── bootstrap/                          # Test bootstrap files
│   ├── bootstrap.php                   # PHPUnit bootstrap
│   ├── wp-tests-config.php            # WordPress test configuration
│   └── test-helpers.php               # Test helper functions
├── static-analysis/                    # Static code analysis
│   ├── php-syntax-checker.php         # PHP syntax & redeclaration checker
│   └── StaticAnalysisTest.php         # PHPUnit static analysis tests
├── unit/                              # Unit tests
│   ├── ProfileFieldsAdminTest.php     # Admin interface unit tests
│   └── test-profile-fields-admin.php  # Legacy unit tests
├── integration/                       # Integration tests
│   └── ProfileFieldsIntegrationTest.php
├── security/                          # Security tests
│   └── SecurityTest.php
├── performance/                       # Performance tests
│   └── PerformanceTest.php
├── wordpress/                         # WordPress compatibility tests
│   └── WordPressCompatibilityTest.php
└── coverage/                          # Coverage reports (generated)
    ├── html/                          # HTML coverage report
    └── clover.xml                     # Clover coverage report
```

## 🚀 Quick Start

### 1. Run All Tests
```bash
./run-tests.sh all
```

### 2. Run Static Analysis Only (Detects Critical Errors)
```bash
./run-tests.sh static
```

### 3. Run Specific Test Suite
```bash
./run-tests.sh unit          # Unit tests only
./run-tests.sh integration   # Integration tests only
./run-tests.sh security      # Security tests only
./run-tests.sh performance   # Performance tests only
./run-tests.sh wordpress     # WordPress compatibility only
```

### 4. Generate Coverage Report
```bash
./run-tests.sh coverage
```

### 5. Run Performance Benchmarks
```bash
./run-tests.sh benchmark
```

## 📋 Test Categories

### 1. Static Analysis Tests
**Purpose**: Detect code errors before runtime
- ✅ PHP syntax validation
- ✅ Function/method redeclaration detection
- ✅ Class redefinition detection  
- ✅ Namespace conflict analysis
- ✅ WordPress coding standards

**Command**: `./run-tests.sh static`

### 2. Unit Tests
**Purpose**: Test individual classes and methods
- ✅ ProfileFieldsAdmin class methods
- ✅ AJAX handler validation
- ✅ Input sanitization
- ✅ Field validation logic
- ✅ Error handling

**Command**: `./run-tests.sh unit`

### 3. Integration Tests
**Purpose**: Test component interactions
- ✅ Admin interface with database operations
- ✅ AJAX workflows end-to-end
- ✅ Frontend integration with shortcodes
- ✅ Import/export functionality
- ✅ Search integration

**Command**: `./run-tests.sh integration`

### 4. Security Tests
**Purpose**: Validate security implementations
- ✅ Capability checks and nonces
- ✅ Rate limiting and brute force protection
- ✅ Input sanitization and validation
- ✅ CSRF protection
- ✅ XSS prevention
- ✅ SQL injection prevention

**Command**: `./run-tests.sh security`

### 5. Performance Tests
**Purpose**: Validate optimization features
- ✅ Database query performance
- ✅ Caching effectiveness
- ✅ Large dataset handling (1,000+ users)
- ✅ Memory usage optimization
- ✅ AJAX response times

**Command**: `./run-tests.sh performance`

### 6. WordPress Compatibility Tests
**Purpose**: Ensure WordPress standards
- ✅ Plugin activation/deactivation
- ✅ Database table creation/cleanup
- ✅ WordPress hooks and filters
- ✅ Multisite compatibility
- ✅ Coding standards compliance

**Command**: `./run-tests.sh wordpress`

## 🛠 Requirements

### PHP Requirements
- PHP 7.4 or higher
- Required extensions: json, mbstring, mysqli
- PHPUnit 9.0 or higher

### WordPress Requirements
- WordPress 5.0 or higher
- WordPress testing framework (optional for advanced tests)

### Installation
```bash
# Install PHPUnit via Composer (recommended)
composer install --dev

# Or install PHPUnit globally
wget https://phar.phpunit.de/phpunit.phar
chmod +x phpunit.phar
sudo mv phpunit.phar /usr/local/bin/phpunit
```

## 📊 Performance Benchmarks

The test suite enforces these performance requirements:

| Metric | Target | Test |
|--------|--------|------|
| Large dataset query | < 2.0 seconds | 1,500+ user profiles |
| Cache retrieval | < 1ms | Field configuration cache |
| AJAX response | < 500ms | All admin actions |
| Pagination query | < 100ms | 10,000+ records |
| Search query | < 1.0 second | Complex multi-field search |
| Bulk operation memory | < 10MB | 1,000 field operations |
| Form rendering | < 100ms | 50+ field form |

## 🔧 Configuration

### PHPUnit Configuration
The test suite uses `phpunit.xml` for configuration:

```xml
<phpunit>
    <testsuites>
        <testsuite name="Static Analysis">
            <directory>./tests/static-analysis/</directory>
        </testsuite>
        <testsuite name="Unit Tests">
            <directory>./tests/unit/</directory>
        </testsuite>
        <!-- Additional test suites... -->
    </testsuites>
</phpunit>
```

### Test Environment Variables
Set these in your test environment:

```php
const WP_TESTS_DOMAIN = 'example.org';
const WP_TESTS_EMAIL = 'admin@example.org';
const WP_TESTS_TITLE = 'Test';
const WPMATCH_TESTING = true;
```

## 🚨 Critical Error Resolution

### Current Issues That Must Be Fixed:

#### 1. Function Redeclaration Error
**File**: `admin/class-admin.php`
**Line**: 1134
**Error**: `Cannot redeclare WPMatch_Admin::get_dashboard_stats()`

**Fix**: Remove duplicate method definition at line 1134

#### 2. Method Redeclaration Error
**File**: `includes/class-profile-field-manager.php`
**Line**: 1012
**Error**: `Cannot redeclare WPMatch_Profile_Field_Manager::get_next_field_order()`

**Fix**: Remove duplicate method definition at line 1012

### To Fix These Errors:
```bash
# 1. Run static analysis to identify exact locations
./run-tests.sh static

# 2. Edit the files to remove duplicate methods
# 3. Re-run tests to verify fixes
./run-tests.sh static
```

## 📈 Test Coverage

Target coverage levels:
- **Unit Tests**: 80% line coverage minimum
- **Integration Tests**: All API endpoints covered
- **Security Tests**: OWASP Top 10 coverage
- **Performance Tests**: All optimization features tested

Generate coverage report:
```bash
./run-tests.sh coverage
```

View HTML coverage report: `tests/coverage/html/index.html`

## 🔍 Debugging Tests

### Verbose Output
```bash
phpunit --verbose --testdox
```

### Run Single Test
```bash
phpunit tests/unit/ProfileFieldsAdminTest.php::test_ajax_create_field_success
```

### Debug Mode
```bash
phpunit --debug tests/security/SecurityTest.php
```

## 🤝 Contributing

### Adding New Tests

1. **Unit Test**: Add to `tests/unit/`
2. **Integration Test**: Add to `tests/integration/`
3. **Security Test**: Add to `tests/security/`
4. **Performance Test**: Add to `tests/performance/`

### Test Naming Convention
```php
public function test_[functionality]_[scenario]() {
    // Test implementation
}
```

### Test Structure
```php
public function test_example() {
    // Arrange
    $input = 'test data';
    
    // Act
    $result = $this->object_under_test->method($input);
    
    // Assert
    $this->assertEquals('expected', $result);
}
```

## 📝 Test Reports

### Daily Test Report
Run this command to generate a comprehensive test report:
```bash
./run-tests.sh all > test-report-$(date +%Y%m%d).log 2>&1
```

### CI/CD Integration
For continuous integration, use:
```bash
# In your CI pipeline
./run-tests.sh all
EXIT_CODE=$?

if [ $EXIT_CODE -ne 0 ]; then
    echo "Tests failed - blocking deployment"
    exit $EXIT_CODE
fi
```

## 🆘 Support

### Common Issues

**Issue**: "PHPUnit not found"
**Solution**: Install PHPUnit via Composer: `composer install --dev`

**Issue**: "WordPress functions not defined"
**Solution**: Check bootstrap configuration in `tests/bootstrap/bootstrap.php`

**Issue**: "Memory limit exceeded"
**Solution**: Increase PHP memory limit: `php -d memory_limit=256M phpunit`

### Getting Help
1. Check test output for specific error messages
2. Run static analysis first: `./run-tests.sh static`
3. Fix critical errors before running other tests
4. Use verbose mode for debugging: `phpunit --verbose`

## 📚 References

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)
- [PHP Static Analysis Tools](https://github.com/exakat/php-static-analysis-tools)

---

**⚠️ IMPORTANT**: Always run static analysis first to detect critical PHP fatal errors that will prevent the plugin from loading. Fix these errors before running other tests.