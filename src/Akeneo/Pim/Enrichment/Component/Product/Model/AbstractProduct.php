<?php

namespace Akeneo\Pim\Enrichment\Component\Product\Model;

use Akeneo\Pim\Enrichment\Component\Category\Model\CategoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\QuantifiedAssociation\EntityWithQuantifiedAssociationTrait;
use Akeneo\Pim\Enrichment\Component\Product\Model\QuantifiedAssociation\QuantifiedAssociationCollection;
use Akeneo\Pim\Structure\Component\AttributeTypes;
use Akeneo\Pim\Structure\Component\Model\AssociationTypeInterface;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Structure\Component\Model\FamilyInterface;
use Akeneo\Pim\Structure\Component\Model\FamilyVariantInterface;
use Akeneo\Tool\Component\Classification\Model\CategoryInterface as BaseCategoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Abstract product
 *
 * @author    Nicolas Dupont <nicolas@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
abstract class AbstractProduct implements ProductInterface
{
    use EntityWithQuantifiedAssociationTrait;

    /** @var int|string */
    protected $id;

    /** @var array */
    protected array $rawValues;

    /** @var \DateTime */
    protected $created;

    /** @var \DateTime */
    protected $updated;

    /**
     * Not persisted. Loaded on the fly via the $rawValues.
     *
     * @var WriteValueCollection
     */
    protected $values;

    /** @var FamilyInterface|null */
    protected $family;

    /** @var Collection */
    protected $categories;

    /** @var bool $enabled */
    protected bool $enabled = true;

    /** @var Collection */
    protected $groups;

    /** @var Collection */
    protected $associations;

    /**
     * Not persisted.
     *
     * @var QuantifiedAssociationCollection|null
     */
    protected $quantifiedAssociationCollection;

    /** @var Collection */
    protected $completenesses;

    /** @var string|null */
    protected $identifier;

    /** @var Collection */
    protected $uniqueData;

    /** @var ProductModelInterface|null */
    protected $parent;

    /** @var FamilyVariantInterface|null */
    protected $familyVariant;

    protected bool $wasUpdated = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->values = new WriteValueCollection();
        $this->categories = new ArrayCollection();
        $this->completenesses = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->associations = new ArrayCollection();
        $this->uniqueData = new ArrayCollection();
        $this->quantifiedAssociationCollection = QuantifiedAssociationCollection::createFromNormalized([]);
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * {@inheritdoc}
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * {@inheritdoc}
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addValue(ValueInterface $value)
    {
        if (true === $this->values->add($value)) {
            $this->wasUpdated = true;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeValue(ValueInterface $value)
    {
        if (true === $this->values->remove($value)) {
            $this->wasUpdated = true;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsedAttributeCodes(): array
    {
        return $this->getValues()->getAttributeCodes();
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($attributeCode, $localeCode = null, $scopeCode = null)
    {
        $value = $this->values->getByCodes($attributeCode, $scopeCode, $localeCode);
        if (null !== $value) {
            return $value;
        }

        if (null === $this->getParent()) {
            return null;
        }

        return $this->getParent()->getValue($attributeCode, $localeCode, $scopeCode);
    }

    /**
     * {@inheritdoc}
     */
    public function getRawValues()
    {
        return $this->rawValues;
    }

    /**
     * {@inheritdoc}
     */
    public function setRawValues(array $rawValues)
    {
        $this->rawValues = $rawValues;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAttribute(string $attributeCode): bool
    {
        return in_array($attributeCode, $this->getValues()->getAttributeCodes(), true);
    }

    /**
     * {@inheritdoc}
     */
    public function getFamily(): ?FamilyInterface
    {
        return $this->family;
    }

    /**
     * {@inheritdoc}
     */
    public function setFamily(FamilyInterface $family = null)
    {
        if ($family !== $this->family) {
            $this->wasUpdated = true;
        }
        $this->family = $family;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFamilyId()
    {
        return $this->family ? $this->family->getId() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * {@inheritdoc}
     */
    public function setIdentifier(?string $identifierValue): ProductInterface
    {
        if ($identifierValue !== $this->identifier) {
            $this->identifier = $identifierValue;
            $this->wasUpdated = true;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues(): WriteValueCollection
    {
        if (!$this->isVariant()) {
            return WriteValueCollection::fromCollection($this->values);
        }

        $values = WriteValueCollection::fromCollection($this->values);

        return $this->getAllValues($this, $values);
    }

    /**
     * {@inheritdoc}
     */
    public function setValues(WriteValueCollection $values)
    {
        $formerValues = WriteValueCollection::fromCollection($this->values ?? new WriteValueCollection());
        foreach ($formerValues as $formerValue) {
            $matching = $values->getSame($formerValue);
            if (null === $matching || !$formerValue->isEqual($matching)) {
                $this->wasUpdated = true;
            }
        }
        foreach ($values as $value) {
            $matching = $formerValues->getSame($value);
            if (null === $matching) {
                $this->wasUpdated = true;
            }
        }
        $this->values = $values;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getImage()
    {
        if (null === $this->family) {
            return null;
        }

        $attributeAsImage = $this->family->getAttributeAsImage();

        if (null === $attributeAsImage) {
            return null;
        }

        return $this->getValue($attributeAsImage->getCode());
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel($locale = null, $scope = null)
    {
        $identifier = (string) $this->getIdentifier();

        if (null === $this->family) {
            return $identifier;
        }

        $attributeAsLabel = $this->family->getAttributeAsLabel();

        if (null === $attributeAsLabel) {
            return $identifier;
        }

        $locale = $attributeAsLabel->isLocalizable() ? $locale : null;
        $scope = $attributeAsLabel->isScopable() ? $scope : null;
        $value = $this->getValue($attributeAsLabel->getCode(), $locale, $scope);

        if (null === $value) {
            return $identifier;
        }

        $data = $value->getData();

        if (empty($data)) {
            return $identifier;
        }

        return (string) $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getCategories()
    {
        $categories = new ArrayCollection($this->categories->toArray());

        return $this->getAllCategories($this, $categories);
    }

    /**
     * {@inheritdoc}
     */
    public function addCategory(BaseCategoryInterface $category)
    {
        if (!$this->categories->contains($category) && !$this->hasAncestryCategory($category)) {
            $this->categories->add($category);
            $this->wasUpdated = true;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCategories(Collection $categories): void
    {
        $formerCategories = $this->getCategories();
        $categoriesToAdd = $categories->filter(
            function (CategoryInterface $category) use ($formerCategories) {
                return !$formerCategories->contains($category);
            }
        );
        foreach ($categoriesToAdd as $categoryToAdd) {
            $this->addCategory($categoryToAdd);
        }
        $categoriesToRemove = $formerCategories->filter(
            function (Categoryinterface $category) use ($categories) {
                return !$categories->contains($category);
            }
        );
        foreach ($categoriesToRemove as $categoryToRemove) {
            $this->removeCategory($categoryToRemove);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeCategory(BaseCategoryInterface $category)
    {
        if (true === $this->categories->removeElement($category)) {
            $this->wasUpdated = true;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCategoryCodes()
    {
        $codes = $this->getCategories()->map(function (CategoryInterface $category): string {
            return $category->getCode();
        })->toArray();
        sort($codes);

        return $codes;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupCodes()
    {
        $codes = $this->groups->map(function (GroupInterface $group): string {
            return $group->getCode();
        })->toArray();
        sort($codes);

        return $codes;
    }

    /**
     * {@inheritdoc}
     */
    public function setGroups(Collection $groups): void
    {
        $formerGroups = $this->getGroups();
        $groupsToAdd = $groups->filter(function (GroupInterface $group) use ($formerGroups): bool {
            return !$formerGroups->contains($group);
        });
        foreach ($groupsToAdd as $groupToAdd) {
            $this->addGroup($groupToAdd);
        }
        $groupsToRemove = $formerGroups->filter(function (GroupInterface $group) use ($groups): bool {
            return !$groups->contains($group);
        });
        foreach ($groupsToRemove as $groupToRemove) {
            $this->removeGroup($groupToRemove);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function setEnabled($enabled)
    {
        if ($enabled !== $this->enabled) {
            $this->enabled = $enabled;
            $this->wasUpdated = true;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAttributeInFamily(AttributeInterface $attribute)
    {
        return null !== $this->family && $this->family->getAttributes()->contains($attribute);
    }

    /**
     * {@inheritdoc}
     */
    public function isAttributeRemovable(AttributeInterface $attribute)
    {
        if (AttributeTypes::IDENTIFIER === $attribute->getType()) {
            return false;
        }

        if ($this->hasAttributeInFamily($attribute)) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isAttributeEditable(AttributeInterface $attribute)
    {
        if (!$this->hasAttributeInFamily($attribute)) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroups()
    {
        return new ArrayCollection($this->groups->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function addGroup(GroupInterface $group)
    {
        if (!$this->groups->contains($group)) {
            $this->groups->add($group);
            $group->addProduct($this);
            $this->wasUpdated = true;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeGroup(GroupInterface $group)
    {
        if (true === $this->groups->removeElement($group)) {
            $this->wasUpdated = true;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getLabel();
    }

    /**
     * {@inheritdoc}
     */
    public function addAssociation(AssociationInterface $newAssociation): EntityWithAssociationsInterface
    {
        $currentAssociation = $this->getSimilarAssociation($newAssociation);
        if ($currentAssociation) {
            throw new \LogicException(
                sprintf(
                    'Can not add an association of type %s because the product already has one',
                    $currentAssociation->getAssociationType()->getCode()
                )
            );
        }

        $newAssociation->setOwner($this);
        $this->associations->add($newAssociation);
        if (
            $newAssociation->getProducts()->count() > 0 ||
            $newAssociation->getProductModels()->count() > 0 ||
            $newAssociation->getGroups()->count() > 0
        ) {
            $this->wasUpdated = true;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeAssociation(AssociationInterface $association): EntityWithAssociationsInterface
    {
        $similarAssociation = $this->getSimilarAssociation($association);
        if (
            null !== $similarAssociation &&
            true === $this->associations->removeElement($similarAssociation) &&
            (
                $similarAssociation->getProducts()->count() > 0 ||
                $similarAssociation->getProductModels()->count() > 0 ||
                $similarAssociation->getGroups()->count() > 0
            )
        ) {
            $this->wasUpdated = true;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAssociations()
    {
        return new ArrayCollection($this->associations->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function getAllAssociations()
    {
        $associations = new ArrayCollection($this->associations->toArray());
        $allAssociations = $this->getAncestryAssociations($this, $associations);

        return $allAssociations;
    }

    /**
     * {@inheritdoc}
     */
    public function getAssociationForType(AssociationTypeInterface $type): ?AssociationInterface
    {
        return $this->getAssociationForTypeCode($type->getCode());
    }

    /**
     * {@inheritdoc}
     */
    public function getAssociationForTypeCode($typeCode): ?AssociationInterface
    {
        foreach ($this->getAssociations() as $association) {
            if ($association->getAssociationType()->getCode() === $typeCode) {
                return $association;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function setAssociations(Collection $associations): EntityWithAssociationsInterface
    {
        $this->associations = $associations;
        $this->wasUpdated = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getReference()
    {
        return $this->getIdentifier();
    }

    /**
     * @return ArrayCollection
     */
    public function getUniqueData()
    {
        return $this->uniqueData;
    }

    /**
     * @param ProductUniqueDataInterface $uniqueData
     *
     * @return ProductInterface
     */
    public function addUniqueData(ProductUniqueDataInterface $uniqueData)
    {
        $this->uniqueData->add($uniqueData);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setUniqueData(Collection $data): void
    {
        $this->uniqueData = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?ProductModelInterface
    {
        return $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function setParent(ProductModelInterface $parent = null): void
    {
        if ($parent !== $this->parent) {
            $this->parent = $parent;
            $this->wasUpdated = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFamilyVariant(): ?FamilyVariantInterface
    {
        return $this->familyVariant;
    }

    /**
     * {@inheritdoc}
     */
    public function setFamilyVariant(FamilyVariantInterface $familyVariant): void
    {
        if ($familyVariant !== $this->familyVariant) {
            $this->familyVariant = $familyVariant;
            $this->wasUpdated = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVariationLevel(): int
    {
        return $this->getParent()->getVariationLevel() + 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForVariation(): WriteValueCollection
    {
        return WriteValueCollection::fromCollection($this->values);
    }

    /**
     * {@inheritdoc}
     */
    public function getCategoriesForVariation(): Collection
    {
        return new ArrayCollection($this->categories->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function isVariant(): bool
    {
        return null !== $this->getParent();
    }

    /**
     * {@inheritdoc}
     */
    public function filterQuantifiedAssociations(array $productIdentifiersToKeep, array $productModelCodesToKeep): void
    {
        if (null === $this->quantifiedAssociationCollection) {
            return;
        }

        $this->quantifiedAssociationCollection = $this->quantifiedAssociationCollection
            ->filterProductIdentifiers($productIdentifiersToKeep)
            ->filterProductModelCodes($productModelCodesToKeep);
        $this->wasUpdated = true;
    }

    /**
     * {@inheritdoc}
     */
    public function mergeQuantifiedAssociations(QuantifiedAssociationCollection $quantifiedAssociations): void
    {
        if ($this->quantifiedAssociationCollection === null) {
            return;
        }
        $this->quantifiedAssociationCollection = $this->quantifiedAssociationCollection->merge($quantifiedAssociations);
        $this->wasUpdated = true;
    }

    /**
     * {@inheritdoc}
     */
    public function patchQuantifiedAssociations(array $submittedQuantifiedAssociations): void
    {
        if (null === $this->quantifiedAssociationCollection) {
            return;
        }

        $this->quantifiedAssociationCollection = $this->quantifiedAssociationCollection->patchQuantifiedAssociations(
            $submittedQuantifiedAssociations
        );
        $this->wasUpdated = true;
    }

    /**
     * {@inheritdoc}
     */
    public function clearQuantifiedAssociations(): void
    {
        if (null === $this->quantifiedAssociationCollection) {
            return;
        }

        $this->quantifiedAssociationCollection = $this->quantifiedAssociationCollection->clearQuantifiedAssociations();
        $this->wasUpdated = true;
    }

    /**
     * {@inheritdoc}
     */
    public function wasUpdated(): bool
    {
        return $this->wasUpdated;
    }

    /**
     * {@inheritdoc}
     */
    public function cleanup(): void
    {
        $this->wasUpdated = false;
    }

    public function __clone()
    {
        $this->values = clone $this->values;
        $this->categories = clone $this->categories;
        $this->groups = clone $this->groups;
        $clonedAssociations = $this->associations->map(
            fn (AssociationInterface $association): AssociationInterface => clone $association
        );
        $this->associations = $clonedAssociations;
        $this->quantifiedAssociationCollection = clone $this->quantifiedAssociationCollection;
    }

    /**
     * Should be handled by an AssociationsCollection->contains()
     *
     * @param AssociationInterface $needleAssociation
     *
     * @return AssociationInterface|null
     */
    private function getSimilarAssociation(AssociationInterface $needleAssociation): ?AssociationInterface
    {
        if ($this->associations->contains($needleAssociation)) {
            return $needleAssociation;
        }

        foreach ($this->associations as $current) {
            if ($current->getReference() === $needleAssociation->getReference()) {
                return $current;
            }
        }

        return null;
    }

    /**
     * @param EntityWithFamilyVariantInterface $entity
     * @param WriteValueCollection         $valueCollection
     *
     * @return WriteValueCollection
     */
    private function getAllValues(
        EntityWithFamilyVariantInterface $entity,
        WriteValueCollection $valueCollection
    ): WriteValueCollection {
        $parent = $entity->getParent();

        if (null === $parent) {
            return $valueCollection;
        }

        foreach ($parent->getValuesForVariation() as $value) {
            $valueCollection->add($value);
        }

        return $this->getAllValues($parent, $valueCollection);
    }

    /**
     * @param EntityWithFamilyVariantInterface $entity
     * @param Collection                       $categoryCollection
     *
     * @return Collection
     */
    private function getAllCategories(
        EntityWithFamilyVariantInterface $entity,
        Collection $categoryCollection
    ): Collection {
        $parent = $entity->getParent();

        if (null === $parent) {
            return $categoryCollection;
        }

        foreach ($parent->getCategories() as $category) {
            if (!$categoryCollection->contains($category)) {
                $categoryCollection->add($category);
            }
        }

        return $this->getAllCategories($parent, $categoryCollection);
    }

    /**
     * Does the ancestry of the entity already has the $category?
     *
     * @param CategoryInterface $category
     *
     * @return bool
     */
    private function hasAncestryCategory(CategoryInterface $category): bool
    {
        $parent = $this->getParent();
        if (null === $parent) {
            return false;
        }

        // no need recursion here as getCategories already look in the whole ancestry
        foreach ($parent->getCategories() as $ancestryCategory) {
            if ($ancestryCategory->getCode() === $category->getCode()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param EntityWithFamilyVariantInterface $entity
     * @param Collection                       $associationsCollection
     *
     * @return Collection
     */
    private function getAncestryAssociations(
        EntityWithFamilyVariantInterface $entity,
        Collection $associationsCollection
    ): Collection {
        $parent = $entity->getParent();

        if (null === $parent) {
            return $associationsCollection;
        }

        foreach ($parent->getAllAssociations() as $association) {
            $associationsCollection = $this->mergeAssociation($association, $associationsCollection);
        }

        return $associationsCollection;
    }

    /**
     * Merges one association in an association collection.
     * It first merge the product existing association
     * And then merges the association into the collection
     *
     * Merging an association means merging all the products, product models and groups
     * into the collection associations or adding it if it doesn't exist
     *
     * @param AssociationInterface $association
     * @param Collection           $associationsCollection
     *
     * @return Collection
     */
    private function mergeAssociation(
        AssociationInterface $association,
        Collection $associationsCollection
    ): Collection {
        $foundInCollection = null;
        foreach ($associationsCollection as $associationInCollection) {
            if ($associationInCollection->getAssociationType()->getCode() ===
                $association->getAssociationType()->getCode()) {
                $foundInCollection = $associationInCollection;
            }
        }

        if (null !== $foundInCollection) {
            foreach ($association->getProducts() as $product) {
                $foundInCollection->addProduct($product);
            }
            foreach ($association->getProductModels() as $productModel) {
                $foundInCollection->addProductModel($productModel);
            }
            foreach ($association->getGroups() as $group) {
                $foundInCollection->addGroup($group);
            }
        }
        $associationsCollection->add($association);

        return $associationsCollection;
    }
}
