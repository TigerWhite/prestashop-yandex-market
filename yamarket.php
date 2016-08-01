<?php
/**
 * 2011-2015 Roman Prokofyev
 *
 * NOTICE OF LICENSE
 *
 *  @author    Roman Prokofyev
 *  @copyright 2011-2015 Roman Prokofyev
 *  @license   MIT
 */

/* Security*/
if (!defined('_PS_VERSION_'))
    exit;

require (dirname(__FILE__).'/ym_mappings.php');

set_time_limit(0);

class YaMarket extends Module
{
    private static $ALLOWED_CURRENCIES = array('RUB', 'RUR', 'UAH', 'BYR', 'KZT', 'USD', 'EUR');
    public function __construct()
    {
        $this->name = 'yamarket';
        $this->tab = 'smart_shopping';
        $this->version = '1.8.9';
        $this->author = 'Roman Prokofyev';
        $this->need_instance = 1;
        $this->display = 'view';
        $this->bootstrap = true;
        //$this->ps_versions_compliancy = array('min' => '1.5.0.0', 'max' => '1.6');

        $this->custom_attributes = array('YAMARKET_COMPANY_NAME', 'YAMARKET_DELIVERY_PRICE', 'YAMARKET_SALES_NOTES',
            'YAMARKET_COUNTRY_OF_ORIGIN', 'YAMARKET_EXPORT_TYPE', 'YAMARKET_MODEL_NAME', 'YAMARKET_DESC_TYPE',
            'YAMARKET_DELIVERY_DELIVERY', 'YAMARKET_DELIVERY_PICKUP', 'YAMARKET_DELIVERY_STORE', 'YAMARKET_CURRENCY');
        $this->country_of_origin_attr = Configuration::get('YAMARKET_COUNTRY_OF_ORIGIN');
        $this->model_name_attr = Configuration::get('YAMARKET_MODEL_NAME');

        // set default currency
        $ym_currency = Configuration::get('YAMARKET_CURRENCY');
        if (!$ym_currency)
            $ym_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        Configuration::updateValue('YAMARKET_CURRENCY', $ym_currency);

        parent::__construct();

        $this->displayName = $this->l('Yandex Market');

        if ($this->id && !Configuration::get('YAMARKET_COMPANY_NAME'))
            $this->warning = $this->l('You have not yet set your Company Name');
        $this->description = $this->l('Provides price list export to Yandex Market');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details ?');

        // Variables fro price list
        $this->id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $this->proto_prefix = _PS_BASE_URL_;

        // Get groups
        $attribute_groups = AttributeGroup::getAttributesGroups($this->id_lang);
        $this->attibute_groups = array();
        foreach ($attribute_groups as $group){
            $this->attibute_groups[$group['id_attribute_group']] = $group['public_name'];
        }

        // Get categories
        $this->excluded_cats = explode(',', Configuration::get('YAMARKET_EXCLUDED_CATS'));
        if (!$this->excluded_cats)
            $this->excluded_cats = array();
        $all_cats = Category::getSimpleCategories($this->id_lang);
        $this->selected_cats = array();
        $this->all_cats = array();
        foreach ($all_cats as $cat){
            $this->all_cats[] = $cat['id_category'];
            if (!in_array($cat['id_category'], $this->excluded_cats))
                $this->selected_cats[] = $cat['id_category'];
        }

        //determine image type
        $this->image_type = 'large_default';
        if(Tools::substr(_PS_VERSION_,0, 5) == '1.5.0')
            $this->image_type = 'large';

        // ym category mappings, only when db exists
        if (self::isInstalled($this->name)) {
            $this->ym_bindings = YMMappings::getAllDict();
        }
    }

    public function install()
    {
        if (!parent::install() ||
            !YMMappings::installDb())
            return false;
        return true;
    }

    public function uninstall()
    {
        foreach ($this->custom_attributes as $attr)
        {
            if (!Configuration::deleteByName($attr))
                return false;
        }
        if (!parent::uninstall() ||
            !YMMappings::uninstallDb())
            return false;
        return true;
    }

    public function getContent()
    {
        $output = '<h2>'.$this->displayName.'</h2>';
        if (Tools::isSubmit('submit'.$this->name))
        {
            foreach ($this->custom_attributes as $i => $value)
            {
                Configuration::updateValue($value, Tools::getValue($value));
            }

            // Categories
            $selected_cats = array();
            foreach (Tools::getValue('categoryBox') as $row)
                $selected_cats[] = $row;
            $this->excluded_cats = array();
            foreach ($this->all_cats as $cat)
                if (!in_array($cat, $selected_cats))
                    $this->excluded_cats[] = $cat;
            Configuration::updateValue('YAMARKET_EXCLUDED_CATS', implode(',', $this->excluded_cats));
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }
        else if (Tools::isSubmit('submit'.$this->name.'binding'))
        {
            $binding_category = Tools::getValue('category');
            $ym_category = Tools::getValue('ym_category');

            if (!$binding_category)
                $this->_html .= $this->displayError($this->l('Please fill the "Shop category" field.'));
            elseif (!$ym_category)
                $this->_html .= $this->displayError($this->l('Please fill the "Yandex.Market category" field.'));
            else
            {
                YMMappings::add($binding_category, $ym_category);
            }
        }
        elseif (Tools::isSubmit('delete'.$this->name . '_bindings'))
        {
            $id = Tools::getValue('id', 0);
            YMMappings::remove($id);
        }

        $output .= $this->renderForm().$this->renderMarketCategoryMappings();

        $shop_url = (Configuration::get('PS_SSL_ENABLED') ? _PS_BASE_URL_SSL_ : _PS_BASE_URL_) . __PS_BASE_URI__;
        $default_lang_name = Language::getLanguage($this->id_lang);
        $default_lang_name = $default_lang_name['name'];
        $output .= '
        <fieldset class="space">
            <legend><img src="../img/admin/unknown.gif" alt="" class="middle" />'.$this->l('Help') . '</legend>
            <h2>'.$this->l('Registration').'</h2>
             <p>'.$this->l('Register your price list on site').'
             <a class="action_module" href="http://partner.market.yandex.ru/">http://partner.market.yandex.ru</a><br/><br/>
             '.$this->l('Use the following address as a price list address').':<br/>
             <div>
             <a  style="padding: 5px; border: 1px solid;" class="action_module" href="'.$shop_url.'modules/yamarket/">'.$shop_url.'modules/yamarket/</a>
             </div>
             </p>
             <h2>'.$this->l('Miscellaneous').'</h2>
             <p>'.$this->l('When filling the attribute name fields, it is necessary to use default shop language').'.
             '.$this->l('Current default shop language: '). $default_lang_name .'</p>
             <p class="warn">
               '.$this->l('When using vendor.model offer type, make sure you have filled the manufacturer name field for all your products').'.
             </p>
        </fieldset>';

        return $output;
    }

    public function renderForm()
    {
        $this->context->controller->addCSS($this->_path.'views/css/yamarket.css', 'all');
        $this->context->controller->addJS($this->_path.'views/js/yamarket.js', 'all');

        $offer_type = Tools::getValue('YAMARKET_EXPORT_TYPE', Configuration::get('YAMARKET_EXPORT_TYPE'));
        $root_category = Category::getRootCategory();
        if (!$root_category->id)
        {
            $root_category->id = 0;
            $root_category->name = $this->l('Root');
        }
        $root_category = array('id_category' => (int)$root_category->id, 'name' => $root_category->name);
        $selected_cat = array();
        foreach (Tools::getValue('categoryBox', $this->selected_cats) as $row)
            $selected_cat[] = $row;

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Company Name'),
                        'name' => 'YAMARKET_COMPANY_NAME',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Local delivery cost'),
                        'name' => 'YAMARKET_DELIVERY_PRICE',
                        'required' => true,
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Market currency'),
                        'name' => 'YAMARKET_CURRENCY',
                        'required' => true,
                        'options' => array(
                            'query' => Currency::getCurrencies(),
                            'id' => 'id_currency',
                            'name' => 'iso_code'
                        )
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Description'),
                        'name' => 'YAMARKET_DESC_TYPE',
                        'class' => 't',
                        'values' => array(
                            array(
                                'id' => 'YAMARKET_DESC_TYPE_normal',
                                'value' => 0,
                                'label' => $this->l('Normal')
                            ),
                            array(
                                'id' => 'YAMARKET_DESC_TYPE_short',
                                'value' => 1,
                                'label' => $this->l('Short')
                            )
                        ),
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Offer type'),
                        'name' => 'YAMARKET_EXPORT_TYPE',
                        'class' => 't',
                        'desc' => '<p style="clear:both">' . $this->l('Offer type, see descriptions on') . '<br>
                                   <a class="action_module" href="http://help.yandex.ru/partnermarket/offers.xml#base">http://help.yandex.ru/partnermarket/offers.xml#base</a><br>
                                   <a class="action_module" href="http://help.yandex.ru/partnermarket/offers.xml#vendor">http://help.yandex.ru/partnermarket/offers.xml#vendor</a>
                                   </p>',
                        'values' => array(
                            array(
                                'id' => 'YAMARKET_EXPORT_TYPE_simple',
                                'value' => 0,
                                'label' => $this->l('Simplified')
                            ),
                            array(
                                'id' => 'YAMARKET_EXPORT_TYPE_vendor',
                                'value' => 1,
                                'label' => $this->l('Vendor.model')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Country of origin attribute name'),
                        'name' => 'YAMARKET_COUNTRY_OF_ORIGIN',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Model attribute name'),
                        'name' => 'YAMARKET_MODEL_NAME',
                        'desc' => $this->l('Leave empty to use product name as model name'),
                    ),
                    array(
                        'type' => 'categories',
                        'label' => $this->l('Categories to export:'),
                        'name' => 'categoryBox',
                        'values' => array(
                            'trads' => array(
                                'Root' => $root_category,
                                'selected' => $this->l('Selected'),
                                'Check all' => $this->l('Check all'),
                                'Check All' => $this->l('Check All'),
                                'Uncheck All'  => $this->l('Uncheck All'),
                                'Collapse All' => $this->l('Collapse All'),
                                'Expand All' => $this->l('Expand All')
                            ),
                            'selected_cat' => $selected_cat,
                            'input_name' => 'categoryBox[]',
                            'use_radio' => false,
                            'use_search' => false,
                            'disabled_categories' => array(),
                            'top_category' => Category::getTopCategory(),
                            'use_context' => true,
                        ),
                        'tree' => array(
                            'id' => 'categories-tree',
                            'use_checkbox' => true,
                            'use_search' => false,
                            'selected_categories' => $selected_cat,
                            'input_name' => 'categoryBox[]',
                        ),
                        'selected_cat' => $selected_cat,
                    ),
                    array(
                        'type' => 'text',
                        'label' => '&lt;sales_notes&gt;',
                        'name' => 'YAMARKET_SALES_NOTES',
                        'size' => 50,
                        'desc' => $this->l('50 characters max'),
                    ),
                    array(
                        'type' => 'checkbox',
                        'label' => $this->l('Delivery settings'),
                        'name' => 'YAMARKET_DELIVERY',
                        'desc' => $this->l('Details: ').'<a class="action_module" href="https://help.yandex.ru/partnermarket/delivery.xml">help.yandex.ru/partnermarket/delivery.xml</a>',
                        'values' => array(
                            'query' => array(
                                array('id' => 'DELIVERY', 'name' => '&lt;delivery&gt;', 'val' => '1'),
                                array('id' => 'PICKUP', 'name' => '&lt;pickup&gt;', 'val' => '1'),
                                array('id' => 'STORE', 'name' => '&lt;store&gt;', 'val' => '1'),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'name' => 'submit'.$this->name
                )
            ),
        );
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $this->fields_form = array();
        $helper->table =  $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        $output = $helper->generateForm(array($fields_form));
        return $output;
    }

    private function renderMarketCategoryMappings()
    {
        // List Helper
        $fields_list = array(
            'category' => array(
                'title' => $this->l('Category'),
                'width' => 140,
                'type' => 'text',
            ),
            'ym_category' => array(
                'title' => $this->l('Yandex.Market category'),
                'width' => 140,
                'type' => 'text',
            ),
        );
        $helper = new HelperList();

        $helper->shopLinkType = '';
        $helper->simple_header = true;

        // Actions to be displayed in the "Actions" column
        $helper->actions = array('delete');

        // name of the ID field in the DB table
        $helper->identifier = 'id';
        $helper->show_toolbar = true;
        $helper->title = $this->l('Mappings for Yandex.Market categories');
        $helper->table = $this->name . '_bindings';

        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;


        return $helper->generateList(YMMappings::getAll(), $fields_list).$this->renderAddCategoryMappingForm();
    }

    private function renderAddCategoryMappingForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Add a new mapping'),
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('Shop category'),
                        'name' => 'category',
                        'options' => array(
                            'query' => YMMappings::getCategoriesWithParents(),
                            'id' => 'id_category',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Yandex.Market category'),
                        'name' => 'ym_category',
                        'desc' => $this->l('Details: ').'<a href="https://help.yandex.ru/partnermarket/guides/classification.xml#market-category">https://help.yandex.ru/partnermarket/guides/classification.xml#market-category</a>'
                    )
                ),
                'submit' => array(
                    'name' => 'submit'.$this->name.'binding',
                    'title' => $this->l('Add')
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table =  $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->identifier = $this->identifier;
        $helper->tpl_vars = array(
            'fields_value' => array('category' => '', 'ym_category' => ''),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        $configs = array();
        foreach ($this->custom_attributes as $i => $value)
        {
            $configs[$value] = Tools::getValue($value, Configuration::get($value));
        }
        return $configs;
    }

    private function ensureHttpPrefix($link)
    {
        $link->protocol_link = 'http://';
        $link->protocol_content = 'http://';
    }

    private function getPictures($prod_id, $link_rewrite)
    {
        $link = $this->context->link;

        $cover = Image::getCover($prod_id);
        $cover_picture = $link->getImageLink($link_rewrite, $prod_id.'-' .$cover['id_image'], $this->image_type);
        $pictures = array($cover_picture);
        $images = Image::getImages($this->id_lang, $prod_id);
        foreach ($images AS $image) {
            if ($image['id_image'] != $cover['id_image']) {
                $picture = $link->getImageLink($link_rewrite, $prod_id.'-'.$image['id_image'], $this->image_type);
                $pictures[] = safe_urlencode($picture);
            }
        }
        return array_slice($pictures, 0, 10);
    }

    private function getAccessories($product)
    {
        $accessories_arr = array();
        $accessories = Product::getAccessoriesLight($this->id_lang, $product['id_product']);
        foreach ($accessories as $acc) {
            $accessories_arr[] = $acc['id_product'];
        }
        return $accessories_arr;
    }

    protected function getImages($image_elem)
    {
        return $image_elem['id_image'];
    }

    private function getCombinations($prod_obj, $currency_obj)
    {

        /* Build attributes combinations */
        $combinations = $prod_obj->getAttributeCombinations($this->id_lang);
        $comb_array = array();
        if (is_array($combinations)) {
            $combination_images = $prod_obj->getCombinationImages($this->id_lang);
            foreach ($combinations as $k => $combination) {
                $price_to_convert = Tools::convertPrice($combination['price'], $currency_obj);
                $price = Tools::convertPrice($price_to_convert, $currency_obj);
                $group_name = $this->attibute_groups[$combination['id_attribute_group']];

                $comb_array[$combination['id_product_attribute']]['id_product_attribute'] = $combination['id_product_attribute'];
                $comb_array[$combination['id_product_attribute']]['attributes'][] = array($group_name, $combination['attribute_name']);
                $comb_array[$combination['id_product_attribute']]['price'] = $price;
                $comb_array[$combination['id_product_attribute']]['weight'] = $combination['weight'] . Configuration::get('PS_WEIGHT_UNIT');
                $comb_array[$combination['id_product_attribute']]['unit_impact'] = $combination['unit_price_impact'];
                $comb_array[$combination['id_product_attribute']]['reference'] = $combination['reference'];
                $comb_array[$combination['id_product_attribute']]['ean13'] = $combination['ean13'];
                $comb_array[$combination['id_product_attribute']]['quantity'] = $combination['quantity'];
                // TODO: put back anonymous function when PHP 5.3
                //$get_images = function($image_elem) {
                //    return $image_elem['id_image'];
                //};
                if (isset($combination_images[$combination['id_product_attribute']][0]['id_image'])) {
                    //$comb_array[$combination['id_product_attribute']]['id_images'] = array_map($get_images, $combination_images[$combination['id_product_attribute']]);
                    $comb_array[$combination['id_product_attribute']]['id_images'] = array_map(array($this, 'getImages'), $combination_images[$combination['id_product_attribute']]);
                }
                else {
                    $comb_array[$combination['id_product_attribute']]['id_images'] = array();
                }
            }
        }
        return $comb_array;
    }

    private function getFeatures($prod_id)
    {
        $features = Product::getFeaturesStatic((int)$prod_id);
        $params = array();
        foreach ($features as $feature) {
            $feature_name = Feature::getFeature($this->id_lang, $feature['id_feature']);
            $feature_name = $feature_name['name'];
            $feature_values = FeatureValue::getFeatureValueLang($feature['id_feature_value']);
            $feature_value = null;
            foreach ($feature_values as $f_value) {
                $feature_value = $f_value['value'];
                if ($f_value['id_lang'] == $this->id_lang) {
                    break;
                }
            }
            if ($feature_value != null)
                $params[$feature_name] = $feature_value;
        }
        return $params;
    }

    private function getParams($combination)
    {
        $params = array();
        foreach($combination['attributes'] as $attribute) {
            $params[$attribute[0]] = $attribute[1];
        }
        return $params;
    }

    private function isGreaterThan1611()
    {
        $majorVersion = (float)Tools::substr(_PS_VERSION_,0, 3);
        if($majorVersion < 1.6 )
            return false;
        if ($majorVersion== 1.6) {
            $minorVersion = (float)Tools::substr(_PS_VERSION_,4, 3);
            if ($minorVersion < 1.1)
                return false;
            return true;
        }
        else {
            return true;
        }
    }

    private function writeOffers(XMLWriter $writer)
    {
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        //$currency = new Currency(Configuration::get('YAMARKET_CURRENCY'));
        $this->context->currency = $currency;

        $desc_type = Configuration::get('YAMARKET_DESC_TYPE');

        $link = $this->context->link;
        $this->ensureHttpPrefix($link);

        // Get products
        $products = Product::getProducts($this->id_lang, 0, 0, 'name', 'asc');

        // Offers
        $writer->startElement("offers");
        foreach ($products AS $product)
        {
            // Get home category
            $category = $product['id_category_default'];

            if ($category == 1)
            {
                $temp_categories = Product::getProductCategories($product['id_product']);
                foreach ($temp_categories as $category)
                {
                    if ($category != 1)
                        break;
                }
                if ($category == 1)
                    continue;
            }
            if (in_array($category, $this->excluded_cats))
                continue;

            $prod_obj = new Product($product['id_product']);
            $crewrite = Category::getLinkRewrite($product['id_category_default'], $this->id_lang);

            $accessories = $this->getAccessories($product);
            $features = $this->getFeatures($product['id_product']);
            $combinations = $this->getCombinations($prod_obj, $currency);

            // template array
            $product_item = array('name' => html_entity_decode($product['name']),
                'description' => decode_html($product['description']),
                'id_category_default' => $category,
                'ean13' => $product['ean13'],
                'accessories' => implode(',', $accessories),
                'vendor' => $product['manufacturer_name']);
            if ($desc_type == 1)
                $product_item['description'] = decode_html($product['description_short']);
            if ($this->country_of_origin_attr !='' && array_key_exists($this->country_of_origin_attr, $features)){
                $product_item['country_of_origin'] = $features[$this->country_of_origin_attr];
                unset($features[$this->country_of_origin_attr]);
            }
            if ($this->model_name_attr !='' && array_key_exists($this->model_name_attr, $features)){
                $product_item['name'] = $features[$this->model_name_attr];
                unset($features[$this->model_name_attr]);
            }

            if (!$product['available_for_order'] or !$product['active'])
                continue;

            if (!empty($combinations))
            {
                foreach ($combinations as $combination)
                {
                    $prod_obj->id_product_attribute = $combination['id_product_attribute'];
                    $available_for_order = 1 <= StockAvailable::getQuantityAvailableByProduct($product['id_product'], $combination['id_product_attribute']);
                    if (!$available_for_order && !$prod_obj->checkQty(1)) {
                        continue;
                    }
                    $params = $this->getParams($combination);

                    $pictures = array();
                    foreach($combination['id_images'] as $id_image){
                        $pictures[] = $link->getImageLink($product['link_rewrite'], $product['id_product'].'-'.$id_image, $this->image_type);
                    }
                    // if image array is empty: get images from the product
                    if (empty($pictures))
                        $pictures = $this->getPictures($product['id_product'], $product['link_rewrite']);

                    if($this->isGreaterThan1611())
                        $url = $link->getProductLink($prod_obj, $product['link_rewrite'], $crewrite, null, null, null, $combination['id_product_attribute'], null, null, true);
                    else
                        $url = $link->getProductLink($prod_obj, $product['link_rewrite'], $crewrite, null, null, null, $combination['id_product_attribute']);
                    $extra_product_item = array('id_product' => $product['id_product'].'c'.$combination['id_product_attribute'],
                        'available_for_order' => $available_for_order,
                        'price' => $prod_obj->getPrice(true, $combination['id_product_attribute']),
                        'pictures' => $pictures,
                        'group_id' => $product['id_product'],
                        'params' => array_merge($params, $features),
                        'url' => safe_urlencode($url)
                    );
                    $offer = array_merge($product_item, $extra_product_item);
                    $this->writeOfferElem($offer, $writer, $currency);
                }

            } else {
                $pictures = $this->getPictures($product['id_product'], $product['link_rewrite']);
                $available_for_order = 1 <= StockAvailable::getQuantityAvailableByProduct($product['id_product'], 0);
                if (!$available_for_order && !$prod_obj->checkQty(1)) {
                    continue;
                }

                $url = $link->getProductLink($prod_obj, $product['link_rewrite'], $crewrite);
                $extra_product_item = array('id_product' => $product['id_product'],
                    'available_for_order' => $available_for_order,
                    'price' => $prod_obj->getPrice(),
                    'pictures' => $pictures,
                    'params' => $features,
                    'url' => safe_urlencode($url)
                );
                $offer = array_merge($product_item, $extra_product_item);

                $this->writeOfferElem($offer, $writer, $currency);

            }
            $prod_obj->clearCache(true);
        }

        $writer->endElement();
    }

    public function getPriceList()
    {
        $writer = new XMLWriter();
        $writer->openURI('php://output');
        $writer->startDocument('1.0','UTF-8');

        $writer->startElement("yml_catalog");
            $writer->writeAttribute("date", date('Y-m-d H:i'));
            $writer->startElement("shop");
                $writer->startElement("name");
                    $writer->writeCData(decode_html(Configuration::get('PS_SHOP_NAME')));
                $writer->endElement();
                $writer->startElement("company");
                    $writer->writeCData(Configuration::get('YAMARKET_COMPANY_NAME'));
                $writer->endElement();
                $writer->writeElement("url", $this->proto_prefix . __PS_BASE_URI__);
                $writer->writeElement("platform", "PrestaShop");

                $writer->startElement("currencies");
                    // Get currencies
                    $currencies = Currency::getCurrencies();
                    $default_currency = new Currency(Configuration::get('YAMARKET_CURRENCY'));
                    foreach ($currencies as $cur) {
                        if (in_array($cur['iso_code'], self::$ALLOWED_CURRENCIES)) {
                            $writer->startElement("currency");
                            $writer->writeAttribute("id", $cur['iso_code']);
                            if ($default_currency->iso_code == $cur['iso_code'])
                                $writer->writeAttribute("rate", 1);
                            else
                                $writer->writeAttribute("rate", 1/$cur['conversion_rate']*$default_currency->conversion_rate);
                            $writer->endElement();
                        }
                    }
                $writer->endElement();

                // Get categories
                $categories = YMMappings::getCategories();

                $writer->startElement("categories");
                foreach ($categories as $category) {
                    $writer->startElement("category");
                        $writer->writeAttribute("id", $category['id_category']);
                        if (array_key_exists('id_parent', $category) && $category['id_parent'] != 1)
                            $writer->writeAttribute("parentId", $category['id_parent']);
                        $writer->writeCData($category['name']);
                    $writer->endElement();
                }
                $writer->endElement();


                $local_delivery_price = Configuration::get('YAMARKET_DELIVERY_PRICE');
                if ($local_delivery_price or $local_delivery_price === "0") {
                    $writer->writeElement("local_delivery_cost", $local_delivery_price);
                }

                $this->writeOffers($writer);
            $writer->endElement();
        $writer->endElement();
    }

    private function writeOfferElem($offer, XMLWriter $writer, $currency)
    {
        $writer->startElement("offer");
        if ($offer['available_for_order'])
            $writer->writeAttribute("available", 'true');
        else
            $writer->writeAttribute("available", 'false');

        $writer->writeAttribute("id", $offer['id_product']);

        if ($offer['group_id'])
            $writer->writeAttribute("group_id", $offer['group_id']);

        // need to add vendor also here
        if (Configuration::get('YAMARKET_EXPORT_TYPE') != 0)
            $writer->writeAttribute("type", 'vendor.model');
        $writer->writeElement("url", $offer['url']);
        $writer->writeElement("price", $offer['price']);
        $writer->writeElement("currencyId", $currency->iso_code);
        $writer->writeElement("categoryId", $offer['id_category_default']);

        // market category mapping
        if (array_key_exists($offer['id_category_default'], $this->ym_bindings))
            $writer->writeElement("market_category", $this->ym_bindings[$offer['id_category_default']]);


        foreach ($offer['pictures'] as $pic) {
            $writer->writeElement("picture", $pic);
        }
        if (Configuration::get('YAMARKET_DELIVERY_STORE'))
            $writer->writeElement("store", "true");
        if (Configuration::get('YAMARKET_DELIVERY_PICKUP'))
            $writer->writeElement("pickup", "true");
        if (Configuration::get('YAMARKET_DELIVERY_DELIVERY'))
            $writer->writeElement("delivery", "true");


        if (Configuration::get('YAMARKET_EXPORT_TYPE') == 0) {
            $this->writeSimpleOffer($offer, $writer);
        }
        else {
            $this->writeVendorOffer($offer, $writer);
        }
        $writer->endElement();
    }

    private function writeSimpleOffer($offer, XMLWriter $writer)
    {
        $writer->startElement("name");
        $writer->writeCdata($offer['name']);
        $writer->endElement();

        if (array_key_exists('vendor', $offer))
            $writer->writeElement("vendor", $offer['vendor']);

        $writer->startElement("description");
        $writer->writeCdata(strip_tags($offer['description']));
        $writer->endElement();

        if (Configuration::get('YAMARKET_SALES_NOTES'))
            $writer->writeElement("sales_notes", Configuration::get('YAMARKET_SALES_NOTES'));

        if (array_key_exists('country_of_origin', $offer))
            $writer->writeElement("country_of_origin", $offer['country_of_origin']);

        foreach ($offer['params'] as $param_name => $value) {
            $writer->startElement("param");
            $writer->writeAttribute("name", $param_name);
            $writer->writeRaw($value);
            $writer->endElement();
        }
    }

    private function writeVendorOffer($offer, XMLWriter $writer)
    {
        $writer->writeElement("vendor", $offer['vendor']);

        $writer->startElement("model");
        $writer->writeCdata($offer['name']);
        $writer->endElement();

        $writer->startElement("description");
        $writer->writeCdata(strip_tags($offer['description']));
        $writer->endElement();

        if (Configuration::get('YAMARKET_SALES_NOTES'))
            $writer->writeElement("sales_notes", Configuration::get('YAMARKET_SALES_NOTES'));

        if (array_key_exists('country_of_origin', $offer))
            $writer->writeElement("country_of_origin", $offer['country_of_origin']);

        if (array_key_exists('accessories', $offer) && $offer['accessories'])
            $writer->writeElement("rec", $offer['accessories']);

        foreach ($offer['params'] as $param_name => $value) {
            $writer->startElement("param");
            $writer->writeAttribute("name", $param_name);
            $matches = split_param_units($value);
            if ($matches[2])
                $writer->writeAttribute("unit", $matches[2]);
            $writer->writeRaw($matches[1]);
            $writer->endElement();
        }
    }

}

function decode_html($html_string)
{
    $html_string = str_replace("&nbsp;", " ", $html_string);
    $html_string = html_entity_decode($html_string);
    return $html_string;
}

function safe_urlencode_match($match)
{
    return rawurlencode($match[0]);
}

// Used in combination urls encoding - for cyrillic attributes for example
function safe_urlencode($txt)
{
    // Skip all URL reserved characters plus dot, dash, underscore and tilde..
    $result = preg_replace_callback("/[^-\._~:\/\?#\\[\\]@!\$&'\(\)\*\+,;=]+/",
        "safe_urlencode_match", $txt);
    return ($result);
}

function split_param_units($value)
{
    $matches = array();
    if (preg_match("/^([\d\-\/]+)\s*(\p{L}+)$/u", $value, $matches)) {
        return $matches;
    }
    return array(null, $value, null);
}
