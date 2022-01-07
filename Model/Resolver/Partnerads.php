<?php
namespace Partner\Module\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;

class Partnerads implements ResolverInterface
{
    
	
	/**
     * @var ValueFactory
     */
    private $valueFactory;
    protected $_categoryFactory;
    protected $_storeManager;
    protected $_objectManager;
    protected $_resource;
    protected $_product;
    protected $_image_helper;
    protected $_abstractProductBlock;
    


    
    public function __construct(
        ValueFactory $valueFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Helper\Image $image_helper,
        \Magento\Catalog\Model\ProductFactory $product,
        \Magento\Framework\Pricing\Helper\Data $abstractProductBlock
    ) {
        $this->valueFactory = $valueFactory;
        $this->_storeManager = $storeManager;
        $this->_product = $product;
        $this->_image_helper = $image_helper;
        $this->_abstractProductBlock = $abstractProductBlock;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
		$returndatata = array();
		$updata = array();
		
		 $returndatata['data'] = $args;

		
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();

		
		$updata['partnerads_partner_id'] = $args['order_id'];
		$updata['partnerads_pacid_id'] = $args['pac_id'];
		
		$orderInterface = $objectManager->create('Magento\Sales\Api\Data\OrderInterface'); 
		$order = $orderInterface->loadByIncrementId($args['order_id']);
		$orderModel = $objectManager->create('Magento\Sales\Model\Order')->load($order->getentity_id());
		if($orderModel->getId()){
			$orderModel->setpartnerads_partner_id($args['partner_id']);
			$orderModel->setpartnerads_pacid_id($args['pac_id']);
			$orderModel->save();
        }

		
		
        return $returndatata;
    }

    
}