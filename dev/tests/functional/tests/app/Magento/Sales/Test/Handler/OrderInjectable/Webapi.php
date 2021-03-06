<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Sales\Test\Handler\OrderInjectable;

use Magento\Bundle\Test\Fixture\BundleProduct;
use Magento\ConfigurableProduct\Test\Fixture\ConfigurableProduct;
use Magento\Downloadable\Test\Fixture\DownloadableProduct;
use Magento\Sales\Test\Fixture\OrderInjectable;
use Magento\Mtf\Fixture\FixtureInterface;
use Magento\Mtf\Util\Protocol\CurlTransport;
use Magento\Mtf\Handler\Webapi as AbstractWebapi;
use Magento\Mtf\Util\Protocol\CurlTransport\WebapiDecorator;

/**
 * Create new order via web API.
 */
class Webapi extends AbstractWebapi implements OrderInjectableInterface
{
    /**
     * Mapping values for data.
     *
     * @var array
     */
    protected $mappingData = [
        'region_id' => [
            'California' => '12',
        ],
        'country_id' => [
            'United States' => 'US',
        ],
    ];

    /**
     * Order quote value.
     *
     * @var string
     */
    protected $quote;

    /**
     * First part of Web API url for creating order.
     *
     * @var string
     */
    protected $url;

    /**
     * Creating order using quote via web API.
     *
     * @param FixtureInterface|null $fixture [optional]
     * @return array
     */
    public function persist(FixtureInterface $fixture = null)
    {
        /** @var OrderInjectable $fixture */
        $this->createQuote($fixture);
        $this->url = $_ENV['app_frontend_url'] . 'rest/V1/carts/' . (int)$this->quote;
        $this->setProducts($fixture);
        $this->setCoupon($fixture);
        $this->setAddress($fixture, 'billing');
        $this->setAddress($fixture, 'shipping');
        $this->setShippingMethod($fixture);
        $this->setPaymentMethod($fixture);
        $orderId = $this->placeOrder();

        return ['id' => sprintf("%09d", $orderId)];
    }

    /**
     * Create checkout quote.
     *
     * @param OrderInjectable $order
     * @return void
     * @throws \Exception
     */
    protected function createQuote(OrderInjectable $order)
    {
        $url = $_ENV['app_frontend_url'] . 'rest/V1/customers/' . $order->getCustomerId()->getId() . '/carts';
        $data = '{"customerId": "' . $order->getCustomerId()->getId() . '"}';
        $this->webapiTransport->write($url, $data);
        $response = json_decode($this->webapiTransport->read(), true);
        $this->webapiTransport->close();
        if (!is_numeric($response)) {
            $this->eventManager->dispatchEvent(['webapi_failed'], [$response]);
            throw new \Exception('Could not create checkout quote viw web API!');
        }
        $this->quote = $response;
    }

    /**
     * Add products to quote.
     *
     * @param OrderInjectable $order
     * @return void
     * @throws \Exception
     */
    protected function setProducts(OrderInjectable $order)
    {
        $url = $_ENV['app_frontend_url'] . 'rest/V1/carts/items';
        $products = $order->getEntityId()['products'];
        foreach ($products as $product) {
            $data = [
                'cartItem' => [
                    'sku' => $product->getSku(),
                    'qty' => isset($product->getCheckoutData()['qty']) ? $product->getCheckoutData()['qty'] : 1,
                    'quote_id' => $this->quote
                ]
            ];
            $methodName = 'prepare' . ucfirst($product->getDataConfig()['type_id']) . 'Options';
            if (method_exists($this, $methodName)) {
                $data['cartItem']['product_option'] = $this->$methodName($product);
            }
            $this->webapiTransport->write($url, $data);
            $response = (array)json_decode($this->webapiTransport->read(), true);
            $this->webapiTransport->close();
            if (isset($response['message'])) {
                $this->eventManager->dispatchEvent(['webapi_failed'], [$response]);
                throw new \Exception('Could not add product item to quote!');
            }
        }
    }

    /**
     * Set coupon to quote.
     *
     * @param OrderInjectable $order
     * @return void
     * @throws \Exception
     */
    protected function setCoupon(OrderInjectable $order)
    {
        if (!$order->hasData('coupon_code')) {
            return;
        }
        $url = $this->url . '/coupons/' . $order->getCouponCode()->getCouponCode();
        $data = [
            'cartId' => $this->quote,
            'couponCode' => $order->getCouponCode()->getCouponCode()
        ];
        $this->webapiTransport->write($url, $data, WebapiDecorator::PUT);
        $response = json_decode($this->webapiTransport->read(), true);
        $this->webapiTransport->close();
        if ($response !== true) {
            $this->eventManager->dispatchEvent(['webapi_failed'], [$response]);
            throw new \Exception('Could not apply coupon code!');
        }
    }

    /**
     * Set address to quote.
     *
     * @param OrderInjectable $order
     * @param string $addressType billing|shipping
     * @return void
     * @throws \Exception
     */
    protected function setAddress(OrderInjectable $order, $addressType)
    {
        $url = $this->url . "/$addressType-address";
        if ($addressType == 'billing') {
            $address = $order->getBillingAddressId();
        } else {
            if (!$order->hasData('shipping_method')) {
                return;
            }
            $address = $order->hasData('shipping_address_id')
                ? $order->getShippingAddressId()
                : $order->getBillingAddressId();
        }
        unset($address['default_billing']);
        unset($address['default_shipping']);
        foreach (array_keys($this->mappingData) as $key) {
            if (isset($address[$key])) {
                $address[$key] = $this->mappingData[$key][$address[$key]];
            }
        }
        $data = ["address" => $address];
        $this->webapiTransport->write($url, $data);
        $response = json_decode($this->webapiTransport->read(), true);
        $this->webapiTransport->close();
        if (!is_numeric($response)) {
            $this->eventManager->dispatchEvent(['webapi_failed'], [$response]);
            throw new \Exception("Could not set $addressType addresss to quote!");
        }
    }

    /**
     * Set shipping method to quote.
     *
     * @param OrderInjectable $order
     * @return void
     * @throws \Exception
     */
    protected function setShippingMethod(OrderInjectable $order)
    {
        if (!$order->hasData('shipping_method')) {
            return;
        }
        $url = $this->url . '/selected-shipping-method';
        list($carrier, $method) = explode('_', $order->getShippingMethod());
        $data = [
            "carrierCode" => $carrier,
            "methodCode" => $method
        ];
        $this->webapiTransport->write($url, $data, WebapiDecorator::PUT);
        $response = json_decode($this->webapiTransport->read(), true);
        $this->webapiTransport->close();
        if ($response !== true) {
            $this->eventManager->dispatchEvent(['webapi_failed'], [$response]);
            throw new \Exception('Could not set shipping method to quote!');
        }
    }

    /**
     * Set payment method to quote.
     *
     * @param OrderInjectable $order
     * @return void
     * @throws \Exception
     */
    protected function setPaymentMethod(OrderInjectable $order)
    {
        $url = $this->url . '/selected-payment-method';
        $data = [
            "cartId" => $this->quote,
            "method" => $order->getPaymentAuthExpiration()
        ];
        $this->webapiTransport->write($url, $data, WebapiDecorator::PUT);
        $response = json_decode($this->webapiTransport->read(), true);
        $this->webapiTransport->close();
        if (!is_numeric($response)) {
            $this->eventManager->dispatchEvent(['webapi_failed'], [$response]);
            throw new \Exception('Could not set payment method to quote!');
        }
    }

    /**
     * Place order.
     *
     * @return array
     * @throws \Exception
     */
    protected function placeOrder()
    {
        $url = $this->url . '/order';
        $data = ["cartId" => $this->quote];
        $this->webapiTransport->write($url, $data, WebapiDecorator::PUT);
        $response = json_decode($this->webapiTransport->read(), true);
        $this->webapiTransport->close();
        if (!is_numeric($response)) {
            $this->eventManager->dispatchEvent(['webapi_failed'], [$response]);
            throw new \Exception('Could not place order via web API!');
        }

        return $response;
    }

    /**
     * Prepare configurable product options.
     *
     * @param ConfigurableProduct $product
     * @return array
     */
    protected function prepareConfigurableOptions(ConfigurableProduct $product)
    {
        $options = [];
        $attributesData = $product->getDataFieldConfig('configurable_attributes_data')['source']->getAttributesData();
        foreach ($product->getCheckoutData()['options']['configurable_options'] as $checkoutOption) {
            $options[] = [
                'option_id' => $attributesData[$checkoutOption['title']]['attribute_id'],
                'option_value' => $attributesData[$checkoutOption['title']]['options'][$checkoutOption['value']]['id'],
            ];
        }

        return ['extension_attributes' => ['configurable_item_options' => $options]];
    }

    /**
     * Prepare bundle product options.
     *
     * @param BundleProduct $product
     * @return array
     */
    protected function prepareBundleOptions(BundleProduct $product)
    {
        $options = [];
        foreach ($product->getCheckoutData()['options']['bundle_options'] as $checkoutOption) {
            foreach ($product->getBundleSelections()['bundle_options'] as $productOption) {
                if (strpos($productOption['title'], $checkoutOption['title']) !== false) {
                    $option = [];
                    foreach ($productOption['assigned_products'] as $productData) {
                        if (strpos($productData['search_data']['name'], $checkoutOption['value']['name']) !== false) {
                            $qty = isset($checkoutOption['qty'])
                                ? $checkoutOption['qty']
                                : $productData['data']['selection_qty'];
                            $option['option_id'] = $productData['option_id'];
                            $option['option_selections'][] = $productData['selection_id'];
                            $option['option_qty'] = $qty;
                        }
                    }
                    $options[] = $option;
                }
            }
        }

        return ['extension_attributes' => ['bundle_options' => $options]];
    }

    /**
     * Prepare downloadable product options.
     *
     * @param DownloadableProduct $product
     * @return array
     */
    protected function prepareDownloadableOptions(DownloadableProduct $product)
    {
        $checkoutData = $product->getCheckoutData();
        $links = [];
        foreach ($checkoutData['options']['links'] as $link) {
            $links[] = $link['id'];
        }

        return ['extension_attributes' => ['downloadable_option' => ['downloadable_links' => $links]]];
    }
}
