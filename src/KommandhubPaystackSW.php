<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW;

use Kommandhub\PaystackSW\Core\Installer\CustomFieldsInstaller;
use Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\Handler\PaystackPaymentHandler;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Main Shopware plugin class for Kommandhub Paystack integration.
 *
 * Responsibilities:
 * - Registers plugin services (via Symfony container)
 * - Manages lifecycle (install/activate/deactivate/uninstall)
 * - Creates and maintains Paystack payment method
 * - Installs custom fields required for integration
 */
class KommandhubPaystackSW extends Plugin
{
    /**
     * Allow composer commands during plugin execution.
     */
    public function executeComposerCommands(): bool
    {
        return true;
    }

    /**
     * Load additional service configuration files.
     *
     * This extends Shopware's DI container with custom YAML configurations.
     * @throws \Exception
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
        ]);

        $loader = new DelegatingLoader($resolver);

        $configPath = rtrim($this->getPath(), '/') . '/Resources/config';

        // Load all package service definitions
        $loader->load($configPath . '/{packages}/*.yaml', 'glob');
    }

    /**
     * Plugin installation lifecycle hook.
     *
     * Creates payment method and installs required custom fields.
     */
    public function install(InstallContext $installContext): void
    {
        $context = $installContext->getContext();

        $this->addOrActivatePaymentMethod($context);

        $installer = $this->getCustomFieldsInstaller();
        $installer->install($context);
        $installer->addRelations($context);
    }

    /**
     * Plugin activation lifecycle hook.
     */
    public function activate(ActivateContext $activateContext): void
    {
        $this->setPaymentMethodActive(true, $activateContext->getContext());

        parent::activate($activateContext);
    }

    /**
     * Plugin deactivation lifecycle hook.
     */
    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->setPaymentMethodActive(false, $deactivateContext->getContext());

        parent::deactivate($deactivateContext);
    }

    /**
     * Plugin uninstall lifecycle hook.
     *
     * Important:
     * We do NOT delete the payment method to avoid breaking historical orders.
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        $this->setPaymentMethodActive(false, $uninstallContext->getContext());

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->getCustomFieldsInstaller()->uninstall($uninstallContext->getContext());
    }

    /**
     * Creates payment method if it does not exist, otherwise ensures it is active.
     */
    private function addOrActivatePaymentMethod(Context $context): void
    {
        if (!$this->hasContainer()) {
            return;
        }

        $paymentId = $this->getPaymentMethodId();

        // If already exists, just ensure it's active
        if ($paymentId !== null) {
            $this->setPaymentMethodActive(true, $context);
            return;
        }

        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);

        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(static::class, $context);

        /** @var EntityRepository<PaymentMethodCollection> $repository */
        $repository = $this->container->get('payment_method.repository');

        $repository->create([
            [
                'handlerIdentifier' => PaystackPaymentHandler::class,
                'name' => 'Pay with Paystack',
                'description' => 'Securely pay with card, bank transfer or mobile money via Paystack.',
                'pluginId' => $pluginId,
                'technicalName' => 'kommandhub_paystack_payment',
                'afterOrderEnabled' => true,
                'active' => true,
            ],
        ], $context);
    }

    /**
     * Activates or deactivates the Paystack payment method.
     */
    private function setPaymentMethodActive(bool $active, Context $context): void
    {
        if (!$this->hasContainer()) {
            return;
        }

        $paymentId = $this->getPaymentMethodId();

        if ($paymentId === null) {
            return;
        }

        /** @var EntityRepository<PaymentMethodCollection> $repository */
        $repository = $this->container->get('payment_method.repository');

        $repository->update([
            [
                'id' => $paymentId,
                'active' => $active,
            ],
        ], $context);
    }

    /**
     * Returns the Paystack payment method ID if it exists.
     */
    private function getPaymentMethodId(): ?string
    {
        if (!$this->hasContainer()) {
            return null;
        }

        /** @var EntityRepository $repository */
        $repository = $this->container->get('payment_method.repository');

        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('handlerIdentifier', PaystackPaymentHandler::class)
        );

        return $repository
            ->searchIds($criteria, Context::createDefaultContext())
            ->firstId();
    }

    /**
     * Returns configured custom field installer.
     */
    private function getCustomFieldsInstaller(): CustomFieldsInstaller
    {
        if (!$this->hasContainer()) {
            throw new \RuntimeException('Container is not available.');
        }

        $setRepo = $this->container->get('custom_field_set.repository');
        $relationRepo = $this->container->get('custom_field_set_relation.repository');

        if (
            !$setRepo instanceof EntityRepository ||
            !$relationRepo instanceof EntityRepository
        ) {
            throw new \RuntimeException('Invalid repository services.');
        }

        return new CustomFieldsInstaller(
            $setRepo,
            $relationRepo
        );
    }

    /**
     * Checks whether the DI container is available.
     */
    private function hasContainer(): bool
    {
        return isset($this->container);
    }
}