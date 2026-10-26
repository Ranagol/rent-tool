# PHPUnit Quick Reference for RentTool

## Installation Status ✅
- ✅ PHPUnit 13.4.3 installed
- ✅ Test directory structure created (Unit, Functional, Integration)
- ✅ `phpunit.xml.dist` configured
- ✅ Example test passing

## Running Tests

### Run all tests
```bash
docker compose exec php bin/phpunit
```

### Run specific test file
```bash
docker compose exec php bin/phpunit tests/Unit/ExampleTest.php
```

### Run specific test suite
```bash
docker compose exec php bin/phpunit --testsuite Unit
docker compose exec php bin/phpunit --testsuite Functional
docker compose exec php bin/phpunit --testsuite Integration
```

### Run tests matching a filter
```bash
docker compose exec php bin/phpunit --filter "testUserCanRegister"
```

### Run tests with specific group (if tagged with `@group`)
```bash
docker compose exec php bin/phpunit --group "critical"
```

### Run with coverage report
```bash
docker compose exec php bin/phpunit --coverage-text
```

### Run in separate processes (better isolation)
```bash
docker compose exec php bin/phpunit --process-isolation
```

## Test Database
- Separate database: `app_test`
- Configured in `phpunit.xml.dist`
- Automatically uses test environment
- Transactions rolled back after each test (no permanent changes)

## Creating New Tests

### Using make:test (recommended)
```bash
docker compose exec php bin/console make:test
```

This will prompt:
- Test type: `UnitTest` or `FunctionalTest`
- Test class name (e.g., `UserTest`)
- Target class to test

### Example test locations
- `tests/Unit/Entity/UserTest.php`
- `tests/Functional/Controller/UserControllerTest.php`
- `tests/Integration/Rental/RentalCreationTest.php`

## Test Structure Template

### Unit Test
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
        
        $this->assertSame('test@example.com', $user->getEmail());
    }
}
```

### Functional Test
```php
<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserControllerTest extends WebTestCase
{
    public function testUserCanRegister(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');
        
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Register')->form();
        $form['email'] = 'test@example.com';
        $client->submit($form);
        
        $this->assertResponseRedirects('/login');
    }
}
```

### Integration Test
```php
<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Entity\Tool;
use App\Repository\ToolRepository;

class RentalCreationTest extends KernelTestCase
{
    public function testRentalCreationLocksTools(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        $toolRepo = self::getContainer()->get(ToolRepository::class);
        
        // Create test tool
        $tool = new Tool();
        $tool->setName('Drill');
        $tool->setStatus('available');
        $em->persist($tool);
        $em->flush();
        
        // Assert tool was created
        $this->assertNotNull($toolRepo->find($tool->getId()));
    }
}
```

## Common Assertions

### HTTP Response
```php
$this->assertResponseIsSuccessful();        // 2xx
$this->assertResponseStatusCodeSame(200);   // Exact status
$this->assertResponseRedirects('/path');    // Redirect check
```

### Content
```php
$this->assertSelectorTextContains('h1', 'Welcome');
$this->assertSelectorExists('form#loginForm');
$this->assertSelectorNotExists('.error-message');
```

### Forms
```php
$form = $crawler->selectButton('Submit')->form();
$this->assertFormValue('form', 'email', 'test@example.com');
```

### Database
```php
$this->assertCount(5, $collection);
$this->assertSame('John', $user->getName());
$this->assertNull($user->getDeletedAt());
```

## Test Isolation
- Each test runs in a database transaction
- Transaction is rolled back after test completes
- No test data persists between tests
- Tests run independently (no cross-contamination)

## Before You Commit
```bash
# 1. Run tests for your feature
docker compose exec php bin/phpunit tests/Unit/Entity/YourEntityTest.php

# 2. Run full test suite
docker compose exec php bin/phpunit

# 3. Check coverage
docker compose exec php bin/phpunit --coverage-text

# 4. If passing, commit with confidence!
```

## Coverage Goals
- **Business Logic**: 80%+ coverage
- **Controllers**: 70%+ coverage
- **Entities/Models**: 85%+ coverage
- **Views/Templates**: Minimal coverage (integration tests enough)

## Troubleshooting

### "No tests executed!"
- Check test file naming: must end with `Test.php`
- Check test methods: must start with `test`
- Check namespace: must be under `App\Tests\`

### "Database connection failed"
- Ensure Docker containers are running: `docker compose ps`
- Check test database URL in `phpunit.xml.dist`
- Verify PostgreSQL is healthy: `docker compose exec php bin/console doctrine:database:create --if-not-exists --env=test`

### "Transaction not rolled back"
- Use `KernelTestCase` for integration tests, not direct Doctrine calls
- Database isolation is automatic

---

## Next: Phase 1.1 Tests

Once we start Phase 1.1 (User entity), we'll create:
1. `tests/Unit/Entity/UserTest.php` — User entity validation
2. `tests/Functional/UserRegistrationTest.php` — Registration form, validation
3. `tests/Functional/UserLoginTest.php` — Login form, authentication

Tests will be written **before or alongside** the implementation.
