<?php declare(strict_types=1);

namespace Magedia\MessageQueue\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

class ProductDescriptionUpdateMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly Context $context,
        private readonly string  $id,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getId(): string
    {
        return $this->id;
    }
}