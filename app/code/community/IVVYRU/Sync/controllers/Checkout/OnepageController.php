<?php
include_once('Mage/Checkout/controllers/OnepageController.php');
class IVVYRU_Sync_Checkout_OnepageController extends Mage_Checkout_OnepageController
{
    public function indexAction()
    {
        if (!Mage::helper('checkout')->canOnepageCheckout()) {
            Mage::getSingleton('checkout/session')->addError($this->__('The onepage checkout is disabled.'));
            $this->_redirect('checkout/cart');
            return;
        }
        $quote = $this->getOnepage()->getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->_redirect('checkout/cart');
            return;
        }
        if (!$quote->validateMinimumAmount()) {
            $error = Mage::getStoreConfig('sales/minimum_order/error_message');
            Mage::getSingleton('checkout/session')->addError($error);
            $this->_redirect('checkout/cart');
            return;
        }

        /*****BALANCE CHECK*******/
        if(Mage::getSingleton('customer/session')->isLoggedIn()){
          $totals = Mage::getResourceModel('sales/sale_collection')
            ->setCustomerFilter(Mage::getSingleton('customer/session')->getCustomer())
            ->setOrderStateFilter(Mage_Sales_Model_Order::STATE_CANCELED, true)
            ->load()
            ->getTotals()
            ->getBaseLifetime();
           $totals += $quote->getGrandTotal();
          if($totals > Mage::getSingleton('customer/session')->getCustomer()->getBalance()){
            Mage::getSingleton('checkout/session')->addError($this->__('You are overflowing your balance'));
            $this->_redirect('checkout/cart');
            return;     
          }
        }
      
        Mage::getSingleton('checkout/session')->setCartWasUpdated(false);
        Mage::getSingleton('customer/session')->setBeforeAuthUrl(Mage::getUrl('*/*/*', array('_secure'=>true)));
        $this->getOnepage()->initCheckout();
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->getLayout()->getBlock('head')->setTitle($this->__('Checkout'));
        $this->renderLayout();
    }
}
