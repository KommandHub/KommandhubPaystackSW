<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Logging;

use Kommandhub\PaystackSW\Setting\Service\Config;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConfigurableLoggerTest extends TestCase
{
    private MockObject&LoggerInterface $innerLogger;
    private MockObject&Config $config;
    private ConfigurableLogger $configurableLogger;

    protected function setUp(): void
    {
        $this->innerLogger = $this->createMock(LoggerInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->configurableLogger = new ConfigurableLogger($this->innerLogger, $this->config);
    }

    public function testLogIsSkippedIfDebuggingIsDisabled(): void
    {
        $this->config->method('getBool')->with('enableDebugging', null)->willReturn(false);
        $this->innerLogger->expects($this->never())->method('log');

        $this->configurableLogger->info('test message');
    }

    public function testLogIsAllowedIfLevelIsSelected(): void
    {
        $this->config->method('getBool')->with('enableDebugging', null)->willReturn(true);
        $this->config->method('getArray')->with('logLevels', null)->willReturn(['info', 'error']);

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with('info', 'test message', []);

        $this->configurableLogger->info('test message');
    }

    public function testLogIsSkippedIfLevelIsNotSelected(): void
    {
        $this->config->method('getBool')->with('enableDebugging', null)->willReturn(true);
        $this->config->method('getArray')->with('logLevels', null)->willReturn(['error']);

        $this->innerLogger->expects($this->never())->method('log');

        $this->configurableLogger->info('test message');
    }

    public function testLogIsAllowedIfNoLevelsAreSelectedButDebuggingIsEnabled(): void
    {
        $this->config->method('getBool')->with('enableDebugging', null)->willReturn(true);
        $this->config->method('getArray')->with('logLevels', null)->willReturn([]);

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with('info', 'test message', []);

        $this->configurableLogger->info('test message');
    }

    public function testAlwaysLoggedLevelsAreLoggedEvenIfDebuggingIsDisabled(): void
    {
        $this->config->method('getBool')->with('enableDebugging', null)->willReturn(false);

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with('error', 'error message', []);

        $this->configurableLogger->error('error message');
    }

    public function testLogWithNonScalarLevel(): void
    {
        $this->config->method('getBool')->with('enableDebugging', null)->willReturn(true);
        $this->config->method('getArray')->with('logLevels', null)->willReturn(['info']);

        $level = new class() implements \Stringable {
            public function __toString(): string
            {
                return 'info';
            }
        };

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with('info', 'test message', []);

        $this->configurableLogger->log($level, 'test message');
    }

    public function testLogWithUnsupportedLevelType(): void
    {
        $this->config->method('getBool')->with('enableDebugging', null)->willReturn(false);

        // 'unknown' is not in ALWAYS_LOGGED, so it should be skipped when debugging is disabled
        $this->innerLogger->expects($this->never())->method('log');

        $this->configurableLogger->log([], 'test message');
    }

    public function testAlwaysLoggedLevelsAreLoggedEvenIfLevelNotSelected(): void
    {
        $this->config->method('getBool')->with('enableDebugging', null)->willReturn(true);
        $this->config->method('getArray')->with('logLevels', null)->willReturn(['info']);

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with('error', 'error message', []);

        $this->configurableLogger->error('error message');
    }

    /**
     * enableDebugging is a sales-channel-scopable setting. Reading it without a
     * sales channel id resolved only the global scope, so a merchant who enabled
     * debugging on a single sales channel got nothing at all.
     */
    public function testDebuggingIsResolvedAgainstTheSalesChannelInContext(): void
    {
        $this->config->expects($this->once())
            ->method('getBool')
            ->with('enableDebugging', 'sales-channel-id')
            ->willReturn(true);
        $this->config->method('getArray')
            ->with('logLevels', 'sales-channel-id')
            ->willReturn([]);

        $this->innerLogger->expects($this->once())->method('log');

        $this->configurableLogger->info('scoped', [
            ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => 'sales-channel-id',
        ]);
    }

    public function testDebuggingDisabledForThatSalesChannelSuppressesTheEntry(): void
    {
        $this->config->method('getBool')
            ->with('enableDebugging', 'sales-channel-id')
            ->willReturn(false);

        $this->innerLogger->expects($this->never())->method('log');

        $this->configurableLogger->info('scoped', [
            ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => 'sales-channel-id',
        ]);
    }

    public function testLogLevelsAreResolvedAgainstTheSalesChannelInContext(): void
    {
        $this->config->method('getBool')->willReturn(true);
        $this->config->expects($this->once())
            ->method('getArray')
            ->with('logLevels', 'sales-channel-id')
            ->willReturn(['info']);

        $this->innerLogger->expects($this->never())->method('log');

        $this->configurableLogger->debug('scoped', [
            ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => 'sales-channel-id',
        ]);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableSalesChannelIdProvider(): array
    {
        return [
            'absent' => [null],
            'empty string' => [''],
            'not a string' => [123],
        ];
    }

    /**
     * An unusable id must fall back to the global scope, preserving the previous
     * behaviour for every caller that does not supply one.
     *
     * @param mixed $salesChannelId
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unusableSalesChannelIdProvider')]
    public function testUnusableSalesChannelIdFallsBackToGlobalScope($salesChannelId): void
    {
        $this->config->expects($this->once())
            ->method('getBool')
            ->with('enableDebugging', null)
            ->willReturn(true);
        $this->config->method('getArray')->with('logLevels', null)->willReturn([]);

        $this->innerLogger->expects($this->once())->method('log');

        $this->configurableLogger->info('global', [
            ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $salesChannelId,
        ]);
    }

    /**
     * The id stays in the forwarded context: it is useful structured data on the
     * entry itself, not just a routing hint.
     */
    public function testSalesChannelIdRemainsInTheForwardedContext(): void
    {
        $this->config->method('getBool')->willReturn(true);
        $this->config->method('getArray')->willReturn([]);

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with('info', 'kept', [
                ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => 'sales-channel-id',
                'reference' => 'ref-1',
            ]);

        $this->configurableLogger->info('kept', [
            ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => 'sales-channel-id',
            'reference' => 'ref-1',
        ]);
    }

    /**
     * A failure on a channel with debugging off must still be recorded.
     */
    public function testSeverityIgnoresScopedDebuggingToo(): void
    {
        $this->config->expects($this->never())->method('getBool');

        $this->innerLogger->expects($this->once())->method('log');

        $this->configurableLogger->error('boom', [
            ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => 'sales-channel-id',
        ]);
    }
}
