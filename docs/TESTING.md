# Testing Strategy for RentTool

## Overview
Every new feature must have tests. Tests are organized into three categories:
- **Unit**: Test individual classes/methods in isolation
- **Functional**: Test controllers, forms, routes (HTTP-level)
- **Integration**: Test features that span multiple components (transactions, concurrency, etc.)

## Test Infrastructure

### Files
- `phpunit.xml.dist` — Main PHPUnit configuration
- `tests/bootstrap.php` — Test environment setup
- `tests/Unit/` — Unit tests
- `tests/Functional/` — Functional/HTTP tests
- `tests/Integration/` — Integration tests

### Running Tests

```bash
# Run all tests
docker compose exec php bin/phpunit

# Run a specific test file
docker compose exec php bin/phpunit tests/Unit/MyTest.php

# Run a test suite
docker compose exec php bin/phpunit --testsuite Unit

# Run with coverage report
docker compose exec php bin/phpunit --coverage-html coverage/
```

### Test Database
- Separate test database: `app_test` (see phpunit.xml.dist `DATABASE_URL`)
- Automatically created/reset per test run
- Doctrine migrations run in test environment

---

## Test Organization by Feature

### Phase 1: User Entity & Security
**Unit Tests:**
- User entity validation (email format, password requirements)
- Password hashing

**Functional Tests:**
- User registration form submission
- Registration validation errors
- User login/logout
- Protected routes require authentication
- Unauthenticated redirect to login

**Integration Tests:**
- User registration → login → access protected route (full flow)

### Phase 2: CRUD Operations
**Unit Tests:**
- Entity validation (all required fields, constraints)
- Repository queries

**Functional Tests:**
- List pages render correctly
- Create form displays and submits
- Edit form pre-fills data
- Delete confirmation and action
- Search/filter functionality
- 404 on non-existent resources

**Integration Tests:**
- Full CRUD flow: create → read → update → delete

### Phase 3: Business Logic
**Unit Tests:**
- Cost calculation logic
- Rental status transitions
- Tool availability checks

**Functional Tests:**
- Create rental button visible only for available tools
- Return rental form displays correct status options

**Integration Tests:**
- Rental creation: transaction, tool locking, status updates
- Concurrent rental attempts (two simultaneous requests for same tool)
- Rental return: cost calculation, tool status updates
- Cancellation: is_cancelled flag, no automatic return

### Phase 4: PDFs
**Unit Tests:**
- PDF content generation (not actual file, just HTML)

**Functional Tests:**
- PDF download returns correct content-type
- PDF contains all required fields (company, customer, dates, costs)

**Integration Tests:**
- Full rental flow → contract generation → invoice generation

---

## Test Conventions

### Naming
- Test classes: `FeatureTest` (e.g., `UserRegistrationTest`, `RentalCreationTest`)
- Test methods: `testSomethingDoesSomething()` (e.g., `testUnregisteredUserCannotAccessRentals()`)
- Descriptive names; avoid `test1()`, `test2()`

### Base Classes
- **Unit tests**: Extend `\PHPUnit\Framework\TestCase`
- **Functional tests**: Extend `Symfony\Bundle\FrameworkBundle\Test\WebTestCase`
- **Integration tests**: Extend `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase`

### Fixtures
- Use `doctrine/doctrine-fixtures-bundle` for test data
- Create fixtures in `tests/Fixtures/`
- Load via `make:fixtures` or manually in test setup

### Assertions
Use Symfony WebTestCase assertions:
```php
$this->assertResponseIsSuccessful();        // 2xx response
$this->assertResponseStatusCodeSame(404);   // Specific status
$this->assertSelectorTextContains('h1', 'Title');
$this->assertFormValue('form', 'name', 'John');
```

---

## Continuous Testing Workflow

1. **Write test first** (or alongside code)
2. **Run test** — it fails (red)
3. **Implement feature** — test passes (green)
4. **Refactor** if needed — test still passes
5. **Run full suite** before committing

### Command Workflow
```bash
# After making a change:
docker compose exec php bin/phpunit tests/Unit/MyFeatureTest.php

# Before committing:
docker compose exec php bin/phpunit

# Check coverage:
docker compose exec php bin/phpunit --coverage-text --coverage-text-show-uncovered-files
```

---

## Database Isolation
- Each test runs in a transaction
- Transaction is rolled back after test (no permanent changes)
- This ensures tests don't interfere with each other

## Coverage Goals
- Aim for **80%+ coverage** on business logic
- Core features (rentals, users, tools) need high coverage
- UI-only code (template rendering) can be lower
- Report in CI/CD pipeline

---

## Example: Simple Test

### Unit Test (e.g., `tests/Unit/Entity/UserTest.php`)
```php
<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserCanBeCreated(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('hashed_password');

        $this->assertSame('test@example.com', $user->getEmail());
    }

    public function testUserPasswordCanBeSet(): void
    {
        $user = new User();
        $user->setPassword('new_password');

        $this->assertSame('new_password', $user->getPassword());
    }
}
```

### Functional Test (e.g., `tests/Functional/UserRegistrationTest.php`)
```php
<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserRegistrationTest extends WebTestCase
{
    public function testRegistrationPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Register');
    }

    public function testUserCanRegister(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $form = $crawler->selectButton('Register')->form();
        $form['email'] = 'newuser@example.com';
        $form['password'] = 'SecurePassword123';
        $client->submit($form);

        $this->assertResponseRedirects('/login');
    }
}
```

---

## CI/CD Integration
When tests are added to the repository:
1. Tests run on every commit/PR
2. Coverage reports generated
3. Tests must pass before merging
4. Coverage must not decrease

---

## Next Steps
1. ✅ PHPUnit installed and configured
2. ✅ Directory structure created (Unit, Functional, Integration)
3. ⏳ Write tests as each feature is implemented
4. ⏳ Run tests before committing

Ready to write your first tests for Phase 1.1 (User entity)?
