<?php

/**
 * TechDivision\Import\Observers\AttributeObserverTrait
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import
 * @link      http://www.techdivision.com
 */

namespace TechDivision\Import\Observers;

use TechDivision\Import\Utils\MemberNames;
use TechDivision\Import\Utils\StoreViewCodes;
use TechDivision\Import\Utils\BackendTypeKeys;

/**
 * Observer that creates/updates the EAV attributes.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import
 * @link      http://www.techdivision.com
 */
trait AttributeObserverTrait
{

    /**
     * The ID of the attribute to create the values for.
     *
     * @var integer
     */
    protected $attributeId;

    /**
     * The attribute code of the attribute to create the values for.
     *
     * @var string
     */
    protected $attributeCode;

    /**
     * The backend type of the attribute to create the values for.
     *
     * @var string
     */
    protected $backendType;

    /**
     * The attribute value to process.
     *
     * @var mixed
     */
    protected $attributeValue;

    /**
     * The attribute code that has to be processed.
     *
     * @return string The attribute code
     */
    public function getAttributeCode()
    {
        return $this->attributeCode;
    }

    /**
     * The attribute value that has to be processed.
     *
     * @return string The attribute value
     */
    public function getAttributeValue()
    {
        return $this->attributeValue;
    }

    /**
     * Process the observer's business logic.
     *
     * @return void
     */
    protected function process()
    {

        // initialize the store view code
        $this->prepareStoreViewCode();

        // load the attributes by the found attribute set and the backend types
        $attributes = $this->getAttributes();
        $backendTypes = $this->getBackendTypes();

        // remove all the empty values from the row
        $row = array_filter(
            $this->row,
            function ($value, $key) {
                return ($value !== null && $value !== '');
            },
            ARRAY_FILTER_USE_BOTH
        );

        // load the header keys
        $headers = array_flip($this->getHeaders());

        // iterate over the attributes and append them to the row
        foreach ($row as $key => $attributeValue) {
            // query whether or not attribute with the found code exists
            if (!isset($attributes[$attributeCode = $headers[$key]])) {
                // log a message in debug mode
                if ($this->isDebugMode()) {
                    $this->getSystemLogger()->debug(
                        sprintf(
                            'Can\'t find attribute with attribute code %s in file %s on line %d',
                            $attributeCode,
                            $this->getFilename(),
                            $this->getLineNumber()
                        )
                    );
                }

                // stop processing
                continue;

            } else {
                // log a message in debug mode
                if ($this->isDebugMode()) {
                    $this->getSystemLogger()->debug(
                        sprintf(
                            'Found attribute with attribute code %s in file %s on line %d',
                            $attributeCode,
                            $this->getFilename(),
                            $this->getLineNumber()
                        )
                    );
                }
            }

            // if yes, load the attribute by its code
            $attribute = $attributes[$attributeCode];

            // load the backend type => to find the apropriate entity
            $backendType = $attribute[MemberNames::BACKEND_TYPE];
            if ($backendType == null) {
                $this->getSystemLogger()->warning(
                    sprintf(
                        'Found EMTPY backend type for attribute %s in file %s on line %d',
                        $attributeCode,
                        $this->getFilename(),
                        $this->getLineNumber()
                    )
                );
                continue;
            }

            // do nothing on static backend type
            if ($backendType === BackendTypeKeys::BACKEND_TYPE_STATIC) {
                continue;
            }

            // query whether or not we've found a supported backend type
            if (isset($backendTypes[$backendType])) {
                // initialize attribute ID/code and backend type
                $this->attributeId = $attribute[MemberNames::ATTRIBUTE_ID];
                $this->attributeCode = $attributeCode;
                $this->backendType = $backendType;

                // initialize the persist method for the found backend type
                list ($persistMethod, ) = $backendTypes[$backendType];

                // set the attribute value
                $this->attributeValue = $attributeValue;

                // try to prepare the attribute values
                if ($attr = $this->prepareAttributes()) {
                    // initialize and persist the attribute
                    $entity = $this->initializeAttribute($attr);
                    $this->$persistMethod($entity);
                }

                // continue with the next value
                continue;
            }

            // log the debug message
            $this->getSystemLogger()->debug(
                sprintf(
                    'Found invalid backend type %s for attribute %s in file %s on line %s',
                    $this->backendType,
                    $this->attributeCode,
                    $this->getFilename(),
                    $this->getLineNumber()
                )
            );
        }
    }

    /**
     * Prepare the attributes of the entity that has to be persisted.
     *
     * @return array|null The prepared attributes
     */
    protected function prepareAttributes()
    {

        // laod the callbacks for the actual attribute code
        $callbacks = $this->getCallbacksByType($this->attributeCode);

        // invoke the pre-cast callbacks
        /** @var \TechDivision\Import\Callbacks\CallbackInterface $callback */
        foreach ($callbacks as $callback) {
            $this->attributeValue = $callback->handle($this);
        }

        // query whether or not the attribute has been be processed by the callbacks
        if ($this->attributeValue === null) {
            return;
        }

        // load the ID of the product that has been created recently
        $lastEntityId = $this->getPrimaryKey();

        // load the store ID
        $storeId = $this->getRowStoreId(StoreViewCodes::ADMIN);

        // cast the value based on the backend type
        $castedValue = $this->castValueByBackendType($this->backendType, $this->attributeValue);

        // prepare the attribute values
        return $this->initializeEntity(
            array(
                MemberNames::ENTITY_ID    => $lastEntityId,
                MemberNames::ATTRIBUTE_ID => $this->attributeId,
                MemberNames::STORE_ID     => $storeId,
                MemberNames::VALUE        => $castedValue
            )
        );
    }

    /**
     * Initialize the category product with the passed attributes and returns an instance.
     *
     * @param array $attr The category product attributes
     *
     * @return array The initialized category product
     */
    protected function initializeAttribute(array $attr)
    {
        return $attr;
    }

    /**
     * Return's the PK to create the product => attribute relation.
     *
     * @return integer The PK to create the relation with
     */
    protected function getPrimaryKey()
    {
        return $this->getLastEntityId();
    }

    /**
     * Map the passed attribute code, if a header mapping exists and return the
     * mapped mapping.
     *
     * @param string $attributeCode The attribute code to map
     *
     * @return string The mapped attribute code, or the original one
     */
    protected function mapAttributeCodeByHeaderMapping($attributeCode)
    {
        return $this->getSubject()->mapAttributeCodeByHeaderMapping($attributeCode);
    }

    /**
     * Return's the array with callbacks for the passed type.
     *
     * @param string $type The type of the callbacks to return
     *
     * @return array The callbacks
     */
    protected function getCallbacksByType($type)
    {
        return $this->getSubject()->getCallbacksByType($type);
    }

    /**
     * Return's mapping for the supported backend types (for the product entity) => persist methods.
     *
     * @return array The mapping for the supported backend types
     */
    protected function getBackendTypes()
    {
        return $this->getSubject()->getBackendTypes();
    }

    /**
     * Return's the attributes for the attribute set of the product that has to be created.
     *
     * @return array The attributes
     * @throws \Exception
     */
    protected function getAttributes()
    {
        return $this->getSubject()->getAttributes();
    }
}
