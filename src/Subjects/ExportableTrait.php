<?php

/**
 * TechDivision\Import\Subjects\ExportableTrait
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

namespace TechDivision\Import\Subjects;

use TechDivision\Import\Utils\ColumnKeys;
use TechDivision\Import\Adapter\ExportAdapterInterface;

/**
 * The trait implementation for the artefact export functionality.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import
 * @link      http://www.techdivision.com
 */
trait ExportableTrait
{

    /**
     * The array containing the data for product type configuration (configurables, bundles, etc).
     *
     * @var array
     */
    protected $artefacs = array();

    /**
     * The export adapter instance.
     *
     * @var \TechDivision\Import\Adapter\ExportAdapterInterface
     */
    protected $exportAdapter;

    /**
     * Return's the artefacts for post-processing.
     *
     * @return array The artefacts
     */
    public function getArtefacts()
    {
        return $this->artefacs;
    }

    /**
     * Add the passed product type artefacts to the product with the
     * last entity ID.
     *
     * @param string $type      The artefact type, e. g. configurable
     * @param array  $artefacts The product type artefacts
     *
     * @return void
     * @uses \TechDivision\Import\Product\Subjects\BunchSubject::getLastEntityId()
     */
    public function addArtefacts($type, array $artefacts)
    {

        // query whether or not, any artefacts are available
        if (sizeof($artefacts) === 0) {
            return;
        }

        // serialize the original data
        array_walk($artefacts, function(&$artefact) {
            if (isset($artefact[ColumnKeys::ORIGINAL_DATA])) {
                $artefact[ColumnKeys::ORIGINAL_DATA] = serialize($artefact[ColumnKeys::ORIGINAL_DATA]);
            }
        });

        // append the artefacts to the stack
        $this->artefacs[$type][$this->getLastEntityId()][] = $artefacts;
    }

    /**
     * Return the artefacts for the passed type and entity ID.
     *
     * @param string $type     The artefact type, e. g. configurable
     * @param string $entityId The entity ID to return the artefacts for
     *
     * @return array The array with the artefacts
     * @throws \Exception Is thrown, if no artefacts are available
     */
    public function getArtefactsByTypeAndEntityId($type, $entityId)
    {

        // query whether or not, artefacts for the passed params are available
        if (isset($this->artefacs[$type][$entityId])) {
            return $this->artefacs[$type][$entityId];
        }

        // throw an exception if not
        throw new \Exception(
            sprintf(
                'Cant\'t load artefacts for type %s and entity ID %d',
                $type,
                $entityId
            )
        );
    }

    /**
     * Create's and return's a new empty artefact entity.
     *
     * @param array $columns             The array with the column data
     * @param array $originalColumnNames The array with a mapping from the old to the new column names
     *
     * @return array The new artefact entity
     */
    public function newArtefact(array $columns, array $originalColumnNames = array())
    {

        // initialize the original data
        $originalData = array(
            ColumnKeys::ORIGINAL_FILENAME     => $this->getFilename(),
            ColumnKeys::ORIGINAL_LINE_NUMBER  => $this->getLineNumber(),
            ColumnKeys::ORIGINAL_COLUMN_NAMES => $originalColumnNames
        );

        // prepare a new artefact entity
        $artefact = array(ColumnKeys::ORIGINAL_DATA => $originalData);

        // merge the columns into the artefact entity and return it
        return array_merge($artefact, $columns);
    }

    /**
     * Export's the artefacts to CSV files.
     *
     * @param integer $timestamp The timestamp part of the original import file
     * @param string  $counter   The counter part of the origin import file
     *
     * @return void
     */
    public function export($timestamp, $counter)
    {
        $this->getExportAdapter()->export($this->getArtefacts(), $this->getTargetDir(), $timestamp, $counter);
    }

    /**
     * Set's the exporter adapter instance.
     *
     * @param \TechDivision\Import\Adapter\ExportAdapterInterface $exportAdapter The exporter adapter instance
     *
     * @return void
     */
    public function setExportAdapter(ExportAdapterInterface $exportAdapter)
    {
        $this->exportAdapter = $exportAdapter;
    }

    /**
     * Return's the exporter adapter instance.
     *
     * @return \TechDivision\Import\Adapter\ExportAdapterInterface The exporter adapter instance
     */
    public function getExportAdapter()
    {
        return $this->exportAdapter;
    }

    /**
     * Return's the target directory for the artefact export.
     *
     * @return string The target directory for the artefact export
     */
    protected function getTargetDir()
    {
        return $this->getNewSourceDir($this->getSerial());
    }
}
