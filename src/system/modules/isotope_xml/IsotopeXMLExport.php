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

class IsotopeXMLExport extends IsotopeXML
{
	/**
     * @var DOMDocument
     */
    protected $domDocument;

    /**
     * @var DataContainer
     */
    protected $dataContainer;

    public function create(DataContainer $dc, $strXSDPath)
    {
        // assign data container
        $this->dataContainer = $dc;

		// create new dom document (xml)
        $this->domDocument = new DOMDocument('1.0', $GLOBALS['TL_CONFIG']['dbCharset']);
        $this->domDocument->formatOutput = true;

        // add isotope node
        $this->addIsotopeNode($strXSDPath);
    }

    public function output($strFilename)
    {
        $strXML = $this->domDocument->saveXML();

        ob_clean();

        foreach(headers_list() as $strKey => $value)
        {
            header_remove($strKey);
        }

        header('Content-type: text/xml');
        header('Content-Disposition: attachment; filename="' . $strFilename . '"');
        header('Content-Length: ' . mb_strlen($strXML));

        print $strXML;
        die();
    }

    protected function addIsotopeNode($strXSDPath)
    {
        // define isotope node
        $objIsotopeNode = $this->domDocument->createElement('isotope');
        $this->domDocument->appendChild($objIsotopeNode);

        // add attributes
        self::addAttributesToNode($this->domDocument, $objIsotopeNode, array(
            'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            'xsi:schemaLocation' => $strXSDPath,
        ));

        $this->addProductsNode($objIsotopeNode);
    }

    protected function addProductsNode(DOMNode $objIsotopeNode)
    {
        // get the fields per product type
        $arrFieldsPerProductTypes = $this->getFieldsPerProductTypes();

        // define products node
        $objProductsNode = $this->domDocument->createElement('products');
        $objIsotopeNode->appendChild($objProductsNode);

        foreach($this->getProducts() as $arrProduct)
        {
            $this->addProductToDOMDocument($objProductsNode, $arrProduct, $arrFieldsPerProductTypes);
        }
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

        foreach($arrFieldValue as $mixKey => $mixValue)
        {
            self::addAttributeToNode($this->domDocument, $objNode, $mixKey, $mixValue);
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
            $objSubNode = $this->domDocument->createElement(self::englishSingular($strFieldName));
            $objNode->appendChild($objSubNode);

            foreach($arrMedia as $mixKey => $mixValue)
            {
                self::addAttributeToNode($this->domDocument, $objSubNode, $mixKey, $mixValue);
            }
        }

        return $objNode;
    }

    /**
     * @param DOMNode $objNode
     * @param array $arrOptions
     * @param array $arrFieldValues
     */
    protected function handleMultiOptions(DOMNode $objNode, array $arrOptions, array $arrFieldValues)
    {
        $boolNumeric = false;

        foreach($arrOptions as $mixKey => $mixValue)
        {
            if(is_numeric($mixKey))
            {
                $boolNumeric = true;
            }
        }

        if(!$boolNumeric)
        {
            $this->handleMultiOptionsWithStrinKeys($objNode, $arrOptions, $arrFieldValues);
        }
        else
        {
            $this->handleMultiOptionsWithNumericKeys($objNode, $arrOptions, $arrFieldValues);
        }
    }

    /**
     * @param DOMNode $objNode
     * @param array $arrOptions
     * @param array $arrFieldValues
     */
    protected function handleMultiOptionsWithStrinKeys(DOMNode $objNode, array $arrOptions, array $arrFieldValues)
    {
        foreach($arrOptions as $mixKey => $mixValue)
        {
            $objAttribute = $this->domDocument->createAttribute($mixKey);
            $objNode->appendChild($objAttribute);

            if(in_array($mixKey, $arrFieldValues))
            {
                $objAttribute->nodeValue = 1;
            }
            else
            {
                $objAttribute->nodeValue = 0;
            }
        }
    }

    /**
     * @param DOMNode $objNode
     * @param array $arrOptions
     * @param array $arrFieldValues
     */
    protected function handleMultiOptionsWithNumericKeys(DOMNode $objNode, array $arrOptions, array $arrFieldValues)
    {
        foreach($arrOptions as $mixKey => $mixValue)
        {
            if(in_array($mixKey, $arrFieldValues))
            {                
                $objSubNode = $this->domDocument->createElement(self::englishSingular($objNode->tagName));
                $objNode->appendChild($objSubNode);

                self::addAttributesToNode($this->domDocument, $objSubNode, array(
                    'key' => $mixKey,
                    'value' => $mixValue,
                ));        
            }
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