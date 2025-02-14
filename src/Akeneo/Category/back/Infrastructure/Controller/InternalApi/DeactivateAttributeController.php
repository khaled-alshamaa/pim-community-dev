<?php

declare(strict_types=1);

namespace Akeneo\Category\Infrastructure\Controller\InternalApi;

use Akeneo\Category\Api\Command\CommandMessageBus;
use Akeneo\Category\Api\Command\Exceptions\ViolationsException;
use Akeneo\Category\Application\Command\DeactivateAttributeCommand;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @copyright 2023 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DeactivateAttributeController
{
    public function __construct(
        private readonly SecurityFacade $securityFacade,
        private readonly CommandMessageBus $categoryCommandBus,
    ) {
    }

    public function __invoke(Request $request, string $templateUuid, string $attributeUuid): Response
    {
        if (!$this->securityFacade->isGranted('pim_enrich_product_category_template')) {
            throw new AccessDeniedException();
        }

        try {
            $command = DeactivateAttributeCommand::create(
                templateUuid: $templateUuid,
                attributeUuid: $attributeUuid,
            );
            $this->categoryCommandBus->dispatch($command);
        } catch (ViolationsException $exception) {
            return new JsonResponse($exception->normalize(), Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(null, Response::HTTP_OK);
    }
}
