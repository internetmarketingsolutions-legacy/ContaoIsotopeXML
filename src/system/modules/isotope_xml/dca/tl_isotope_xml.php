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

$GLOBALS['TL_DCA']['tl_isotope_xml'] = array
(
	// Config
    'config' => array
    (
        'dataContainer' => 'Memory',
        'closed' => true,
        'dcMemory_showAll_callback' => array
        (
            array('tl_isotope_xml','dcMemory_showAll_callback'),
        ),
        'onsubmit_callback' => array(
            array('tl_isotope_xml','onsubmit_callback'),
        ),
        'disableSubmit' => true,
    ),
    // Palettes
    'palettes' => array
    (
        'default' => '{export_legend},exportButton',
    ),
    // Fields
    'fields' => array
    (
        'exportButton' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_isotope_xml']['exportButton'],
            'addSubmit' => true,
            'eval' => array
            (
                'button_class' => 'button'
            )
        ),
    ),
);

class tl_isotope_xml extends Backend
{
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
    }

	public function dcMemory_showAll_callback(DataContainer $dc)
	{
		$this->redirect('contao/main.php?do=isotope_xml&act=edit', 301);
	}

	public function onsubmit_callback(DataContainer $dc)
	{
        // check permission
        if($this->User->isAdmin)
        {
            // check if its an export call
            if($this->Input->post('submit_exportButton'))
            {
                //$objIsotopeXSDGenerator = new IsotopeXSDGenerator();
                //$objIsotopeXSDGenerator->create($dc);

                $objIsotopeXMLExport = new IsotopeXMLExport();
                $objIsotopeXMLExport->create($dc, $this->Environment->base . 'isotope-products.xsd');
                $objIsotopeXMLExport->output('isotope-products.xml');
            }
        }
	}
}