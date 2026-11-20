<?php

namespace IProDev\Sitemap\Tests;

use PHPUnit\Framework\TestCase;
use IProDev\Sitemap\RobotsTxt;

class RobotsTxtTest extends TestCase
{
    public function testParseSimpleDisallow(): void
    {
        $content = <<<ROBOTS
User-agent: *
Disallow: /admin/
Disallow: /private/
ROBOTS;

        $robots = new RobotsTxt();
        $reflection = new \ReflectionClass($robots);
        $method = $reflection->getMethod('parse');
        $method->setAccessible(true);
        $method->invoke($robots, $content);

        $this->assertFalse($robots->isAllowed('https://example.com/admin/page'));
        $this->assertFalse($robots->isAllowed('https://example.com/private/file'));
        $this->assertTrue($robots->isAllowed('https://example.com/public/page'));
    }

    public function testParseWithAllow(): void
    {
        $content = <<<ROBOTS
User-agent: *
Disallow: /admin/
Allow: /admin/public/
ROBOTS;

        $robots = new RobotsTxt();
        $reflection = new \ReflectionClass($robots);
        $method = $reflection->getMethod('parse');
        $method->setAccessible(true);
        $method->invoke($robots, $content);

        $this->assertTrue($robots->isAllowed('https://example.com/admin/public/page'));
        $this->assertFalse($robots->isAllowed('https://example.com/admin/private/page'));
    }

    public function testWildcardMatching(): void
    {
        $content = <<<ROBOTS
User-agent: *
Disallow: /*.pdf$
Disallow: /search?*
ROBOTS;

        $robots = new RobotsTxt();
        $reflection = new \ReflectionClass($robots);
        $method = $reflection->getMethod('parse');
        $method->setAccessible(true);
        $method->invoke($robots, $content);

        $this->assertFalse($robots->isAllowed('https://example.com/document.pdf'));
        $this->assertTrue($robots->isAllowed('https://example.com/document.pdf.html'));
        $this->assertFalse($robots->isAllowed('https://example.com/search?q=test'));
    }

    public function testEmptyRobotsTxt(): void
    {
        $robots = new RobotsTxt();

        $this->assertTrue($robots->isAllowed('https://example.com/any/path'));
    }

    public function testCommentsIgnored(): void
    {
        $content = <<<ROBOTS
# This is a comment
User-agent: * # inline comment
Disallow: /admin/ # another comment
ROBOTS;

        $robots = new RobotsTxt();
        $reflection = new \ReflectionClass($robots);
        $method = $reflection->getMethod('parse');
        $method->setAccessible(true);
        $method->invoke($robots, $content);

        $this->assertFalse($robots->isAllowed('https://example.com/admin/page'));
    }

    public function testMultipleUserAgents(): void
    {
        $content = <<<ROBOTS
User-agent: badbot
Disallow: /

User-agent: *
Disallow: /admin/
ROBOTS;

        $robots = new RobotsTxt();
        $reflection = new \ReflectionClass($robots);
        $method = $reflection->getMethod('parse');
        $method->setAccessible(true);
        $method->invoke($robots, $content);

        // Should only apply * rules
        $this->assertFalse($robots->isAllowed('https://example.com/admin/'));
        $this->assertTrue($robots->isAllowed('https://example.com/public/'));
    }

    public function testGetDisallowsAndAllows(): void
    {
        $content = <<<ROBOTS
User-agent: *
Disallow: /admin/
Disallow: /private/
Allow: /public/
ROBOTS;

        $robots = new RobotsTxt();
        $reflection = new \ReflectionClass($robots);
        $method = $reflection->getMethod('parse');
        $method->setAccessible(true);
        $method->invoke($robots, $content);

        $disallows = $robots->getDisallows();
        $allows = $robots->getAllows();

        $this->assertCount(2, $disallows);
        $this->assertCount(1, $allows);
        $this->assertContains('/admin/', $disallows);
        $this->assertContains('/public/', $allows);
    }

    public function testFromString(): void
    {
        $robots = RobotsTxt::fromString("User-agent: *\nDisallow: /admin/\n");

        $this->assertFalse($robots->isAllowed('https://example.com/admin/x'));
        $this->assertTrue($robots->isAllowed('https://example.com/'));
    }

    public function testConsecutiveUserAgentLinesShareOneGroup(): void
    {
        $robots = RobotsTxt::fromString(
            "User-agent: *\nUser-agent: SomeOtherBot\nDisallow: /blocked/\n"
        );

        $this->assertFalse($robots->isAllowed('https://example.com/blocked/page'));
    }

    public function testLongestMatchingRuleWins(): void
    {
        $robots = RobotsTxt::fromString("User-agent: *\nAllow: /p\nDisallow: /private/\n");

        $this->assertFalse($robots->isAllowed('https://example.com/private/secret'));
        $this->assertTrue($robots->isAllowed('https://example.com/public'));
    }

    public function testAllowWinsWhenEquallySpecific(): void
    {
        $robots = RobotsTxt::fromString("User-agent: *\nDisallow: /page\nAllow: /page\n");

        $this->assertTrue($robots->isAllowed('https://example.com/page'));
    }

    public function testOwnUserAgentGroupReplacesWildcardGroup(): void
    {
        $robots = RobotsTxt::fromString(
            "User-agent: php-sitemap-generator\nDisallow: /mine/\n\nUser-agent: *\nDisallow: /all/\n"
        );

        $this->assertFalse($robots->isAllowed('https://example.com/mine/x'));
        $this->assertTrue($robots->isAllowed('https://example.com/all/x'));
    }

    public function testGroupsForOtherCrawlersAreIgnored(): void
    {
        $robots = RobotsTxt::fromString(
            "User-agent: Googlebot\nDisallow: /google-only/\n\nUser-agent: *\nDisallow:\n"
        );

        $this->assertTrue($robots->isAllowed('https://example.com/google-only/x'));
    }
}
