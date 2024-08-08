<?php declare(strict_types=1);

namespace Magedia\MessageQueue\Handler;

use Magedia\MessageQueue\Message\ProductDescriptionUpdateMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class ProductDescriptionUpdateHandler
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly HttpClientInterface $client,
        private readonly EntityRepository    $productRepository,
        private readonly LoggerInterface     $logger,
    ) {
    }

    public function __invoke(ProductDescriptionUpdateMessage $message)
    {
        try {
            $endpointUrl = $this->systemConfigService->get('MagediaTextEngine.config.endpointUrl');
            $apiToken = $this->systemConfigService->get('MagediaTextEngine.config.apiToken');
            if (!$endpointUrl || !$apiToken) {
                return;
            }

            $body = json_encode([
                'data' => [
                    'Material DE' => 'Metal'
                ],
                'strict_validation' => 'true',
                'refresh' => $this->systemConfigService->get('MagediaTextEngine.config.refreshTexts') ? 'true' : 'false',
                'output_format' => 'PLAIN_TEXT'
            ]);

            $response = $this->client->request(
                'POST', $endpointUrl, [
                    'auth_bearer' => $apiToken,
                    'body' => $body
                ]
            );

            $content = $response->toArray(false);
            if (!isset($content['messages'][0]['text']) || $content['messages'][0]['text'] === '') {
                throw new \RuntimeException('Product description update failed');
            }

            $description = $content['messages'][0]['text'];
            $this->productRepository->update([
                [
                    'id' => $message->getId(),
                    'description' => $description,
                    'customFields' => [
                        'magedia_text_engine_is_description_updated_manually' => true,
                    ]
                ]
            ], $message->getContext());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['error' => $e]);
        }
    }
}