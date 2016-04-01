<?php

class ModulCarrier extends CarrierModule
{
	public $id_carrier;	public $aaa;

	public function __construct()
	{
		$this->name = 'modulcarrier';
		$this->tab = 'shipping_logistics';
		$this->version = '0.1';
		$this->author = 'Fachrizal Lukman H';
		$this->bootstrap = true;
		parent::__construct();
		$this->displayName = $this->l('Modul Carrier');
		$this->description = $this->l('Modul untuk menambahkan biaya pengiriman');				$this->step = (int)Tools::getValue('step');		$controller = Tools::getValue('controller');		if((($controller == 'order')&&$this->step > 1)||($controller =='payment')||($controller =='validation')||($controller == 'order-confirmation')){					$this->aaa = $this->getDeliveryService();		}				//$this->aaa = $this->getDeliveryService();
	}

	public function install()
	{
		if (!parent::install())
			return false;

		if (!$this->registerHook('actionCarrierUpdate')
			|| !$this->registerHook('displayCarrierList')
			|| !$this->registerHook('displayAdminOrder'))
			return false;

		if (!$this->installCarriers())
			return false;

		return true;
	}

	public function installCarriers()
	{
		$id_lang_default = Language::getIsoById(Configuration::get('PS_LANG_DEFAULT'));
		$carriers_list = array(
			'MODUL_CA_JNE_OKE' => 'JNE Ongkos Kirim Ekonomis',
			'MODUL_CA_JNE_REG' => 'JNE Layanan Reguler',
			'MODUL_CA_JNE_SPS' => 'JNE Super Speed',
			'MODUL_CA_JNE_YES' => 'JNE Yakin Esok Sampai',
			'MODUL_CA_TIKI_HDS' => 'TIKI Holiday Delivery Service',
			'MODUL_CA_TIKI_ONS' => 'TIKI Over Night Service',
			'MODUL_CA_TIKI_REG' => 'TIKI Regular Service',
			'MODUL_CA_TIKI_ECO' => 'TIKI Economi Service',
			'MODUL_CA_POS_SKS' => 'Pos Indonesia Surat Kilat Khusus',
			'MODUL_CA_POS_PED' => 'Pos Indonesia Pos Express Dokumen',
		);
		foreach ($carriers_list as $carrier_key => $carrier_name)
			if (Configuration::get($carrier_key) < 1)
			{
				// Create new carrier
				$carrier = new Carrier();
				$carrier->name = $carrier_name;
				$carrier->id_tax_rules_group = 0;
				$carrier->active = 1;
				$carrier->deleted = 0;
				foreach (Language::getLanguages(true) as $language)
					$carrier->delay[(int)$language['id_lang']] = 'Delay '.$carrier_name;
				$carrier->shipping_handling = false;
				$carrier->range_behavior = 0;
				$carrier->is_module = true;
				$carrier->shipping_external = true;
				$carrier->external_module_name = $this->name;
				$carrier->need_range = true;
				if (!$carrier->add())
					return false;

				// Associate carrier to all groups
				$groups = Group::getGroups(true);
				foreach ($groups as $group)
					Db::getInstance()->insert('carrier_group', array('id_carrier' => (int)$carrier->id, 'id_group' => (int)$group['id_group']));

				// Create price range
				$rangePrice = new RangePrice();
				$rangePrice->id_carrier = $carrier->id;
				$rangePrice->delimiter1 = '0';
				$rangePrice->delimiter2 = '10000';
				$rangePrice->add();

				// Create weight range
				$rangeWeight = new RangeWeight();
				$rangeWeight->id_carrier = $carrier->id;
				$rangeWeight->delimiter1 = '0';
				$rangeWeight->delimiter2 = '10000';
				$rangeWeight->add();

				// Associate carrier to all zones
				$zones = Zone::getZones(true);
				foreach ($zones as $zone)
				{
					Db::getInstance()->insert('carrier_zone', array('id_carrier' => (int)$carrier->id, 'id_zone' => (int)$zone['id_zone']));
					Db::getInstance()->insert('delivery', array('id_carrier' => (int)$carrier->id, 'id_range_price' => (int)$rangePrice->id, 'id_range_weight' => NULL, 'id_zone' => (int)$zone['id_zone'], 'price' => '0'));
					Db::getInstance()->insert('delivery', array('id_carrier' => (int)$carrier->id, 'id_range_price' => NULL, 'id_range_weight' => (int)$rangeWeight->id, 'id_zone' => (int)$zone['id_zone'], 'price' => '0'));
				}

				// Copy the carrier logo
				copy(dirname(__FILE__).'/views/img/'.$carrier_key.'.jpg', _PS_SHIP_IMG_DIR_.'/'.(int)$carrier->id.'.jpg');

				// Save the carrier ID in the Configuration table
				Configuration::updateValue($carrier_key, $carrier->id);
			}

		return true;
	}

	public function getHookController($hook_name)
	{
		require_once(dirname(__FILE__).'/controllers/hook/'. $hook_name.'.php');
		$controller_name = $this->name.$hook_name.'Controller';
		$controller = new $controller_name($this, __FILE__, $this->_path);
		return $controller;
	}

	public function getOrderShippingCost($params, $shipping_cost)
	{
		$controller = $this->getHookController('getOrderShippingCost');
		return $controller->run($params, $shipping_cost);
	}

	public function getOrderShippingCostExternal($params)
	{
		//return $this->getOrderShippingCost($params, 0);
		return false;
	}
	public function getDeliveryService()	{		$namakota = $this->getKota();		$berat = $this->getWeight();		$curl = curl_init();		curl_setopt_array($curl, array(		  CURLOPT_URL => "http://rajaongkir.com/api/starter/cost",		  CURLOPT_RETURNTRANSFER => true,		  CURLOPT_CUSTOMREQUEST => "POST",		  CURLOPT_POSTFIELDS => "origin=$namakota[1]&destination=$namakota[0]&weight=$berat",		  CURLOPT_HTTPHEADER => array(			"content-type: application/x-www-form-urlencoded",			"key: 4f56bb2e977b8886c7eb120dfc54a69f"		  ),		));		$response = curl_exec($curl);		$err = curl_error($curl);		curl_close($curl);		$result = json_decode($response, true);		//$resulta = $this->getHasil($result);		return $result;	}		public function loadCity()	{		$bbb = $this->context->cart;		$address = new Address($bbb->id_address_delivery);		$value = $address->city;		return $value;	}		public function getKota(){		$kotatujuan = $this->loadCity();		$curl = curl_init();		curl_setopt_array($curl, array(		  CURLOPT_URL => "http://rajaongkir.com/api/starter/city",		  CURLOPT_RETURNTRANSFER => true,		  CURLOPT_ENCODING => "",		  CURLOPT_MAXREDIRS => 10,		  CURLOPT_TIMEOUT => 30,		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,		  CURLOPT_CUSTOMREQUEST => "GET",		  CURLOPT_HTTPHEADER => array(			"key: 4f56bb2e977b8886c7eb120dfc54a69f"		  ),		));		$response = curl_exec($curl);		$err = curl_error($curl);		curl_close($curl);		$result = json_decode($response, true);		$acak = count($result['rajaongkir']['results']);		for($i=0; $i<$acak; $i++){			if(strtoupper($kotatujuan)==strtoupper($result['rajaongkir']['results'][$i]['type']." ". $result['rajaongkir']['results'][$i]['city_name'])){								$kotatujuannya = '';				$kotatujuannya = $result['rajaongkir']['results'][$i]['city_id'];			}			if(strtoupper(Configuration::get('PS_SHOP_CITY'))==strtoupper($result['rajaongkir']['results'][$i]['type']." ". $result['rajaongkir']['results'][$i]['city_name'])){				$kotaasalnya = '';				$kotaasalnya = $result['rajaongkir']['results'][$i]['city_id'];			}		}		return array($kotatujuannya, $kotaasalnya);	}	public function getWeight(){		//var_dump(context::getContext()->cart->getProducts());		$bbb = $this->context->cart;		//var_dump($bbb);		$keranjang = $bbb->getProducts();		//var_dump($keranjang);		$a = 0;		for($i=0; $i<count($keranjang); $i++){			$a = $a + ($keranjang[$i]['weight']*$keranjang[$i]['cart_quantity']);		}		$value = $a*1000;		return $value;	}
}