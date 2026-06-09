<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Storefront\Validation;

use Kommandhub\PaystackSW\Service\Config;
use Kommandhub\PaystackSW\Storefront\Validation\BankValidationFactory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class BankValidationFactoryTest extends TestCase
{
    public function testCreateReturnsCorrectDefinitionWhenBvnIsShownAndOptional(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getBool')->willReturnCallback(function($key, $salesChannelId) {
            if ($key === 'showBvnField') return true;
            if ($key === 'requireBvn') return false;
            return false;
        });

        $factory = new BankValidationFactory($config);
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');

        $definition = $factory->create($context);

        $this->assertEquals('paystack.bank.save', $definition->getName());

        $this->assertArrayHasKey('bvn', $definition->getProperties());
        $bvnConstraints = $definition->getProperties()['bvn'];
        $this->assertCount(1, $bvnConstraints);
        $this->assertInstanceOf(Length::class, $bvnConstraints[0]);
    }

    public function testCreateReturnsCorrectDefinitionWhenBvnIsShownAndRequired(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getBool')->willReturnCallback(function($key, $salesChannelId) {
            if ($key === 'showBvnField') return true;
            if ($key === 'requireBvn') return true;
            return false;
        });

        $factory = new BankValidationFactory($config);
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');

        $definition = $factory->create($context);

        $this->assertArrayHasKey('bvn', $definition->getProperties());
        $bvnConstraints = $definition->getProperties()['bvn'];
        $this->assertCount(2, $bvnConstraints);
        $this->assertInstanceOf(Length::class, $bvnConstraints[0]);
        $this->assertInstanceOf(NotBlank::class, $bvnConstraints[1]);
    }

    public function testCreateReturnsCorrectDefinitionWhenBvnIsHidden(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getBool')->with('showBvnField', 'sales-channel-id')->willReturn(false);

        $factory = new BankValidationFactory($config);
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');

        $definition = $factory->create($context);

        $this->assertArrayNotHasKey('bvn', $definition->getProperties());
    }

    public function testUpdateReturnsCorrectDefinition(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getBool')->willReturn(true);
        $factory = new BankValidationFactory($config);
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');

        $definition = $factory->update($context);

        $this->assertEquals('paystack.bank.save', $definition->getName());
        $this->assertArrayHasKey('bankName', $definition->getProperties());
        $this->assertArrayHasKey('bankCode', $definition->getProperties());
        $this->assertArrayHasKey('accountNumber', $definition->getProperties());
        $this->assertArrayHasKey('accountName', $definition->getProperties());
    }
}
