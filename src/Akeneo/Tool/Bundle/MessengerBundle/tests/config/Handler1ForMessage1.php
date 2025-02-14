<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\MessengerBundle\tests\config;

use Akeneo\Tool\Component\Messenger\TraceableMessageHandlerInterface;
use Akeneo\Tool\Component\Messenger\TraceableMessageInterface;
use Webmozart\Assert\Assert;

/**
 * @copyright 2023 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class Handler1ForMessage1 implements TraceableMessageHandlerInterface
{
    public function __construct(private readonly HandlerObserver $handlerObserver)
    {
    }

    public function __invoke(TraceableMessageInterface $message): void
    {
        Assert::isInstanceOf($message, Message1::class);
        $this->handlerObserver->handlerWasExecuted(self::class, $message);
    }
}
