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

        header('Content-type: text/xml');
        header('Content-Disposition: attachment; filename="isotope-products.xsd"');

        echo $this->domDocument->saveXML();
        die();
    }

    protected function addXSDSchemaNode()
    {
        // define xsd schmea node
        $objXsdSchemaNode = $this->domDocument->createElement('xsd:schema');

        // add attributes
        self::addAttributeToNode($this->domDocument, $objXsdSchemaNode, 'xmlns', 'http://www.isotopeecommerce.com');
        self::addAttributeToNode($this->domDocument, $objXsdSchemaNode, 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        self::addAttributeToNode($this->domDocument, $objXsdSchemaNode, 'targetNamespace', 'http://www.isotopeecommerce.com');
        self::addAttributeToNode($this->domDocument, $objXsdSchemaNode, 'elementFormDefault', 'qualified');

        // append node to document
        $this->domDocument->appendChild($objXsdSchemaNode);

        // add isotope xsd node
        $this->addIsotopeXSDNode($objXsdSchemaNode);
    }

    protected function addIsotopeXSDNode(DOMNode $objXsdSchemaNode)
    {
        $objIsotopeXsdNode = $this->domDocument->createElement('xsd:element');
        $objXsdSchemaNode->appendChild($objIsotopeXsdNode);
        
        // add attributes
        self::addAttributeToNode($this->domDocument, $objIsotopeXsdNode, 'name', 'isotope');
        self::addAttributeToNode($this->domDocument, $objIsotopeXsdNode, 'type', 'isotope');

        $this->addIsotopeXSDTypeNode($objIsotopeXsdNode);
    }

    protected function addIsotopeXSDTypeNode(DOMNode $objXsdSchemaNode)
    {
        $objIsotopeXsdTypeNode = $this->domDocument->createElement('xsd:complexType');
        $objXsdSchemaNode->appendChild($objIsotopeXsdTypeNode);

        $objIsotopeXsdTypeSequenceNode = $this->domDocument->createElement('xsd:sequence');
        $objIsotopeXsdTypeNode->appendChild($objIsotopeXsdTypeSequenceNode);

        // add attributes
        self::addAttributeToNode($this->domDocument, $objIsotopeXsdTypeNode, 'name', 'isotope');

        $objIsotopeXsdTypeProductsNode = $this->domDocument->createElement('xsd:element');
        $objIsotopeXsdTypeSequenceNode->appendChild($objIsotopeXsdTypeProductsNode);

        // add attributes
        self::addAttributeToNode($this->domDocument, $objIsotopeXsdTypeProductsNode, 'name', 'products');
        self::addAttributeToNode($this->domDocument, $objIsotopeXsdTypeProductsNode, 'type', 'products');
        self::addAttributeToNode($this->domDocument, $objIsotopeXsdTypeProductsNode, 'minOccurs', 1);
        self::addAttributeToNode($this->domDocument, $objIsotopeXsdTypeProductsNode, 'maxOccurs', 1);
    }
}