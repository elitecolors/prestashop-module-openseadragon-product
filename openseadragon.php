<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class openseadragon extends Module
{
    const HOOKS = [
        'actionFrontControllerSetMedia',
        'displayAfterProductThumbs',
    ];

    public function __construct()
    {
        $this->name = 'openseadragon';
        $this->author = 'Saidani Ahmed';
        $this->author_uri = 'saidaniahmed125@gmail.com';
        $this->version = '1.0.0';
        $this->need_instance = 0;

        $this->ps_versions_compliancy = [
            'min' => '1.7.1.0',
            'max' => _PS_VERSION_,
        ];

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Openseadragon zoom product', [], 'Modules.Openseadragon.Admin');
        $this->description = $this->trans('Product page image zoom with openseadragon', [], 'Modules.Openseadragon.Admin');

        $this->templateFile = 'module:openseadragon/views/templates/hook/product.tpl';
    }

    public function install()
    {
        $this->_clearCache('*');

        return parent::install()
            && $this->registerHook(static::HOOKS);
    }

    public function uninstall()
    {
        $this->_clearCache('*');

        return parent::uninstall();
    }

    public function hookActionFrontControllerSetMedia(array $params)
    {
        $this->context->controller->registerStylesheet(
            'openseadragon',
            'modules/'.$this->name.'/views/css/style.css',
            [
                'media' => 'all',
                'priority' => 150,
            ]
        );

        $this->context->controller->registerJavascript(
            'openseadragonmin',
            'modules/'.$this->name.'/views/js/openseadragon.min.js',
            [
                'priority' => 160,
            ]
        );

        $this->context->controller->registerJavascript(
            'openseadragonjs',
            'modules/'.$this->name.'/views/js/openseadragon.js',
            [
                'priority' => 170,
            ]
        );
    }

    public function getContent()
    {
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->cron();
        }

        $this->_html .= $this->_displayInfo();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    private function _displayInfo()
    {
        $this->smarty->assign([
            'show_url' => $this->context->shop->getBaseURL(),
        ]);

        return $this->display(__FILE__, './views/hook/infos.tpl');
    }

    public function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Contact details', [], 'Modules.Openseadragon.Admin'),
                    'icon' => 'icon-envelope',
                ],
                'submit' => [
                    'title' => $this->trans('Run Cron', [], 'Modules.Openseadragon.Admin'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => [],
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function hookDisplayAfterProductThumbs($configuration)
    {
        if (!$this->isCached($this->templateFile, $this->getCacheId('openseadragon'))) {
            $variables = $this->getWidgetVariables($configuration);

            if (empty($variables)) {
                return false;
            }

            $this->smarty->assign($variables);
        }

        return $this->fetch($this->templateFile, $this->getCacheId('openseadragon'));
    }

    public function getWidgetVariables(array $configuration = [])
    {
        $product = $configuration['product'];
        $idProduct = $product['id_product'];
        $finder = new Symfony\Component\Finder\Finder();
        $finder
            ->files()
            ->name('*.dzi')
            ->in(__DIR__.'/images/product_'.$idProduct);

        if (!$finder->hasResults()) {
            return;
        }
        $config = [];
        foreach ($finder as $file) {
            $config[] = '/modules/openseadragon/images/product_'.$idProduct.'/'.$file->getFilename();
        }
        if (!empty($config)) {
            return [
                'dzi_files' => $config,
                'dirDzi' => '/modules/openseadragon/views/css/images/',
            ];
        }

        return false;
    }

    public function cron()
    {
        set_time_limit(0);
        $deepzoom = Jeremytubbs\Deepzoom\DeepzoomFactory::create([
            'path' => __DIR__.'/images', // Export path for tiles
            'driver' => 'gd', // Choose between gd and imagick support.
            'format' => 'jpg',
        ]);

        $products = $this->getProducts();

        if (empty($products)) {
            return;
        }

        foreach ($products as $product) {
            $idProduct = $product['id_product'];
            $images = Image::getImages($this->context->language->getId(), $idProduct);
            if (empty($images)) {
                continue;
            }
            foreach ($images as $img) {
                $idImage = $img['id_image'];
                $image = new Image($idImage);
                // if file already exist skip
                if (file_exists(__DIR__.'/images/product_'.$idProduct.'/file_'.$idImage.'.dzi')) {
                    continue;
                }
                $deepzoom->makeTiles($image->getPathForCreation().'.jpg', 'file_'.$idImage, 'product_'.$idProduct);
            }
        }
    }

    public static function getProducts(
    ) {
        $only_active = true;
        $sql = 'SELECT p.*, product_shop.*, pl.* , m.`name` AS manufacturer_name, s.`name` AS supplier_name
                FROM `'._DB_PREFIX_.'product` p
                '.Shop::addSqlAssociation('product', 'p').'
                LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (p.`id_product` = pl.`id_product` '.Shop::addSqlRestrictionOnLang('pl').')
                LEFT JOIN `'._DB_PREFIX_.'manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
                LEFT JOIN `'._DB_PREFIX_.'supplier` s ON (s.`id_supplier` = p.`id_supplier`)'.
            ($only_active ? ' AND product_shop.`active` = 1' : ''
             );
        $rq = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        foreach ($rq as &$row) {
            $row = Product::getTaxesInformations($row);
        }

        return $rq;
    }
}
