<?php
class IVVYRU_Sync_Model_CatalogInventory_Stock_Item extends Mage_CatalogInventory_Model_Stock_Item
{
    public function checkQuoteItemQty($qty, $summaryQty, $origQty = 0)
    {   
        $result = new Varien_Object();
        $result->setHasError(false);

        $_helper = Mage::helper('cataloginventory');

        if (!is_numeric($qty)) {
            $qty = Mage::app()->getLocale()->getNumber($qty);
        }   

        $result->setItemIsQtyDecimal($this->getIsQtyDecimal());

        if (!$this->getIsQtyDecimal()) {
            $result->setHasQtyOptionUpdate(true);
            $qty = intval($qty);

            $result->setItemQty($qty);

            if (!is_numeric($qty)) {
                $qty = Mage::app()->getLocale()->getNumber($qty);
            }
            $origQty = intval($origQty);
            $result->setOrigQty($origQty);
        }

        if ($this->getMinSaleQty() && ($qty) < $this->getMinSaleQty()) {
            $result->setHasError(true)
                ->setMessage(
                    $_helper->__('Minimaalne kogus seda toodet tellida on %s.', $this->getMinSaleQty() * 1)
                )
                ->setQuoteMessage($_helper->__('Osasid tooteid ei saa sellises mahus tellida.'))
                ->setQuoteMessageIndex('qty');
            return $result;
        }

        if ($this->getMaxSaleQty() && ($qty) > $this->getMaxSaleQty()) {
            $result->setHasError(true)
                ->setMessage(
                    $_helper->__('Maksimaalne kogus, mida saab tellida, on %s.', $this->getMaxSaleQty() * 1)
                )
                ->setQuoteMessage($_helper->__('Osasid tooteid ei saa sellises mahus tellida.'))
                ->setQuoteMessageIndex('qty');
            return $result;
        }

        $result->addData($this->checkQtyIncrements($qty)->getData());

        if ($result->getHasError()) {
            return $result;
        }

        if (!$this->getManageStock()) {
            return $result;
        }

       /* if (!$this->getIsInStock()) {
            $result->setHasError(true)
                ->setMessage($_helper->__('This product is currently out of stock.'))
                ->setQuoteMessage($_helper->__('Some of the products are currently out of stock'))
                ->setQuoteMessageIndex('stock');
            $result->setItemUseOldQty(true);
            return $result;
        }  */

        /*if (!$this->checkQty($summaryQty) || !$this->checkQty($qty)) {
            $message = $_helper->__('The requested quantity for "%s" is not available.', $this->getProductName());
            $result->setHasError(true)
                ->setMessage($message)
                ->setQuoteMessage($message)
                ->setQuoteMessageIndex('qty');
            return $result;
        } else {*/
            if (($this->getQty() - $summaryQty) < 0) {
                if ($this->getProductName()) {
                    if ($this->getIsChildItem()) {
                        $backorderQty = ($this->getQty() > 0) ? ($summaryQty - $this->getQty()) * 1 : $qty * 1;
                        if ($backorderQty > $qty) {
                            $backorderQty = $qty;
                        }

                        $result->setItemBackorders($backorderQty);
                    } else {
                        $orderedItems = $this->getOrderedItems();
                        $itemsLeft = ($this->getQty() > $orderedItems) ? ($this->getQty() - $orderedItems) * 1 : 0;
                        $backorderQty = ($itemsLeft > 0) ? ($qty - $itemsLeft) * 1 : $qty * 1;

                        if ($backorderQty > 0) {
                            $result->setItemBackorders($backorderQty);
                        }
                        $this->setOrderedItems($orderedItems + $qty);
                    }

                    if ($this->getBackorders() == Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NOTIFY) {
                        if (!$this->getIsChildItem()) {
                            $result->setMessage(
                                $_helper->__('Seda toodet ei saa soovitud koguses tellida. %s toodet tellitakse.', ($backorderQty * 1))
                            );
                        } else {
                            $result->setMessage(
                               $_helper->__('"%s" ei saa soovitud koguses tellida. %s toodet tellitakse.', $this->getProductName(), ($backorderQty * 1))
                            );
                        }
                    } elseif (Mage::app()->getStore()->isAdmin()) {
                        $result->setMessage(
                            $_helper->__('Soovitud kogus "%s" ei ole saadaval.', $this->getProductName())
                        );
                    }
                }
            } else {
                if (!$this->getIsChildItem()) {
                    $this->setOrderedItems($qty + (int)$this->getOrderedItems());
                }
            }
        /*}*/

        return $result;
    }
}
