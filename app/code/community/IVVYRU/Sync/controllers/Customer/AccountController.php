<?php
include_once('Mage/Customer/controllers/AccountController.php');
class IVVYRU_Sync_Customer_AccountController extends Mage_Customer_AccountController
{
    public function createAction()
    {   
        $this->_getSession()->addError('Registration is closed');
        $this->_redirect('*/*');
        return;
    }   
    
    public function createPostAction()
    {
        $this->_getSession()->addError('Registration is closed');
        $this->_redirect('*/*');
        return;
    }
}
 
