<?php

declare(strict_types=1);

namespace Akeneo\Catalogs\Test\Integration\Infrastructure\Validation\ProductMapping\AttributeSource;

use Akeneo\Catalogs\Infrastructure\Validation\ProductMapping\AttributeSource\SystemAttributeFamilySource;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @copyright 2023 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @covers \Akeneo\Catalogs\Infrastructure\Validation\ProductMapping\AttributeSource\SystemAttributeFamilySource
 */
class SystemAttributeFamilySourceTest extends AbstractAttributeSourceTest
{
    private ?ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttribute([
            'code' => 'family',
            'type' => 'family',
            'scopable' => false,
            'localizable' => false,
            'attribute_group_code' => 'system',
            'attribute_group_label' => 'System',
        ]);
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testItReturnsNoViolation(): void
    {
        $source = [
            'source' => 'family',
            'scope' => null,
            'locale' => null,
            'parameters' => [
                'label_locale' => 'en_US',
            ],
        ];
        $violations = $this->validator->validate($source, new SystemAttributeFamilySource());

        $this->assertEmpty($violations);
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testItReturnsViolationsWhenInvalid(
        array $source,
        string $expectedMessage,
    ): void {
        $violations = $this->validator->validate($source, new SystemAttributeFamilySource());

        $this->assertViolationsListContains($violations, $expectedMessage);
    }

    public function invalidDataProvider(): array
    {
        return [
            'missing source value' => [
                'source' => [
                    'scope' => null,
                    'locale' => null,
                    'parameters' => [
                        'label_locale' => 'en_US',
                    ],
                ],
                'expectedMessage' => 'This field is missing.',
            ],
            'blank source value' => [
                'source' => [
                    'source' => '',
                    'scope' => null,
                    'locale' => null,
                    'parameters' => [
                        'label_locale' => 'en_US',
                    ],
                ],
                'expectedMessage' => 'This value should not be blank.',
            ],
            'invalid source value' => [
                'source' => [
                    'source' => 42,
                    'scope' => null,
                    'locale' => null,
                    'parameters' => [
                        'label_locale' => 'en_US',
                    ],
                ],
                'expectedMessage' => 'This value should be of type string.',
            ],
            'non null scope' => [
                'source' => [
                    'source' => 'family',
                    'scope' => 'e-commerce',
                    'locale' => null,
                    'parameters' => [
                        'label_locale' => 'en_US',
                    ],
                ],
                'expectedMessage' => 'This value should be null.',
            ],
            'missing scope field' => [
                'source' => [
                    'source' => 'family',
                    'locale' => null,
                    'parameters' => [
                        'label_locale' => 'en_US',
                    ],
                ],
                'expectedMessage' => 'This field is missing.',
            ],
            'missing locale' => [
                'source' => [
                    'source' => 'family',
                    'scope' => null,
                    'parameters' => [
                        'label_locale' => 'en_US',
                    ],
                ],
                'expectedMessage' => 'This field is missing.',
            ],
            'with locale' => [
                'source' => [
                    'source' => 'family',
                    'scope' => null,
                    'locale' => 'en_US',
                    'parameters' => [
                        'label_locale' => 'en_US',
                    ],
                ],
                'expectedMessage' => 'This value should be null.',
            ],
            'missing parameters' => [
                'source' => [
                    'source' => 'family',
                    'scope' => null,
                    'locale' => null,
                ],
                'expectedMessage' => 'This field is missing.',
            ],
            'missing label locale' => [
                'source' => [
                    'source' => 'family',
                    'scope' => null,
                    'locale' => null,
                    'parameters' => [],
                ],
                'expectedMessage' => 'This field is missing.',
            ],
            'blank label locale' => [
                'source' => [
                    'source' => 'family',
                    'scope' => null,
                    'locale' => null,
                    'parameters' => [
                        'label_locale' => '',
                    ],
                ],
                'expectedMessage' => 'This value should not be blank.',
            ],
            'null label locale' => [
                'source' => [
                    'source' => 'family',
                    'scope' => null,
                    'locale' => null,
                    'parameters' => [
                        'label_locale' => null,
                    ],
                ],
                'expectedMessage' => 'This value should be of type string.',
            ],
            'invalid locale' => [
                'source' => [
                    'source' => 'family',
                    'scope' => null,
                    'locale' => null,
                    'parameters' => [
                        'label_locale' => 'zz_ZZ',
                    ],
                ],
                'expectedMessage' => 'This locale is disabled or does not exist anymore. Please check your channels and locales settings.',
            ],
            'disabled locale' => [
                'source' => [
                    'source' => 'family',
                    'scope' => null,
                    'locale' => null,
                    'parameters' => [
                        'label_locale' => 'kz_KZ',
                    ],
                ],
                'expectedMessage' => 'This locale is disabled or does not exist anymore. Please check your channels and locales settings.',
            ],
        ];
    }
}
