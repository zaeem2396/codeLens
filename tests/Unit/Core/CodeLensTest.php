<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core;

use CodeLens\Core\CodeLens;
use CodeLens\Core\Config\Configuration;
use CodeLens\Core\Contracts\FrameworkAdapterInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the CodeLens main class.
 */
class CodeLensTest extends TestCase
{
    protected function tearDown(): void
    {
        CodeLens::resetInstance();
    }

    public function testGetInstance(): void
    {
        $instance = CodeLens::getInstance();

        $this->assertInstanceOf(CodeLens::class, $instance);
        $this->assertSame($instance, CodeLens::getInstance());
    }

    public function testGetInstanceWithConfiguration(): void
    {
        $config = new Configuration(['scan_paths' => ['custom']]);
        $instance = CodeLens::getInstance($config);

        $this->assertSame($config, $instance->getConfiguration());
    }

    public function testInitialize(): void
    {
        $adapter = $this->createMockAdapter('local');
        $config = new Configuration(['enabled_environments' => ['local']]);

        $instance = CodeLens::getInstance($config);
        $instance->initialize($adapter);

        $this->assertTrue($instance->isInitialized());
        $this->assertTrue($instance->isEnabled());
    }

    public function testIsDisabledInProductionEnvironment(): void
    {
        $adapter = $this->createMockAdapter('production');
        $config = new Configuration(['enabled_environments' => ['local']]);

        $instance = CodeLens::getInstance($config);
        $instance->initialize($adapter);

        // Should silently disable
        $this->assertFalse($instance->isEnabled());
    }

    public function testGetFrameworkInfo(): void
    {
        $adapter = $this->createMockAdapter('local', 'TestFramework', '1.0.0');
        $config = new Configuration(['enabled_environments' => ['local']]);

        $instance = CodeLens::getInstance($config);
        $instance->initialize($adapter);

        $this->assertEquals('TestFramework', $instance->getFrameworkName());
        $this->assertEquals('1.0.0', $instance->getFrameworkVersion());
    }

    public function testGetAdapterReturnsNullBeforeInitialization(): void
    {
        $instance = CodeLens::getInstance();

        $this->assertNull($instance->getAdapter());
        $this->assertEquals('Unknown', $instance->getFrameworkName());
        $this->assertEquals('Unknown', $instance->getFrameworkVersion());
    }

    public function testResetInstance(): void
    {
        $instance1 = CodeLens::getInstance();
        CodeLens::resetInstance();
        $instance2 = CodeLens::getInstance();

        $this->assertNotSame($instance1, $instance2);
    }

    private function createMockAdapter(
        string $environment,
        string $name = 'TestFramework',
        string $version = '1.0.0',
    ): FrameworkAdapterInterface {
        $adapter = $this->createMock(FrameworkAdapterInterface::class);

        $adapter->method('getCurrentEnvironment')->willReturn($environment);
        $adapter->method('getFrameworkName')->willReturn($name);
        $adapter->method('getFrameworkVersion')->willReturn($version);
        $adapter->method('getBasePath')->willReturn('/test/path');
        $adapter->method('getSourcePath')->willReturn('/test/path/src');
        $adapter->method('getStoragePath')->willReturn('/test/path/storage');
        $adapter->method('isDebugMode')->willReturn(true);
        $adapter->method('getRoutes')->willReturn([]);
        $adapter->method('getServiceBindings')->willReturn([]);

        return $adapter;
    }
}
