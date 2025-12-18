# Testing Guide

## Test Overview

PhpStanHub includes comprehensive tests for both backend (PHP) and frontend (JavaScript) components.

## Running Tests

### PHP Tests (PHPUnit)

#### All Tests
```bash
# Local
vendor/bin/phpunit

# With Docker
docker-compose run --rm phpstan-hub vendor/bin/phpunit
```

#### Specific Test Suite
```bash
# Test file content endpoint
vendor/bin/phpunit tests/Command/ServeCommandFileContentTest.php --testdox

# All command tests
vendor/bin/phpunit tests/Command/

# All web tests
vendor/bin/phpunit tests/Web/
```

#### With Coverage
```bash
vendor/bin/phpunit --coverage-html coverage/
```

### JavaScript Tests (Vitest)

#### All Tests
```bash
npm test
```

#### Specific Test File
```bash
npm test -- fileTree.test.js
```

#### Watch Mode (auto-rerun on changes)
```bash
npm test -- --watch
```

#### With Coverage
```bash
npm test -- --coverage
```

## Test Structure

### PHP Tests (`tests/`)
```
tests/
├── Command/
│   ├── ServeCommandTest.php
│   └── ServeCommandFileContentTest.php  ← Explorer view endpoint tests
├── PhpStan/
│   └── PhpStanRunnerTest.php
├── Watcher/
│   └── FileWatcherTest.php
└── Web/
    ├── StatusHandlerTest.php
    └── ViteManifestTest.php
```

### JavaScript Tests (`assets/js/`)
```
assets/js/
└── utils/
    └── fileTree.test.js  ← Explorer view tree utility tests
```

## Test Coverage

### Explorer View Implementation

#### JavaScript (`fileTree.test.js`)
- ✅ Tree building from flat file list
- ✅ Error count calculation
- ✅ Path handling with/without projectRoot
- ✅ Parent references
- ✅ Deep nested paths
- ✅ Children sorting (folders first, alphabetical)
- ✅ File search in tree
- ✅ Auto-expand to file

#### PHP (`ServeCommandFileContentTest.php`)
- ✅ File exists within project root
- ✅ File outside project root (security)
- ✅ Non-existent file handling
- ✅ Symlink escape attempt (security)
- ✅ Directory path rejection
- ✅ File content retrieval
- ✅ Empty file handling
- ✅ Large file handling (>100KB)
- ✅ Special characters in content
- ✅ Nested directory file access

## Writing New Tests

### PHP Test Template
```php
<?php

namespace PhpStanHub\Tests\YourNamespace;

use PHPUnit\Framework\TestCase;

class YourTest extends TestCase
{
    protected function setUp(): void
    {
        // Setup test environment
    }

    protected function tearDown(): void
    {
        // Cleanup
    }

    public function testYourFeature(): void
    {
        // Arrange
        $expected = 'value';

        // Act
        $actual = yourFunction();

        // Assert
        $this->assertEquals($expected, $actual);
    }
}
```

### JavaScript Test Template
```javascript
import { describe, it, expect } from 'vitest';
import { yourFunction } from './yourModule.js';

describe('yourModule', () => {
    describe('yourFunction', () => {
        it('should do something', () => {
            // Arrange
            const input = 'test';
            const expected = 'result';

            // Act
            const actual = yourFunction(input);

            // Assert
            expect(actual).toBe(expected);
        });
    });
});
```

## Continuous Integration

Tests should be run before:
- Committing code
- Creating pull requests
- Deploying to production

### Pre-commit Hook (recommended)
Add to `.git/hooks/pre-commit`:
```bash
#!/bin/bash

echo "Running PHP tests..."
vendor/bin/phpunit --testdox || exit 1

echo "Running JavaScript tests..."
npm test || exit 1

echo "All tests passed ✅"
```

Make it executable:
```bash
chmod +x .git/hooks/pre-commit
```

## Debugging Tests

### PHP Tests
```bash
# Verbose output
vendor/bin/phpunit --verbose

# Stop on first failure
vendor/bin/phpunit --stop-on-failure

# Filter tests by name
vendor/bin/phpunit --filter testFileExistsWithinProjectRoot
```

### JavaScript Tests
```bash
# Run specific test with debug output
npm test -- fileTree.test.js --reporter=verbose

# Run single test case
npm test -- fileTree.test.js -t "should build a tree"
```

## Test Best Practices

1. **Follow AAA Pattern**: Arrange, Act, Assert
2. **One assertion per test**: Keep tests focused
3. **Use descriptive test names**: Test name should describe what is being tested
4. **Clean up after tests**: Use `tearDown()` to remove test artifacts
5. **Mock external dependencies**: Don't rely on external services
6. **Test edge cases**: Empty inputs, null values, boundary conditions
7. **Test error conditions**: Not just happy paths
8. **Keep tests fast**: Unit tests should run in milliseconds

## Common Issues

### PHPUnit: "Class not found"
**Solution**: Run `composer dump-autoload`

### Vitest: "Cannot find module"
**Solution**: Check import paths are relative and include `.js` extension

### Tests pass locally but fail in CI
**Solution**: Check for hardcoded paths or environment-specific code

### Slow tests
**Solution**:
- Avoid sleep/wait calls
- Mock network requests
- Use in-memory databases for integration tests

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Vitest Documentation](https://vitest.dev/)
- [Vue Test Utils](https://test-utils.vuejs.org/)

---

For questions or issues with tests, check:
- Test output logs
- `phpunit.xml` configuration
- `vitest.config.js` (if present)
