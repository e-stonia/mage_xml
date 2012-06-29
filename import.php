<?php

set_time_limit(0);
ignore_user_abort (true);

require_once 'app/Mage.php';

Mage :: app();

class Import
{
        private $customers;
	
	private $date;
	
	private $key = '26DF1A27E6C9360D3247E1BDE43B30AF';
	
	private $product_url = 'https://directo.gate.ee/xmlcore/farron_tehnika/transport/xmlcore.asp?get=1&what=item';
        
        private $customer_url = 'https://directo.gate.ee/xmlcore/farron_tehnika/transport/xmlcore.asp?get=1&what=customer';
        
        private $save_dir = 'var/xml';
         
        private $product_file = 'product';
	
	private $product_response;

	private $customer_response;
	
	private $product_items;

	private $customer_items;
	
	private $cat;
	
	public function __construct( )
    {
		$this->date = date( 'd.m.Y' );
		
		$this->product_url = (isset($_GET['full']))
	//	$this->product_url = (1)
						? $this->product_url."&key={$this->key}"
						: $this->product_url."&ts={$this->date}&key={$this->key}";

		$this->customer_url = (isset($_GET['full']))
//		$this->customer_url = (1)
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
	
	public function loadData() 
	{
		$ch = curl_init ();
		
		if ($ch != false)
		{
                        /***PRODUCTS****/
			curl_setopt ( $ch, CURLOPT_URL, $this->product_url );
			curl_setopt ( $ch, CURLOPT_PORT , 443);
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt ( $ch, CURLOPT_HEADER, 0 );
	//		curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 0 );
			curl_setopt ( $ch, CURLOPT_TIMEOUT, 3600 );
			curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
			
			$header = array ('Content-Type: text/xml' );
			
			curl_setopt ( $ch, CURLOPT_HTTPHEADER, $header );
			
			$this->product_response = curl_exec ( $ch );
			if(curl_errno( $ch )) throw new Exception('Curl error: ' . curl_error( $ch ));
			curl_close ( $ch );
                        $product_fh = fopen($this->save_dir.'/'.$this->product_file.time().'.xml','w') or die("can't open file");
                        fwrite($product_fh,$this->product_response);
                        fclose($product_fh);
		}

		$ct = curl_init ();
		
		if ($ct != false)
		{
                        /***CUSTOMERS****/
			curl_setopt ( $ct, CURLOPT_URL, $this->customer_url );
			curl_setopt ( $ct, CURLOPT_PORT , 443);
			curl_setopt ( $ct, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt ( $ct, CURLOPT_HEADER, 0 );
		//	curl_setopt ( $ct, CURLOPT_CONNECTTIMEOUT, 0);
			curl_setopt ( $ct, CURLOPT_TIMEOUT, 3600);
			curl_setopt ( $ct, CURLOPT_SSL_VERIFYPEER, 0 );
			
			$header = array ('Content-Type: text/xml' );
			
			curl_setopt ( $ct, CURLOPT_HTTPHEADER, $header );
			
			$this->customer_response = curl_exec ( $ct );
			if(curl_errno( $ct )) throw new Exception('Curl error: ' . curl_error( $ct ));                  
			curl_close ( $ct );
			
			return true;
		}

		throw new Exception('Curl error: failure init.');
	}

	public function parseResponse ()
	{	
                libxml_use_internal_errors(true);
		if (!$this->product_response && !$this->customer_response) return false;
		if (($this->product_items = simplexml_load_string($this->product_response,'SimpleXMLElement',LIBXML_PARSEHUGE | LIBXML_NOCDATA)) == false &&
                    ($this->customer_items = simplexml_load_string ( $this->customer_response,'SimpleXMLElement',LIBXML_PARSEHUGE | LIBXML_NOCDATA)) == false)
                {
                   $errors = libxml_get_errors();
                   foreach($errors as $err){
                     echo $err->message().'<br/>';
                   }   
                }
		return true;
	}

	public function process () {
                /*****PRODUCTS******/
		$data = (!empty($this->product_items->items->item)) ? $this->product_items->items->item : $this->product_items ;
		if (!$data) return false;
		$site = array(Mage::app()->getStore(true)->getWebsite()->getId());

                $i = 0;
		foreach ($data AS $item)
		{
                 Mage::log('  item import start '.$item->attributes()->code);

                 ++$i; 

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
						}
					}
				}
			}
                         
                        Mage::log('  stock calc finish item # '.$item->attributes()->code);
                        $priceFormula = array();

                        if(!empty($item->prices))
                             foreach($item->prices AS $prices)
                                  foreach($prices->price as $price)
                                         $priceFormula[] = array('formula' => (string)$price->attributes()->formula, 'price' => (string)$price->attributes()->price);
			
                        Mage::log('  price formula finish item # '.$item->attributes()->code);
                        $cats = array();
                        if(!empty($item->datafields))
                           foreach($item->datafields as $fields)
                              foreach($fields->data as $field){
                                     if($field->attributes()->code == 'VALDKOND')
                                        $cats[0] = (string)$field->attributes()->content;
                                     elseif($field->attributes()->code == 'KAUBAGRUPP')
                                        $cats[1] = (string)$field->attributes()->content;
                              }
                            
                        Mage::log('  category array construction finish item # '.$item->attributes()->code);
                                 
			$stock = ( $qty > 0 ) ? 1 : 0 ;			
							
			if (!empty($item->attributes()->name))
			{
				$product  = $new = $id = false;
				$sku      = $item->attributes()->code;
				$_product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
				
				if ( $_product && $_product->getId() > 0 )
				{
					Mage :: app()->setCurrentStore( Mage_Core_Model_App::ADMIN_STORE_ID );
					$id = $_product->getId();
					$product = Mage::getModel('catalog/product')->load( $id );
				}
				
				if ( !$product ) 
				{	
					$product = Mage::getModel('catalog/product');
					$product->setSku( $item->attributes()->code );
					if ( $id ) $product->setId( $id );
					$product->setWebsiteIds( $site );
					$product->setAttributeSetId(4);
					$product->setTypeId('simple');
					$product->setStatus(1);
					$product->setTaxClassId(0);
					$product->setVisibility(4);
					$new = true;
				} 

				$product->setName( $item->attributes()->name );
                                if((int)$item->attributes()->delivery_days >= 0)
                                   $product->setDeliveryDays($item->attributes()->delivery_days);
				$product->setPrice($item->attributes()->VATprice);
                                $product->setWeight($item->attributes()->weight);
				$product->setStockData( array( 'is_in_stock' => $stock, 'qty' => $qty ) );
                                $product->setPriceFormula(serialize($priceFormula));
				
                                /******CATEGORY HANDLING********/
                                $_root_cat_id = 2;
                                $_root_cat_model = Mage::getModel('catalog/category')->load($_root_cat_id);
                                       
                              try{
                              Mage::log('  about to construct category item # '.$item->attributes()->code);
                              if(count($cats) > 0){
                                $firstlevel = null;
                                foreach(Mage::getModel('catalog/category')->getCategories($_root_cat_id) as $_curr_cat){
                                 if($_curr_cat->getName() == $cats[0]){
                                   $firstlevel = Mage::getModel('catalog/category')->load($_curr_cat->getId());
                                   break;
                                 }
                                }
                               
                                 Mage::log('  category first level item # '.$item->attributes()->code.'  cat name '.$cats[0]);
                                /*$firstlevel = Mage::getModel('catalog/category')->getCollection()
                                                                                ->setStoreId(Mage::app()->getStore(true)->getId())
                                                                                ->addAttributeToSelect('*')
                                                                                ->addAttributeToFilter('name',$cats[0])
                                                                                ->getFirstItem();*/

                                if(!($firstlevel && $firstlevel->getId()>0)){
                                   $firstlevel = Mage::getModel('catalog/category');
                                   $firstlevel->setPath($_root_cat_model->getPath());
                                }
                               

                                $firstlevel->setName($cats[0])
                                           ->setIsActive(1);

                                $firstlevel->save();

                                Mage::log('  saved category first level item # '.$item->attributes()->code.'  cat name '.$cats[0]);
                                if(isset($cats[1]) && strlen($cats[1]) > 0){
                                $secondlevel = null;
                                foreach(Mage::getModel('catalog/category')->getCategories($firstlevel->getId()) as $_curr_cat){
                                 if($_curr_cat->getName() == $cats[1]){
                                  $secondlevel = Mage::getModel('catalog/category')->load($_curr_cat->getId());
                                  break;        
                                 }   
                                }  

                                Mage::log('  category second level item # '.$item->attributes()->code.'  cat name '.$cats[1]);
                                /*$secondlevel = Mage::getModel('catalog/category')->getCollection()
                                                                                 ->setStoreId(Mage::app()->getStore(true)->getId())
                                                                                 ->addAttributeToSelect('*')
                                                                                 ->addAttributeToFilter('name',$cats[1])
                                                                                 ->getFirstItem();*/
                                  
                                if(!($secondlevel && $secondlevel->getId()>0)){
                                     $secondlevel = Mage::getModel('catalog/category');
                                     $secondlevel->setPath($firstlevel->getPath());
                                }
                                   
                                $secondlevel->setName($cats[1])
                                            ->setIsActive(1);

                                $secondlevel->save(); 
                                      
                                Mage::log('  saved category second level item # '.$item->attributes()->code.'  cat name '.$cats[1]);
                                $thirdlevel = null;
                                if(!empty($item->attributes()->class)){
                                foreach(Mage::getModel('catalog/category')->getCategories($secondlevel->getId()) as $_curr_cat){
                                  if($_curr_cat->getName() == $item->attributes()->class){
                                    $thirdlevel = Mage::getModel('catalog/category')->load($_curr_cat->getId());
                                    break; 
                                  }  
                                }

                                Mage::log('  category third level item # '.$item->attributes()->code.'  cat name '.$item->attributes()->class);
                                /*$thirdlevel = Mage::getModel('catalog/category')->getCollection()
                                                                                 ->setStoreId(Mage::app()->getStore(true)->getId())
                                                             ->addAttributeToSelect('*')
                                                             ->addAttributeToFilter('name',$item->attributes()->class)
                                                             ->getFirstItem();*/
                               if(!($thirdlevel && $thirdlevel->getId()>0)){
                                     $thirdlevel = Mage::getModel('catalog/category');
                                     $thirdlevel->setPath($secondlevel->getPath());
                               }
                               $thirdlevel->setName($item->attributes()->class)
                                               ->setIsActive(1);

                              $thirdlevel->save();
                              Mage::log('  saved category third level item # '.$item->attributes()->code.'  cat name '.$item->attributes()->class);
                              }
		              $product->setCategoryIds(array(($firstlevel ? $firstlevel->getId() : null)
                                                           , ($secondlevel ? $secondlevel->getId() : null)
                                                           , ($thirdlevel ? $thirdlevel->getId() : null)));
                              }else{
                                  $secondlevel = null;
                                  if(!empty($item->attributes()->class)){
                                  foreach(Mage::getModel('catalog/category')->getCategories($firstlevel->getId()) as $_curr_cat){
                                    if($_curr_cat->getName() == $item->attributes()->class){
                                     $secondlevel = Mage::getModel('catalog/category')->load($_curr_cat->getId()); 
                                     break;
                                    }
                                  } 

                                  Mage::log('  [2] category second level item # '.$item->attributes()->code.'  cat name '.$item->attributes()->class);

                                  /*$secondlevel = Mage::getModel('catalog/category')->getCollection()
                                                                                 ->setStoreId(Mage::app()->getStore(true)->getId())
                                                             ->addAttributeToSelect('*')
                                                             ->addAttributeToFilter('name',$item->attributes()->class) 
                                                             ->getFirstItem();*/
                                  if(!($secondlevel && $secondlevel->getId()>0)){
                                     $secondlevel = Mage::getModel('catalog/category');
                                     $secondlevel->setPath($firstlevel->getPath());
                                  }
                                      
                                  $secondlevel->setName($item->attributes()->class)
                                              ->setIsActive(1);
                                  $secondlevel->save();

                                  Mage::log('  [2] saved category second level item # '.$item->attributes()->code.'  cat name '.$item->attributes()->class);
                                  }
                                  $product->setCategoryIds(array(($firstlevel ? $firstlevel->getId() : null)
                                                              ,  ($secondlevel ? $secondlevel->getId() : null)));
                                }
                                }else{
                                  if(!empty($item->attributes()->class)){
                                    $maincat = null;
                                    foreach(Mage::getModel('catalog/category')->getCategories($_root_cat_id) as $_curr_cat){
                                       if($_curr_cat->getName() == $item->attributes()->class){
                                        $maincat = Mage::getModel('catalog/category')->load($_curr_cat->getId());
                                        break;  
                                       }     
                                    }     

                                    Mage::log('  [3] category second level item # '.$item->attributes()->code.'  cat name '.$item->attributes()->class);
                                    /*$maincat = Mage::getModel('catalog/category')->getCollection()
                                                                                 ->setStoreId(Mage::app()->getStore(true)->getId())
                                                             ->addAttributeToSelect('*')
                                                             ->addAttributeToFilter('name',$item->attributes()->class)
                                                             ->getFirstItem();*/
                                    if(!($maincat && $maincat->getId()>0)){
                                       $maincat = Mage::getModel('catalog/category');
                                       $maincat->setPath($_root_cat_model->getPath());
                                    }   
                                    $maincat->setName($item->attributes()->class)
                                            ->setIsActive(1);
                                    $maincat->save();

                                    Mage::log('  [3] saved category second level item # '.$item->attributes()->code.'  cat name '.$item->attributes()->class);
                                    $product->setCategoryIds(array($maincat->getId()));
 
                                    Mage::log('  [3] setCategoryId category second level item # '.$item->attributes()->code.'  cat name '.$item->attributes()->class);
                                  } 
                                }
                                }catch(Exception $e){Mage::log('error '.$e->getMessage());}
				if (!empty($item->attributes()->description))
				{
					$product->setDescription($item->attributes()->description);
					$product->setShortDescription($item->attributes()->description);
				}
				
				try
				{

                                        Mage::log('  about to item # '.$item->attributes()->code);
					$product->save();
				}
				catch (Exception $e)
				{ Mage::log('error '.$e->getMessage());}
			} 
                        Mage::log('['.$i.'] product '.$product->getId().' imported');
                        //echo('['.++$i.'] product '.$product->getId().' imported');
		}
              
                Mage::log('product import finished');
                //echo('product import finished');
                
                /******CUSTOMERS*******/
             	$data = (!empty($this->customer_items->customers->customer)) ? $this->customer_items->customers->customer : $this->customer_items ;
                   
		if (!$data) return false;

                foreach($data as $item){
                   if(empty($item->attributes()->email))
                     continue;
                   $customer = null;
                   $customer = Mage::getModel('customer/customer')->setWebsiteId(Mage::app()->getStore(true)->getWebsiteId())->loadByEmail($item->attributes()->email);
                   if(!($customer && $customer->getId()>0))
                     $customer = Mage::getModel('customer/customer');
                   $customer->getGroupId();
                   $name = explode(' ',$item->attributes()->name);
                   if(isset($name[0]) && strlen($name[0]) > 0)
                      $customer->setFirstname($name[0]);
                   else
                      $customer->setFirstname('n/a');
                    
                   if(isset($name[1]) && strlen($name[1]) > 0)
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
                   Mage::log('customer '.$customer->getId().' imported');
                   //echo('customer '.$customer->getId().' imported');
                }
                /****REINDEX*****/

                $indexingProcesses = Mage::getSingleton('index/indexer')->getProcessesCollection(); 
                foreach ($indexingProcesses as $process) {
                         $process->reindexEverything();
                }
                Mage::log('customer import finished');
                //echo('customer import finished');
	}	
	
}

$import = new Import ( );

$import->loadData();

$import->parseResponse();

$import->process();

echo 'done';

