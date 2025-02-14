<?php

declare(strict_types=1);

namespace Specification\Akeneo\Pim\Automation\IdentifierGenerator\Application\Generate\Property;

use Akeneo\Pim\Automation\IdentifierGenerator\Application\Exception\UnableToTruncateException;
use Akeneo\Pim\Automation\IdentifierGenerator\Application\Generate\Property\PropertyProcessApplier;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\Condition\Conditions;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\Delimiter;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\IdentifierGenerator;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\IdentifierGeneratorCode;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\IdentifierGeneratorId;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\LabelCollection;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\ProductProjection;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\Property\AutoNumber;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\Property\Process;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\Property\PropertyInterface;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\Property\SimpleSelectProperty;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\Structure;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\Target;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Model\TextTransformation;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Repository\FamilyNomenclatureRepository;
use Akeneo\Pim\Automation\IdentifierGenerator\Domain\Repository\SimpleSelectNomenclatureRepository;
use PhpSpec\ObjectBehavior;

class GenerateSimpleSelectHandlerSpec extends ObjectBehavior
{
    public function let(
        FamilyNomenclatureRepository $familyNomenclatureRepository,
        SimpleSelectNomenclatureRepository $simpleSelectNomenclatureRepository
    ) {
        $this->beConstructedWith(
            new PropertyProcessApplier(
                $familyNomenclatureRepository->getWrappedObject(),
                $simpleSelectNomenclatureRepository->getWrappedObject()
            )
        );
    }

    public function it_should_support_only_simple_select_property(): void
    {
        $this->getPropertyClass()->shouldReturn(SimpleSelectProperty::class);
    }

    public function it_should_throw_exception_when_invoked_with_something_else_than_simple_select_property(): void
    {
        $autoNumber = AutoNumber::fromNormalized([
            'type' => AutoNumber::type(),
            'numberMin' => 0,
            'digitsMin' => 1,
        ]);

        $identifierGenerator = $this->getIdentifierGenerator($autoNumber);

        $this
            ->shouldThrow(\InvalidArgumentException::class)
            ->during('__invoke', [
                $autoNumber,
                $identifierGenerator,
                new ProductProjection(true, null, [], []),
                'AKN-',
            ]);
    }

    public function it_should_return_simple_select_code_without_truncate(): void
    {
        $simpleSelect = SimpleSelectProperty::fromNormalized([
            'type' => SimpleSelectProperty::type(),
            'attributeCode' => 'color',
            'process' => [
                'type' => 'no',
            ],
            'scope' => null,
            'locale' => null,
        ]);

        $identifierGenerator = $this->getIdentifierGenerator($simpleSelect);

        $this->__invoke(
            $simpleSelect,
            $identifierGenerator,
            $this->getProductProjection(['color-<all_channels>-<all_locales>' => 'purple']),
            'AKN-'
        )->shouldReturn('purple');
    }

    public function it_should_return_simple_select_code_without_truncate_for_specific_locale_and_scope(): void
    {
        $simpleSelect = SimpleSelectProperty::fromNormalized([
            'type' => SimpleSelectProperty::type(),
            'attributeCode' => 'color',
            'process' => [
                'type' => 'no',
            ],
            'scope' => 'ecommerce',
            'locale' => 'fr_FR',
        ]);

        $identifierGenerator = $this->getIdentifierGenerator($simpleSelect);

        $this->__invoke(
            $simpleSelect,
            $identifierGenerator,
            $this->getProductProjection([
                'color-ecommerce-fr_FR' => 'violet',
                'color-mobile-fr_FR' => 'violet_shiny',
                'color-ecommerce-en_US' => 'purple',
                'color-mobile-en_US' => 'shiny_purple',
            ]),
            'AKN-'
        )->shouldReturn('violet');
    }

    public function it_should_return_simple_select_value_with_truncate(): void
    {
        $simpleSelect = SimpleSelectProperty::fromNormalized([
            'type' => SimpleSelectProperty::type(),
            'attributeCode' => 'color',
            'process' => [
                'type' => 'truncate',
                'operator' => Process::PROCESS_OPERATOR_LTE,
                'value' => 3,
            ],
            'scope' => null,
            'locale' => null,
        ]);

        $identifierGenerator = $this->getIdentifierGenerator($simpleSelect);

        $this->__invoke(
            $simpleSelect,
            $identifierGenerator,
            $this->getProductProjection(['color-<all_channels>-<all_locales>' => 'purple']),
            'AKN-'
        )->shouldReturn('pur');
    }

    public function it_should_return_simple_select_value_with_truncate_and_smaller_value(): void
    {
        $simpleSelect = SimpleSelectProperty::fromNormalized([
            'type' => SimpleSelectProperty::type(),
            'attributeCode' => 'color',
            'process' => [
                'type' => 'truncate',
                'operator' => Process::PROCESS_OPERATOR_LTE,
                'value' => 3,
            ],
            'scope' => null,
            'locale' => null,
        ]);

        $identifierGenerator = $this->getIdentifierGenerator($simpleSelect);

        $this->__invoke(
            $simpleSelect,
            $identifierGenerator,
            $this->getProductProjection(['color-<all_channels>-<all_locales>' => 'pu']),
            'AKN-'
        )->shouldReturn('pu');
    }

    public function it_should_throw_an_error_if_simple_select_value_is_too_small(): void
    {
        $simpleSelect = SimpleSelectProperty::fromNormalized([
            'type' => SimpleSelectProperty::type(),
            'attributeCode' => 'color',
            'process' => [
                'type' => 'truncate',
                'operator' => Process::PROCESS_OPERATOR_EQ,
                'value' => 4,
            ],
            'scope' => null,
            'locale' => null,
        ]);

        $identifierGenerator = $this->getIdentifierGenerator($simpleSelect);

        $this->shouldThrow(new UnableToTruncateException('AKN-pur', 'sku', 'pur'))->during(
            '__invoke',
            [
                $simpleSelect,
                $identifierGenerator,
                $this->getProductProjection(['color-<all_channels>-<all_locales>' => 'pur']),
                'AKN-',
            ]
        );
    }

    public function it_should_not_throw_an_error_if_simple_select_value_is_exactly_the_right_length(): void
    {
        $simpleSelect = SimpleSelectProperty::fromNormalized([
            'type' => SimpleSelectProperty::type(),
            'attributeCode' => 'color',
            'process' => [
                'type' => 'truncate',
                'operator' => Process::PROCESS_OPERATOR_EQ,
                'value' => 3,
            ],
            'scope' => null,
            'locale' => null,
        ]);

        $identifierGenerator = $this->getIdentifierGenerator($simpleSelect);

        $this->__invoke(
            $simpleSelect,
            $identifierGenerator,
            $this->getProductProjection(['color-<all_channels>-<all_locales>' => 'pur']),
            'AKN-'
        )->shouldReturn('pur');
    }

    private function getProductProjection(array $productValues): ProductProjection
    {
        return new ProductProjection(true, 'accessories', $productValues, []);
    }

    private function getIdentifierGenerator(PropertyInterface $property): IdentifierGenerator
    {
        return new IdentifierGenerator(
            IdentifierGeneratorId::fromString('d556e59e-d46c-465e-863d-f4a39d0b7485'),
            IdentifierGeneratorCode::fromString('my_generator'),
            Conditions::fromArray([]),
            Structure::fromArray([$property]),
            LabelCollection::fromNormalized(['en_US' => 'MyGenerator']),
            Target::fromString('sku'),
            Delimiter::fromString(null),
            TextTransformation::fromString('no'),
        );
    }
}
