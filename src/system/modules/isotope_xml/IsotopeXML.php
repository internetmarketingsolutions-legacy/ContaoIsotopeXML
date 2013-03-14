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

class IsotopeXML extends Backend
{
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
     * @param string
     * @return string
     */
    protected static function englishSingular($strPlural)
    {
        switch(substr($strPlural, -3))
        {
            case 'ies':
                return substr($strPlural, 0, -3) . 'y';
            case 'ren':
                return substr($strPlural, 0, -3);
        }

        $strEndingWithOneSign = substr($strPlural, 0, -1);

        switch(substr($strPlural, -1))
        {
            case 's':
                return substr($strPlural, 0, -1);
        }

        return $strPlural . '_singluar';
    }

    /**
     * @param DOMDocument $objDocument
     * @param DomNode $objNode
     * @param string $strAttributeName
     * @param scalar $strAttributeValue
     */
    protected static function addAttributeToNode(DOMDocument $objDocument, DomNode $objNode, $strAttributeName, $strAttributeValue)
    {
        $objAttribute = $objDocument->createAttribute($strAttributeName);
        $objAttribute->value = $strAttributeValue;
        $objNode->appendChild($objAttribute);
    }

    /**
     * @param DOMDocument $objDocument
     * @param DomNode $objNode
     * @param array $arrAttributes
     */
    protected static function addAttributesToNode(DOMDocument $objDocument, DomNode $objNode, array $arrAttributes)
    {
        foreach($arrAttributes as $strAttributeName => $strAttributeValue)
        {
            self::addAttributeToNode($objDocument, $objNode, $strAttributeName, $strAttributeValue);
        }
    }
}