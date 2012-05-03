<?php
class IVVYRU_Sync_Block_Catalog_Navigation extends Mage_Catalog_Block_Navigation
{
    public function drawItemHw($category, $id) {
        if ($category->getId() == $id) return $category->getName();
         
        $children = $category->getChildren();
        $hasChildren = $children && $children->count();
        if ($hasChildren) {
            foreach ($children as $child) $this->drawItemHw($child, $id);
        }
        return '';
    }
        
    public function getDrawItemHw($id) {
        $_categories = $this->getStoreCategories();
        $flag = false;        

        foreach ($_categories as $_category):
            $sCategoryName = $this->drawItemHw($_category, $id);
            if (!empty($sCategoryName)) return $sCategoryName;
        endforeach;

        return '';
    }
}
