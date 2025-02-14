<?php

declare(strict_types=1);

namespace AkeneoTest\Pim\Enrichment\Integration\Product\Import;

use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Repository\ProductRepositoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Validator\UniqueValuesSet;
use Akeneo\Pim\Enrichment\Product\API\Command\UpsertProductCommand;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetFamily;
use Akeneo\Pim\Enrichment\Product\API\ValueObject\ProductIdentifier;
use Akeneo\Test\Integration\TestCase;
use Akeneo\Test\IntegrationTestsBundle\Helper\AuthenticatorHelper;
use Akeneo\Test\IntegrationTestsBundle\Launcher\JobLauncher;
use Akeneo\Tool\Component\StorageUtils\Cache\CachedQueriesClearerInterface;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @copyright 2023 Akeneo SAS (https://www.akeneo.com)
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ImportProductsIntegration extends TestCase
{
    public function testItUpdatesAProductWithoutSku()
    {
        $uuid = $this->createProduct('product_1', []);
        $this->assertTrue($this->getProduct($uuid)->isEnabled());
        $csv = <<<CSV
uuid;enabled
{$uuid};0
CSV;

        $this->getJobLauncher()->launchImport('csv_product_import', $csv);
        $this->assertFalse($this->getProduct($uuid)->isEnabled());

        $this->assertEmpty($this->getWarnings());
    }

    public function testItCreatesProductWithFamilyWithoutSku()
    {
        $this->assertEquals(0, $this->getProductRepository()->countAll());
        $csv = <<<CSV
family
familyA
CSV;
        $this->getJobLauncher()->launchImport('csv_product_import', $csv);
        $this->assertEmpty($this->getWarnings());

        $this->assertEquals(1, $this->getProductRepository()->countAll());
        $product = $this->getProductRepository()->findOneBy([]);
        $this->assertEquals('familyA', $product->getFamily()->getCode());
    }

    public function testItUpdatesProductsFamily()
    {
        $uuid = $this->createProduct('product_1', [new SetFamily('familyA')]);
        $this->assertEquals(1, $this->getProductRepository()->countAll());
        $csv = <<<CSV
uuid;family
{$uuid};familyA1
CSV;
        $this->getJobLauncher()->launchImport('csv_product_import', $csv);
        $this->assertEmpty($this->getWarnings());
        $this->assertEquals(1, $this->getProductRepository()->countAll());
        $product = $this->getProductRepository()->find($uuid);
        $this->assertEquals('familyA1', $product->getFamily()->getCode());
    }

    public function testItCannotCreateProductAssociationsWithoutSkusOrIdentifier()
    {
        $this->createProduct('associated_product', []);
        $this->assertEquals(1, $this->getProductRepository()->countAll());

        $csv = <<<CSV
family;X_SELL-products
familyA1;associated_product
CSV;

        $this->getJobLauncher()->launchImport('csv_product_import', $csv);
        $warnings = $this->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertEquals('Either the identifier or the uuid must be filled', $warnings[0]['reason']);
        $this->assertEquals(2, $this->getProductRepository()->countAll());
    }

    public function testItCreatesProductAssociationsWithSku()
    {
        $this->createProduct('associated_product', []);
        $this->assertEquals(1, $this->getProductRepository()->countAll());

        $csv = <<<CSV
sku;family;X_SELL-products
new_product;familyA1;associated_product
CSV;

        $this->getJobLauncher()->launchImport('csv_product_import', $csv);
        $this->assertEmpty($this->getWarnings());
        $this->assertEquals(2, $this->getProductRepository()->countAll());
    }

    public function testItCreatesProductAssociationsWithUuid()
    {
        $this->createProduct('associated_product', []);
        $this->assertEquals(1, $this->getProductRepository()->countAll());
        $uuid = Uuid::uuid4()->toString();
        $csv = <<<CSV
uuid;family;X_SELL-products
{$uuid};familyA1;associated_product
CSV;

        $this->getJobLauncher()->launchImport('csv_product_import', $csv);
        var_dump($this->getWarnings());
        $this->assertEmpty($this->getWarnings());
        $this->assertEquals(2, $this->getProductRepository()->countAll());
    }

    protected function getConfiguration()
    {
        return $this->catalog->useTechnicalCatalog();
    }

    private function createProduct(string $identifier, array $userIntents): string
    {
        $this->getAuthenticator()->logIn('admin');
        $command = UpsertProductCommand::createWithIdentifier(
            userId: $this->getUserId('admin'),
            productIdentifier: ProductIdentifier::fromIdentifier($identifier),
            userIntents: $userIntents
        );
        $this->getMessageBus()->dispatch($command);
        $this->getUniqueValueSetValidator()->reset();
        $this->getCacheClearer()->clear();

        return $this->getProductUuid($identifier)->toString();
    }

    private function getUserId(string $username): int
    {
        $query = <<<SQL
            SELECT id FROM oro_user WHERE username = :username
        SQL;
        $stmt = $this->getConnection()->executeQuery($query, ['username' => $username]);
        $id = $stmt->fetchOne();
        if (null === $id) {
            throw new \InvalidArgumentException(\sprintf('No user exists with username "%s"', $username));
        }

        return \intval($id);
    }

    private function getJobLauncher(): JobLauncher
    {
        return $this->get('akeneo_integration_tests.launcher.job_launcher');
    }

    private function getWarnings(): array
    {
        $query = <<<SQL
SELECT reason, item FROM akeneo_batch_warning
SQL;

        return \array_map(
            static fn (array $warning): array => ['reason' => $warning['reason'], 'item' => \unserialize($warning['item'])],
            $this->getConnection()->fetchAllAssociative($query)
        );
    }

    private function getConnection(): Connection
    {
        return $this->get('database_connection');
    }

    private function getProduct(string $uuid): ProductInterface
    {
        return $this->getProductRepository()->find($uuid);
    }

    private function getProductRepository(): ProductRepositoryInterface
    {
        return $this->get('pim_catalog.repository.product');
    }

    private function getMessageBus(): MessageBusInterface
    {
        return $this->get('pim_enrich.product.message_bus');
    }

    private function getUniqueValueSetValidator(): UniqueValuesSet
    {
        return $this->get('pim_catalog.validator.unique_value_set');
    }

    private function getCacheClearer(): CachedQueriesClearerInterface
    {
        return $this->get('akeneo.pim.storage_utils.cache.cached_queries_clearer');
    }

    private function getAuthenticator(): AuthenticatorHelper
    {
        return $this->get('akeneo_integration_tests.helper.authenticator');
    }
}
