<?php
@set_time_limit(0);
@ignore_user_abort (true);

require_once 'app/Mage.php';

Mage :: app();

class Import
{
        private $customers;
	
	private $date;
	
	private $key = '26DF1A27E6C9360D3247E1BDE43B30AF';
	
	private $product_url = 'https://directo.gate.ee/xmlcore/farron_tehnika/transport/xmlcore.asp?get=1&what=item';
        
        private $customer_url = 'https://directo.gate.ee/xmlcore/farron_tehnika/transport/xmlcore.asp?get=1&what=customer';
	
	private $product_response;

	private $customer_response;
	
	private $product_items;

	private $customer_items;
	
	private $cat;
	
	public function __construct( )
    {
		$this->date = date( 'd.m.Y' );
		
		$this->product_url = (isset($_GET['full']))
						? $this->product_url."&key={$this->key}"
						: $this->product_url."&ts={$this->date}&key={$this->key}";

		$this->customer_url = (isset($_GET['full']))
						? $this->customer_url."&key={$this->key}"
						: $this->customer_url."&ts={$this->date}&key={$this->key}";
						
		//$this->url = $this->url."&ts=13.03.2012&key={$this->key}";
		//$this->url = $this->url."&key={$this->key}";
		$this->getCategories();
    }

	function getCategories()
	{ 
		$category = Mage::getModel('catalog/category'); 
		$tree = $category->getTreeModel(); 
		$tree->load(); 

		$ids = $tree->getCollection()->getAllIds(); 
		$this->cat = array(); 

		if ($ids)
		{ 
			foreach ($ids as $id)
			{ 
				$cat = Mage::getModel('catalog/category'); 
				$cat->load($id);
				$url = $cat->getData('url_key');
				if ($url AND $url != 'root-catalog') $this->cat[$url] = $cat->getId();
			} 
		} 
	}
	
	public function loadData( $timeout = 300 ) 
	{
		$ch = curl_init ();
		
		if ($ch != false)
		{
                        /***PRODUCTS****/
			curl_setopt ( $ch, CURLOPT_URL, $this->product_url );
			curl_setopt ( $ch, CURLOPT_PORT , 443);
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt ( $ch, CURLOPT_HEADER, 0 );
			curl_setopt ( $ch, CURLOPT_TIMEOUT, ( int ) $timeout );
			curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
			
			$header = array ('Content-Type: text/xml' );
			
			curl_setopt ( $ch, CURLOPT_HTTPHEADER, $header );
			
			$this->product_response = curl_exec ( $ch );
			if(curl_errno( $ch )) throw new Exception('Curl error: ' . curl_error( $ch ));
                       
                        /******CUSTOMERS*******/
        		curl_setopt ( $ch, CURLOPT_URL, $this->customer_url );

			$this->customer_response = curl_exec ( $ch );
			if(curl_errno( $ch )) throw new Exception('Curl error: ' . curl_error( $ch ));                  
			curl_close ( $ch );
			
			return true;
		}
		throw new Exception('Curl error: failure init.');
	}

	public function parseResponse ()
	{	
		if (!$this->product_response && !$this->customer_response) return false;
		//header('Content-Type: text/xml' );
		//echo $this->response;
		if (($this->product_items = simplexml_load_string ( $this->product_response )) == false) return false;
		if (($this->customer_items = simplexml_load_string ( $this->customer_response )) == false) return false;
		return true;
	}

	public function process ()
	{	//echo 'process...<br>';
                /*****PRODUCTS******/
		$data = (!empty($this->product_items->items->item)) ? $this->product_tems->items->item : $this->product_items ;
		if (!$data) return false;
		//echo 'search data...<br>';
		$site = array(Mage::app()->getStore(true)->getWebsite()->getId());
		
		foreach ($data AS $item)
		{
			//echo '<pre>'.print_r($item->stocklevels[0]->stocklevel[0]->attributes()).'</pre>';
			//echo $item->stocklevels->stocklevel->attributes()->level.'</br>';
			//echo $item->attributes()->code.'</br>';
			$qty = 0;
			
			if (!empty($item->stocklevels))
			{
				foreach ($item->stocklevels AS $stocklevels)
				{	
					if ($stocklevels->stocklevel)
					{
						foreach ($stocklevels->stocklevel AS $stocklevel)
						{
							$level = isset($stocklevel->attributes()->level) ? intval($stocklevel->attributes()->level) : 0;
							$level = ( $level > 0 ) ? $level : 0 ;
							$qty+= $level;			
							//if (!empty($stocklevel->attributes()->stock) and $stocklevel->attributes()->stock == 'PL')
							//{
							//	$qty = isset($stocklevel->attributes()->level) ? intval($stocklevel->attributes()->level) : 0;
							//}
						}
					}
				}
			}
			
			//$qty = ( $qty > 0 ) ? $qty : 0 ;					
			$stock = ( $qty > 0 ) ? 1 : 0 ;			
							
			if (!empty($item->attributes()->name))
			{
				$product  = $new = $id = false;
				$sku      = $item->attributes()->code;
				$_product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
				
				if ( $_product )
				{
					//continue;
					Mage :: app()->setCurrentStore( Mage_Core_Model_App::ADMIN_STORE_ID );
					$id = $_product->getId();
					$product = Mage::getModel('catalog/product')->load( $id );
					Mage::dispatchEvent('catalog_controller_product_delete', array('product' => $product));
					$product->setStoreIDs( 0 );
					//$product->setWebsiteIds( $site );
					$product->delete(); 
					$product = null;
					//$product->setStoreIDs( 0 );
					//var_dump ($product->getAttributeSetId());
					//$product->setAttributeSetId(9);
				}
				
				if ( !$product ) 
				{	
					//echo 'new product sku='.$sku.'<br>';
					$product = Mage::getModel('catalog/product');
					$product->setSku( $item->attributes()->code );
					if ( $id ) $product->setId( $id );
					$product->setWebsiteIds( $site );
					$product->setAttributeSetId(4);
					//$product->setAttributeSetId(9); // local test
					$product->setTypeId('simple');
					$product->setStatus(1);
					$product->setTaxClassId(0);
					$product->setVisibility(4);
					$new = true;
				} 
				//$product->setName( 'xname - '.$item->attributes()->name );
				$product->setName( $item->attributes()->name );
                                if((int)$item->attributes()->delivery_days >= 0)
                                   $product->setDeliveryDays($item->attributes()->delivery_days);
				$product->setPrice($item->attributes()->price);
				$product->setStockData( array( 'is_in_stock' => $stock, 'qty' => $qty ) );
				
				if (!empty($item->attributes()->class))
				{
					$alias = strtolower(trim($item->attributes()->class));
					if (isset($this->cat[$alias]))
					{
						$product->setCategoryIds(array($this->cat[$alias]));
					}
				}
				
				if (!empty($item->attributes()->description))
				{
					$product->setDescription($item->attributes()->description);
					$product->setShortDescription($item->attributes()->description);
				}
				
				try
				{
					$product->save();
					//echo 'ok<br>';
				}
				catch (Exception $e)
				{}
			} //break;
		}
                  
                /******CUSTOMERS*******/
             	$data = (!empty($this->customer_items->items->customer)) ? $this->customer_tems->items->customer : $this->customer_items ;
		if (!$data) return false;
                foreach($data as $item){
                   $customer = null;
                   if(!empty($item->attributes()->item))
                     $customer = Mage::getModel('customer/customer')->loadByAttribute('code',$item->attributes()->code);
                   if(!$customer)
                     $customer = Mage::getModel('customer/customer');
                   $customer->getGroupId();
                   $name = explode(' ',$item->attributes()->name);
                   if(isset($name[0]) && strlen($name) > 0)
                      $customer->setFirstname($name[0]);
                   else
                      $customer->setFirstname('n/a');
                    
                   if(isset($name[1]) && strlen($name) > 0)
                      $customer->setLastname($name[1]);
                   else
                      $customer->setLastname('n/a');
                     
                   if(!empty($item->attributes()->code))
                    $customer->setCode($item->attributes()->code);   
                   
                   if(!empty($item->attributes()->balance))
                    $customer->setBalance($item->attributes()->balance);
                     
                   if(!empty($item->attributes()->pricelist))
                    $customer->setPricelist($item->attributes()->pricelist);
                        
                   if(!empty($item->attributes()->email))
                    $customer->setEmail($item->attributes()->email);   
                     
                   $customer->setStoreId(Mage::app()->getStore(true)->getId())
                            ->setWebsiteId(Mage::app()->getStore(true)->getWebsiteId());
                      
                   $customer->save();
                }
	}	
	
}


$import = new Import ( );

$import->loadData();

$import->parseResponse();

//var_dump ($import);
$import->process();

