# Testing Guide for Fellowship Project

This document provides instructions on how to run the tests for both JavaScript and PHP components of the Fellowship project.

## JavaScript Tests

The JavaScript tests use Vitest as the test runner and Vue Test Utils for testing Vue components.

### Running JavaScript Tests

To run all JavaScript tests:

```bash
npm run test
```

To run tests in watch mode (tests will re-run when files change):

```bash
npm run test:watch
```

To run tests with coverage report:

```bash
npm run test:coverage
```

### Test Files

JavaScript test files are located in the `resources/js/tests` directory. The main test files are:

- `DetailsDialog.spec.js` - Tests for the DetailsDialog component
- `CalendarEventHandler.spec.js` - Tests for the CalendarEventHandler component

## PHP Tests

PHP tests use PHPUnit and are divided into Feature tests and Unit tests.

### Running PHP Tests

To run all PHP tests:

```bash
php artisan test
```

To run a specific test file:

```bash
php artisan test --filter=EventControllerTest
```

To run tests with coverage report (requires Xdebug):

```bash
php artisan test --coverage
```

### Test Files

PHP test files are located in the `tests` directory:

- Feature tests: `tests/Feature/EventControllerTest.php`
- Unit tests: `tests/Unit/EventGuestTest.php`

## Test-Driven Development (TDD) Workflow

When implementing new features or fixing bugs, follow these steps:

1. Write a failing test that describes the expected behavior
2. Run the test to confirm it fails
3. Implement the minimum code necessary to make the test pass
4. Run the test to confirm it passes
5. Refactor the code while ensuring tests continue to pass
6. Repeat for additional functionality

## Continuous Integration

Tests are automatically run in the CI pipeline when code is pushed to the repository. Make sure all tests pass locally before pushing your changes.

## Adding New Tests

When adding new functionality:

1. Create a new test file or add tests to an existing file
2. Follow the existing test patterns and conventions
3. Ensure tests cover both success and failure cases
4. Test edge cases and potential error conditions

## Mocking Dependencies

- For JavaScript tests, use Vitest's mocking capabilities (`vi.mock()`, `vi.fn()`)
- For PHP tests, use PHPUnit's mocking features or Laravel's built-in testing helpers

## Database Testing

Feature tests use the `RefreshDatabase` trait to ensure a clean database state for each test. This means each test runs in isolation with a fresh database schema.
