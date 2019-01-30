<?php
/*************************************************************************************/
/*      This file is part of the module AdminOrderCreation                           */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AdminOrderCreation\Controller;

use AdminOrderCreation\AdminOrderCreation;
use AdminOrderCreation\Util\Calc;
use AdminOrderCreation\Util\CriteriaSearchTrait;
use CreditNote\Model\CreditNote;
use CreditNote\Model\CreditNoteAddress;
use CreditNote\Model\CreditNoteDetail;
use CreditNote\Model\CreditNoteQuery;
use CreditNote\Model\CreditNoteStatusQuery;
use CreditNote\Model\CreditNoteTypeQuery;
use CreditNote\Model\OrderCreditNote;
use InvoiceRef\EventListeners\OrderListener;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Propel;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\Loop\ProductSaleElements;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\ProductI18nTableMap;
use AdminOrderCreation\Model\Order;
use Symfony\Component\Form\Form;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductAttributeCombination;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleI18n;
use Thelia\Tools\I18n;
use Thelia\Tools\URL;

class OrderController extends BaseAdminController
{
    use CriteriaSearchTrait;

    public function ajaxModalCreateAction(Request $request)
    {
        if (!AdminOrderCreation::checkLicence()) {
            return new RedirectResponse(
                URL::getInstance()->absoluteUrl(
                    '/admin/admin-order-creation/licence'
                )
            );
        }

        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::CREATE)) {
            return $response;
        }

        $order = new Order();

        $order->setLang($this->getLang());

        $order->setDispatcher($this->getDispatcher());

        $form = $this->createForm('admin-order-creation.create', 'form', [], ['csrf_protection' => false]);

        $formValidate = $this->validateForm($form, 'post');

        $this->performOrder($order, $formValidate);

        $this->getParserContext()->addForm($form);

        $errorMessage = [];
        foreach ($formValidate->getErrors() as $error) {
            $errorMessage[] = $error->getMessage();
        }

        if (count($errorMessage)) {
            $form->setErrorMessage(implode('<br/>', $errorMessage));
        }

        if (!$form->hasError() && 'create' === $formValidate->get('action')->getData()) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderTableMap::DATABASE_NAME);

            // use transaction because $criteria could contain info
            // for more than one table (I guess, conceivably)
            $con->beginTransaction();

            try {
                $cart = new Cart();

                $cart->save();

                $order->setCartId($cart->getId());

                // on passe par le statut par défault
                $order->setOrderStatus(
                    OrderStatusQuery::create()->findOneById(1)
                );

                $order->save();

                $this->performCreditNote($order, $formValidate);

                $orderStatusId = $formValidate->get('status_id')->getData();

                if ((int) $orderStatusId >= 2) {
                    if ((int) AdminOrderCreation::getConfigValue(AdminOrderCreation::CONFIG_KEY_INVOICE_REF_TYPE) === 0) {
                        // pour retirer les stocks et générer la référence facture
                        $order->setOrderStatus(
                            OrderStatusQuery::create()->findOneById(2)
                        );

                        $this->getDispatcher()->dispatch(
                            TheliaEvents::ORDER_UPDATE_STATUS,
                            (new OrderEvent($order))->setStatus(2)
                        );

                        $order->save();
                    } else { // dans le cas d'une facturation à par
                        $order->setInvoiceRef((int) AdminOrderCreation::getConfigValue(AdminOrderCreation::CONFIG_KEY_INVOICE_REF_INCREMENT));

                        AdminOrderCreation::setConfigValue(
                            AdminOrderCreation::CONFIG_KEY_INVOICE_REF_INCREMENT,
                            (int) AdminOrderCreation::getConfigValue(AdminOrderCreation::CONFIG_KEY_INVOICE_REF_INCREMENT) + 1
                        );

                        $order->setOrderStatus(
                            OrderStatusQuery::create()->findOneById(2)
                        );
                        $order->save();
                    }
                }

                if ((int) $orderStatusId > 2) {
                    $order->setOrderStatus(
                        OrderStatusQuery::create()->findOneById((int) $orderStatusId)
                    );
                    $this->getDispatcher()->dispatch(
                        TheliaEvents::ORDER_UPDATE_STATUS,
                        (new OrderEvent($order))->setStatus((int) $orderStatusId)
                    );
                }

                $order->save();

                $con->commit();
            } catch (\Exception $e) {
                $con->rollBack();
                throw $e;
            }
        }

        if ($order->getId()) {
            return $this->render('admin-order-creation/ajax/order-create-modal-success', [
                'order' => $order
            ]);
        } else {
            return $this->render('admin-order-creation/ajax/order-create-modal', [
                'order' => $order,
                'hasCreditNoteModule' => $this->hasCreditNoteModule(),
                'configNewCreditNoteStatusId' => AdminOrderCreation::getConfigValue(AdminOrderCreation::CONFIG_KEY_DEFAULT_NEW_CREDIT_NOTE_STATUS_ID),
                'configNewCreditNoteTypeId' => AdminOrderCreation::getConfigValue(AdminOrderCreation::CONFIG_KEY_DEFAULT_NEW_CREDIT_NOTE_TYPE_ID),
                'configPayedOrderMinimumStatusId' => AdminOrderCreation::getConfigValue(AdminOrderCreation::CONFIG_KEY_PAYED_ORDER_MINIMUM_STATUS_ID)
            ]);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function ajaxSearchCustomerAction(Request $request)
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::CREATE)) {
            return $response;
        }

        $customerQuery = CustomerQuery::create()
            ->innerJoinAddress()
            ->groupById()
            ->limit(20);

        $this->whereConcatRegex($customerQuery, [
            'customer.FIRSTNAME',
            'customer.LASTNAME',
            'customer.EMAIL',
            'address.COMPANY',
            'address.PHONE'
        ], $request->get('q'));

        $customerQuery
            ->withColumn(AddressTableMap::COMPANY, 'COMPANY')
            ->withColumn(AddressTableMap::ADDRESS1, 'ADDRESS')
            ->withColumn(AddressTableMap::CITY, 'CITY')
            ->withColumn(AddressTableMap::ZIPCODE, 'ZIPCODE')
            ->withColumn(AddressTableMap::PHONE, 'PHONE');

        $customers = $customerQuery->find();

        $json = [
            'incomplete_results' => count($customers) ? false : true,
            'items' => []
        ];

        /** @var Customer $customer */
        foreach ($customers as $customer) {
            $json['items'][] = [
                'id' => $customer->getId(),
                'company' => $customer->getVirtualColumn('COMPANY'),
                'firstname' => $customer->getFirstname(),
                'lastname' => $customer->getLastname(),
                'ref' => $customer->getRef(),
                'address' => $this->formatAddress($customer)
            ];
        }

        return new JsonResponse($json);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function ajaxSearchProductAction(Request $request)
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::CREATE)) {
            return $response;
        }

        $productQuery = ProductQuery::create();

        $productQuery->useI18nQuery(
            $this->getRequest()->getSession()->getAdminEditionLang()->getLocale()
        );

        $productQuery
            ->withColumn(ProductI18nTableMap::TITLE, 'TITLE');

        $this->whereConcatRegex($productQuery, array(
            'product.REF',
            'product_i18n.TITLE'
        ), $request->get('q'));

        $productQuery->setLimit(10);

        $products = $productQuery->find();

        $json = [
            'incomplete_results' => count($products) ? false : true,
            'items' => []
        ];

        /** @var Product $product */
        foreach ($products as $product) {
            $json['items'][] = [
                'id' => $product->getId(),
                'ref' => $product->getRef(),
                'title' => $product->getVirtualColumn('TITLE')
            ];
        }

        return new JsonResponse($json);
    }

    protected function hasCreditNoteModule()
    {
        return class_exists('\CreditNote\CreditNote');
    }

    protected function performOrder(Order $order, Form $formValidate)
    {
        $this
            ->performCurrency($order, $formValidate)
            ->performOrderStatus($order, $formValidate)
            ->performCustomer($order, $formValidate)
            ->performInvoiceAddress($order, $formValidate)
            ->performDeliveryAddress($order, $formValidate)
            ->performDeliveryAddress($order, $formValidate)
            ->performProducts($order, $formValidate)
            ->performShipping($order, $formValidate)
            ->performGlobalReduction($order, $formValidate)
            ->performPaymentModule($order, $formValidate)
            ->performDeliveryModule($order, $formValidate)
        ;

        return $this;
    }

    protected function performPaymentModule(Order $order, Form $form)
    {
        $paymentModuleId = (int) $form->get('payment_module_id')->getData();

        $order->setPaymentModuleId($paymentModuleId);

        return $this;
    }

    protected function performDeliveryModule(Order $order, Form $form)
    {
        $deliveryModuleId = (int) $form->get('delivery_module_id')->getData();

        $order->setDeliveryModuleId($deliveryModuleId);

        return $this;
    }

    protected function performShipping(Order $order, Form $form)
    {
        $price = (float) $form->get('shipping_price_with_tax')->getData();

        $priceWithTax = (float) $form->get('shipping_price_with_tax')->getData();

        $order->setPostage($priceWithTax);
        $order->setPostageTax($priceWithTax - $price);

        return $this;
    }

    protected function performGlobalReduction(Order $order, Form $form)
    {
        $reduction = (float) $form->get('reduction')->getData();

        $reductionType = (int) $form->get('reduction_type')->getData();

        $total = $order->getTotalAmountWithTax(false);

        $afterDiscount = Calc::reduction(
            $reduction,
            $reductionType,
            $total,
            1
        );

        $order->setDiscount(-($afterDiscount - $total));

        return $this;
    }

    protected function performCurrency(Order $order, Form $form)
    {
        /** @var int $currencyId */
        $currencyId = $form->get('currency_id')->getData();

        if (empty($currencyId) || null === $currency = CurrencyQuery::create()->findPk($currencyId)) {
            $currency = CurrencyQuery::create()->findOneByByDefault(true);
        }

        $order->setCurrency($currency);
        $order->setCurrencyRate($currency->getRate());

        return $this;
    }

    protected function getCountry(Form $form)
    {
        /** @var int $countryId */
        $countryId = $form->get('country_id')->getData();

        if (!empty($countryId) && $country = CountryQuery::create()->findPk($countryId)) {
            return $country;
        }

        return CountryQuery::create()->filterByByDefault(true)->findOne();
    }

    protected function performOrderStatus(Order $order, Form $form)
    {
        if (null !== $statusId = $form->get('status_id')->getData()) {
            $order->setOrderStatus(
                OrderStatusQuery::create()->findOneById((int) $statusId)
            );
        } else {
            $order->setOrderStatus(
                OrderStatusQuery::create()->findOneById(1)
            );
        }

        return $this;
    }

    protected function performCustomer(Order $order, Form $form)
    {
        if (null !== $customerId = $form->get('customer_id')->getData()) {
            $order->setCustomer(
                CustomerQuery::create()->findOneById((int) $customerId)
            );
        }

        if (null === $customerId && 'create' === $form->get('action')->getData()) {
            $form->addError(
                new FormError('Please select a customer')
            );
        }

        return $this;
    }

    protected function performCreditNote(Order $order, Form $form)
    {
        if (!$this->hasCreditNoteModule()) {
            return $this;
        }

        $action = $form->get('action')->getData();

        $creditNoteId = $form->get('credit_note_id')->getData();

        $creditNoteStatusId = $form->get('credit_note_status_id')->getData();

        $creditNoteTypeId = $form->get('credit_note_type_id')->getData();

        if ($creditNoteId && $action === 'create') {
            /** @var CreditNote $creditNote */
            $creditNote = CreditNoteQuery::create()
                ->filterById($creditNoteId)
                ->useCreditNoteStatusQuery()
                    ->filterByUsed(false)
                    ->filterByInvoiced(true)
                ->endUse()
                ->findOne();

            if (null === $creditNote) {
                $form->addError(
                    new FormError('Please select a valid credit note')
                );
            }

            $orderCreditNote = (new OrderCreditNote())
                ->setOrderId($order->getId())
                ->setCreditNoteId($creditNote->getId());

            if (round($order->getTotalAmountWithTax() - $creditNote->getTotalPriceWithTax(), 2) > 0) { // cas crédit note plus petit
                $orderCreditNote->setAmountPrice($creditNote->getTotalPriceWithTax());
            } elseif (round($order->getTotalAmountWithTax() - $creditNote->getTotalPriceWithTax(), 2) < 0) { // cas crédit note plus grand
                $orderCreditNote->setAmountPrice($order->getTotalAmountWithTax());

                // copy address
                $lastInvoiceAddress = $creditNote->getCreditNoteAddress();
                $invoiceAddress = (new CreditNoteAddress())
                    ->setFirstname($lastInvoiceAddress->getFirstname())
                    ->setLastname($lastInvoiceAddress->getLastname())
                    ->setCity($lastInvoiceAddress->getCity())
                    ->setZipcode($lastInvoiceAddress->getZipcode())
                    ->setAddress1($lastInvoiceAddress->getAddress1())
                    ->setAddress2($lastInvoiceAddress->getAddress2())
                    ->setAddress3($lastInvoiceAddress->getAddress3())
                    ->setCustomerTitleId($lastInvoiceAddress->getCustomerTitleId())
                    ->setCountryId($lastInvoiceAddress->getCountryId());

                $invoiceAddress->save();

                $crediNoteDetail = (new CreditNoteDetail())
                    ->setTitle('Remboursement différence')
                    ->setTaxRuleId(null)
                    ->setPriceWithTax(-($order->getTotalAmountWithTax() - $creditNote->getTotalPriceWithTax()))
                    ->setPrice(-($order->getTotalAmountWithTax() - $creditNote->getTotalPriceWithTax()))
                    ->setType('other');

                $newCreditNote = (new CreditNote())
                    ->setCurrency($creditNote->getCurrency())
                    ->setTypeId($creditNoteTypeId)
                    ->setStatusId($creditNoteStatusId)
                    ->setCreditNoteAddress($invoiceAddress)
                    ->addCreditNoteDetail($crediNoteDetail)
                    ->setTotalPrice(-($order->getTotalAmountWithTax() - $creditNote->getTotalPriceWithTax()))
                    ->setTotalPriceWithTax(-($order->getTotalAmountWithTax() - $creditNote->getTotalPriceWithTax()))
                    ->setCustomerId($creditNote->getCustomer()->getId())
                    ->setParentId($creditNote->getId())
                    ->setOrderId($order->getId());

                    $newCreditNote->setDispatcher($this->getDispatcher());

                    $newCreditNote->save();
            } else { // cas crédit note égale
                $orderCreditNote->setAmountPrice($creditNote->getTotalPriceWithTax());
            }

            $orderCreditNote->save();

            $newStatus = CreditNoteStatusQuery::findNextCreditNoteUsedStatus($creditNote->getCreditNoteStatus());

            $creditNote->setCreditNoteStatus($newStatus);
            $creditNote->save();
        }
    }

    protected function performInvoiceAddress(Order $order, Form $form)
    {
        $action = $form->get('action')->getData();

        $invoiceAddressId = $form->get('invoice_address_id')->getData();

        if ($invoiceAddressId) {
            $address = AddressQuery::create()->findOneById((int) $invoiceAddressId);

            $orderAddress = (new OrderAddress())
                ->setCustomerTitle($address->getCustomerTitle())
                ->setAddress1($address->getAddress1())
                ->setAddress2($address->getAddress2())
                ->setAddress3($address->getAddress3())
                ->setFirstname($address->getFirstname())
                ->setLastname($address->getLastname())
                ->setCity($address->getCity())
                ->setZipcode($address->getZipcode())
                ->setCompany($address->getCompany())
                ->setCountry($address->getCountry());
        } else {
            $invoiceAddressTitle = $form->get('invoice_address_title')->getData();
            $invoiceAddressFirstname = $form->get('invoice_address_firstname')->getData();
            $invoiceAddressLastname = $form->get('invoice_address_lastname')->getData();
            $invoiceAddressCompany = $form->get('invoice_address_company')->getData();
            $invoiceAddressAddress1 = $form->get('invoice_address_address1')->getData();
            $invoiceAddressAddress2 = $form->get('invoice_address_address2')->getData();
            $invoiceAddressZipcode = $form->get('invoice_address_zipcode')->getData();
            $invoiceAddressCity = $form->get('invoice_address_city')->getData();
            $invoiceAddressCountryId = $form->get('invoice_address_country_id')->getData();

            $orderAddress = (new OrderAddress())
                ->setCustomerTitleId($invoiceAddressTitle)
                ->setAddress1($invoiceAddressAddress1)
                ->setAddress2($invoiceAddressAddress2)
                ->setFirstname($invoiceAddressFirstname)
                ->setLastname($invoiceAddressLastname)
                ->setCity($invoiceAddressCity)
                ->setZipcode($invoiceAddressZipcode)
                ->setCompany($invoiceAddressCompany)
                ->setCountry(
                    CountryQuery::create()->findOneById($invoiceAddressCountryId)
                );
        }

        if (empty($orderAddress->getLastname()) && 'create' === $form->get('action')->getData()) {
            $form->addError(
                new FormError('Please select a invoice address')
            );
        }

        if ($action === 'create') {
            $orderAddress->save();

            $order->setInvoiceOrderAddressId($orderAddress->getId());
        }

        return $this;
    }

    protected function performDeliveryAddress(Order $order, Form $form)
    {
        $action = $form->get('action')->getData();

        $deliveryAddressId = $form->get('delivery_address_id')->getData();

        if ($deliveryAddressId) {
            $address = AddressQuery::create()->findOneById((int) $deliveryAddressId);

            $orderAddress = (new OrderAddress())
                ->setCustomerTitle($address->getCustomerTitle())
                ->setAddress1($address->getAddress1())
                ->setAddress2($address->getAddress2())
                ->setAddress3($address->getAddress3())
                ->setFirstname($address->getFirstname())
                ->setLastname($address->getLastname())
                ->setCity($address->getCity())
                ->setZipcode($address->getZipcode())
                ->setCompany($address->getCompany())
                ->setCountry($address->getCountry());
        } else {
            $deliveryAddressTitle = $form->get('delivery_address_title')->getData();
            $deliveryAddressFirstname = $form->get('delivery_address_firstname')->getData();
            $deliveryAddressLastname = $form->get('delivery_address_lastname')->getData();
            $deliveryAddressCompany = $form->get('delivery_address_company')->getData();
            $deliveryAddressAddress1 = $form->get('delivery_address_address1')->getData();
            $deliveryAddressAddress2 = $form->get('delivery_address_address2')->getData();
            $deliveryAddressZipcode = $form->get('delivery_address_zipcode')->getData();
            $deliveryAddressCity = $form->get('delivery_address_city')->getData();
            $deliveryAddressCountryId = $form->get('delivery_address_country_id')->getData();

            $orderAddress = (new OrderAddress())
                ->setCustomerTitleId($deliveryAddressTitle)
                ->setAddress1($deliveryAddressAddress1)
                ->setAddress2($deliveryAddressAddress2)
                ->setFirstname($deliveryAddressFirstname)
                ->setLastname($deliveryAddressLastname)
                ->setCity($deliveryAddressCity)
                ->setZipcode($deliveryAddressZipcode)
                ->setCompany($deliveryAddressCompany)
                ->setCountry(
                    CountryQuery::create()->findOneById($deliveryAddressCountryId)
                );
        }

        if (empty($orderAddress->getLastname()) && 'create' === $form->get('action')->getData()) {
            $form->addError(
                new FormError('Please select a delivery address')
            );
        }

        if ($action === 'create') {
            $orderAddress->save();

            $order->setDeliveryOrderAddressId($orderAddress->getId());
        }

        return $this;
    }

    protected function getLang()
    {
        return $this->getSession()->getAdminEditionLang();
    }

    protected function performProducts(Order $order, Form $form)
    {
        $country = $this->getCountry($form);

        $productIds = $form->get('product_id')->getData();
        $quantities = $form->get('product_quantity')->getData();
        $productSaleElementIds = $form->get('product_sale_element_id')->getData();
        $productPriceWithoutTax = $form->get('product_price_without_tax')->getData();
        $refreshPrice = $form->get('refresh_price')->getData();

        $currency = $this->getCurrency($form);

        foreach ($productIds as $key => $id) {
            if (!isset($quantities[$key])) {
                $quantities[$key] = 1;
            }

            $product = ProductQuery::create()->findOneById($id);

            /** @var ProductI18n $productI18n */
            $productI18n = I18n::forceI18nRetrieving(
                $order->getLang()->getLocale(),
                'Product',
                $product->getId()
            );

            $productSaleElementsLoop = new ProductSaleElements($this->container);

            if (isset($productSaleElementIds[$key])) {
                if (null !== ProductSaleElementsQuery::create()
                        ->filterByProductId($product->getId())
                        ->filterById($productSaleElementIds[$key])
                        ->findOne()) {
                    $productSaleElementsLoop->initializeArgs([
                        'name' => 'product_sale_elements',
                        'type' => 'product_sale_elements',
                        'id' => $productSaleElementIds[$key],
                        'currency' => $currency->getId()
                    ]);
                } else {
                    $productSaleElementsLoop->initializeArgs([
                        'name' => 'product_sale_elements',
                        'type' => 'product_sale_elements',
                        'product' => $product->getId(),
                        'currency' => $currency->getId()
                    ]);
                }
            } else {
                $productSaleElementsLoop->initializeArgs([
                    'name' => 'product_sale_elements',
                    'type' => 'product_sale_elements',
                    'product' => $product->getId(),
                    'currency' => $currency->getId()
                ]);
            }

            $pagination = null;
            $results = $productSaleElementsLoop->exec($pagination);

            /** @var \Thelia\Model\ProductSaleElements $productSaleElement */
            $productSaleElement = $results->getResultDataCollection()[0];

            /** @var TaxRuleI18n $taxRuleI18n */
            $taxRuleI18n = I18n::forceI18nRetrieving(
                $order->getLang()->getLocale(),
                'TaxRule',
                $product->getTaxRuleId()
            );

            if (isset($refreshPrice[$key]) && (int) $refreshPrice[$key]) {
                $price = $productSaleElement->getVirtualColumn('price_PRICE');
                $promoPrice = $productSaleElement->getVirtualColumn('price_PROMO_PRICE');
            } else {
                $price = isset($productPriceWithoutTax[$key]) ? (float) $productPriceWithoutTax[$key] : $productSaleElement->getVirtualColumn('price_PRICE');
                $promoPrice = isset($productPriceWithoutTax[$key]) ? (float) $productPriceWithoutTax[$key] : $productSaleElement->getVirtualColumn('price_PROMO_PRICE');
            }

            $taxDetail = $product->getTaxRule()->getTaxDetail(
                $product,
                $country,
                $price,
                $promoPrice,
                $order->getLang()->getLocale()
            );

            $orderProduct = (new OrderProduct())
                ->setProductRef($product->getRef())
                ->setProductSaleElementsRef($productSaleElement->getRef())
                ->setProductSaleElementsId($productSaleElement->getId())
                ->setTitle($productI18n->getTitle())
                ->setChapo($productI18n->getChapo())
                ->setDescription($productI18n->getDescription())
                ->setPostscriptum($productI18n->getPostscriptum())
                ->setVirtual($product->getVirtual())
                ->setQuantity($quantities[$key])
                ->setWasNew($productSaleElement->getNewness())
                ->setWeight($productSaleElement->getWeight())
                ->setTaxRuleTitle($taxRuleI18n->getTitle())
                ->setTaxRuleDescription($taxRuleI18n->getDescription())
                ->setEanCode($productSaleElement->getEanCode())
                ->setDispatcher($this->getDispatcher())
                ->setPrice($price)
                ->setPromoPrice($promoPrice)
                ->setWasInPromo($productSaleElement->getPromo())
            ;

            /** @var OrderProductTax $tax */
            foreach ($taxDetail as $tax) {
                $orderProduct->addOrderProductTax($tax);
            }

            foreach ($productSaleElement->getAttributeCombinations() as $attributeCombination) {
                /** @var \Thelia\Model\Attribute $attribute */
                $attribute = I18n::forceI18nRetrieving(
                    $this->getSession()->getLang()->getLocale(),
                    'Attribute',
                    $attributeCombination->getAttributeId()
                );

                /** @var \Thelia\Model\AttributeAv $attributeAv */
                $attributeAv = I18n::forceI18nRetrieving(
                    $this->getSession()->getLang()->getLocale(),
                    'AttributeAv',
                    $attributeCombination->getAttributeAvId()
                );

                $orderProduct->addOrderProductAttributeCombination(
                    (new OrderProductAttributeCombination())
                        ->setOrderProductId($orderProduct->getId())
                        ->setAttributeTitle($attribute->getTitle())
                        ->setAttributeChapo($attribute->getChapo())
                        ->setAttributeDescription($attribute->getDescription())
                        ->setAttributePostscriptum($attribute->getPostscriptum())
                        ->setAttributeAvTitle($attributeAv->getTitle())
                        ->setAttributeAvChapo($attributeAv->getChapo())
                        ->setAttributeAvDescription($attributeAv->getDescription())
                        ->setAttributeAvPostscriptum($attributeAv->getPostscriptum())
                );
            }

            $order->addOrderProduct($orderProduct);
        }


        if (!count($order->getOrderProducts()) && 'create' === $form->get('action')->getData()) {
            $form->addError(
                new FormError('Please select a product')
            );
        }

        return $this;
    }

    protected function getCurrency(Form $form)
    {
        $currencyId = $form->get('currency_id')->getData();
        if (null !== $currencyId) {
            $currency = CurrencyQuery::create()->findPk($currencyId);
            if (null === $currency) {
                throw new \InvalidArgumentException('Cannot found currency id: `' . $currency . '` in product_sale_elements loop');
            }
        } else {
            $currency = $this->getRequest()->getSession()->getCurrency();
        }

        return $currency;
    }

    /**
     * @param ActiveRecordInterface $model
     * @return string
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function formatAddress(ActiveRecordInterface $model)
    {
        /** @var Order|Customer $model */
        return implode(' ', [$model->getVirtualColumn('ADDRESS'), $model->getVirtualColumn('ZIPCODE'), $model->getVirtualColumn('CITY')]);
    }
}
