# Alabama Test Suite

This directory contains automated tests for the Alabama system.

## Directory Structure

```
tests/
├── bootstrap.php              # Test environment setup
├── Security/                  # Security feature tests
│   ├── CsrfTest.php          # CSRF token tests (6 tests)
│   └── RateLimiterTest.php   # Rate limiting tests (4 tests)
├── Llm/                       # LLM/AI feature tests
│   └── PromptSanitizationTest.php  # Prompt injection prevention (7 tests)
├── WhatsApp/                  # WhatsApp integration tests
│   ├── PhoneNormalizationTest.php  # Phone number formatting (7 tests)
│   └── WebhookSignatureTest.php    # Webhook HMAC validation (7 tests)
└── Campanhas/                 # Campaign management tests
    └── RemarketingCampanhasTest.php  # Campaign CRUD operations (8 tests)
```

## Test Coverage

- **Security Tests (10 tests):** CSRF protection, rate limiting
- **LLM Tests (7 tests):** Prompt sanitization, injection prevention
- **WhatsApp Tests (14 tests):** Phone normalization, webhook security
- **Campaign Tests (8 tests):** CRUD operations, ID generation

**Total:** 39+ test methods covering critical functionality

## Running Tests

### Prerequisites

```bash
# Install dependencies (including PHPUnit)
composer install
```

### Run All Tests

```bash
# Using composer script
composer test

# Or directly with PHPUnit
./vendor/bin/phpunit

# With verbose output
./vendor/bin/phpunit --verbose
```

### Run Specific Test Suites

```bash
# Security tests only
./vendor/bin/phpunit tests/Security/

# LLM tests only
./vendor/bin/phpunit tests/Llm/

# WhatsApp tests only
./vendor/bin/phpunit tests/WhatsApp/

# Campaign tests only
./vendor/bin/phpunit tests/Campanhas/
```

### Run Specific Test Class

```bash
./vendor/bin/phpunit tests/Security/CsrfTest.php
```

### Run Specific Test Method

```bash
./vendor/bin/phpunit --filter testTokenGeneration tests/Security/CsrfTest.php
```

## Test Coverage Reports

```bash
# Generate HTML coverage report (requires Xdebug)
./vendor/bin/phpunit --coverage-html coverage/

# View coverage report
open coverage/index.html
```

## Writing New Tests

### Test Class Template

```php
<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MyFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup code runs before each test
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Cleanup code runs after each test
    }

    public function testSomething(): void
    {
        // Arrange
        $input = 'test data';
        
        // Act
        $result = someFunction($input);
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Naming Conventions

- Test classes: `*Test.php` (e.g., `CsrfTest.php`)
- Test methods: `testSomething()` (must start with `test`)
- Use descriptive names that explain what is being tested

### Assertions

Common PHPUnit assertions:

```php
$this->assertTrue($value);
$this->assertFalse($value);
$this->assertEquals($expected, $actual);
$this->assertSame($expected, $actual);  // Strict comparison
$this->assertNull($value);
$this->assertNotNull($value);
$this->assertEmpty($value);
$this->assertNotEmpty($value);
$this->assertCount($count, $array);
$this->assertArrayHasKey($key, $array);
$this->assertStringContainsString($needle, $haystack);
$this->assertMatchesRegularExpression($pattern, $string);
```

## Best Practices

1. **One Test, One Thing:** Each test should verify one specific behavior
2. **Independent Tests:** Tests should not depend on each other
3. **Clean Up:** Use `tearDown()` to clean up resources (files, database records)
4. **Descriptive Names:** Test names should clearly describe what they test
5. **Arrange-Act-Assert:** Structure tests in three clear phases
6. **Test Edge Cases:** Include tests for empty inputs, boundary values, errors

## Continuous Integration

These tests are designed to run in CI/CD pipelines:

```yaml
# Example GitHub Actions workflow
- name: Run Tests
  run: |
    composer install
    composer test
```

## Troubleshooting

### PHPUnit Not Found

```bash
# Reinstall dependencies
rm -rf vendor/
composer install
```

### Tests Fail Due to Missing Extensions

```bash
# Install required PHP extensions
sudo apt-get install php-mbstring php-xml php-curl
```

### Session Warnings

Tests run with session handling suppressed. If you see session warnings, ensure `tests/bootstrap.php` is being loaded.

## Contributing

When adding new features to the Alabama system:

1. Write tests first (TDD approach recommended)
2. Ensure all existing tests pass
3. Add tests to the appropriate directory
4. Update this README if adding a new test category
5. Aim for at least 80% code coverage for new code

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPUnit Best Practices](https://phpunit.de/best-practices.html)
- [Test-Driven Development](https://en.wikipedia.org/wiki/Test-driven_development)
