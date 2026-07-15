<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\BankVerification\Controller;

use Kommandhub\PaystackSW\Setting\Service\Config;
use Kommandhub\PaystackSW\BankVerification\Controller\BankVerificationController;
use Kommandhub\PaystackSW\BankVerification\Service\BankValidationFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(BankVerificationController::class)]
#[UsesClass(BankValidationFactory::class)]
class BankVerificationControllerTest extends TestCase
{
    public function testGetBanksReturnsBanks(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->willReturnCallback(function ($key, $salesChannelId) {
            if ($key === 'collectBankData') {
                return true;
            }

            if ($key === 'enableSandbox') {
                return false;
            }

            return false;
        });
        $config->method('getString')->willReturn('secret-key');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true, 'data' => []]);
        $httpClient->method('request')->willReturn($response);

        $result = $controller->getBanks($salesChannelContext);
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals('{"status":true,"data":[]}', $result->getContent());
    }

    public function testGetBanksReturns440WhenDisabled(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->with('collectBankData', 'sales-channel-id')->willReturn(false);

        $result = $controller->getBanks($salesChannelContext);
        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testGetBanksReturns500OnException(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->willReturn(true);
        $httpClient->method('request')->willThrowException(new \Exception('API Error'));

        $result = $controller->getBanks($salesChannelContext);
        $this->assertEquals(500, $result->getStatusCode());
        $this->assertStringContainsString('API Error', $result->getContent());
    }

    public function testGetBanksSandbox(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->willReturnCallback(function ($key, $salesChannelId) {
            if ($key === 'collectBankData') {
                return true;
            }

            if ($key === 'enableSandbox') {
                return true;
            }

            return false;
        });
        $config->method('getString')->with('apiSecretKeySandbox', salesChannelId: 'sales-channel-id')->willReturn('sandbox-secret-key');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true, 'data' => []]);

        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.paystack.co/bank', $this->callback(function ($options) {
                return $options['headers']['Authorization'] === 'Bearer sandbox-secret-key';
            }))
            ->willReturn($response);

        $result = $controller->getBanks($salesChannelContext);
        $this->assertInstanceOf(JsonResponse::class, $result);
    }

    public function testVerifyAccountSuccess(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->willReturnCallback(function ($key, $salesChannelId) {
            if ($key === 'collectBankData') {
                return true;
            }

            if ($key === 'enableSandbox') {
                return false;
            }

            return false;
        });
        $config->method('getString')->willReturn('secret-key');

        $request = new Request([], ['account_number' => '0123456789', 'bank_code' => '123']);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['status' => true, 'data' => ['account_name' => 'John Doe']]);
        $httpClient->method('request')->willReturn($response);

        $result = $controller->verifyAccount($request, $salesChannelContext);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertStringContainsString('John Doe', $result->getContent());
    }

    public function testVerifyAccountMissingParams(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->willReturn(true);

        $request = new Request();
        $result = $controller->verifyAccount($request, $salesChannelContext);
        $this->assertEquals(400, $result->getStatusCode());
    }

    public function testVerifyAccountSandbox(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->willReturnCallback(function ($key, $salesChannelId) {
            if ($key === 'collectBankData') {
                return true;
            }

            if ($key === 'enableSandbox') {
                return true;
            }

            return false;
        });
        $config->method('getString')->with('apiSecretKeySandbox', salesChannelId: 'sales-channel-id')->willReturn('secret-key');

        $request = new Request([], ['account_number' => '0123456789', 'bank_code' => '123']);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['status' => true, 'data' => []]);

        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.paystack.co/bank/resolve', $this->callback(function ($options) {
                return $options['query']['bank_code'] === '001';
            }))
            ->willReturn($response);

        $controller->verifyAccount($request, $salesChannelContext);
    }

    public function testVerifyAccountFailure(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->willReturn(true);

        $request = new Request([], ['account_number' => '0123456789', 'bank_code' => '123']);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('toArray')->willReturn(['status' => false, 'message' => 'Invalid account']);
        $httpClient->method('request')->willReturn($response);

        $result = $controller->verifyAccount($request, $salesChannelContext);
        $this->assertEquals(400, $result->getStatusCode());
    }

    public function testSaveBankDisabled(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->with('collectBankData', 'sales-channel-id')->willReturn(false);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->saveBank(new RequestDataBag(), $salesChannelContext, new CustomerEntity());
    }

    public function testSaveBankSuccessWithFlash(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = $this->createPartialMock(BankVerificationController::class, ['addFlash', 'redirectToRoute']);
        $controller->__construct($httpClient, $config, $customerRepository, $bankValidationFactory, $validator);

        $data = new RequestDataBag([
            'bankName' => 'Test Bank',
            'bankCode' => '123',
            'accountNumber' => '0123456789',
            'accountName' => 'John Doe',
            'bvn' => '12345678901',
        ]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $customer = new CustomerEntity();
        $customer->setId('customer-id');

        $config->method('getBool')->willReturn(true);
        $bankValidationFactory->method('create')->willReturn(new \Shopware\Core\Framework\Validation\DataValidationDefinition());
        $validator->method('getViolations')->willReturn(new \Symfony\Component\Validator\ConstraintViolationList());

        $customerRepository->expects($this->once())->method('update');
        $controller->expects($this->once())->method('addFlash')->with('success', 'Bank details saved successfully.');
        $controller->expects($this->once())->method('redirectToRoute')->willReturn($this->createMock(\Symfony\Component\HttpFoundation\RedirectResponse::class));

        $controller->saveBank($data, $salesChannelContext, $customer);
    }

    public function testSaveBankAddsFlashMessagesOnInvalidData(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = new BankValidationFactory($config);
        $validator = $this->createMock(DataValidator::class);

        // We need a partial mock of the controller to mock addFlash and redirectToRoute
        $controller = $this->createPartialMock(BankVerificationController::class, ['addFlash', 'redirectToRoute']);
        $controller->__construct($httpClient, $config, $customerRepository, $bankValidationFactory, $validator);

        $data = new RequestDataBag([
            'bankName' => '', // Invalid
        ]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $customer = new CustomerEntity();
        $customer->setId('customer-id');

        $config->method('getBool')->willReturn(true);

        $violation = $this->createMock(\Symfony\Component\Validator\ConstraintViolationInterface::class);
        $violation->method('getMessage')->willReturn('Error message');
        $violations = new \Symfony\Component\Validator\ConstraintViolationList([$violation]);

        $validator->expects($this->once())
            ->method('getViolations')
            ->willReturn($violations);

        $controller->expects($this->exactly(2))
            ->method('addFlash');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('frontend.account.profile.page')
            ->willReturn($this->createMock(\Symfony\Component\HttpFoundation\RedirectResponse::class));

        $controller->saveBank($data, $salesChannelContext, $customer);
    }
    public function testVerifyAccountFailureWithNon200(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->willReturn(true);

        $request = new Request([], ['account_number' => '0123456789', 'bank_code' => '123']);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);
        $response->method('toArray')->willReturn(['status' => false, 'message' => 'Not Found']);
        $httpClient->method('request')->willReturn($response);

        $result = $controller->verifyAccount($request, $salesChannelContext);
        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testVerifyAccountException(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->willReturn(true);

        $request = new Request([], ['account_number' => '0123456789', 'bank_code' => '123']);
        $httpClient->method('request')->willThrowException(new \Exception('Network error'));

        $result = $controller->verifyAccount($request, $salesChannelContext);
        $this->assertEquals(500, $result->getStatusCode());
    }

    public function testVerifyAccountDisabled(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->with('collectBankData', 'sales-channel-id')->willReturn(false);

        $request = new Request();
        $result = $controller->verifyAccount($request, $salesChannelContext);
        $this->assertEquals(404, $result->getStatusCode());
    }
    public function testVerifyAccountFailureWith200AndFalseStatus(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = $this->createMock(Config::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $bankValidationFactory = $this->createMock(BankValidationFactory::class);
        $validator = $this->createMock(DataValidator::class);

        $controller = new BankVerificationController(
            $httpClient,
            $config,
            $customerRepository,
            $bankValidationFactory,
            $validator
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getId')->willReturn('sales-channel-id');

        $config->method('getBool')->willReturn(true);

        $request = new Request([], ['account_number' => '0123456789', 'bank_code' => '123']);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['status' => false, 'message' => 'Verification failed']);
        $httpClient->method('request')->willReturn($response);

        $result = $controller->verifyAccount($request, $salesChannelContext);
        $this->assertEquals(400, $result->getStatusCode());
        $this->assertStringContainsString('Verification failed', $result->getContent());
    }
}
