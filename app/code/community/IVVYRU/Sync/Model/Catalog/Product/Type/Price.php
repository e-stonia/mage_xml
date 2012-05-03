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
      
    public function getFinalPrice($qty=null, $product)
    {   
        if (is_null($qty) && !is_null($product->getCalculatedFinalPrice())) {
            return $product->getCalculatedFinalPrice();
        }   

        $_product = Mage::getModel('catalog/product')->load($product->getId());
        $finalPrice = $this->getPrice($_product);
        $finalPrice = $this->_applyTierPrice($product, $qty, $finalPrice);
        $finalPrice = $this->_applySpecialPrice($product, $finalPrice);
        $product->setFinalPrice($finalPrice);

        Mage::dispatchEvent('catalog_product_get_final_price', array('product'=>$product, 'qty' => $qty));

        $finalPrice = $product->getData('final_price');
        $finalPrice = $this->_applyOptionsPrice($product, $qty, $finalPrice);

        return max(0, $finalPrice);
    }  
}

