<?php

class Moak_Vip_Model_Observer
{
    private $url = 'https://directo.gate.ee/xmlcore/farron_tehnika/transport/xmlcore.asp';
	//private $url = 'http://farron.e-stoniaweb.eu/i.php';
	
	private $header = '<orders>';
	
	private $body;
	
	private $footer = '</orders>';
	
	public function sendOrder( $observer )
    {
        $order = $observer->getEvent()->getOrder();
		
		if ( $order )
		{
			$id = $order->getRealOrderId();
			$cid = $order->getCustomerId();
			$name = $order->getCustomerName();
			
			$customer = Mage::getModel('customer/customer')->load( $cid );
			$this->body = '<order id="'.$id.'" date="'.date('Y-m-d').'" customer_code="'.$cid.'" customer_name="'.$name.'" email="'.$customer->getEmail().'">';

			$items = $order->getAllItems();
			
			foreach ($items as $item)
			{
				if ( $item->getQtyToInvoice() > 0)
				{
					$this->body.= '<line item="'.$item->getSku().'" price="'.$item->getPrice().'" vatcode="1" qty="1" name="'.$item->getName().'" discount="0" />';
				}
			}
			$this->body.= '</order>';
		}
		
		Mage::log($this->header.$this->body.$this->footer, null, 'xml.log');
		
		$this->sendXml( $this->header.$this->body.$this->footer );
        return $this;
    }
	
	protected function sendXml( $data, $timeout = 30 ) 
	{
        $ch = curl_init ();
        
        if ( $ch != false )
        {
			curl_setopt ( $ch, CURLOPT_URL, $this->url );
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt ( $ch, CURLOPT_HEADER, 0 );
			curl_setopt ( $ch, CURLINFO_HEADER_OUT, true);
			curl_setopt ( $ch, CURLOPT_POST, 1 );
			curl_setopt ( $ch, CURLOPT_POSTFIELDS, http_build_query(array('xmldata' => $data, 'what' => 'order', 'put' => '1'), null, '&') );
			curl_setopt ( $ch, CURLOPT_TIMEOUT, ( int ) $timeout );
			curl_setopt ( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:11.0) Gecko/20100101 Firefox/11.0");
			curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
			
			$xmlResponse = curl_exec ( $ch );
			$curlinfo = curl_getinfo ( $ch , CURLINFO_HEADER_OUT);
			if(curl_errno($ch)) throw new Exception('Curl error: ' . curl_error($ch));
			curl_close ( $ch );
			Mage::log($xmlResponse, null, 'Response.log');
			Mage::log($curlinfo, null, 'info.log');
			return $xmlResponse;
		}
		return false;
	}	

}