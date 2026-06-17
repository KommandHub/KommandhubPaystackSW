<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Storefront\Validation;

use Kommandhub\PaystackSW\Core\Config\Config;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class BankValidationFactory implements DataValidationFactoryInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->buildCommonValidation($context);
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->buildCommonValidation($context);
    }

    private function buildCommonValidation(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('paystack.bank.save');

        $definition
            ->add('bankName', new NotBlank())
            ->add('bankCode', new NotBlank())
            ->add('accountNumber', new NotBlank(), new Length(['min' => 10, 'max' => 10]))
            ->add('accountName', new NotBlank());

        if ($this->config->getBool('showBvnField', $context->getSalesChannelId())) {
            $definition->add('bvn', new Length(['min' => 11, 'max' => 11]));

            if ($this->config->getBool('requireBvn', $context->getSalesChannelId())) {
                $definition->add('bvn', new NotBlank());
            }
        }

        return $definition;
    }
}
