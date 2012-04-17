<?php
/*test*/
@set_time_limit(0);
@ignore_user_abort (true);

require_once 'app/Mage.php';

Mage :: app();

class ProductImport
{
	private $products;
	
	private $date;
	
	private $key = '26DF1A27E6C9360D3247E1BDE43B30AF';
	
	private $url = 'https://directo.gate.ee/xmlcore/farron_tehnika/transport/xmlcore.asp?get=1&what=item';
	
	private $response;
	
	private $items;
	
	private $cat;
	
	public function __construct( $products )
    {
		$this->products = $products;
		$this->date = date( 'd.m.Y' );
		
		$this->url = (isset($_GET['full']))
						? $this->url."&key={$this->key}"
						: $this->url."&ts={$this->date}&key={$this->key}";
						
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
			curl_setopt ( $ch, CURLOPT_URL, $this->url );
			curl_setopt ( $ch, CURLOPT_PORT , 443);
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt ( $ch, CURLOPT_HEADER, 0 );
			curl_setopt ( $ch, CURLOPT_TIMEOUT, ( int ) $timeout );
			curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
			
			$header = array ('Content-Type: text/xml' );
			
			curl_setopt ( $ch, CURLOPT_HTTPHEADER, $header );
			
			$this->response = curl_exec ( $ch );
			if(curl_errno( $ch )) throw new Exception('Curl error: ' . curl_error( $ch ));
			curl_close ( $ch );
			
			return true;
		}
		throw new Exception('Curl error: failure init.');
	}

	public function parseResponse ()
	{	
		if (!$this->response) return false;
		//header('Content-Type: text/xml' );
		//echo $this->response;
		if (($this->items = simplexml_load_string ( $this->response )) == false) return false;
		return true;
	}

	public function process ()
	{	//echo 'process...<br>';
		$data = (!empty($this->items->items->item)) ? $this->items->items->item : $this->items ;
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
				else 
				{}
				//$product->setName( 'xname - '.$item->attributes()->name );
				$product->setName( $item->attributes()->name );
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
	}	
	
}


$import = new ProductImport ( Mage::getModel('catalog/product') );

$import->loadData();

$import->parseResponse();

//var_dump ($import);
$import->process();

