<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW;

use Kommandhub\PaystackSW\Checkout\Payment\PaystackPaymentHandler;
use Kommandhub\PaystackSW\Service\CustomFieldsInstaller;
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

class KommandhubPaystackSW extends Plugin
{
    public function executeComposerCommands(): bool
    {
        return true;
    }

    public function install(InstallContext $installContext): void
    {
        $this->addPaymentMethod($installContext->getContext());
        $this->getCustomFieldsInstaller()->install($installContext->getContext());
        $this->getCustomFieldsInstaller()->addRelations($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        // Only set the payment method to inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders
        $this->setPaymentMethodIsActive(false, $uninstallContext->getContext());

        if ($uninstallContext->keepUserData()) {
            return; // @codeCoverageIgnore
        }

        $this->getCustomFieldsInstaller()->uninstall($uninstallContext->getContext()); // @codeCoverageIgnore
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->setPaymentMethodIsActive(true, $activateContext->getContext());
        parent::activate($activateContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->setPaymentMethodIsActive(false, $deactivateContext->getContext());
        parent::deactivate($deactivateContext);
    }

    private function addPaymentMethod(Context $context): void
    {
        /** @phpstan-ignore-next-line */
        if (!isset($this->container) || $this->container === null) {
            return; // @codeCoverageIgnore
        }

        $paymentMethodExists = $this->getPaymentMethodId();

        // Payment method exists already, no need to continue here
        if ($paymentMethodExists) { // @codeCoverageIgnoreStart
            $this->setPaymentMethodIsActive(true, $context);

            return;
        } // @codeCoverageIgnoreEnd

        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);

        $paymentData = [
            [
                // the identifier will select the payment handler
                'handlerIdentifier' => PaystackPaymentHandler::class,
                'name' => 'Pay with Paystack',
                'description' => 'Securely pay with your card, bank account, or mobile money via Paystack.',
                'pluginId' => $pluginId,
                'afterOrderEnabled' => true,
                'technicalName' => 'kommandhub_paystack_payment',
            ],
        ];

        /** @var EntityRepository<PaymentMethodCollection> $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create($paymentData, $context);
    }

    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        /** @phpstan-ignore-next-line */
        if (!isset($this->container) || $this->container === null) {
            return; // @codeCoverageIgnore
        }

        /** @var EntityRepository<PaymentMethodCollection> $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodId = $this->getPaymentMethodId();

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodId) {
            return; // @codeCoverageIgnore
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    private function getPaymentMethodId(): ?string
    {
        /** @phpstan-ignore-next-line */
        if (!isset($this->container) || $this->container === null) {
            return null; // @codeCoverageIgnore
        }

        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaystackPaymentHandler::class));

        return $paymentMethodRepository->searchIds($paymentCriteria, Context::createDefaultContext())->firstId();
    }

    private function getCustomFieldsInstaller(): CustomFieldsInstaller
    {
        /** @phpstan-ignore-next-line */
        if (!isset($this->container) || $this->container === null) {
            throw new \RuntimeException('The container is not available.'); // @codeCoverageIgnore
        }

        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $customFieldSetRelationRepository = $this->container->get('custom_field_set_relation.repository');

        if (!$customFieldSetRelationRepository instanceof EntityRepository || !$customFieldSetRepository instanceof EntityRepository) {
            throw new \RuntimeException('Required repositories are not available in the container.'); // @codeCoverageIgnore
        }

        return new CustomFieldsInstaller(
            $customFieldSetRepository,
            $customFieldSetRelationRepository
        );
    }
}
