<?php
/*************************************************************************************/
/*      This file is part of the module AdminOrderCreation                           */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AdminOrderCreation\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Form\BaseForm;

/**
 * Class CreditNoteCreateForm
 * @package CreditNote\Form
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class OrderCreateForm extends BaseForm
{
    /**
     * @return string the name of you form. This name must be unique
     */
    public function getName()
    {
        return 'admin-order-creation-create';
    }

    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :
     *
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add('currency_id', IntegerType::class, array(
                'required' => false
            ))
            ->add('country_id', IntegerType::class, array(
                'required' => false
            ));

        $this->formBuilder
            ->add('action', ChoiceType::class, array(
                'required' => true,
                'choices'  => array(
                    'open' => 'open',
                    'refresh' => 'refresh',
                    'create' => 'create',
                ),
            ))
            ->add('status_id', IntegerType::class, array(
                'required' => false
            ))
            ->add('customer_id', IntegerType::class, array(
                'required' => false
            ))
            ->add('invoice_address_id', IntegerType::class, array(
                'required' => false
            ))
            ->add('delivery_address_id', IntegerType::class, array(
                'required' => false
            ))
        ;

        $this->formBuilder
            ->add('invoice_address_id', IntegerType::class, array(
                'required' => false
            ))
            ->add('invoice_address_title', TextType::class, array(
                'required' => false
            ))
            ->add('invoice_address_firstname', TextType::class, array(
                'required' => false
            ))
            ->add('invoice_address_lastname', TextType::class, array(
                'required' => false
            ))
            ->add('invoice_address_company', TextType::class, array(
                'required' => false
            ))
            ->add('invoice_address_address1', TextType::class, array(
                'required' => false
            ))
            ->add('invoice_address_address2', TextType::class, array(
                'required' => false
            ))
            ->add('invoice_address_zipcode', TextType::class, array(
                'required' => false
            ))
            ->add('invoice_address_city', TextType::class, array(
                'required' => false
            ))
            ->add('invoice_address_country_id', IntegerType::class, array(
                'required' => false
            ))
        ;

        $this->formBuilder
            ->add('delivery_address_id', IntegerType::class, array(
                'required' => false
            ))
            ->add('delivery_address_title', TextType::class, array(
                'required' => false
            ))
            ->add('delivery_address_firstname', TextType::class, array(
                'required' => false
            ))
            ->add('delivery_address_lastname', TextType::class, array(
                'required' => false
            ))
            ->add('delivery_address_company', TextType::class, array(
                'required' => false
            ))
            ->add('delivery_address_address1', TextType::class, array(
                'required' => false
            ))
            ->add('delivery_address_address2', TextType::class, array(
                'required' => false
            ))
            ->add('delivery_address_zipcode', TextType::class, array(
                'required' => false
            ))
            ->add('delivery_address_city', TextType::class, array(
                'required' => false
            ))
            ->add('delivery_address_country_id', IntegerType::class, array(
                'required' => false
            ))
        ;

        $this->formBuilder
            ->add('product_id', 'collection', array(
                'required' => false,
                'allow_add'    => true,
                'allow_delete' => true
            ))
            ->add('product_sale_element_id', 'collection', array(
                'required' => false,
                'allow_add'    => true,
                'allow_delete' => true
            ))
            ->add('product_quantity', 'collection', array(
                'required' => false,
                'allow_add'    => true,
                'allow_delete' => true
            ))
            ->add('product_price_with_tax', 'collection', array(
                'required' => false,
                'allow_add'    => true,
                'allow_delete' => true
            ))
            ->add('product_price_without_tax', 'collection', array(
                'required' => false,
                'allow_add'    => true,
                'allow_delete' => true
            ))
            ->add('refresh_price', 'collection', array(
                'required' => false,
                'allow_add'    => true,
                'allow_delete' => true
            ))
        ;

        $this->formBuilder
            ->add('reduction', TextType::class, array(
                'required' => false,
                'empty_data' => 0
            ))
            ->add('reduction_type', NumberType::class, array(
                'required' => false
            ));

        $this->formBuilder
            ->add('shipping_price', TextType::class, array(
                'required' => false,
                'empty_data' => 0
            ))
            ->add('shipping_tax_rule_id', IntegerType::class, array(
                'required' => false
            ))
            ->add('shipping_price_with_tax', TextType::class, array(
                'required' => false,
                'empty_data' => 0
            ));

        $this->formBuilder
            ->add('payment_module_id', IntegerType::class, array(
                'required' => false
            ))
            ->add('delivery_module_id', IntegerType::class, array(
                'required' => false
            ))
        ;

        $this->formBuilder
            ->add('credit_note_id', IntegerType::class, array(
                'required' => false
            ));
    }
}
