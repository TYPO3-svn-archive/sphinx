<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Xavier Perseguers <xavier@causal.ch>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

namespace Causal\Sphinx\ViewHelpers\Form;

/**
 * Extends the EXT:fluid's select VH to support onchange attribute.
 *
 * @category    ViewHelpers\Form
 * @package     tx_sphinx
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   Causal Sàrl
 * @license     http://www.gnu.org/copyleft/gpl.html
 */
class SelectViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\Form\SelectViewHelper {

	/**
	 * Initialize arguments.
	 *
	 * @return void
	 */
	public function initializeArguments() {
		$this->registerTagAttribute('onchange', 'string', 'Javascript for the onchange event');
		$this->registerArgument('groupOptions', 'boolean', 'Whether options should be grouped by 1st-dimension key');
		parent::initializeArguments();
	}

	/**
	 * Render the option tags.
	 *
	 * @param array $options the options for the form.
	 * @return string rendered tags.
	 */
	protected function renderOptionTags($options) {
		$output = '';
		if ($this->hasArgument('prependOptionLabel')) {
			$value = $this->hasArgument('prependOptionValue') ? $this->arguments['prependOptionValue'] : '';
			$label = $this->arguments['prependOptionLabel'];
			$output .= $this->renderOptionTag($value, $label, FALSE) . chr(10);
		}
		if ($this->arguments['groupOptions']) {
			foreach ($options as $group => $valueLabel) {
				$output .= '<optgroup label="' . htmlentities($group) . '">';
				foreach ($valueLabel as $value => $label) {
					$isSelected = $this->isSelected($value);
					$output .= $this->renderOptionTag($value, $label, $isSelected) . chr(10);
				}
				$output .= '</optgroup>';
			}
		} else {
			foreach ($options as $value => $label) {
				$isSelected = $this->isSelected($value);
				$output .= $this->renderOptionTag($value, $label, $isSelected) . chr(10);
			}
		}
		return $output;
	}

}

?>