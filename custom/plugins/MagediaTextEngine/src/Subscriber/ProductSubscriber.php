<?php declare(strict_types=1);

namespace Magedia\Subscriber;

use Magedia\MessageQueue\Message\ProductDescriptionUpdateMessage;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Content\Product\ProductEvents;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository    $productRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten'
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        foreach ($event->getIds() as $id) {
            /** @var ProductEntity $product */
            $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
            if (!$product || $product->getCustomFieldsValue('magedia_text_engine_is_description_updated_manually')) {
                continue;
            }

            $this->messageBus->dispatch(
                new Envelope(
                    new ProductDescriptionUpdateMessage($context, $id)
                )
            );
        }
    }
}
