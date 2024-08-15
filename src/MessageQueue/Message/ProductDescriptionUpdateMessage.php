<?php declare(strict_types=1);

namespace Magedia\TextEngine\MessageQueue\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

class ProductDescriptionUpdateMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly Context $context,
        private readonly string  $productId,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }
}