<?php

declare(strict_types=1);

namespace Tests\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Static checks: every entry HTML page loads API config and key assets (no HTTP server).
 */
final class HtmlPagesContractTest extends TestCase
{
    /**
     * @return list<string>
     */
    private static function htmlFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = glob($root . '/*.html') ?: [];

        return array_values(array_filter($files, static function (string $path): bool {
            return is_readable($path);
        }));
    }

    public function testEachRootHtmlIncludesApiConfigScript(): void
    {
        foreach (self::htmlFiles() as $file) {
            $html = (string) file_get_contents($file);
            $base = basename($file);
            $this->assertStringContainsString(
                'assets/js/api-config.js',
                $html,
                $base . ' must include api-config.js'
            );
            $this->assertStringContainsString(
                'LoveMatchApiConfig',
                $html,
                $base . ' should reference LoveMatchApiConfig'
            );
        }
    }

    public function testEachRootHtmlHasUtf8Charset(): void
    {
        foreach (self::htmlFiles() as $file) {
            $html = (string) file_get_contents($file);
            $this->assertMatchesRegularExpression(
                '/charset\s*=\s*["\']UTF-8["\']/i',
                $html,
                basename($file) . ' should declare UTF-8'
            );
        }
    }

    public function testEachRootHtmlReferencesFavicon(): void
    {
        foreach (self::htmlFiles() as $file) {
            $html = (string) file_get_contents($file);
            $this->assertStringContainsString(
                'favicon',
                $html,
                basename($file) . ' should reference favicon'
            );
        }
    }

    public function testIndexHtmlIncludesRegisterPaymentBundle(): void
    {
        $root = dirname(__DIR__, 2);
        $html = (string) file_get_contents($root . '/index.html');
        $this->assertStringContainsString('register-payment.js', $html);
        $this->assertStringContainsString('register-payment.css', $html);
    }

    public function testAdminHtmlHasDashboardSections(): void
    {
        $root = dirname(__DIR__, 2);
        $html = (string) file_get_contents($root . '/admin.html');
        $this->assertStringContainsString('pendingUpgradesList', $html);
        $this->assertStringContainsString('/admin/dashboard', $html);
    }
}
