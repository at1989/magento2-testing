<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Checkout\Test\Unit\Block;

class OnepageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Checkout\Block\Onepage
     */
    protected $model;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $configProviderMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $storeManagerMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $formKeyMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $layoutProcessorMock;

    protected function setUp()
    {
        $contextMock = $this->getMock('\Magento\Framework\View\Element\Template\Context', [], [], '', false);
        $directoryHelperMock = $this->getMock('\Magento\Directory\Helper\Data', [], [], '', false);
        $configCacheTypeMock = $this->getMock('\Magento\Framework\App\Cache\Type\Config', [], [], '', false);
        $customerSessionMock = $this->getMock('\Magento\Customer\Model\Session', [], [], '', false);
        $resourceSessionMock = $this->getMock('\Magento\Checkout\Model\Session', [], [], '', false);
        $addressConfigMock = $this->getMock('\Magento\Customer\Model\Address\Config', [], [], '', false);
        $httpContextMock = $this->getMock('\Magento\Framework\App\Http\Context', [], [], '', false);
        $addressMapperMock = $this->getMock('\Magento\Customer\Model\Address\Mapper', [], [], '', false);
        $this->formKeyMock = $this->getMock('\Magento\Framework\Data\Form\FormKey', [], [], '', false);
        $this->configProviderMock = $this->getMock(
            '\Magento\Checkout\Model\CompositeConfigProvider',
            [],
            [],
            '',
            false
        );
        $countryCollectionFactoryMock = $this->getMock(
            'Magento\Directory\Model\ResourceModel\Country\CollectionFactory',
            ['create'],
            [],
            '',
            false
        );
        $regionCollectionFactoryMock = $this->getMock(
            'Magento\Directory\Model\ResourceModel\Region\CollectionFactory',
            ['create'],
            [],
            '',
            false
        );
        $customerRepositoryMock = $this->getMock(
            '\Magento\Customer\Api\CustomerRepositoryInterface',
            [],
            [],
            '',
            false
        );

        $this->storeManagerMock = $this->getMock('\Magento\Store\Model\StoreManagerInterface', [], [], '', false);
        $contextMock->expects($this->once())->method('getStoreManager')->willReturn($this->storeManagerMock);
        $this->layoutProcessorMock = $this->getMock(
            '\Magento\Checkout\Block\Checkout\LayoutProcessorInterface',
            [],
            [],
            '',
            false
        );

        $this->model = new \Magento\Checkout\Block\Onepage(
            $contextMock,
            $directoryHelperMock,
            $configCacheTypeMock,
            $customerSessionMock,
            $resourceSessionMock,
            $countryCollectionFactoryMock,
            $regionCollectionFactoryMock,
            $customerRepositoryMock,
            $addressConfigMock,
            $httpContextMock,
            $addressMapperMock,
            $this->formKeyMock,
            $this->configProviderMock,
            [$this->layoutProcessorMock]
        );
    }

    public function testGetBaseUrl()
    {
        $baseUrl = 'http://magento.com';
        $storeMock = $this->getMock('\Magento\Store\Model\Store', [], [], '', false);

        $storeMock->expects($this->once())->method('getBaseUrl')->willReturn($baseUrl);
        $this->storeManagerMock->expects($this->once())->method('getStore')->willReturn($storeMock);

        $this->assertEquals($baseUrl, $this->model->getBaseUrl());
    }

    public function testGetCheckoutConfig()
    {
        $checkoutConfig = ['checkout', 'config'];
        $this->configProviderMock->expects($this->once())->method('getConfig')->willReturn($checkoutConfig);

        $this->assertEquals($checkoutConfig, $this->model->getCheckoutConfig());
    }

    public function testGetFormKey()
    {
        $formKey = 'form_key';
        $this->formKeyMock->expects($this->once())->method('getFormKey')->willReturn($formKey);

        $this->assertEquals($formKey, $this->model->getFormKey());
    }

    public function testGetJsLayout()
    {
        $processedLayout = ['layout' => ['processed' => true]];
        $jsonLayout = '{"layout":{"processed":true}}';
        $this->layoutProcessorMock->expects($this->once())->method('process')->with([])->willReturn($processedLayout);

        $this->assertEquals($jsonLayout, $this->model->getJsLayout());
    }
}
