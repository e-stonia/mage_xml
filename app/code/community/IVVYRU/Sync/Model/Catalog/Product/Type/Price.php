<?php
class IVVYRU_Sync_Model_Catalog_Product_Type_Price extends Mage_Catalog_Model_Product_Type_Price
{
    public function getPrice($product)
    {
        $session = Mage::getSingleton('customer/session');
        if($session->isLoggedIn()){
           if(strlen($session->getCustomer()->getPricelist())>0){
             $price_formula = unserialize($product->getPriceFormula());
             foreach($price_formula as $pr){
                  if($pr['formula'] == $session->getCustomer()->getPricelist())
                      return $pr['price'];     
             }
           }
        }
        return $product->getData('price');
    }
}

