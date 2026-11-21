<?php

namespace IProDev\Sitemap\Tests;

use IProDev\Sitemap\Version;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    public function testVersionMatchesTheLatestChangelogEntry(): void
    {
        $changelog = (string)file_get_contents(__DIR__ . '/../CHANGELOG.md');
        $this->assertMatchesRegularExpression('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog);

        preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $matches);
        $this->assertSame($matches[1], Version::NUMBER, 'Update Version::NUMBER together with CHANGELOG.md');
    }
}
