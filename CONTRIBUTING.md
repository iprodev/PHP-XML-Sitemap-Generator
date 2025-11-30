# Contributing to PHP XML Sitemap Generator Pro

First off, thank you for considering contributing to this project! 🎉

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Commit Message Guidelines](#commit-message-guidelines)

## 📜 Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment. Be kind, be professional.

## 🤝 How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates.

**When submitting a bug report, include:**

- PHP version
- Operating system
- Steps to reproduce
- Expected behavior
- Actual behavior
- Error messages/stack traces
- Configuration used

**Example:**

```markdown
**PHP Version**: 8.2.0
**OS**: Ubuntu 22.04
**Command**: `php bin/sitemap --url=https://example.com --cache-enabled`

**Expected**: Sitemap generation completes successfully
**Actual**: Fatal error: Call to undefined method...

**Stack Trace**:
```
[paste stack trace]
```
```

### Suggesting Features

Feature suggestions are welcome! Please provide:

- Clear description of the feature
- Use case / why it's needed
- Possible implementation approach
- Examples from similar tools

### Submitting Code

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Update documentation
6. Submit a pull request

## 🛠️ Development Setup

### Prerequisites

- PHP >= 8.0
- Composer
- Git
- Docker (optional)

### Setup

```bash
# Clone repository
git clone https://github.com/iprodev/sitemap-generator-pro.git
cd sitemap-generator-pro

# Install dependencies
make install-dev

# Setup environment
cp .env.example .env

# Create directories
mkdir -p output cache logs

# Run tests
make test
```

### Using Docker

```bash
# Start services
docker-compose up -d

# Run tests in container
docker-compose exec app composer test

# Open shell
docker-compose exec app sh
```

### Using Make

```bash
make help           # Show available commands
make install        # Install dependencies
make test          # Run tests
make lint          # Check code style
make fix           # Fix code style
make analyze       # Run static analysis
make check         # Run all checks
```

## 📝 Coding Standards

### PSR-12

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard.

```bash
# Check code style
make lint

# Fix automatically
make fix
```

### Type Hints

Always use type hints:

```php
// ✅ Good
public function crawl(string $url, int $maxPages = 10000): array

// ❌ Bad
public function crawl($url, $maxPages = 10000)
```

### Documentation

All public methods must have PHPDoc:

```php
/**
 * Crawl website starting from given URL
 *
 * @param string $url Starting URL
 * @param int $maxPages Maximum pages to crawl
 * @param int $maxDepth Maximum link depth
 * @return array<int, array{url: string, status: int}>
 * @throws \InvalidArgumentException If URL is invalid
 */
public function crawl(string $url, int $maxPages, int $maxDepth): array
{
    // ...
}
```

### Naming Conventions

```php
// Classes: PascalCase
class SitemapWriter {}

// Methods: camelCase
public function generateSitemap() {}

// Properties: camelCase
private string $outputPath;

// Constants: UPPER_SNAKE_CASE
const MAX_PAGES = 50000;

// Interfaces: PascalCase + Interface suffix
interface CacheInterface {}
```

### Error Handling

```php
// ✅ Good - Specific exceptions
if (!$url) {
    throw new \InvalidArgumentException('URL cannot be empty');
}

// ✅ Good - Try-catch with logging
try {
    $result = $this->processUrl($url);
} catch (\Throwable $e) {
    $this->logger->error('Failed to process URL', [
        'url' => $url,
        'error' => $e->getMessage()
    ]);
    throw new \RuntimeException('Processing failed', 0, $e);
}

// ❌ Bad - Silent failures
try {
    $result = $this->processUrl($url);
} catch (\Exception $e) {
    // Nothing
}
```

## 🧪 Testing

### Running Tests

```bash
# All tests
make test

# With coverage
make test-coverage

# Specific test
vendor/bin/phpunit tests/ParserTest.php
```

### Writing Tests

Every new feature must include tests:

```php
namespace IProDev\Sitemap\Tests;

use PHPUnit\Framework\TestCase;
use IProDev\Sitemap\MyNewClass;

class MyNewClassTest extends TestCase
{
    public function testFeatureWorks(): void
    {
        $instance = new MyNewClass();
        $result = $instance->doSomething('input');
        
        $this->assertEquals('expected', $result);
    }
    
    public function testHandlesErrors(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $instance = new MyNewClass();
        $instance->doSomething('');
    }
}
```

### Test Coverage

Aim for >80% code coverage for new features.

## 🔄 Pull Request Process

### Before Submitting

1. **Update your fork**
   ```bash
   git fetch upstream
   git rebase upstream/main
   ```

2. **Run all checks**
   ```bash
   make check
   ```

3. **Update documentation**
   - Update README.md if adding features
   - Add examples if applicable
   - Update CHANGELOG.md

4. **Add tests**
   - Unit tests for new features
   - Integration tests if applicable

### PR Title Format

Use conventional commits format:

```
feat: add video sitemap support
fix: resolve memory leak in crawler
docs: update installation instructions
test: add tests for rate limiter
refactor: improve cache implementation
perf: optimize URL filtering
```

### PR Description Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
How has this been tested?

## Checklist
- [ ] Code follows PSR-12
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] All tests passing
- [ ] No breaking changes (or documented)
```

### Review Process

1. Automated tests run via GitHub Actions
2. Code review by maintainers
3. Address feedback
4. Merge when approved

## 💬 Commit Message Guidelines

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `style`: Code style (formatting)
- `refactor`: Code refactoring
- `perf`: Performance improvement
- `test`: Tests
- `chore`: Maintenance tasks

### Examples

```bash
# Simple
feat: add Redis cache support

# With scope
fix(crawler): resolve race condition in concurrent crawling

# With body
feat(sitemap): add video sitemap support

Add VideoSitemapWriter class with support for:
- YouTube videos
- Vimeo videos
- HTML5 video tags

Includes tests and documentation.

Closes #123
```

## 🎯 Development Workflow

### Feature Development

```bash
# 1. Create branch
git checkout -b feat/my-feature

# 2. Make changes
# ... code, code, code ...

# 3. Test
make test

# 4. Commit
git add .
git commit -m "feat: add my awesome feature"

# 5. Push
git push origin feat/my-feature

# 6. Create PR on GitHub
```

### Bug Fixes

```bash
# 1. Create branch
git checkout -b fix/issue-123

# 2. Fix bug
# ... fix, fix, fix ...

# 3. Add test
# Add regression test

# 4. Commit
git commit -m "fix: resolve issue with URL parsing

Fixes #123"

# 5. Push and create PR
git push origin fix/issue-123
```

## 🏗️ Project Structure

```
sitemap-generator-pro/
├── src/               # Source code
│   ├── Analyzer/      # SEO and quality analysis
│   ├── Cache/         # Caching implementations
│   ├── Database/      # Database storage
│   ├── Filter/        # URL filtering
│   ├── Scheduler/     # Scheduled crawling
│   ├── Sitemap/       # Sitemap writers
│   └── *.php          # Core classes
├── tests/             # Unit tests
├── examples/          # Usage examples
├── bin/               # CLI scripts
├── .github/           # GitHub Actions
└── docs/              # Documentation
```

## 📚 Resources

- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Conventional Commits](https://www.conventionalcommits.org/)

## ❓ Questions?

- Open a [Discussion](https://github.com/iprodev/sitemap-generator-pro/discussions)
- Join our [Discord](https://discord.gg/example) (if available)
- Email: support@iprodev.com

## 🙏 Thank You!

Your contributions make this project better for everyone. Thank you for taking the time to contribute!

---

Happy coding! 🚀
