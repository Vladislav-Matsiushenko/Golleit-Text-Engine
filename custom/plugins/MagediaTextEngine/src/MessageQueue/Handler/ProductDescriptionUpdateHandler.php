<?php declare(strict_types=1);

namespace Magedia\MessageQueue\Handler;

use Magedia\MessageQueue\Message\ProductDescriptionUpdateMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class ProductDescriptionUpdateHandler
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository    $productRepository,
        private readonly EntityRepository    $propertyGroupRepository,
        private readonly EntityRepository    $propertyGroupOptionRepository,
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface     $logger,
    ) {
    }

    public function __invoke(ProductDescriptionUpdateMessage $message)
    {
        try {
            $endpointUrl = $this->systemConfigService->get('MagediaTextEngine.config.endpointUrl');
            $apiToken = $this->systemConfigService->get('MagediaTextEngine.config.apiToken');
            $dataMapping = $this->systemConfigService->get('MagediaTextEngine.config.dataMapping');
            if (!$endpointUrl || !$apiToken || !$dataMapping) {
                return;
            }

            $productId = $message->getProductId();
            $context = $message->getContext();

            $response = $this->client->request(
                'POST', $endpointUrl, [
                    'auth_bearer' => $apiToken,
                    'body' => $this->getBody($productId, $dataMapping, $context)
                ]
            );

            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException('Product description update failed');
            }

            $content = $response->toArray();
            if (!isset($content['messages'][0]['text']) || $content['messages'][0]['text'] === '') {
                throw new \RuntimeException('Product description update failed');
            }

            $this->productRepository->update([
                [
                    'id' => $productId,
                    'description' => $content['messages'][0]['text'],
                    'customFields' => [
                        'magedia_text_engine_is_description_updated_manually' => true,
                    ]
                ]
            ], $context);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['error' => $e]);
        }
    }

    private function getBody($productId, $dataMapping, $context): string
    {
        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        $productPropertyNameValues = [];
        foreach ($product->getPropertyIds() as $productPropertyId) {
            /** @var PropertyGroupOptionEntity $propertyGroupOption */
            $propertyGroupOption = $this->propertyGroupOptionRepository->search(new Criteria([$productPropertyId]), $context)->first();

            /** @var PropertyGroupEntity $propertyGroup */
            $propertyGroup = $this->propertyGroupRepository->search(new Criteria([$propertyGroupOption->getGroupId()]), $context)->first();
            $productPropertyNameValues[$propertyGroup->getName()][] = $propertyGroupOption->getName();
        }

        if ($product->getCustomFields()) {
            foreach ($product->getCustomFields() as $name => $value) {
                $productPropertyNameValues[$name][] = $value;
            }
        }

        $productPropertyNameValues['Name'][] = $product->getName();
        $productPropertyNameValues['Description'][] = $product->getDescription();

        preg_match_all("/\"\{\{\s*([a-z_]+)\s*}}\"/i", $dataMapping, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            if (isset($productPropertyNameValues[$matches[1][$i]])) {
                $dataMapping = str_replace(
                    $matches[0][$i],
                    count($productPropertyNameValues[$matches[1][$i]]) > 1
                        ? json_encode($productPropertyNameValues[$matches[1][$i]])
                        : json_encode($productPropertyNameValues[$matches[1][$i]][0]),
                    $dataMapping
                );
            } else {
                $dataMapping = str_replace(
                    $matches[0][$i],
                    json_encode(''),
                    $dataMapping
                );
            }
        }

        return '{'
            . $dataMapping
            . ', "refresh": '
            . json_encode($this->systemConfigService->get('MagediaTextEngine.config.refreshTexts'))
            . ', "output_format": "PLAIN_TEXT" }';
    }
}