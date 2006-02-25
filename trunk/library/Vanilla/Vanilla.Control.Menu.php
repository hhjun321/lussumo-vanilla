<?php
/*
* Copyright 2003 - 2005 Mark O'Sullivan
* This file is part of Vanilla.
* Vanilla is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
* Vanilla is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
* You should have received a copy of the GNU General Public License along with Vanilla; if not, write to the Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
* The latest source code for Vanilla is available at www.lussumo.com
* Contact Mark O'Sullivan at mark [at] lussumo [dot] com
*
* Description: The Menu control is used to display the menu tabs in Vanilla.
*/

// The Menu control handles building the main menu
class Menu extends Control {
	var $Tabs;				// Tab collection
   var $CurrentTab;		// The current tab
	
	function AddTab($Text, $Value, $Url, $CssClass, $Attributes = '', $Position = '0', $ForcePosition = '0') {
		$this->AddItemToCollection($this->Tabs, array('Text' => $Text, 'Value' => $Value, 'Url' => $Url, 'CssClass' => $CssClass, 'Attributes' => $Attributes), $Position, $ForcePosition);
	}
	
	function ClearTabs() {
		$this->Tabs = array();
	}
	
	function Menu(&$Context) {
		$this->Name = 'Menu';
		$this->Control($Context);
		$this->ClearTabs();
	}
	
	function RemoveTab($TabUrl) {
		$TabKey = 0;
      while (list($Key, $Tab) = each($this->Tabs)) {
			if ($Tab['Url'] == $TabUrl) $TabKey = $Key;
      }
		
		if ($TabKey !== false) array_splice($this->Tabs, $TabKey, 1);
	}
	
   function Render() {
		// First sort the tabs by key
      ksort($this->Tabs);
		// Now write the Menu
      $this->CallDelegate('PreRender');
		include(ThemeFilePath($this->Context->Configuration, 'menu.php'));
		$this->CallDelegate('PostRender');
   }
	
	function TabClass($CurrentTab, $ComparisonTab) {
		return ($CurrentTab == $ComparisonTab) ? 'TabOn' : 'TabOff';
	}	
}
?>