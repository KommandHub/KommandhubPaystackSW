<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Core\Config;

use Kommandhub\PaystackSW\Core\Config\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(Config::class)]
class ConfigTest extends TestCase
{
    private SystemConfigService $systemConfigService;
    private Config $config;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->config = new Config($this->systemConfigService);
    }

    public function testGet(): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('get')
            ->with(Config::KEY . 'test_key', 'channel-id')
            ->willReturn('test_value');

        $this->assertEquals('test_value', $this->config->get('test_key', null, 'channel-id'));
    }

    public function testGetReturnsDefault(): void
    {
        $this->systemConfigService->method('get')->willReturn(null);
        $this->assertEquals('default', $this->config->get('test_key', 'default'));
    }

    public function testGetString(): void
    {
        $this->systemConfigService->method('get')->willReturn('string_value');
        $this->assertEquals('string_value', $this->config->getString('test_key'));
    }

    public function testGetStringReturnsEmptyString(): void
    {
        $this->systemConfigService->method('get')->willReturn(null);
        $this->assertEquals('', $this->config->getString('test_key'));

        $this->systemConfigService->method('get')->willReturn(123);
        $this->assertEquals('', $this->config->getString('test_key'));
    }

    public function testGetBool(): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('getBool')
            ->with(Config::KEY . 'bool_key', 'channel-id')
            ->willReturn(true);

        $this->assertTrue($this->config->getBool('bool_key', 'channel-id'));
    }
}
