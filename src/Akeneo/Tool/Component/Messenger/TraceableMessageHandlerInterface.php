<?php

declare(strict_types=1);

namespace Akeneo\Tool\Component\Messenger;

/**
 * @copyright 2023 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
interface TraceableMessageHandlerInterface
{
    public function __invoke(TraceableMessageInterface $message): void;
}
