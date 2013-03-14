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

class IsotopeXSDGenerator extends IsotopeXML
{
	/**
     * @var DOMDocument
     */
    protected $domDocument;

    /**
     * @var DataContainer
     */
    protected $dataContainer;

    public function create(DataContainer $dc)
    {
        // assign data container
        $this->dataContainer = $dc;

        // create new dom document (xml)
        $this->domDocument = new DOMDocument('1.0', $GLOBALS['TL_CONFIG']['dbCharset']);
        $this->domDocument->formatOutput = true;

        // add xsd schema
        $this->addXSDSchemaNode();
    }

    /**
     * @param sting $strPath
     */
    public function save($strPath)
    {
        $objFile = new File($strPath);
        $objFile->write($this->domDocument->saveXML());
        $objFile->close();
    }

    protected function addXSDSchemaNode()
    {
        // define xsd schmea node
        $objXsdSchema = $this->domDocument->createElement('xsd:schema');

        // add attributes
        self::addAttributesToNode($this->domDocument, $objXsdSchema, array(
            'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            'elementFormDefault' => 'qualified',
            'attributeFormDefault' => 'unqualified',
        ));

        // append node to document
        $this->domDocument->appendChild($objXsdSchema);

        // add isotope xsd node
        $this->addIsotopeXSDElement($objXsdSchema);
    }

    /**
     * @param DOMNode $objXsdSchema
     */
    protected function addIsotopeXSDElement(DOMNode $objXsdSchema)
    {
        $objIsotopeXsdElement = $this->domDocument->createElement('xsd:element');
        $objXsdSchema->appendChild($objIsotopeXsdElement);
        
        // add attributes
        self::addAttributeToNode($this->domDocument, $objIsotopeXsdElement, 'name', 'isotope');

        $objIsotopeXsdComplexType = $this->domDocument->createElement('xsd:complexType');
        $objIsotopeXsdElement->appendChild($objIsotopeXsdComplexType);

        $objIsotopeXsdSequence = $this->domDocument->createElement('xsd:sequence');
        $objIsotopeXsdComplexType->appendChild($objIsotopeXsdSequence);

        $this->addProductsXSDElement($objIsotopeXsdSequence);
    }

    /**
     * @param DOMNode $objIsotopeXsdSequence
     */
    protected function addProductsXSDElement(DOMNode $objIsotopeXsdSequence)
    {
        $objProductsXsdElement = $this->domDocument->createElement('xsd:element');
        $objIsotopeXsdSequence->appendChild($objProductsXsdElement);

        // add attributes
        self::addAttributeToNode($this->domDocument, $objProductsXsdElement, 'name', 'products');

        $objProductsXsdComplexType = $this->domDocument->createElement('xsd:complexType');
        $objProductsXsdElement->appendChild($objProductsXsdComplexType);

        $objProductsXsdSequence = $this->domDocument->createElement('xsd:sequence');
        $objProductsXsdComplexType->appendChild($objProductsXsdSequence);

        $this->addProductXSDElement($objProductsXsdSequence);
    }

    /**
     * @param DOMNode $objProductsXsdSequence
     */
    protected function addProductXSDElement(DOMNode $objProductsXsdSequence)
    {
        $objProductXsdElement = $this->domDocument->createElement('xsd:element');
        $objProductsXsdSequence->appendChild($objProductXsdElement);

        // add attributes
        self::addAttributesToNode($this->domDocument, $objProductXsdElement, array(
            'name' => 'product',
            'minOccurs' => 0,
            'maxOccurs' => 'unbounded',
        ));

        $objProductXsdComplexType = $this->domDocument->createElement('xsd:complexType');
        $objProductXsdElement->appendChild($objProductXsdComplexType);

        self::addAttributeToNode($this->domDocument, $objProductXsdComplexType, 'mixed', true);

        $objProductXsdSequence = $this->domDocument->createElement('xsd:sequence');
        $objProductXsdComplexType->appendChild($objProductXsdSequence);

        $this->addProductXSDElements($objProductXsdSequence);

        $objAnyXsdElement = $this->domDocument->createElement('xsd:any');
        //$objProductXsdSequence->appendChild($objAnyXsdElement);

        // add attributes
        self::addAttributesToNode($this->domDocument, $objAnyXsdElement, array(
            'namespace' => '##any',
            'processContents' => 'lax',
            'minOccurs' => 0,
            'maxOccurs' => 'unbounded',
        ));
    }

    /**
     * @param DOMNode $objProductXsdSequence
     */
    protected function addProductXSDElements(DOMNode $objProductXsdSequence)
    {
        foreach($this->getFieldsPerProductTypes() as $intProductTypeid => $arrFieldsPerProductType)
        {
            foreach($arrFieldsPerProductType as $strFieldName => $arrFieldDefinition)
            {
                $strMethodName = 'prepare' . ucfirst($arrFieldDefinition['inputType']) . 'Element';

                if(method_exists($this, $strMethodName))
                {
                    $objProductXsdElement = call_user_func_array(
                        array($this, $strMethodName),
                        array(
                            $strFieldName,
                            $arrFieldDefinition
                        )
                    );

                    $objProductXsdSequence->appendChild($objProductXsdElement);
                }
                else
                {
                    throw new Exception("Please implement method {$strMethodName} for field {$strFieldName}");
                }
            }
        }
    }

    /**
     * @param string $strFieldName
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareTextElement($strFieldName, array $arrFieldDefinition)
    {
        $objFieldXsdElement = $this->domDocument->createElement('xsd:element');
        self::addAttributesToNode($this->domDocument, $objFieldXsdElement, array(
            'name' => $strFieldName,
            'type' => 'xsd:string',
        ));
        return $objFieldXsdElement;
    }

    /**
     * @param string $strFieldName
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareTextareaElement($strFieldName, array $arrFieldDefinition)
    {
        $objFieldXsdElement = $this->domDocument->createElement('xsd:element');
        self::addAttributesToNode($this->domDocument, $objFieldXsdElement, array(
            'name' => $strFieldName,
            'type' => 'xsd:string',
        ));
        return $objFieldXsdElement;
    }

    /**
     * @param string $strFieldName
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareCheckboxElement($strFieldName, array $arrFieldDefinition)
    {
        return $this->prepareClickableElement($strFieldName, $arrFieldDefinition);
    }

    /**
     * @param string $strFieldName
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareRadioElement($strFieldName, array $arrFieldDefinition)
    {
        return $this->prepareClickableElement($strFieldName, $arrFieldDefinition);
    }

    /**
     * @param string $strFieldName
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareSelectElement($strFieldName, array $arrFieldDefinition)
    {
        return $this->prepareClickableElement($strFieldName, $arrFieldDefinition);
    }

    /**
     * @param string $strFieldName
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function preparePageTreeElement($strFieldName, array $arrFieldDefinition)
    {
        return $this->prepareClickableElement($strFieldName, $arrFieldDefinition);
    }

    /**
     * @param string $strFieldName
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareTimePeriodElement($strFieldName, array $arrFieldDefinition)
    {
        $objFieldXsdElement = $this->domDocument->createElement('xsd:element');

        self::addAttributeToNode($this->domDocument, $objFieldXsdElement, 'name', $strFieldName);

        $objFieldXsdComplexType = $this->domDocument->createElement('xsd:complexType');
        $objFieldXsdElement->appendChild($objFieldXsdComplexType);

        $objFieldXsdSimpleContent = $this->domDocument->createElement('xsd:simpleContent');
        $objFieldXsdComplexType->appendChild($objFieldXsdSimpleContent);

        $objFieldXsdExtension = $this->domDocument->createElement('xsd:extension');
        $objFieldXsdSimpleContent->appendChild($objFieldXsdExtension);

        self::addAttributeToNode($this->domDocument, $objFieldXsdExtension, 'base', 'xsd:string');

        self::addXsdAttributes($this->domDocument, $objFieldXsdExtension, array(
            'unit' => 'xsd:string',
            'value' => 'xsd:string',
        ));

        return $objFieldXsdElement;
    }

    /**
     * @param string $strFieldName
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareMediaManagerElement($strFieldName, array $arrFieldDefinition)
    {

        $objFieldXsdElement = $this->domDocument->createElement('xsd:element');

        self::addAttributeToNode($this->domDocument, $objFieldXsdElement, 'name', $strFieldName);

        $objFieldXsdComplexType = $this->domDocument->createElement('xsd:complexType');
        $objFieldXsdElement->appendChild($objFieldXsdComplexType);

        $objFieldXsdSequence = $this->domDocument->createElement('xsd:sequence');
        $objFieldXsdComplexType->appendChild($objFieldXsdSequence);

        $objFieldXsdSubElement = $this->domDocument->createElement('xsd:element');
        $objFieldXsdSequence->appendChild($objFieldXsdSubElement);

        self::addAttributesToNode($this->domDocument, $objFieldXsdSubElement, array(
            'name' => self::englishSingular($strFieldName),
            'minOccurs' => 0,
            'maxOccurs' => 'unbounded',
        ));

        $objFieldXsdSubComplexType = $this->domDocument->createElement('xsd:complexType');
        $objFieldXsdSubElement->appendChild($objFieldXsdSubComplexType);

        $objFieldXsdSimpleContent = $this->domDocument->createElement('xsd:simpleContent');
        $objFieldXsdSubComplexType->appendChild($objFieldXsdSimpleContent);

        $objFieldXsdExtension = $this->domDocument->createElement('xsd:extension');
        $objFieldXsdSimpleContent->appendChild($objFieldXsdExtension);

        self::addAttributeToNode($this->domDocument, $objFieldXsdExtension, 'base', 'xsd:string');

        self::addXsdAttributes($this->domDocument, $objFieldXsdExtension, array(
            'src' => 'xsd:string',
            'alt' => 'xsd:string',
            'desc' => 'xsd:string',
            'translate' => 'xsd:string',
        ));

        return $objFieldXsdElement;
    }

    /**
     * @param string $strFieldName
     * @param array $arrFieldDefinition
     * @return DOMNode
     */
    protected function prepareClickableElement($strFieldName, array $arrFieldDefinition)
    {
        $objFieldXsdElement = $this->domDocument->createElement('xsd:element');

        self::addAttributeToNode($this->domDocument, $objFieldXsdElement, 'name', $strFieldName);

        // multiple values
        if(array_key_exists('eval', $arrFieldDefinition) &&
           array_key_exists('multiple', $arrFieldDefinition['eval']) &&
           $arrFieldDefinition['eval']['multiple'])
        {
            $arrOptions = $this->getFieldOptions($arrFieldDefinition);
            $this->handleMultiOptions($objFieldXsdElement, $strFieldName, $arrOptions);           
        }
        else
        {
            self::addAttributeToNode($this->domDocument, $objFieldXsdElement, 'type', 'xsd:string');
        }

        return $objFieldXsdElement;
    }

    /**
     * @param DOMNode $objFieldXsdElement
     * @param string $strFieldName
     * @param array $arrOptions
     */
    protected function handleMultiOptions(DOMNode $objFieldXsdElement, $strFieldName, array $arrOptions)
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
            $this->handleMultiOptionsWithStrinKeys($objFieldXsdElement, $arrOptions);
        }
        else
        {
            $this->handleMultiOptionsWithNumericKeys($objFieldXsdElement, $strFieldName);
        }
    }

    /**
     * @param DOMNode $objFieldXsdElement
     * @param array $arrOptions
     */
    protected function handleMultiOptionsWithStrinKeys(DOMNode $objFieldXsdElement, array $arrOptions)
    {
        $objFieldXsdComplexType = $this->domDocument->createElement('xsd:complexType');
        $objFieldXsdElement->appendChild($objFieldXsdComplexType);

        $objFieldXsdSimpleContent = $this->domDocument->createElement('xsd:simpleContent');
        $objFieldXsdComplexType->appendChild($objFieldXsdSimpleContent);

        $objFieldXsdExtension = $this->domDocument->createElement('xsd:extension');
        $objFieldXsdSimpleContent->appendChild($objFieldXsdExtension);

        self::addAttributeToNode($this->domDocument, $objFieldXsdExtension, 'base', 'xsd:string');

        foreach($arrOptions as $mixKey => $mixValue)
        {
            $objFieldXsdAttribute = $this->domDocument->createElement('xsd:attribute');
            $objFieldXsdExtension->appendChild($objFieldXsdAttribute);

            self::addAttributeToNode($this->domDocument, $objFieldXsdAttribute, 'name', $mixKey);
            self::addAttributeToNode($this->domDocument, $objFieldXsdAttribute, 'type', 'xsd:byte');
        }
    }

    /**
     * @param DOMNode $objFieldXsdElement
     * @param string $strFieldName
     */
    protected function handleMultiOptionsWithNumericKeys(DOMNode $objFieldXsdElement, $strFieldName)
    {
        $objFieldXsdComplexType = $this->domDocument->createElement('xsd:complexType');
        $objFieldXsdElement->appendChild($objFieldXsdComplexType);

        $objFieldXsdSequence = $this->domDocument->createElement('xsd:sequence');
        $objFieldXsdComplexType->appendChild($objFieldXsdSequence);

        $objFieldXsdSubElement = $this->domDocument->createElement('xsd:element');
        $objFieldXsdSequence->appendChild($objFieldXsdSubElement);

        self::addAttributeToNode($this->domDocument, $objFieldXsdSubElement, 'name', self::englishSingular($strFieldName));
        self::addAttributeToNode($this->domDocument, $objFieldXsdSubElement, 'minOccurs', 0);
        self::addAttributeToNode($this->domDocument, $objFieldXsdSubElement, 'maxOccurs', "unbounded");

        $objFieldXsdSubComplexType = $this->domDocument->createElement('xsd:complexType');
        $objFieldXsdSubElement->appendChild($objFieldXsdSubComplexType);

        $objFieldXsdSimpleContent = $this->domDocument->createElement('xsd:simpleContent');
        $objFieldXsdSubComplexType->appendChild($objFieldXsdSimpleContent);

        $objFieldXsdExtension = $this->domDocument->createElement('xsd:extension');
        $objFieldXsdSimpleContent->appendChild($objFieldXsdExtension);

        self::addAttributeToNode($this->domDocument, $objFieldXsdExtension, 'base', 'xsd:string');

        self::addXsdAttributes($this->domDocument, $objFieldXsdExtension, array(
            'key' => 'xsd:integer',
            'value' => 'xsd:string'
        ));
    }

    /**
     * @param DOMDocument $objDocument
     * @param DomNode $objNode
     * @param array $arrAttributes
     */
    protected static function addXsdAttributes(DOMDocument $objDocument, DomNode $objNode, array $arrAttributes)
    {
        foreach($arrAttributes as $strAttributeName => $strAttributeType)
        {
            $objFieldXsdAttribute = $objDocument->createElement('xsd:attribute');
            $objNode->appendChild($objFieldXsdAttribute);

            self::addAttributesToNode($objDocument, $objFieldXsdAttribute, array(
                'name' => $strAttributeName,
                'type' => $strAttributeType,
            ));
        }
    }
}