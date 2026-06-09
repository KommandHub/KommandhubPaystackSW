<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit;

use Kommandhub\PaystackSW\KommandhubPaystackSW;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class KommandhubPaystackSWTest extends TestCase
{
    private KommandhubPaystackSW $plugin;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->plugin = new KommandhubPaystackSW(true, '');
        $this->container = $this->createMock(ContainerInterface::class);
        $this->plugin->setContainer($this->container);
    }

    public function testExecuteComposerCommands(): void
    {
        $this->assertTrue($this->plugin->executeComposerCommands());
    }

    public function testInstall(): void
    {
        $context = $this->createMock(InstallContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $repository = $this->createMock(EntityRepository::class);
        $customFieldSetRepository = $this->createMock(EntityRepository::class);
        $customFieldSetRelationRepository = $this->createMock(EntityRepository::class);

        $this->container->method('get')->willReturnMap([
            ['payment_method.repository', $repository],
            [PluginIdProvider::class, $this->createMock(PluginIdProvider::class)],
            ['custom_field_set.repository', $customFieldSetRepository],
            ['custom_field_set_relation.repository', $customFieldSetRelationRepository],
        ]);

        // Mock search to return no existing payment method
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn(null);
        $repository->method('searchIds')->willReturn($idSearchResult);

        $repository->expects($this->once())->method('create');

        $this->plugin->install($context);
    }

    public function testActivate(): void
    {
        $context = $this->createMock(ActivateContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $repository = $this->createMock(EntityRepository::class);
        $this->container->method('get')->with('payment_method.repository')->willReturn($repository);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn('payment-method-id');
        $repository->method('searchIds')->willReturn($idSearchResult);

        $repository->expects($this->once())->method('update')->with([
            ['id' => 'payment-method-id', 'active' => true],
        ]);

        $this->plugin->activate($context);
    }

    public function testDeactivate(): void
    {
        $context = $this->createMock(DeactivateContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $repository = $this->createMock(EntityRepository::class);
        $this->container->method('get')->with('payment_method.repository')->willReturn($repository);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn('payment-method-id');
        $repository->method('searchIds')->willReturn($idSearchResult);

        $repository->expects($this->once())->method('update')->with([
            ['id' => 'payment-method-id', 'active' => false],
        ]);

        $this->plugin->deactivate($context);
    }

    public function testUninstall(): void
    {
        $context = $this->createMock(UninstallContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('keepUserData')->willReturn(true);

        $repository = $this->createMock(EntityRepository::class);
        $customFieldSetRepository = $this->createMock(EntityRepository::class);
        $customFieldSetRelationRepository = $this->createMock(EntityRepository::class);

        $this->container->method('get')->willReturnMap([
            ['payment_method.repository', $repository],
            ['custom_field_set.repository', $customFieldSetRepository],
            ['custom_field_set_relation.repository', $customFieldSetRelationRepository],
        ]);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn('payment-method-id');
        $repository->method('searchIds')->willReturn($idSearchResult);

        $repository->expects($this->once())->method('update');

        $this->plugin->uninstall($context);
    }
}
