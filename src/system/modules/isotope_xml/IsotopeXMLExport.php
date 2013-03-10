<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  IMS Internet Marketing Solutions Ltd. 2013
 * @author     Dominik Zogg <dz@erfolgreiche-internetseiten.ch>
 * @package    isotope_xml_export
 * @license    LGPLv3
 */

class IsotopeXMLExport extends Backend
{
	/**
     * @var DOMDocument
     */
    protected $domDocument;

    /**
     * @var DataContainer
     */
    protected $dataContainer;

    public function export(DataContainer $dc)
    {
        // assign data container
        $this->dataContainer = $dc;

		// create new dom document (xml)
        $this->domDocument = new DOMDocument('1.0', $GLOBALS['TL_CONFIG']['dbCharset']);
        $this->domDocument->formatOutput = true;

        // add isotope node
        $this->addIsotopeNode();

        header('Content-type: text/xml');
        header('Content-Disposition: attachment; filename="isotope-products.xml"');

        echo $this->domDocument->saveXML();
        die();
    }

    protected function addIsotopeNode()
    {
        // define isotope node
        $objIsotopeNode = $this->domDocument->createElement('isotope');

        // add xmlns attribute
        $objIsotopeNodeXmlNs = $this->domDocument->createAttribute('xmlns');
        $objIsotopeNodeXmlNs->value = 'http://www.isotopeecommerce.com/schema';
        $objIsotopeNode->appendChild($objIsotopeNodeXmlNs);

        // add xmlns:xsi attribute
        $objIsotopeNodeXmlNsXsi = $this->domDocument->createAttribute('xmlns:xsi');
        $objIsotopeNodeXmlNsXsi->value = 'http://www.w3.org/2001/XMLSchema-instance';
        $objIsotopeNode->appendChild($objIsotopeNodeXmlNsXsi);

        // add xsi:schemaLocation attribute
        $objIsotopeNodeXsiSchemaLocation = $this->domDocument->createAttribute('xsi:schemaLocation');
        $objIsotopeNodeXsiSchemaLocation->value = 'http://www.isotopeecommerce.com/schema https://shop.1-3-5.ch/contao-isotope-xml.xsd';
        $objIsotopeNode->appendChild($objIsotopeNodeXsiSchemaLocation);

        // append node to document
        $this->domDocument->appendChild($objIsotopeNode);

        $this->addProductsNode($objIsotopeNode);
    }

    protected function addProductsNode(DOMNode $objIsotopeNode)
    {
        // define products node
        $objProductsNode = $this->domDocument->createElement('products');

        // append node to isotope node
        $objIsotopeNode->appendChild($objProductsNode);

        // get the fields per product type
        $arrFieldsPerProductTypes = $this->getFieldsPerProductTypes();

        foreach($this->getProducts() as $arrProduct)
        {
            $this->addProductToDOMDocument($objProductsNode, $arrProduct, $arrFieldsPerProductTypes);
        }
    }

    /**
     * @param array $arrProductTypeIds
     * @return array
     */
    protected function getFieldsPerProductTypes(array $arrProductTypeIds = array())
    {
        if(count($arrProductTypeIds) == 0)
        {
            $arrProductTypeIds = $this->getAllUsedProductTypeIds();
        }

        // get all attributes of product types
        $arrAttributesPerProductTypes = $this->getProductTypeAttributes($arrProductTypeIds);

        // get the active fields behind
        return $this->getActiveProductFieldsPerProductTypes($arrAttributesPerProductTypes);
    }

    /**
     * @return array
     */
    protected function getAllUsedProductTypeIds()
    {
        $arrProductTypeIds = array();

        $objProduct = $this
            ->Database
            ->query("
                SELECT
                    type
                FROM
                    tl_iso_products
                GROUP BY
                    type
                ORDER BY
                    type
            ")
        ;

        while($objProduct->next())
        {
            $arrProductTypeIds[] = $objProduct->type;
        }

        return $arrProductTypeIds;
    }

    /**
     * @param array
     * @return array
     */
    protected function getProductTypeAttributes(array $arrProductTypeIds)
    {
        $arrAttributesPerProductType = array();

        $objProductType = $this
            ->Database
            ->query("
                SELECT
                    id,
                    attributes
                FROM
                    tl_iso_producttypes
                WHERE
                    id IN(" . implode(',', $arrProductTypeIds) . ")
            ")
        ;

        while($objProductType->next())
        {
            $arrAttributesPerProductType[$objProductType->id] = unserialize($objProductType->attributes);
        }

        return $arrAttributesPerProductType;
    }

    /**
     * @param array $arrAttributesPerProductTypes
     * @return array
     */
    protected function getActiveProductFieldsPerProductTypes(array $arrAttributesPerProductTypes)
    {
        // alias for readability
        $arrProductFields = &$GLOBALS['TL_DCA']['tl_iso_products']['fields'];

        $arrActiveProductFieldsPerProductTypes = array();

        foreach($arrAttributesPerProductTypes as $intProductTypeId => $arrProductTypeFields)
        {
            // fix sorting based on the position value of the field
            uasort($arrProductTypeFields, function($a, $b){
                if($a['position'] == $b['position']) { return 0; }
                return ($a['position'] < $b['position']) ? -1 : 1;
            });

            foreach($arrProductTypeFields as $strFieldName => $arrProductField)
            {
                if($arrProductField['enabled'] && array_key_exists($strFieldName, $arrProductFields))
                {
                    $arrActiveProductFieldsPerProductTypes[$intProductTypeId][$strFieldName] = $arrProductFields[$strFieldName];
                }
            }
        }

        return $arrActiveProductFieldsPerProductTypes;
    }

    /**
     * @param array $arrProductIds
     * @return array
     */
    protected function getProducts(array $arrProductIds = array())
    {
        $arrProducts = array();

        $objProduct = $this
            ->Database
            ->query("
                SELECT
                    *
                FROM
                    tl_iso_products
                WHERE
                    language = ''
                " . (count($arrProductIds) == 0 ? "" : "AND id IN(" . implode(',', $arrProductIds) . ")") . "
            ")
        ;

        while($objProduct->next())
        {
            $arrProducts[$objProduct->id] = $objProduct->row();
        }

        return $arrProducts;
    }

    /**
     * @param array $arrProduct
     * @param array $arrFieldsPerProductTypes
     */
    protected function addProductToDOMDocument(DOMNode $objProductsNode, array $arrProduct, array $arrFieldsPerProductTypes)
    {
        if(!array_key_exists($arrProduct['type'], $arrFieldsPerProductTypes))
        {
            throw new Exception("There are no field per product type definition for the wished product!");
        }

        $objProductNode = $this->domDocument->createElement('product');

        foreach($arrFieldsPerProductTypes[$arrProduct['type']] as $strFieldName => $arrFieldDefinition)
        {
            $strMethodName = 'prepare' . ucfirst($arrFieldDefinition['inputType']) . 'Node';

            if(method_exists($this, $strMethodName))
            {
                $objFieldNode = call_user_func_array(
                    array($this, $strMethodName),
                    array(
                        $strFieldName,
                        $arrProduct[$strFieldName],
                        $arrFieldDefinition
                    )
                );

                $objProductNode->appendChild($objFieldNode);
            }
            else
            {
                throw new Exception("Please implement method {$strMethodName} for field {$strFieldName}");
            }
        }

        $objProductsNode->appendChild($objProductNode);
    }

    /**
     * @param string $strFieldName
     * @param mixed $mixFieldValue
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareTextNode($strFieldName, $mixFieldValue, array $arrFieldDefinition)
    {
        $objNode = $this->domDocument->createElement($strFieldName);
        $objNode->nodeValue = $mixFieldValue;
        return $objNode;
    }

    /**
     * @param string $strFieldName
     * @param mixed $mixFieldValue
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareTextareaNode($strFieldName, $mixFieldValue, array $arrFieldDefinition)
    {
        $objNode = $this->domDocument->createElement($strFieldName);
        $objCdataSection = $this->domDocument->createCDATASection(self::formatCData($mixFieldValue));
        $objNode->appendChild($objCdataSection);
        return $objNode;
    }

    /**
     * @param string $strFieldName
     * @param mixed $mixFieldValue
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareCheckboxNode($strFieldName, $mixFieldValue, array $arrFieldDefinition)
    {
        return $this->prepareClickableNode($strFieldName, $mixFieldValue, $arrFieldDefinition);
    }

    /**
     * @param string $strFieldName
     * @param mixed $mixFieldValue
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareRadioNode($strFieldName, $mixFieldValue, array $arrFieldDefinition)
    {
        return $this->prepareClickableNode($strFieldName, $mixFieldValue, $arrFieldDefinition);
    }

    /**
     * @param string $strFieldName
     * @param mixed $mixFieldValue
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareSelectNode($strFieldName, $mixFieldValue, array $arrFieldDefinition)
    {
        return $this->prepareClickableNode($strFieldName, $mixFieldValue, $arrFieldDefinition);
    }

    /**
     * @param string $strFieldName
     * @param mixed $mixFieldValue
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function preparePageTreeNode($strFieldName, $mixFieldValue, array $arrFieldDefinition)
    {
        return $this->prepareClickableNode($strFieldName, $mixFieldValue, $arrFieldDefinition);
    }

    /**
     * @param string $strFieldName
     * @param mixed $mixFieldValue
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareClickableNode($strFieldName, $mixFieldValue, array $arrFieldDefinition)
    {
        $objNode = $this->domDocument->createElement($strFieldName);

        // options
        $arrOptions = $this->getFieldOptions($arrFieldDefinition);

        // multiple values
        if(array_key_exists('eval', $arrFieldDefinition) &&
           array_key_exists('multiple', $arrFieldDefinition['eval']) &&
           $arrFieldDefinition['eval']['multiple'])
        {
            $arrFieldValues = unserialize($mixFieldValue);

            if(is_array($arrFieldValues))
            {
                $this->handleMultiOptions($objNode, $arrOptions, $arrFieldValues);
            }            
        }
        // single value
        elseif(count($arrOptions))
        {
            $this->handleSingleOption($objNode, $arrOptions, $mixFieldValue);
        }
        // no opts
        else
        {
            $objNode->nodeValue = $mixFieldValue;
        }

        return $objNode;      
    }

    /**
     * @param string $strFieldName
     * @param mixed $mixFieldValue
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareTimePeriodNode($strFieldName, $mixFieldValue, array $arrFieldDefinition)
    {
        $objNode = $this->domDocument->createElement($strFieldName);

        $arrFieldValue = unserialize($mixFieldValue);

        foreach($arrFieldValue as $mixKey => $mixvalue)
        {
            $strAttributeName = is_numeric($mixKey) ? 'numeric-' . $mixKey : $mixKey;

            $objAttribute = $this->domDocument->createAttribute($strAttributeName);
            $objAttribute->nodeValue = $mixvalue;
            $objNode->appendChild($objAttribute);
        }

        return $objNode;
    }

    /**
     * @param string $strFieldName
     * @param mixed $mixFieldValue
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareMediaManagerNode($strFieldName, $mixFieldValue, array $arrFieldDefinition)
    {
        $objNode = $this->domDocument->createElement($strFieldName);

        $arrFieldValue = unserialize($mixFieldValue);

        foreach($arrFieldValue as $arrMedia)
        {
            $objSubNode = $this->domDocument->createElement(substr($strFieldName, 0, -1));

            foreach($arrMedia as $mixKey => $mixvalue)
            {
                $strAttributeName = is_numeric($mixKey) ? 'numeric-' . $mixKey : $mixKey;
                $objAttribute = $this->domDocument->createAttribute($strAttributeName);
                $objAttribute->nodeValue = $mixvalue;
                $objSubNode->appendChild($objAttribute);
            }

            $objNode->appendChild($objSubNode);
        }

        return $objNode;
    }

    /**
     * @param array $arrFieldDefinition
     * @return array
     */
    protected function getFieldOptions(array $arrFieldDefinition)
    {
        // simple option array
        if(array_key_exists('options', $arrFieldDefinition))
        {
            return $arrFieldDefinition['options'];
        }

        // callback function to get the options
        if(array_key_exists('options_callback', $arrFieldDefinition))
        {
            $arrCallback = &$arrFieldDefinition['options_callback'];
            $objCallback = method_exists($arrCallback[0], 'getInstance') ? $arrCallback[0]::getInstance() : new $arrCallback[0];
            return call_user_func_array(array($objCallback, $arrCallback[1]), array($this->dataContainer));
        }

        // foreign key to get the options (example: tl_page.title)
        if(array_key_exists('foreignKey', $arrFieldDefinition))
        {
            $arrTableAndField = explode('.', $arrFieldDefinition['foreignKey']);

            $arrOptions = array();

            $objTable = $this
                ->Database
                ->query("SELECT id,{$arrTableAndField[1]} FROM {$arrTableAndField[0]}")
            ;

            while($objTable->next())
            {
                $arrOptions[$objTable->id] = $objTable->$arrTableAndField[1];
            }

            return $arrOptions;
        }

        return array();
    }

    /**
     * @param DOMNode$objNode
     * @param array $arrOptions
     * @param array $arrFieldValues
     */
    protected function handleMultiOptions(DOMNode $objNode, array $arrOptions, array $arrFieldValues)
    {
        foreach($arrOptions as $mixKey => $mixValue)
        {
            $strAttributeName = is_numeric($mixKey) ? 'numeric-' . $mixKey : $mixKey;

            $objAttribute = $this->domDocument->createAttribute($strAttributeName);

            if(in_array($mixKey, $arrFieldValues))
            {
                $objAttribute->nodeValue = true;
            }
            else
            {
                $objAttribute->nodeValue = false;
            }

            $objNode->appendChild($objAttribute);
        } 
    }

    /**
     * @param DOMNode $objNode
     * @param array $arrOptions
     * @param scalar $mixFieldValue
     */
    protected function handleSingleOption(DOMNode $objNode, array $arrOptions, $mixFieldValue)
    {
        foreach($arrOptions as $mixKey => $mixValue)
        {
            if($mixKey == $mixFieldValue)
            {
                $objNode->nodeValue = $mixFieldValue;
            }
        } 
    }

    /**
     * @param string
     * @return string
     */
    protected static function formatCData($strData)
    {
        return str_replace(
            array("\r\n", "\r", "\n", '> <'),
            array(' ', ' ', ' ', '><'),
            $strData
        );
    }
}