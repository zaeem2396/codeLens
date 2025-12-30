<?php

declare(strict_types=1);

namespace CodeLens\Tests\Unit\Core;

use CodeLens\Core\Config\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Configuration class.
 */
class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $config = new Configuration();

        $this->assertIsArray($config->getScanPaths());
        $this->assertIsArray($config->getExcludePaths());
        $this->assertIsArray($config->getFileExtensions());
        $this->assertEquals('json', $config->getStorageDriver());
        $this->assertEquals('codelens', $config->getCacheDirectory());
        $this->assertTrue($config->isUiEnabled());
    }

    public function testCustomConfiguration(): void
    {
        $config = new Configuration([
            'scan_paths' => ['custom/path'],
            'storage_driver' => 'sqlite',
            'ui_enabled' => false,
        ]);

        $this->assertEquals(['custom/path'], $config->getScanPaths());
        $this->assertEquals('sqlite', $config->getStorageDriver());
        $this->assertFalse($config->isUiEnabled());
    }

    public function testEnvironmentRestrictions(): void
    {
        $config = new Configuration([
            'enabled_environments' => ['local', 'staging'],
        ]);

        $this->assertTrue($config->isEnabledForEnvironment('local'));
        $this->assertTrue($config->isEnabledForEnvironment('staging'));
        $this->assertTrue($config->isEnabledForEnvironment('LOCAL')); // Case insensitive
        $this->assertFalse($config->isEnabledForEnvironment('production'));
    }

    public function testFromArray(): void
    {
        $config = Configuration::fromArray([
            'scan_paths' => ['src', 'lib'],
        ]);

        $this->assertEquals(['src', 'lib'], $config->getScanPaths());
    }

    public function testMergeConfiguration(): void
    {
        $config = new Configuration(['scan_paths' => ['app']]);
        $config->merge(['storage_driver' => 'sqlite']);

        $this->assertEquals(['app'], $config->getScanPaths());
        $this->assertEquals('sqlite', $config->getStorageDriver());
    }

    public function testGetAndSet(): void
    {
        $config = new Configuration();

        $config->set('custom_key', 'custom_value');
        $this->assertEquals('custom_value', $config->get('custom_key'));

        $this->assertEquals('default', $config->get('nonexistent', 'default'));
    }

    public function testToArray(): void
    {
        $config = new Configuration(['scan_paths' => ['app']]);
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(['app'], $array['scan_paths']);
    }
}
